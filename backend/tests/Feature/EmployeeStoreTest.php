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
        [$user, $office, $division, $unit] = $this->hrdPlacement();

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
        $this->assertSame('111001', $employee->nip);
        $this->assertSame('111001', $employee->device_user_identifier);
    }

    public function test_copies_generated_employee_code_to_nip_and_device_identifier_when_auto_generation_is_enabled(): void
    {
        [$user, $office, $division, $unit] = $this->hrdPlacement();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
            'full_name' => 'Karyawan Otomatis',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'unit_id' => $unit->id,
            'auto_generate_employee_code' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_code', '111001')
            ->assertJsonPath('data.nip', '111001')
            ->assertJsonPath('data.device_user_identifier', '111001');

        $employee = Employee::query()->where('employee_code', '111001')->firstOrFail();

        $this->assertSame('111001', $employee->nip);
        $this->assertSame('111001', $employee->device_user_identifier);
        $this->assertSame(1, Employee::query()->count());
    }

    public function test_ignores_existing_placeholder_nip_when_auto_generation_is_enabled(): void
    {
        [$user, $office, $division, $unit] = $this->hrdPlacement();

        Employee::query()->create([
            'nip' => '000000',
            'full_name' => 'Karyawan Placeholder',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
            'nip' => '000000',
            'full_name' => 'Karyawan Baru',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'unit_id' => $unit->id,
            'auto_generate_employee_code' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_code', '111001')
            ->assertJsonPath('data.nip', '111001')
            ->assertJsonPath('data.device_user_identifier', '111001');

        $this->assertDatabaseHas('employees', [
            'full_name' => 'Karyawan Placeholder',
            'nip' => '000000',
            'device_user_identifier' => '000000',
        ]);

        $this->assertDatabaseHas('employees', [
            'full_name' => 'Karyawan Baru',
            'employee_code' => '111001',
            'nip' => '111001',
            'device_user_identifier' => '111001',
        ]);

        $this->assertSame(2, Employee::query()->count());
    }

    public function test_skips_generated_code_that_already_exists_as_another_employee_nip(): void
    {
        [$user, $office, $division, $unit] = $this->hrdPlacement();

        Employee::query()->create([
            'nip' => '111001',
            'full_name' => 'Karyawan Lama',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
            'nip' => '111001',
            'full_name' => 'Karyawan Baru',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'unit_id' => $unit->id,
            'auto_generate_employee_code' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_code', '111002')
            ->assertJsonPath('data.nip', '111002')
            ->assertJsonPath('data.device_user_identifier', '111002');

        $this->assertDatabaseHas('employees', [
            'full_name' => 'Karyawan Lama',
            'nip' => '111001',
        ]);
    }

    /**
     * @return array{0: User, 1: Office, 2: Division, 3: Unit}
     */
    private function hrdPlacement(): array
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

        return [$user, $office, $division, $unit];
    }
}
