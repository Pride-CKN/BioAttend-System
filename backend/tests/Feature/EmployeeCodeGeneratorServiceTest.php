<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeCodeHistory;
use App\Models\Office;
use App\Models\Unit;
use App\Services\EmployeeCodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCodeGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_six_digit_employee_code(): void
    {
        $office = Office::query()
    ->where('code', '1')
    ->firstOrFail();

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

        $employee = Employee::create([
            'nip' => 'NIP-00001',
            'full_name' => 'Karyawan Test',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $result = app(EmployeeCodeGeneratorService::class)
            ->assignOrChange(
                employee: $employee,
                officeId: $office->id,
                divisionId: $division->id,
                unitId: $unit->id,
                effectiveFrom: '2026-11-06',
            );

        $this->assertSame('111001', $result->employee_code);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employee_code' => '111001',
        ]);

        $this->assertDatabaseHas('employee_code_histories', [
            'employee_id' => $employee->id,
            'employee_code' => '111001',
            'office_id' => $office->id,
            'division_id' => $division->id,
            'unit_id' => $unit->id,
            'effective_from' => '2026-11-06',
            'effective_to' => null,
        ]);

        $this->assertSame(1, EmployeeCodeHistory::count());

        
    }
    
    public function test_generates_new_code_and_closes_old_history_when_employee_moves_unit(): void
    {
        $office = Office::query()
            ->where('code', '1')
            ->firstOrFail();

        $division = Division::create([
            'code' => 'IND',
            'name' => 'Industrial',
            'employee_code_digit' => 1,
            'is_active' => true,
        ]);

        $unitProduksi = Unit::create([
            'code' => 'PRO',
            'name' => 'Produksi',
            'division_id' => $division->id,
            'employee_code_digit' => 1,
            'is_active' => true,
        ]);

        $unitPergudangan = Unit::create([
            'code' => 'GUD',
            'name' => 'Pergudangan',
            'division_id' => $division->id,
            'employee_code_digit' => 2,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'nip' => 'NIP-00002',
            'full_name' => 'Karyawan Pindah Unit',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $service = app(EmployeeCodeGeneratorService::class);

        // Penempatan pertama: Produksi.
        $firstResult = $service->assignOrChange(
            employee: $employee,
            officeId: $office->id,
            divisionId: $division->id,
            unitId: $unitProduksi->id,
            effectiveFrom: '2026-11-06',
        );

        $this->assertSame('111001', $firstResult->employee_code);

        // Pindah ke Pergudangan mulai 1 Desember.
        $secondResult = $service->assignOrChange(
            employee: $firstResult,
            officeId: $office->id,
            divisionId: $division->id,
            unitId: $unitPergudangan->id,
            effectiveFrom: '2026-12-01',
        );

        $this->assertSame('112001', $secondResult->employee_code);

        $this->assertDatabaseHas('employee_code_histories', [
            'employee_id' => $employee->id,
            'employee_code' => '111001',
            'effective_from' => '2026-11-06',
            'effective_to' => '2026-11-30',
        ]);

        $this->assertDatabaseHas('employee_code_histories', [
            'employee_id' => $employee->id,
            'employee_code' => '112001',
            'effective_from' => '2026-12-01',
            'effective_to' => null,
        ]);

        $this->assertSame(
            2,
            EmployeeCodeHistory::where('employee_id', $employee->id)->count()
        );
    }

}
