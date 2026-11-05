<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\DeductionCalculationHistory;
use App\Models\DeductionEvent;
use App\Models\DeductionRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/*
  DEDUCTION ENGINE — aturan bisnis

    P = (Ec x 2%) + (Alpha x 4%)
    Ec = max(0, jumlah_LATE - 2)   [toleransi bulanan, per karyawan]
    D  = bulatkan ke rupiah terdekat(N x P / 100)
    raw_final = N - D
    final = raw_final > 0 ? ceil(raw_final / 1000) * 1000 : raw_final

  Nominal dasar (N) DITENTUKAN MANUAL OLEH HRD. Sistem bukan payroll
 */
class DeductionService
{
    // PPh/full payroll berada di luar scope

    //Parameter yang dapat dikonfigurasi HRD

    protected function tolerance(): int
    {
        return (int) Setting::get('DEDUCTION_TOLERANCE_EVENTS', 2);
    }

    protected function lateRate(): float
    {
        return (float) Setting::get('DEDUCTION_RATE_PER_EVENT', 2);
    }

    protected function maxPercentage(): float
    {
        return (float) Setting::get('MAX_DEDUCTION_PERCENTAGE', 100);
    }

    protected function alphaRate(): float
    {
        return (float) Setting::get('ALPHA_DEDUCTION_RATE_PERCENT', 4);
    }

    /** Alpha tidak pernah ditoleransi. Dipertahankan sebagai konstanta bisnis. */
    protected function alphaIgnoresTolerance(): bool
    {
        return true;
    }

