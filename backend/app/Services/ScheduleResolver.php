<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\WorkScheduleConfig;
use Carbon\Carbon;

/*
  Resolusi jadwal aktif karyawan

  Prioritas: EMPLOYEE (perorangan) › DIVISION › COMPANY
  Hari libur mengesampingkan kewajiban hadir
 */
class ScheduleResolver
{
    /* Hari kerja default bila konfigurasi tidak mengaturnya: Senin..Jumat */
    public const DEFAULT_WORK_DAYS = [1, 2, 3, 4, 5];

    /*
      Hasil resolusi:
       - shift          : objek Shift aktif (null bila tidak ada)
       - scope          : EMPLOYEE / DIVISION / COMPANY / NONE
       - late_tolerance : menit toleransi keterlambatan
       - work_days      : hari kerja efektif
       - is_holiday     : tanggal tersebut hari libur
       - is_work_day    : tanggal tersebut wajib hadir
     */
    public function resolve(Employee $employee, string|Carbon $date): array
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        $isHoliday = $this->isHoliday($date);

        $config = $this->resolveConfig($employee, $date);

        $shift = $config?->shift;

        // Fallback terakhir: penugasan jadwal harian manual (tabel schedules)
        if (! $shift) {
            $shift = $this->resolveFromScheduleTable($employee, $date);
        }

        $workDays = $config?->work_days ?? self::DEFAULT_WORK_DAYS;
        $isWorkDay = ! $isHoliday && $this->isInWorkDays($date, $workDays);

        $lateTolerance = $config?->late_tolerance_minutes
            ?? $shift?->late_tolerance_minutes
            ?? 0;

        return [
            'date' => $date,
            'shift' => $shift,
            'config' => $config,
            'scope' => $config?->scope_type ?? ($shift ? 'SCHEDULE' : 'NONE'),
            'late_tolerance' => (int) $lateTolerance,
            'work_days' => $workDays,
            'is_holiday' => $isHoliday,
            'is_work_day' => $isWorkDay,
        ];
    }

    /* Ambil konfigurasi berjenjang yang masih berlaku pada tanggal tersebut. */
    protected function resolveConfig(Employee $employee, string $date): ?WorkScheduleConfig
    {
        // 1. Perorangan (prioritas tertinggi)
        $config = WorkScheduleConfig::query()
            ->where('scope_type', WorkScheduleConfig::SCOPE_EMPLOYEE)
            ->where('scope_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();

        if ($config) {
            return $config;
        }

        // 2. Divisi
        if ($employee->division_id) {
            $config = WorkScheduleConfig::query()
                ->where('scope_type', WorkScheduleConfig::SCOPE_DIVISION)
                ->where('scope_id', $employee->division_id)
                ->where('is_active', true)
                ->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
                })
                ->latest('effective_from')
                ->first();

            if ($config) {
                return $config;
            }
        }

        // 3. Perusahaan
        return WorkScheduleConfig::query()
            ->where('scope_type', WorkScheduleConfig::SCOPE_COMPANY)
            ->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->latest('effective_from')
            ->first();
    }

    protected function resolveFromScheduleTable(Employee $employee, string $date): ?Shift
    {
        return $employee->schedules()
            ->where('work_date', $date)
            ->with('shift')
            ->first()
            ?->shift;
    }

public function isHoliday(string $date): bool
{
    return Holiday::query()
        ->whereDate('date', $date)
        ->where('is_active', true)
        ->exists();
}

    /* ISO day of week: 1 = Senin .. 7 = Minggu */
    protected function isInWorkDays(string $date, array $workDays): bool
    {
        return in_array(Carbon::parse($date)->dayOfWeekIso, $workDays, true);
    }

    /*
      Deteksi bentrok rentang berlaku pada tingkat yang sama
      Mengembalikan rentang yang bentrok atau null bila aman
     */
    public function detectConflict(
        string $scopeType,
        ?int $scopeId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): ?WorkScheduleConfig {
        $end = $effectiveTo ?? '9999-12-31';

        return WorkScheduleConfig::query()
            ->where('scope_type', $scopeType)
            ->where(function ($query) use ($scopeId) {
                $scopeId === null
                    ? $query->whereNull('scope_id')
                    : $query->where('scope_id', $scopeId);
            })
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            // Rentang A dan B overlap jika A mulai <= B selesai dan A selesai >= B mulai.
            ->whereDate('effective_from', '<=', $end)
            ->where(function ($query) use ($effectiveFrom) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveFrom);
            })
            ->orderBy('effective_from')
            ->first();
    }
}
