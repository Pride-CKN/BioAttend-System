<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Session as SessionRecord;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Auth\Captcha\Contracts\CaptchaVerifier;
use App\Support\LoginThrottle;
use App\Support\PasswordHashPolicy;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /* POST /auth/login
      Rate limit 5 percobaan / 15 menit per (email + IP)
      CAPTCHA wajib setelah N kegagalan bila provider belum ada, permintaan
      DITOLAK dengan pesan jujur, bukan dilewati diam-diam */
    public function login(Request $request, CaptchaVerifier $captcha): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $trigger = (int) config('captcha.trigger_after_failures', 3);

        if (LoginThrottle::failures($request) >= $trigger) {
            if (! $captcha->isConfigured()) {
                AuditService::record('AUTH', 'LOGIN_CAPTCHA_BLOCKED', 'users', null, null, [
                    'email' => $credentials['email'],
                    'status' => $captcha->status(),
                ], $request);

                return $this->error(
                    'Verifikasi CAPTCHA diperlukan, tetapi provider CAPTCHA belum dikonfigurasi. Hubungi administrator.',
                    429
                );
            }

            if (! $captcha->verify((string) $request->input('captcha_token', ''), $request)) {
                throw ValidationException::withMessages([
                    'captcha_token' => 'Verifikasi CAPTCHA gagal.',
                ]);
            }
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        // Akun nonaktif tidak dapat login dan percobaan tetap dicatat
        if ($user && ! $user->is_active) {
            AuditService::record('AUTH', 'LOGIN_INACTIVE_ACCOUNT', 'users', $user->id, null, null, $request);

            throw ValidationException::withMessages([
                'email' => 'Akun tidak aktif.',
            ]);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            LoginThrottle::recordFailure($request);
            AuditService::record('AUTH', 'LOGIN_FAILED', 'users', $user?->id, null, ['email' => $credentials['email']], $request);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        // perkuat hash lama secara aman. Password tidak berubah dan
        // hash yang lebih kuat tidak pernah diturunkan.
        if (PasswordHashPolicy::needsStrongerHash($user->password)) {
            $user->forceFill(['password' => Hash::make($credentials['password'])])->save();

            AuditService::record('AUTH', 'PASSWORD_REHASH', 'users', $user->id, null, [
                'algo' => PasswordHashPolicy::DRIVER,
                'cost' => PasswordHashPolicy::costOf($user->password),
            ], $request);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        LoginThrottle::clear($request);

        $token = $user->createToken('api-token')->plainTextToken;

        AuditService::record('AUTH', 'LOGIN', 'users', $user->id, null, null, $request);

        $payload = $this->userPayload($user);
        $payload['must_change_password'] = (bool) $user->must_change_password;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration'),
            'user' => $payload,
        ], 'Login berhasil');
    }

    /*POST /auth/activate — aktivasi akun baru tanpa login.
      Token sekali pakai yang tersimpan di DB hanyalah SHA-256 dari token */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        $tokenHash = hash('sha256', $data['token']);

        $valid = $user
            && $user->activation_token_hash
            && hash_equals($user->activation_token_hash, $tokenHash)
            && $user->activation_expires_at
            && ! $user->activation_expires_at->isPast();

        if (! $valid) {
            throw ValidationException::withMessages([
                'token' => 'Token aktivasi tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        if (PasswordPolicy::isReused($user, $data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi ini pernah digunakan. Pilih kata sandi lain.',
            ]);
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'activation_token_hash' => null,
                'activation_expires_at' => null,
                'must_change_password' => false,
                // Aktivasi password tidak boleh mengaktifkan akun yang dinonaktifkan HRD.
                'is_active' => (bool) $user->is_active,
            ])->save();

            $user->tokens()->delete();
            SessionRecord::where('user_id', $user->id)->delete();
        });

        // Audit sengaja tidak mencatat token maupun hash password
        AuditService::record('AUTH', 'ACCOUNT_ACTIVATED', 'users', $user->id, null, null, $request);

        return $this->success(null, 'Akun berhasil diaktifkan. Silakan login.');
    }

    /* POST /auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        AuditService::record('AUTH', 'LOGOUT', 'users', $request->user()?->id, null, null, $request);

        return $this->success(null, 'Logout berhasil');
    }

    /* POST /auth/logout-all — cabut seluruh token & sesi milik sendiri */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();
        SessionRecord::where('user_id', $user->id)->delete();

        AuditService::record('AUTH', 'LOGOUT_ALL', 'users', $user->id, null, null, $request);

        return $this->success(null, 'Seluruh sesi telah dicabut.');
    }

    /* GET /auth/sessions — daftar sesi MILIK SENDIRI */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;

        $tokens = $user->tokens()->orderByDesc('created_at')->get()->map(fn ($t) => [
            'id' => 'tok_'.$t->id,
            'type' => 'API_TOKEN',
            'name' => $t->name,
            'ip_address' => null,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
            'is_current' => $t->id === $currentId,
        ])->all();

        // Query di-scope ke user_id: tidak ada jalur membaca sesi pengguna lain
        $sessions = SessionRecord::where('user_id', $user->id)
            ->orderByDesc('last_activity_at')->get()
            ->map(fn ($s) => [
                'id' => 'sess_'.$s->id,
                'type' => 'WEB_SESSION',
                'name' => $s->device,
                'ip_address' => $s->ip_address,
                'last_used_at' => $s->last_activity_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
                'is_current' => false,
            ])->all();

        return $this->success(array_merge($tokens, $sessions));
    }

    /* DELETE /auth/sessions/{id} — pencabutan di-scope ke pemilik token */
    public function revokeSession(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (str_starts_with($id, 'tok_')) {
            $deleted = $user->tokens()->where('id', (int) substr($id, 4))->delete();
        } elseif (str_starts_with($id, 'sess_')) {
            $deleted = SessionRecord::where('user_id', $user->id)
                ->where('id', (int) substr($id, 5))->delete();
        } else {
            $deleted = 0;
        }

        if (! $deleted) {
            return $this->notFound('Sesi tidak ditemukan.');
        }

        AuditService::record('AUTH', 'REVOKE_SESSION', 'users', $user->id, null, ['session_id' => $id], $request);

        return $this->success(null, 'Sesi dicabut.');
    }

    /* GET /profile */
    public function profile(Request $request): JsonResponse
    {
        return $this->success($this->userPayload($request->user()));
    }

    /* PUT /profile */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
        ]);

        $old = $user->only(array_keys($data));
        $user->update($data);

        AuditService::record('PROFILE', 'UPDATE', 'users', $user->id, $old, $data, $request);

        return $this->success($this->userPayload($user->refresh()), 'Profil diperbarui');
    }

    /* POST /auth/change-password */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => PasswordPolicy::rules(),
        ]);

        if (PasswordPolicy::isReused($user, $data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi ini pernah digunakan. Pilih kata sandi lain.',
            ]);
        }

        DB::transaction(function () use ($user, $data): void {
            PasswordPolicy::rememberCurrent($user);

            $user->forceFill([
                'password' => Hash::make($data['password']),
                'must_change_password' => false,
                'activation_token_hash' => null,
                'activation_expires_at' => null,
            ])->save();

            // Seluruh token dicabut saat ganti/reset password
            $user->tokens()->delete();
            SessionRecord::query()->where('user_id', $user->id)->delete();
        });

        AuditService::record('PROFILE', 'CHANGE_PASSWORD', 'users', $user->id, null, null, $request);

        return $this->success(null, 'Kata sandi berhasil diubah. Silakan login ulang.');
    }

    /* POST /auth/forgot-password */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // bila provider email belum ada, jangan klaim tautan terkirim
        if (app()->environment('production')
            && in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            return $this->error(
                'Layanan email belum dikonfigurasi. Reset kata sandi mandiri tidak tersedia; hubungi HRD.',
                503
            );
        }

        $status = Password::sendResetLink($data['email']);

        AuditService::record('AUTH', 'FORGOT_PASSWORD', 'users', null, null, ['email' => $data['email']], $request);

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, 'Tautan reset kata sandi telah dikirim.')
            : $this->unprocessable('Tidak dapat mengirim tautan reset.', ['email' => [__($status)]]);
    }

    protected function userPayload(User $user): array
    {
        $user->loadMissing([
    'roles',
    'employee.division',
    'employee.unit',
    'employee.position',
    ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'must_change_password' => (bool) $user->must_change_password,
            'last_login_at' => $user->last_login_at,
            'roles' => $user->roles->pluck('name'),
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'nip' => $user->employee->nip,
                'full_name' => $user->employee->full_name,
                'division' => $user->employee->division?->name,
                'division_id' => $user->employee->division_id,
                'unit' => $user->employee->unit?->name,
                'unit_id' => $user->employee->unit_id,
                'position' => $user->employee->position?->name,
                'employment_status' => $user->employee->employment_status,
            ] : null,
        ];
    }
}