    public function periodRange(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    public function isPastCutoff(string $period): bool
    {
        [, $end] = $this->periodRange($period);
        $endDate = Carbon::parse($end);
        $cutoffDay = (int) Setting::get('DEDUCTION_CUTOFF_DAY', 0);

        $cutoff = $cutoffDay > 0
            ? $endDate->copy()->day(min($cutoffDay, $endDate->daysInMonth))
            : $endDate->copy();

        return now()->startOfDay()->greaterThan($cutoff->startOfDay());
    }

    //Ekstraksi event (murni, dapat diaudit, idempoten)

    /**
     * @return array{events: array, late_count: int, alpha_count: int, early_count: int, percentage: float}
     */
    public function buildEvents(int $employeeId, string $period): array
    {
        [$start, $end] = $this->periodRange($period);

        $records = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->whereNotNull('violation_types')
            ->where('violation_types', '!=', '')
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $tolerance = $this->tolerance();
        $rate = $this->lateRate();
        $alphaRate = $this->alphaRate();

        $lateSeq = 0;
        $alphaCount = 0;
        $earlyCount = 0;
        $events = [];

        foreach ($records as $record) {
            foreach ($record->violations() as $type) {
                // Pulang Cepat: HANYA warning. Tidak masuk deduction_events,
                // tidak memakan toleransi, tidak menambah persentase
                if ($type === AttendanceRecord::EVENT_EARLY_DEPARTURE) {
                    $earlyCount++;

                    continue;
                }

                if ($type === AttendanceRecord::EVENT_ABSENT) {
                    $alphaCount++;
                    $events[] = [
                        'attendance_record_id' => $record->id,
                        'event_date' => $record->work_date->toDateString(),
                        'event_type' => $type,
                        'sequence' => count($events) + 1,
                        'is_tolerated' => false,
                        'percentage' => $alphaRate,
                        'rate' => $alphaRate,
                    ];

                    continue;
                }

                if ($type !== AttendanceRecord::EVENT_LATE) {
                    continue; // INCOMPLETE / status lain bukan pelanggaran otomatis
                }

                $lateSeq++;
                $isTolerated = $lateSeq <= $tolerance;
                $events[] = [
                    'attendance_record_id' => $record->id,
                    'event_date' => $record->work_date->toDateString(),
                    'event_type' => $type,
                    'sequence' => count($events) + 1,
                    'is_tolerated' => $isTolerated,
                    'percentage' => $isTolerated ? 0.0 : $rate,
                    'rate' => $rate,
                ];
            }
        }

        $charged = max(0, $lateSeq - $tolerance);
        $percentage = min(
            ($charged * $rate) + ($alphaCount * $alphaRate),
            $this->maxPercentage()
        );

        return [
            'events' => $events,
            'late_count' => $lateSeq,
            'alpha_count' => $alphaCount,
            'early_count' => $earlyCount,
            'charged' => $charged,
            'tolerance' => $tolerance,
            'rate' => $rate,
            'alpha_rate' => $alphaRate,
            'percentage' => $percentage,
        ];
    }

    // Perhitungan

    public function calculateForEmployee(int $employeeId, string $period, array $options = []): DeductionRecord
    {
        $this->assertPeriod($period);

        $summary = $this->buildEvents($employeeId, $period);

        return DB::transaction(function () use ($employeeId, $period, $summary, $options) {
            $existing = DeductionRecord::query()
                ->where('employee_id', $employeeId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->isFinal()) {
                throw new RuntimeException(
                    'Periode sudah Final; gunakan alur koreksi berjenjang untuk mengubah.',
                    409
                );
            }

            $baseAmount = $existing?->base_amount !== null
                ? (string) $existing->base_amount
                : null;

            $amounts = $baseAmount !== null
                ? $this->computeAmounts($baseAmount, $summary['percentage'])
                : $this->zeroAmounts();

            $payload = [
                // Early departure hanya warning, bukan event deduction.
                'total_events' => $summary['late_count'] + $summary['alpha_count'],
                'tolerated_events' => min($summary['late_count'], $summary['tolerance']),
                'charged_events' => $summary['charged'],
                'rate_per_event' => $summary['rate'],
                'total_percentage' => $summary['percentage'],
                'alpha_events' => $summary['alpha_count'],
                'alpha_rate' => $summary['alpha_rate'],
                'alpha_percentage' => $summary['alpha_count'] * $summary['alpha_rate'],
                // Pulang Cepat hanya dilaporkan, tidak pernah dihitung.
                'early_departure_events' => $summary['early_count'],
                'base_amount' => $baseAmount,
                'deduction_amount' => $amounts['deduction_amount'],
                'raw_final_amount' => $amounts['raw_final_amount'],
                'rounding_adjustment' => $amounts['rounding_adjustment'],
                'pph_amount' => $amounts['pph_amount'],
                'pph_details' => $amounts['pph_details'],
                'final_amount' => $amounts['final_amount'],
                'status' => DeductionRecord::STATUS_CALCULATED,
                'calculated_at' => now(),
            ];

            $record = $existing
                ? tap($existing)->update($payload)
                : DeductionRecord::create(array_merge($payload, [
                    'employee_id' => $employeeId,
                    'period' => $period,
                ]));

            $record->refresh();

            // Rincian event disimpan ulang secara idempoten (unique employee+period).
            $record->events()->delete();

            foreach ($summary['events'] as $event) {
                unset($event['rate']);
                DeductionEvent::create(array_merge($event, ['deduction_record_id' => $record->id]));
            }

            $this->writeHistory($record, [
                'action' => ! empty($options['recalculate'])
                    ? DeductionCalculationHistory::ACTION_RECALCULATE
                    : DeductionCalculationHistory::ACTION_CALCULATE,
                'actor_user_id' => $options['actor_user_id'] ?? null,
                'note' => $options['note'] ?? null,
            ]);

            return $record;
        });
    }

    public function calculatePeriod(string $period, array $options = []): array
    {
        $this->assertPeriod($period);

        $query = Employee::query()
        ->where('employment_status', Employee::STATUS_ACTIVE);

    if (! empty($options['office_id'])) {
        $query->where('office_id', $options['office_id']);
        }

    if (! empty($options['division_id'])) {
        $query->where('division_id', $options['division_id']);
        }

    if (! empty($options['unit_id'])) {
        $query->where('unit_id', $options['unit_id']);
        }

        if (! empty($options['employee_ids'])) {
            $query->whereIn('id', $options['employee_ids']);
        }

        $calculated = 0;
        $skipped = 0;

        // Proses bertahap agar perhitungan bulanan tidak memuat seluruh
        // daftar karyawan ke memory sekaligus.
        $query->orderBy('id')->chunkById(100, function ($employees) use ($period, $options, &$calculated, &$skipped): void {
            foreach ($employees as $employee) {
                try {
                    $this->calculateForEmployee($employee->id, $period, [
                        'recalculate' => ! empty($options['recalculate']),
                        'actor_user_id' => $options['actor_user_id'] ?? null,
                    ]);
                    $calculated++;
                } catch (RuntimeException $e) {
                    if ($e->getCode() === 409) {
                        $skipped++;
                        continue;
                    }

                    throw $e;
                }
            }
        });

        return ['period' => $period, 'calculated' => $calculated, 'skipped' => $skipped];
    }

    /** Input / perbarui nominal dasar (N) oleh HRD. */
    public function inputBaseAmount(int $deductionId, string|int|float $baseAmount, array $options = []): DeductionRecord
    {
        $normalizedBase = Money::normalize($baseAmount);
        if (Money::toCents($normalizedBase) <= 0) {
            throw ValidationException::withMessages(['base_amount' => 'Nominal dasar harus lebih besar dari 0.']);
        }

        return DB::transaction(function () use ($deductionId, $normalizedBase, $options): DeductionRecord {
            $record = DeductionRecord::query()->whereKey($deductionId)->lockForUpdate()->firstOrFail();

            if ($record->isFinal()) {
                throw new RuntimeException('Status Final; perubahan hanya melalui koreksi berjenjang.', 409);
            }

            $amounts = $this->computeAmounts($normalizedBase, (string) $record->total_percentage);

            $record->update(array_merge($amounts, [
                'base_amount' => $normalizedBase,
                'status' => $record->status === DeductionRecord::STATUS_DRAFT
                    ? DeductionRecord::STATUS_CALCULATED
                    : $record->status,
            ]));

            $record->refresh();

            $this->writeHistory($record, [
                'action' => DeductionCalculationHistory::ACTION_INPUT_NOMINAL,
                'actor_user_id' => $options['actor_user_id'] ?? null,
                'note' => $options['note'] ?? null,
            ]);

            return $record;
        }, 3);
    }

    /**
     * Konfirmasi HRD -> FINAL. Wajib: nominal dasar terisi, status CALCULATED,
     * periode sudah lewat cut-off, dan seluruh event dasar valid.
     */
    public function confirm(int $deductionId, int $userId, ?string $note = null): DeductionRecord
    {
        return DB::transaction(function () use ($deductionId, $userId, $note): DeductionRecord {
            $record = DeductionRecord::query()
                ->whereKey($deductionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->isFinal()) {
                throw new RuntimeException('Periode sudah Final.', 409);
            }

            if ($record->status !== DeductionRecord::STATUS_CALCULATED) {
                throw new RuntimeException("Status {$record->status} tidak dapat difinalisasi.", 409);
            }

            if ($record->base_amount === null || Money::toCents((string) $record->base_amount) <= 0) {
                throw ValidationException::withMessages([
                    'base_amount' => 'Nominal dasar wajib terisi sebelum finalisasi.',
                ]);
            }

            if (! $this->isPastCutoff($record->period)) {
                throw new RuntimeException('Periode belum melewati tanggal cut-off.', 409);
            }

            if ($record->events()->where('event_type', AttendanceRecord::EVENT_EARLY_DEPARTURE)->exists()) {
                throw new RuntimeException('Pulang Cepat tidak boleh menjadi event deduction.', 409);
            }

            // Pastikan nominal tersimpan konsisten dengan persentase terakhir
            // sebelum status Final ditetapkan.
            $amounts = $this->computeAmounts((string) $record->base_amount, (string) $record->total_percentage);
            $record->update(array_merge($amounts, [
                'status' => DeductionRecord::STATUS_FINAL,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'confirmation_note' => $note,
            ]));

            $record->refresh();
            $this->writeHistory($record, [
                'action' => DeductionCalculationHistory::ACTION_FINALIZE,
                'actor_user_id' => $userId,
                'note' => $note,
            ]);

            return $record;
        }, 3);
    }

    /**
     * PPh berada di luar scope fase pertama (PRD 4.2). Method dipertahankan
     * sementara untuk kompatibilitas internal, tetapi tidak mengubah nominal.
     */
    public function recalculateTax(string $period, array $options = []): array
    {
        $this->assertPeriod($period);

        return ['period' => $period, 'updated' => 0, 'skipped' => true, 'reason' => 'PPh di luar scope fase pertama.'];
    }

    /** Simulasi perhitungan tanpa menyimpan. */
    public function preview(int $employeeId, string $period, float $baseAmount = 0): array
    {
        $this->assertPeriod($period);
        $summary = $this->buildEvents($employeeId, $period);
        $amounts = $this->computeAmounts($baseAmount, $summary['percentage']);

        return [
            'employee_id' => $employeeId,
            'period' => $period,
            'total_events' => $summary['late_count'] + $summary['alpha_count'],
            'late_events' => $summary['late_count'],
            'alpha_events' => $summary['alpha_count'],
            'early_departure_events' => $summary['early_count'],
            'tolerance' => $summary['tolerance'],
            'charged_events' => $summary['charged'],
            'rate_per_event' => $summary['rate'],
            'alpha_rate' => $summary['alpha_rate'],
            'alpha_percentage' => $summary['alpha_count'] * $summary['alpha_rate'],
            'early_departure_percentage' => 0,
            'total_percentage' => $summary['percentage'],
            'amounts' => $amounts,
            'events' => array_map(fn ($e) => [
                'date' => $e['event_date'],
                'type' => $e['event_type'],
                'percentage' => $e['percentage'],
                'is_tolerated' => $e['is_tolerated'],
            ], $summary['events']),
        ];
    }

    // ---------------------------------------------------------------
    // Nominal — decimal-safe & pembulatan akhir Rp1.000
    // ---------------------------------------------------------------

    protected function computeAmounts(string|int|float $baseAmount, string|int|float $percentage): array
    {
        $baseAmount = Money::normalize($baseAmount);
        $percentage = Money::normalize($percentage);
        $deduction = Money::deductionAmount($baseAmount, $percentage);
        // Fase pertama hanya deduction kehadiran; PPh tidak ikut dihitung.
        $pphAmount = '0.00';
        $raw = Money::rawFinalAmount($baseAmount, $deduction, $pphAmount);
        $rounding = Money::applyFinalRounding($raw);

        return [
            'base_amount' => Money::normalize($baseAmount),
            'total_percentage' => Money::normalize($percentage),
            'deduction_amount' => $deduction,
            'raw_final_amount' => $rounding['raw_final_amount'],
            'rounding_adjustment' => $rounding['rounding_adjustment'],
            'pph_amount' => '0.00',
            'pph_details' => null,
            'final_amount' => $rounding['final_amount'],
        ];
    }

    protected function zeroAmounts(): array
    {
        return [
            'base_amount' => null,
            'deduction_amount' => 0,
            'raw_final_amount' => 0,
            'rounding_adjustment' => 0,
            'pph_amount' => 0,
            'pph_details' => null,
            'final_amount' => 0,
        ];
    }

    protected function assertPeriod(string $period): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => 'Format periode harus YYYY-MM.']);
        }
    }

    // ---------------------------------------------------------------
    // Riwayat
    // ---------------------------------------------------------------

    protected function writeHistory(DeductionRecord $record, array $options): DeductionCalculationHistory
    {
        return DeductionCalculationHistory::create([
            'deduction_record_id' => $record->id,
            'period' => $record->period,
            'employee_id' => $record->employee_id,
            'total_events' => $record->total_events,
            'tolerated_events' => $record->tolerated_events,
            'charged_events' => $record->charged_events,
            'alpha_events' => $record->alpha_events,
            'early_departure_events' => $record->early_departure_events ?? 0,
            'alpha_rate' => $record->alpha_rate,
            'rate_per_event' => $record->rate_per_event,
            'total_percentage' => $record->total_percentage,
            'base_amount' => $record->base_amount,
            'deduction_amount' => $record->deduction_amount,
            'raw_final_amount' => $record->raw_final_amount ?? 0,
            'rounding_adjustment' => $record->rounding_adjustment ?? 0,
            'pph_amount' => $record->pph_amount,
            'pph_details' => $record->pph_details,
            'final_amount' => $record->final_amount,
            'status' => $record->status,
            'action' => $options['action'],
            'actor_user_id' => $options['actor_user_id'] ?? null,
            'note' => $options['note'] ?? null,
        ]);
    }

    public static function eventLabel(string $type): string
    {
        return match ($type) {
            AttendanceRecord::EVENT_LATE => 'Terlambat',
            AttendanceRecord::EVENT_EARLY_DEPARTURE => 'Pulang Cepat (peringatan, 0%)',
            AttendanceRecord::EVENT_ABSENT => 'Tanpa Keterangan (Alpha)',
            default => $type,
        };
    }
}
