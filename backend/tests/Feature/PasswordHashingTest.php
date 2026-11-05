<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Database\Seeders\RolePermissionSeeder;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\PasswordHashPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Keamanan penyimpanan kata sandi
 *
 * yang diuji:
 *  - algoritma bcrypt & cost minimal 10, bawaan 12
 *  - konfigurasi di bawah minimum ditolak, bukan diterima diam-diam
 *  - seluruh alur (buat akun, login, ubah, reset) memakai mekanisme hashing Laravel
 *  - rehash otomatis saat login memperkuat hash tanpa merusak autentikasi
 *  - kata sandi tidak pernah muncul di response, audit log, maupun database
 */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    private array $hashingConfig;

protected function setUp(): void
{
    parent::setUp();

    // Siapkan HRD, KADIV, EMPLOYEE beserta permission
    $this->seed(RolePermissionSeeder::class);

    $this->hashingConfig = Config::get('hashing');
}

    protected function tearDown(): void
    {
        Config::set('hashing', $this->hashingConfig);

        parent::tearDown();
    }

    /*
      Buat pengguna ber-role dengan password yang di-hash.
      Bila $cost diisi, hash dibuat manual pada cost tersebut (untuk uji rehash).
     */
    protected function userWithRole(
        string $roleName,
        string $password = 'Password123',
        ?int $cost = null,
        ?string $email = null
    ): User {
        $role = Role::query()->firstOrCreate(
    ['name' => $roleName],
    [
        'description' => $roleName,
        'scope' => 'GLOBAL',
        'is_system' => true,
    ]
);

        $employee = Employee::create([
            'nip'               => 'NIP-' . $roleName . '-' . uniqid(),
            'full_name'         => 'Akun ' . $roleName,
            'email'             => $email ?? strtolower($roleName) . uniqid() . '@example.test',
            'join_date'         => '2021-01-01',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'name'        => $employee->full_name,
            'email'       => $employee->email,
            'password'    => Hash::make($password),
            'is_active'   => true,
        ]);

        // Hash dengan cost tertentu ditulis langsung lewat query builder.
        // Cast 'hashed' pada model menolak hash yang lebih kuat dari
        // konfigurasi aktif, jadi tidak bisa dipakai untuk menyiapkan data uji.
        if ($cost !== null) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]),
            ]);

            $user->refresh();
        }

        $user->roles()->sync([$role->id]);

        return $user;
    }

    // ------------------------------------------------------------------
    // Konfigurasi
    // ------------------------------------------------------------------

    public function test_konfigurasi_bawaan_bcrypt_cost_12(): void
    {
        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertSame(12, PasswordHashPolicy::defaultRounds());
        $this->assertSame(12, PasswordHashPolicy::resolveRounds(null));
        $this->assertSame(12, PasswordHashPolicy::resolveRounds(''));
        $this->assertSame(12, config('hashing.bcrypt.rounds'));
        $this->assertSame(12, PasswordHashPolicy::rounds());

        // .env.example wajib memuat BCRYPT_ROUNDS=12
        $this->assertStringContainsString(
            'BCRYPT_ROUNDS=12',
            file_get_contents(base_path('.env.example'))
        );
    }

    public function test_cost_di_bawah_minimum_ditolak(): void
    {
        foreach ([0, 4, 9] as $rounds) {
            try {
                PasswordHashPolicy::validateRounds($rounds);

                $this->fail("Cost {$rounds} seharusnya ditolak, tetapi diterima.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('ditolak', $e->getMessage());
            }
        }

        // Cost tepat pada ambang minimum justru diterima.
        PasswordHashPolicy::validateRounds(PasswordHashPolicy::MIN_ROUNDS);
        $this->assertSame(10, PasswordHashPolicy::MIN_ROUNDS);
    }

    public function test_konfigurasi_aplikasi_dengan_cost_lemah_ditolak(): void
    {
        Config::set('hashing.bcrypt.rounds', 9);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ditolak/');

        PasswordHashPolicy::assertConfiguration();
    }

    public function test_driver_selain_bcrypt_ditolak(): void
    {
        Config::set('hashing.driver', 'argon');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak diizinkan/');

        PasswordHashPolicy::assertConfiguration();
    }

    // Penyimpanan


    public function test_password_baru_tersimpan_sebagai_bcrypt_dengan_cost_minimal_10(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $stored = $user->fresh()->password;

        $this->assertTrue(PasswordHashPolicy::isBcrypt($stored), 'Password harus tersimpan sebagai bcrypt.');
        $this->assertSame('bcrypt', password_get_info($stored)['algoName']);

        $cost = PasswordHashPolicy::costOf($stored);

        $this->assertNotNull($cost);
        $this->assertGreaterThanOrEqual(10, $cost, 'Cost bcrypt minimal 10 (PRD 20.3).');
        $this->assertSame(config('hashing.bcrypt.rounds'), $cost);
        $this->assertTrue(Hash::check('Password123', $stored));
    }

    public function test_password_plaintext_tidak_pernah_tersimpan_di_database(): void
    {
        $this->userWithRole(Role::HRD, 'Password123');

        $stored = DB::table('users')->value('password');

        $this->assertNotSame('Password123', $stored);
        $this->assertStringStartsWith('$2', $stored);
    }

    // Login

    public function test_login_dengan_password_benar_berhasil(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user']]);
    }

    public function test_login_dengan_password_salah_ditolak(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'PasswordSalah',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // Ubah & reset password

    public function test_perubahan_password_memakai_konfigurasi_yang_sama(): void
    {
        $user = $this->userWithRole(Role::EMPLOYEE);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'Password123',
                'password'              => 'BaruPass456',
                'password_confirmation' => 'BaruPass456',
            ])->assertOk()
            ->assertJsonPath('success', true);

        $stored = $user->fresh()->password;

        $this->assertTrue(PasswordHashPolicy::isBcrypt($stored));
        $this->assertSame(config('hashing.bcrypt.rounds'), PasswordHashPolicy::costOf($stored));
        $this->assertTrue(Hash::check('BaruPass456', $stored));
        $this->assertFalse(Hash::check('Password123', $stored));

        // Password lama tidak berlaku lagi, password baru bisa dipakai login.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Password123',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'BaruPass456',
        ])->assertOk();
    }


