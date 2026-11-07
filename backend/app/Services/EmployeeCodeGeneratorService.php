<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeCodeHistory;
use App\Models\Office;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeCodeGeneratorService
{
    /* Membuat Employee ID baru atau menggantinya
      ketika penempatan karyawan berubah */
    public function assignOrChange(
        Employee $employee,
        int $officeId,
        int $divisionId,
        ?int $unitId = null,
        ?string $reason = null,
        ?int $createdBy = null,
        ?string $effectiveFrom = null,
    ): Employee {
        return DB::transaction(function () use (
            $employee,
            $officeId,
            $divisionId,
            $unitId,
            $reason,
            $createdBy,
            $effectiveFrom
        ): Employee {
            // Kunci employee
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);

            // Kunci master kantor untuk serialisasi
            // pembuatan kode pada kantor yang sama
            $office = Office::query()
                ->lockForUpdate()
                ->findOrFail($officeId);

            $division = Division::query()
                ->lockForUpdate()
                ->findOrFail($divisionId);

            $unit = null;

            if ($unitId !== null) {
                $unit = Unit::query()
                    ->lockForUpdate()
                    ->findOrFail($unitId);
            }

            // Validasi master organisasi
            if (! $office->is_active) {
                throw ValidationException::withMessages([
                    'office_id' => 'Kantor yang dipilih sedang tidak aktif.',
                ]);
            }

            if (! $division->is_active) {
                throw ValidationException::withMessages([
                    'division_id' => 'Divisi yang dipilih sedang tidak aktif.',
                ]);
            }

            if (! preg_match('/^[0-9]$/', (string) $office->code)) {
                throw ValidationException::withMessages([
                    'office_id' => 'Kode kantor harus satu digit angka.',
                ]);
            }

                        $rawDivisionDigit = $division->getRawOriginal('employee_code_digit');
            $divisionDigit = filter_var($rawDivisionDigit, FILTER_VALIDATE_INT);

            if (
                $divisionDigit === false
                || $divisionDigit < 1
                || $divisionDigit > 9
            ) {
                throw ValidationException::withMessages([
                    'division_id' => 'Digit Employee ID divisi belum dikonfigurasi dengan benar.',
                ]);
            }

            // Tentukan digit unit
            $unitDigit = '0';

            if ($unit !== null) {
                if (! $unit->is_active) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Unit yang dipilih sedang tidak aktif.',
                    ]);
                }

                if ((int) $unit->division_id !== (int) $division->id) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Unit tidak berada di dalam divisi yang dipilih.',
                    ]);
                }

                $rawUnitDigit = $unit->getRawOriginal('employee_code_digit');
                $validatedUnitDigit = filter_var($rawUnitDigit, FILTER_VALIDATE_INT);

                if (
                    $validatedUnitDigit === false
                    || $validatedUnitDigit < 1
                    || $validatedUnitDigit > 9
                ) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Digit Employee ID unit belum dikonfigurasi.',
                    ]);
                }

                $unitDigit = (string) $validatedUnitDigit;
            } else {
                $hasUnits = Unit::query()
                    ->where('division_id', $division->id)
                    ->exists();

                if ($hasUnits) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Divisi ini memiliki unit. Silakan pilih unit karyawan.',
                    ]);
                }
            }

            // Validasi tanggal efektif
            try {
                $date = $effectiveFrom !== null
                    ? CarbonImmutable::parse($effectiveFrom)->startOfDay()
                    : CarbonImmutable::today();
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Tanggal efektif Employee ID tidak valid.',
                ]);
            }

            $dateString = $date->toDateString();

            // Periksa histori yang sedang aktif
            $activeHistories = EmployeeCodeHistory::query()
                ->where('employee_id', $employee->id)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->get();

            if ($employee->employee_code !== null) {
                if ($activeHistories->count() !== 1) {
                    throw ValidationException::withMessages([
                        'employee_code' => 'Histori aktif Employee ID tidak konsisten. Perlu pemeriksaan data.',
                    ]);
                }

                $oldHistory = $activeHistories->first();

                if ($oldHistory->employee_code !== $employee->employee_code) {
                    throw ValidationException::withMessages([
                        'employee_code' => 'Employee ID aktif tidak cocok dengan histori. Perlu pemeriksaan data.',
                    ]);
                }

                if ($date->lessThanOrEqualTo(
                    CarbonImmutable::parse($oldHistory->effective_from)->startOfDay()
                )) {
                    throw ValidationException::withMessages([
                        'effective_from' => 'Tanggal efektif baru harus setelah tanggal mulai Employee ID sebelumnya.',
                    ]);
                }
            } elseif ($activeHistories->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'employee_code' => 'Terdapat histori aktif tanpa Employee ID pada data karyawan.',
                ]);
            }

            // Periksa apakah penempatan berubah
            $samePlacement =
                (int) $employee->office_id === (int) $office->id
                && (int) $employee->division_id === (int) $division->id
                && (int) $employee->unit_id === (int) ($unit?->id ?? 0);

            if ($samePlacement && $employee->employee_code !== null) {
                return $employee->fresh([
                    'office',
                    'division',
                    'unit',
                    'employeeCodeHistories',
                ]);
            }

            // Bentuk prefix Employee ID
            $prefix = (string) $office->code
    . (string) $divisionDigit
    . $unitDigit;

            // Ambil kode yang pernah atau sedang digunakan
            // Kode historis tidak boleh digunakan kembali
            $historyCodes = EmployeeCodeHistory::query()
                ->where('employee_code', 'like', $prefix . '%')
                ->pluck('employee_code');

            $currentCodes = Employee::query()
                ->where('employee_code', 'like', $prefix . '%')
                ->pluck('employee_code');

            $usedCodes = $historyCodes
                ->merge($currentCodes)
                ->unique();

            $maxSequence = 0;

            foreach ($usedCodes as $usedCode) {
                if (
                    is_string($usedCode)
                    && preg_match('/^[0-9]{6}$/', $usedCode)
                ) {
                    $sequence = (int) substr($usedCode, 3, 3);
                    $maxSequence = max($maxSequence, $sequence);
                }
            }

            $nextSequence = $maxSequence + 1;

            if ($nextSequence > 999) {
                throw ValidationException::withMessages([
                    'employee_code' => 'Nomor urut Employee ID untuk kombinasi kantor, divisi, dan unit ini sudah penuh.',
                ]);
            }

            $newCode = $prefix . str_pad(
                (string) $nextSequence,
                3,
                '0',
                STR_PAD_LEFT
            );

            // Tutup hanya histori Employee ID yang sedang aktif
            if ($employee->employee_code !== null) {
                $oldHistory = $activeHistories->first();

                $oldHistory->update([
                    'effective_to' => $date->subDay()->toDateString(),
                ]);
            }

            // Simpan penempatan dan Employee ID baru
            $employee->forceFill([
                'office_id' => $office->id,
                'division_id' => $division->id,
                'unit_id' => $unit?->id,
                'employee_code' => $newCode,
            ])->save();

            // Simpan histori Employee ID baru
            EmployeeCodeHistory::query()->create([
                'employee_id' => $employee->id,
                'employee_code' => $newCode,
                'office_id' => $office->id,
                'division_id' => $division->id,
                'unit_id' => $unit?->id,
                'effective_from' => $dateString,
                'effective_to' => null,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);

            return $employee->fresh([
                'office',
                'division',
                'unit',
                'employeeCodeHistories',
            ]);
        }, 3);
    }
}