<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_matching_identifiers_when_auto_generation_is_disabled(): void
    {
        $role = Role::create([
            'name' => Role::HRD,
            'scope' => 'GLOBAL',
            'is_system' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $office = Office::query()->where('code', '1')->firstOrFail();
        $division = Division::create([
            'code' => 'IND',
            'name' => 'Industrial',
            'employee_code_digit' => 1,
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'code' => 'PRO',
            'name' => 'Produksi',
            'division_id' => $division->id,
            'employee_code_digit' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
            'nip' => '111001',
            'full_name' => 'Karyawan Manual',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'unit_id' => $unit->id,
            'auto_generate_employee_code' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $employee = Employee::query()->where('nip', '111001')->firstOrFail();

        $this->assertSame('111001', $employee->employee_code);
        $this->assertSame('111001', $employee->device_user_identifier);
    }
}