public function test_pembuatan_dan_reset_password_oleh_hrd_memakai_konfigurasi_yang_sama(): void
{
    $hrd = $this->userWithRole(Role::HRD, 'HrdPass123');

    $employee = Employee::create([
        'nip' => 'NIP-NEW-' . uniqid(),
        'full_name' => 'Karyawan Baru',
        'email' => 'karyawan.baru@example.test',
        'join_date' => '2021-01-01',
        'employment_status' => Employee::STATUS_ACTIVE,
    ]);

    // HRD membuat akun. Password awal dibuat otomatis oleh sistem.
    $response = $this->actingAs($hrd, 'sanctum')
        ->postJson('/api/v1/system/users', [
            'name' => 'Karyawan Baru',
            'email' => 'karyawan.baru@example.test',
            'employee_id' => $employee->id,
            'role' => Role::EMPLOYEE,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $activationToken = $response->json('data.activation.token');
    $this->assertNotEmpty($activationToken);

    $created = User::where('email', 'karyawan.baru@example.test')
        ->firstOrFail();

    $this->assertTrue(PasswordHashPolicy::isBcrypt($created->password));
    $this->assertSame(
        12,
        PasswordHashPolicy::costOf($created->password)
    );

    // Karyawan melakukan aktivasi dan menetapkan password sendiri.
    $this->postJson('/api/v1/auth/activate', [
        'email' => 'karyawan.baru@example.test',
        'token' => $activationToken,
        'password' => 'PasswordBaru789',
        'password_confirmation' => 'PasswordBaru789',
    ])->assertOk()
        ->assertJsonPath('success', true);

    $activated = $created->fresh();

    $this->assertSame(
        12,
        PasswordHashPolicy::costOf($activated->password)
    );
    $this->assertTrue(Hash::check('PasswordBaru789', $activated->password));
    $this->assertFalse($activated->must_change_password);
    $this->assertNull($activated->activation_token_hash);

    // Login setelah aktivasi.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'karyawan.baru@example.test',
        'password' => 'PasswordBaru789',
    ])->assertOk();

    // HRD melakukan reset password
    $this->actingAs($hrd, 'sanctum')
        ->postJson('/api/v1/system/users/' . $created->id . '/reset-password', [
            'password' => 'HasilReset321',
        ])->assertOk()
            ->assertJsonPath('success', true);

    $reset = $created->fresh();

    $this->assertTrue(PasswordHashPolicy::isBcrypt($reset->password));
    $this->assertSame(
        12,
        PasswordHashPolicy::costOf($reset->password)
    );
    $this->assertTrue(Hash::check('HasilReset321', $reset->password));
    $this->assertTrue($reset->must_change_password);

    // Password hasil reset tetap dapat digunakan untuk login
    $this->postJson('/api/v1/auth/login', [
        'email' => 'karyawan.baru@example.test',
        'password' => 'HasilReset321',
    ])->assertOk();
}


    // Rehash

    public function test_rehash_semakin_kuat_saat_login_tanpa_merusak_autentikasi(): void
    {
        // Hash lama dibuat pada cost 10 (masih valid, tetapi lebih lemah dari 12).
        $user = $this->userWithRole(Role::HRD, 'Password123', 10);

        $this->assertSame(10, PasswordHashPolicy::costOf($user->password));
        $this->assertTrue(PasswordHashPolicy::needsStrongerHash($user->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Password123',
        ])->assertOk();

        $stored = $user->fresh()->password;

        $this->assertSame(12, PasswordHashPolicy::costOf($stored), 'Hash harus diperkuat ke cost aktif.');
        $this->assertTrue(Hash::check('Password123', $stored), 'Password harus tetap sama.');

        // Login berikutnya tetap berhasil dengan password yang sama
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Password123',
        ])->assertOk();
    }

    public function test_hash_yang_lebih_kuat_tidak_pernah_diturunkan(): void
    {
        $user = $this->userWithRole(Role::HRD, 'Password123', 14);

        $this->assertFalse(PasswordHashPolicy::needsStrongerHash($user->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Password123',
        ])->assertOk();

        $this->assertSame(14, PasswordHashPolicy::costOf($user->fresh()->password));
    }

    // Kebocoran kata sandi


