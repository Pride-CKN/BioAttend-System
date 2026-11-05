<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Database\Seeders\RolePermissionSeeder;

/**
 * Penguncian kontrak keamanan autentikasi (PRD 20.1 / 20.3 / 20.5 / 20.6).
 * Fokus: endpoint yang sebelumnya terdaftar tanpa method, kebijakan kata
 * sandi, scope sesi, dan kejujuran status CAPTCHA/email.
 *
 * Sengaja memakai token Sanctum nyata (bukan actingAs) supaya alur
 * pencabutan token dan currentAccessToken() diuji apa adanya.
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
{
    parent::setUp();

    $this->seed(RolePermissionSeeder::class);
}

    private function makeUser(string $password = 'Password123'): User
    {
        $role = Role::query()
    ->where('name', Role::HRD)
    ->firstOrFail();

        $user = User::create([
            'name' => 'Uji Hardening',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_endpoint_auth_baru_tidak_mengembalikan_500(): void
    {
        $user = $this->makeUser();
        $a = $user->createToken('perangkat-a');
        $b = $user->createToken('perangkat-b');

        $this->withToken($a->plainTextToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($a->plainTextToken)
            ->getJson('/api/v1/auth/profile')
            ->assertOk();

        // Cabut sesi lain milik sendiri.
        $this->withToken($a->plainTextToken)
            ->deleteJson('/api/v1/auth/sessions/tok_'.$b->accessToken->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($a->plainTextToken)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $user->tokens()->count());

        // Token yang sudah dicabut tidak bisa dipakai mencabut lagi.
        $this->deleteJson('/api/v1/auth/sessions/tok_'.$a->accessToken->id)
            ->assertStatus(404);
    }

    public function test_daftar_dan_cabut_sesi_hanya_milik_sendiri(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $ownerToken = $owner->createToken('owner');
        $foreignToken = $other->createToken('foreign');

        $response = $this->withToken($ownerToken->plainTextToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk();

        $ids = array_column((array) $response->json('data'), 'id');
        $this->assertNotContains('tok_'.$foreignToken->accessToken->id, $ids);

        // Upaya IDOR: mencabut token pengguna lain harus 404, bukan 200.
        $this->withToken($ownerToken->plainTextToken)
            ->deleteJson('/api/v1/auth/sessions/tok_'.$foreignToken->accessToken->id)
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_aktivasi_akun_token_sekali_pakai_dan_kebijakan_password(): void
    {
        $hrd = $this->makeUser();
        $hrdToken = $hrd->createToken('hrd')->plainTextToken;

        $employee = Employee::create([
            'nip' => 'AKT-001',
            'full_name' => 'Karyawan Aktivasi',
            'email' => 'aktivasi@example.test',
            'join_date' => '2026-01-01',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $created = $this->withToken($hrdToken)->postJson('/api/v1/system/users', [
            'name' => 'Karyawan Aktivasi',
            'email' => 'aktivasi@example.test',
            'employee_id' => $employee->id,
            'role' => Role::EMPLOYEE,
        ])->assertStatus(201);

        $token = $created->json('data.activation.token');
        $this->assertNotEmpty($token);

        $newUser = User::where('email', 'aktivasi@example.test')->firstOrFail();
        $this->assertTrue($newUser->must_change_password);
        $this->assertNotSame($token, $newUser->activation_token_hash);
        $this->assertSame(hash('sha256', $token), $newUser->activation_token_hash);

        // Password lemah ditolak (tidak ada angka).
        $this->postJson('/api/v1/auth/activate', [
            'email' => 'aktivasi@example.test',
            'token' => $token,
            'password' => 'hanyahuruf',
            'password_confirmation' => 'hanyahuruf',
        ])->assertStatus(422);

        // Token salah ditolak.
        $this->postJson('/api/v1/auth/activate', [
            'email' => 'aktivasi@example.test',
            'token' => 'token-palsu',
            'password' => 'AktifBaru456',
            'password_confirmation' => 'AktifBaru456',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/activate', [
            'email' => 'aktivasi@example.test',
            'token' => $token,
            'password' => 'AktifBaru456',
            'password_confirmation' => 'AktifBaru456',
        ])->assertOk()->assertJsonPath('success', true);

        // Token tidak bisa dipakai dua kali.
        $this->postJson('/api/v1/auth/activate', [
            'email' => 'aktivasi@example.test',
            'token' => $token,
            'password' => 'AktifLagi789',
            'password_confirmation' => 'AktifLagi789',
        ])->assertStatus(422);

        $fresh = $newUser->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertNull($fresh->activation_token_hash);
        $this->assertTrue(Hash::check('AktifBaru456', $fresh->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'aktivasi@example.test',
            'password' => 'AktifBaru456',
        ])->assertOk()->assertJsonPath('data.user.must_change_password', false);
    }

    public function test_hrd_tidak_dapat_membuat_akun_kadiv_tanpa_divisi(): void
    {
        $hrd = $this->makeUser();
        $hrdToken = $hrd->createToken('hrd-kadiv-validation')->plainTextToken;

        $employee = Employee::create([
            'nip' => 'KADIV-NODIV-001',
            'full_name' => 'Calon KADIV tanpa divisi',
            'email' => 'calon-kadiv@example.test',
            'join_date' => '2026-01-01',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $this->withToken($hrdToken)->postJson('/api/v1/system/users', [
            'name' => 'Calon KADIV',
            'email' => 'calon-kadiv-user@example.test',
            'employee_id' => $employee->id,
            'role' => Role::KADIV,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'calon-kadiv-user@example.test']);
    }

    public function test_password_wajib_ganti_mem_blokir_modul_lain(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['must_change_password' => true])->save();
        $plain = $user->createToken('uji')->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/v1/system/users')
            ->assertStatus(428)
            ->assertJsonPath('error.code', 'PASSWORD_CHANGE_REQUIRED');

        $this->withToken($plain)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password123',
            'password' => 'GantiBaru456',
            'password_confirmation' => 'GantiBaru456',
        ])->assertOk();

        // Ganti password mencabut seluruh token; pakai token baru.
        $this->withToken($user->createToken('baru')->plainTextToken)
            ->getJson('/api/v1/system/users')
            ->assertOk();
    }

    public function test_kata_sandi_tidak_boleh_dipakai_ulang(): void
    {
        $user = $this->makeUser();

        $this->withToken($user->createToken('a')->plainTextToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Password123',
                'password' => 'PertamaBaru1',
                'password_confirmation' => 'PertamaBaru1',
            ])->assertOk();

        $this->withToken($user->createToken('b')->plainTextToken)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'PertamaBaru1',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ])->assertStatus(422);
    }

    public function test_captcha_tidak_pernah_dianggap_sukses_saat_provider_belum_ada(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'salah',
            ])->assertStatus(422);
        }

        // Percobaan ke-4: CAPTCHA dibutuhkan, provider tidak ada => 429 jujur.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
            'captcha_token' => 'token-apa-saja',
        ])->assertStatus(429)->assertJsonPath('success', false);

        $this->assertStringContainsString('CAPTCHA', $response->getContent());
    }

    public function test_request_id_dan_security_headers_hadir_di_semua_respons_api(): void
    {
        $response = $this->getJson('/api/health')->assertOk();

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_kontrak_error_memiliki_error_code_dan_request_id(): void
    {
        $this->getJson('/api/v1/auth/profile')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['success', 'message', 'error' => ['code', 'message'], 'request_id']);
    }
}
