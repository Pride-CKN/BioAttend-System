<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Autentikasi Sanctum + pembatasan role */
class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::create([
            'name'        => $roleName,
            'description' => $roleName,
            'scope'       => 'GLOBAL',
            'is_system'   => true,
        ]);

        $employee = Employee::create([
            'nip'               => 'A-' . $roleName,
            'full_name'         => 'Akun ' . $roleName,
            'email'             => strtolower($roleName) . '@example.test',
            'join_date'         => '2021-01-01',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'name'        => $employee->full_name,
            'email'       => $employee->email,
            'password'    => Hash::make('password123'),
            'is_active'   => true,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_login_mengembalikan_token(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'user']]);
    }

    public function test_login_gagal_tanpa_kredensial_valid(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'salah',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_endpoint_terlindungi_butuh_token(): void
    {
        $this->getJson('/api/v1/auth/profile')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_karyawan_tidak_bisa_akses_menu_hrd(): void
    {
        $user = $this->userWithRole(Role::EMPLOYEE);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/users')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_hrd_bisa_akses_menu_hrd(): void
    {
        $user = $this->userWithRole(Role::HRD);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/users')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_kadiv_tidak_bisa_mengakses_ai(): void
{
    $user = $this->userWithRole(Role::KADIV);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ai/conversations')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
}

public function test_employee_tidak_bisa_mengakses_ai(): void
{
    $user = $this->userWithRole(Role::EMPLOYEE);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ai/conversations')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
}

public function test_hrd_bisa_mengakses_ai(): void
{
    $user = $this->userWithRole(Role::HRD);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/ai/conversations')
        ->assertOk()
        ->assertJsonPath('success', true);
}

    public function test_format_json_seragam(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