public function test_kata_sandi_tidak_muncul_di_response_maupun_audit_log(): void
{
    $hrd = $this->userWithRole(Role::HRD, 'HrdPass123');

    $employee = Employee::create([
        'nip' => 'NIP-AUDIT-' . uniqid(),
        'full_name' => 'Audit Check',
        'email' => 'audit.check@example.test',
        'join_date' => '2021-01-01',
        'employment_status' => Employee::STATUS_ACTIVE,
    ]);

    $response = $this->actingAs($hrd, 'sanctum')
        ->postJson('/api/v1/system/users', [
            'name' => 'Audit Check',
            'email' => 'audit.check@example.test',
            'employee_id' => $employee->id,
            'role' => Role::EMPLOYEE,
        ]);

    $response->assertStatus(201);

    // Password dan hash token aktivasi tidak boleh ada pada objek user response.
    $responseUser = $response->json('data.user');

    $this->assertArrayNotHasKey('password', $responseUser);
    $this->assertArrayNotHasKey('activation_token_hash', $responseUser);

    $created = User::where('email', 'audit.check@example.test')
        ->firstOrFail();

    // Hash password tidak boleh bocor melalui response.
    $this->assertStringNotContainsString(
        $created->password,
        $response->getContent()
    );

    // Audit harus mencatat pembuatan akun
    $audit = AuditLog::query()
        ->where('object_type', 'users')
        ->where('object_id', (string) $created->id)
        ->where('action', 'CREATE')
        ->latest('id')
        ->firstOrFail();

    // Field sensitif harus disamarkan oleh AuditService.
    $this->assertSame(
        '[REDACTED]',
        $audit->new_values['password'] ?? null
    );

    $this->assertSame(
        '[REDACTED]',
        $audit->new_values['activation_token_hash'] ?? null
    );

    $serializedAudit = json_encode([
        $audit->old_values,
        $audit->new_values,
    ]);

    $this->assertStringNotContainsString(
        $created->password,
        $serializedAudit
    );
}

}
