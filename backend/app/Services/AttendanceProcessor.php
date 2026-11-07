<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PermissionRequest;
use App\Models\Setting;
use App\Services\Attendance\EarlyDepartureService;
use App\Services\Attendance\ScanClassifier;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/* Inti pemrosesan kehadiran
 
  PERUBAHAN PENTING (aturan bisnis terbaru):
   - Klasifikasi scan TIDAK lagi memakai titik tengah shift
     Scan pertama = CHECK_IN; scan <= 60 menit dari scan pertama = DUPLICATE;
     scan pertama > 60 menit = CHECK_OUT; sisanya = ADDITIONAL
   - Raw log append-only: hanya metadata pemrosesan yang diperbarui
   - EARLY_DEPARTURE hanya menghasilkan warning (0% deduction) lewat
     EarlyDepartureService; tidak pernah membuat deduction event
   - Shift malam: work_date mengikuti tanggal mulai shift */
class AttendanceProcessor
{
    public function __construct(
        protected ScheduleResolver $resolver,
        protected ScanClassifier $classifier,
        protected EarlyDepartureService $earlyDeparture,
    ) {}

    // Ingest raw log (append-only + idempotency)

    /**
     * Terima satu pemindaian dari perangkat / SDK.
     *
     * @param  array{device_user_identifier?: string, device_log_id?: string, sync_batch_id?: string}  $options
     */
    public function ingest(
        ?Device $device,
        ?int $employeeId,
        Carbon $scannedAt,
        ?array $rawPayload = null,
        array $options = []
    ): AttendanceLog {
        $fingerprint = AttendanceLog::fingerprintFor(
            $device?->id,
            $options['device_log_id'] ?? null,
            $options['device_user_identifier'] ?? null,
            $scannedAt->toIso8601String(),
            $rawPayload
        );

        $attributes = [
            'device_id' => $device?->id,
            'employee_id' => $employeeId,
            'device_user_identifier' => $options['device_user_identifier'] ?? null,
            'device_log_id' => $options['device_log_id'] ?? null,
            'fingerprint' => $fingerprint,
            'sync_batch_id' => $options['sync_batch_id'] ?? null,
            'scanned_at' => $scannedAt,
            'raw_payload' => $rawPayload,
            'processing_status' => $employeeId
                ? AttendanceLog::STATUS_PENDING
                : AttendanceLog::STATUS_UNMATCHED,
            'is_duplicate' => false,
        ];

        try {
            $log = AttendanceLog::create($attributes);
            $created = true;
        } catch (QueryException $e) {
            // Unique constraint (fingerprint / device_log_id) = kiriman ulang
            // Ambil baris yang sudah ada; JANGAN mengubah bukti aslinya
            $log = AttendanceLog::query()
                ->where('fingerprint', $fingerprint)
                ->first();

            if (! $log && ! empty($options['device_log_id'])) {
                $log = AttendanceLog::query()
                    ->where('device_id', $device?->id)
                    ->where('device_log_id', $options['device_log_id'])
                    ->first();
            }

            if (! $log) {
                throw $e;
            }

            $created = false;
        }

        if (! $created || ! $employeeId) {
            return $log;
        }

        $employee = Employee::find($employeeId);

        if (! $employee) {
            $log->forceFill(['processing_status' => AttendanceLog::STATUS_UNMATCHED])->save();

            return $log;
        }

        $workDate = $this->resolveWorkDate($employee, $scannedAt);
        $this->reclassifyWorkDate($employee, $workDate);

        return $log->refresh();
    }

    // Klasifikasi 60 menit

    /* Terapkan aturan 60 menit ke SELURUH log milik satu tanggal kerja
      Idempoten: dipanggil ulang kapan saja menghasilkan keputusan identik */
    public function reclassifyWorkDate(Employee $employee, string $workDate): int
    {
        return DB::transaction(function () use ($employee, $workDate): int {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            return $this->reclassifyWorkDateLocked($employee, $workDate);
        }, 3);
    }

    protected function reclassifyWorkDateLocked(Employee $employee, string $workDate): int
    {
        $window = (int) Setting::getValue('DUPLICATE_WINDOW_MINUTES', 60);

        $candidates = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [
                Carbon::parse($workDate, config('app.timezone'))->startOfDay(),
                Carbon::parse($workDate, config('app.timezone'))->addDay()->endOfDay(),
            ])
            ->orderBy('scanned_at')
            ->get();

        // Shift malam: saring berdasarkan tanggal kerja hasil resolver,
        // bukan tanggal kalender timestamp
        $logs = $candidates->filter(
            fn ($log) => $this->resolveWorkDate($employee, Carbon::parse($log->scanned_at)) === $workDate
        )->values();

        $decisions = $this->classifier->classify($logs, $window);
        $updated = 0;

        foreach ($logs as $log) {
            $decision = $decisions[$log->id] ?? null;

            if (! $decision) {
                continue;
            }

            // Cek perubahan supaya reprocessing benar-benar idempoten
            if ($log->scan_type === $decision['scan_type']
                && (bool) $log->is_duplicate === $decision['is_duplicate']
                && $log->primary_log_id === $decision['primary_log_id']
                && $log->processing_status === AttendanceLog::STATUS_PROCESSED) {
                continue;
            }

            $log->forceFill([
                'scan_type' => $decision['scan_type'],
                'is_duplicate' => $decision['is_duplicate'],
                'primary_log_id' => $decision['primary_log_id'],
                'processing_status' => AttendanceLog::STATUS_PROCESSED,
            ])->save();

            $updated++;
        }

        return $updated;
    }

    /* Kompatibilitas mundur: klasifikasi satu scan (dipakai pemanggil lama)
      Keputusan final tetap ditentukan reclassifyWorkDate()  */
    public function classifyScan(Employee $employee, string $workDate, Carbon $scannedAt): string
    {
        $window = (int) Setting::getValue('DUPLICATE_WINDOW_MINUTES', 60);

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [
                Carbon::parse($workDate, config('app.timezone'))->startOfDay(),
                Carbon::parse($workDate, config('app.timezone'))->addDay()->endOfDay(),
            ])
            ->orderBy('scanned_at')
            ->get()
            ->filter(fn ($log) => $this->resolveWorkDate($employee, Carbon::parse($log->scanned_at)) === $workDate)
            ->values();

        $first = $logs->first();

        if (! $first) {
            return AttendanceLog::TYPE_CHECK_IN;
        }

        $hasCheckOut = $logs->contains(fn ($l) => in_array($l->scan_type, [
            AttendanceLog::TYPE_CHECK_OUT, AttendanceLog::TYPE_ADDITIONAL,
        ], true));

        if ($hasCheckOut) {
            return AttendanceLog::TYPE_ADDITIONAL;
        }

        $seconds = Carbon::parse($scannedAt)->getTimestamp() - Carbon::parse($first->scanned_at)->getTimestamp();

        return $seconds <= ($window * 60)
            ? AttendanceLog::TYPE_DUPLICATE
            : AttendanceLog::TYPE_CHECK_OUT;
    }


    // Work date & shift
    /* Shift malam: work_date mengikuti tanggal MULAI shift */
    public function resolveWorkDate(Employee $employee, Carbon $scannedAt): string
    {
        $resolved = $this->resolver->resolve($employee, $scannedAt->toDateString());
        $shift = $resolved['shift'];

        if (! $shift) {
            return $scannedAt->toDateString();
        }

        $startMinutes = $this->timeToMinutes($shift->start_time);
        $scanMinutes = $scannedAt->hour * 60 + $scannedAt->minute;

        if ($shift->is_night_shift && $scanMinutes < $startMinutes) {
            return $scannedAt->copy()->subDay()->toDateString();
        }

        return $scannedAt->toDateString();
    }

    /** @deprecated Klasifikasi tidak lagi memakai titik tengah shift. */
    public function shiftMidpoint(string $workDate, $shift): Carbon
    {
        [$start, $end] = $this->shiftBounds($workDate, $shift);

        return $start->copy()->addSeconds((int) ($start->diffInSeconds($end) / 2));
    }

    /* Batas awal & akhir shift, mendukung lintas tengah malam */
    public function shiftBounds(string $workDate, $shift): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $start = Carbon::parse($workDate.' '.$shift->start_time, $tz);
        $end = Carbon::parse($workDate.' '.$shift->end_time, $tz);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }


    // Derived attendance record

    public function processEmployeeDate(int $employeeId, string $workDate): ?AttendanceRecord
    {
        return DB::transaction(function () use ($employeeId, $workDate): ?AttendanceRecord {
            // Serialisasi per karyawan mencegah dua worker membentuk record
            // untuk employee/work_date yang sama secara bersamaan
            $employee = Employee::query()->whereKey($employeeId)->lockForUpdate()->first();

            if (! $employee) {
                return null;
            }

            return $this->processEmployeeDateLocked($employee, $workDate);
        }, 3);
    }

    protected function processEmployeeDateLocked(Employee $employee, string $workDate): ?AttendanceRecord
    {
        $resolved = $this->resolver->resolve($employee, $workDate);
        $shift = $resolved['shift'];

        // Pastikan seluruh log (termasuk yang masuk via impor / sync) terklasifikasi
        $this->reclassifyWorkDate($employee, $workDate);

        // Cari berdasarkan tanggal (bukan string mentah) agar aman di
        // PostgreSQL maupun SQLite dan mencegah duplikasi saat reprocessing
        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $record) {
            $record = new AttendanceRecord([
                'employee_id' => $employee->id,
                'work_date' => $workDate,
            ]);
        }

        $record->shift_id = $shift?->id;

        // Hari libur / bukan hari kerja mengesampingkan kewajiban hadir
        if ($resolved['is_holiday'] || ! $resolved['is_work_day']) {
            return $this->saveWithoutViolation($record, AttendanceRecord::STATUS_HOLIDAY);
        }

        // Cuti / izin / dinas luar yang disetujui meniadakan Alpha
        if ($this->hasApprovedLeave($employee->id, $workDate)) {
            return $this->saveWithoutViolation($record, AttendanceRecord::STATUS_LEAVE, true);
        }

        $approvedPermissions = $this->approvedPermissions($employee->id, $workDate);
        if ($this->hasFullDayPermission($approvedPermissions)) {
            return $this->saveWithoutViolation($record, AttendanceRecord::STATUS_PERMISSION, true);
        }

        $permissionAdjustment = null;
        if ($shift && $approvedPermissions->isNotEmpty()) {
            [$permissionShiftStart, $permissionShiftEnd] = $this->shiftBounds($workDate, $shift);
            $permissionAdjustment = $this->applyPartialPermissionToShift(
                $approvedPermissions,
                $workDate,
                $permissionShiftStart,
                $permissionShiftEnd
            );

            if ($permissionAdjustment['covers_full_shift']) {
                return $this->saveWithoutViolation($record, AttendanceRecord::STATUS_PERMISSION, true);
            }
        }

        $this->fillFromLogs($record);
        $hasApprovedCorrections = $this->applyApprovedTimeCorrections($record);

        // Tidak ada scan & kewajiban hadir berlaku = Alpha
        if (! $record->check_in_at && ! $record->check_out_at) {
            $record->status = AttendanceRecord::STATUS_ABSENT;
            $record->violation_types = AttendanceRecord::EVENT_ABSENT;
            $record->is_incomplete = false;
            $record->late_minutes = 0;
            $record->early_departure_minutes = 0;
            $record->work_duration_minutes = null;
            $record->source = $hasApprovedCorrections ? 'CORRECTION' : 'AUTO';
            $record->save();

            $this->earlyDeparture->revoke($record);

            return $record;
        }

        // Check-in tanpa check-out = Belum Lengkap (bukan pelanggaran)
        if ($record->check_in_at && ! $record->check_out_at) {
            $record->status = AttendanceRecord::STATUS_INCOMPLETE;
            $record->violation_types = null;
            $record->is_incomplete = true;
            $record->source = $hasApprovedCorrections ? 'CORRECTION' : 'AUTO';
            $record->save();

            $this->earlyDeparture->revoke($record);

            return $record;
        }

        $record->is_incomplete = false;
        $violations = [];

        if ($shift) {
            [$shiftStart, $shiftEnd] = $this->shiftBounds($workDate, $shift);
            if ($permissionAdjustment !== null) {
                $shiftStart = $permissionAdjustment['effective_start'];
                $shiftEnd = $permissionAdjustment['effective_end'];
            }

            $tolerance = (int) $resolved['late_tolerance'];
            $latestAllowed = $shiftStart->copy()->addMinutes($tolerance);

            $record->scheduled_end_at = $shiftEnd;
            $record->late_minutes = $record->check_in_at?->greaterThan($latestAllowed)
                ? (int) $latestAllowed->diffInMinutes($record->check_in_at)
                : 0;

            if ($record->late_minutes > 0) {
                $violations[] = AttendanceRecord::EVENT_LATE;
            }

            // PULANG CEPAT — warning saja, 0% deduction
            if ($record->check_out_at?->lessThan($shiftEnd)) {
                $record->early_departure_minutes = (int) $record->check_out_at->diffInMinutes($shiftEnd);
                $violations[] = AttendanceRecord::EVENT_EARLY_DEPARTURE;
            } else {
                $record->early_departure_minutes = 0;
            }

            if ($record->check_in_at && $record->check_out_at) {
                $actualDuration = (int) $record->check_in_at->diffInMinutes($record->check_out_at);
                // Izin parsial di tengah shift dikeluarkan secara proporsional
                // dari durasi kerja; izin yang menutup awal/akhir shift sudah
                // diakomodasi melalui effective_start/effective_end
                $record->work_duration_minutes = max(
                    0,
                    $actualDuration - ($permissionAdjustment['interior_minutes'] ?? 0)
                );
            }
        } else {
            $record->late_minutes = 0;
            $record->early_departure_minutes = 0;
        }

        $record->violation_types = $violations === [] ? null : implode(',', $violations);
        $record->status = match (true) {
            in_array(AttendanceRecord::EVENT_LATE, $violations, true) => AttendanceRecord::STATUS_LATE,
            in_array(AttendanceRecord::EVENT_EARLY_DEPARTURE, $violations, true) => AttendanceRecord::STATUS_EARLY_DEPARTURE,
            default => AttendanceRecord::STATUS_PRESENT,
        };
        $record->source = $hasApprovedCorrections ? 'CORRECTION' : 'AUTO';
        $record->save();

        // Warning di luar perhitungan nominal. Tidak pernah membuat deduction event
        $this->earlyDeparture->handle($record);

        return $record;
    }

    protected function saveWithoutViolation(
        AttendanceRecord $record,
        string $status,
        bool $keepLogs = false
    ): AttendanceRecord {
        if ($keepLogs) {
            $this->fillFromLogs($record);
        }

        $record->status = $status;
        $record->violation_types = null;
        $record->is_incomplete = false;
        $record->late_minutes = 0;
        $record->early_departure_minutes = 0;
        $record->source = 'AUTO';
        $record->save();

        $this->earlyDeparture->revoke($record);

        return $record;
    }

    /* Isi jam masuk/pulang dari log hasil klasifikasi 60 menit */
    protected function fillFromLogs(AttendanceRecord $record): void
    {
        $logs = AttendanceLog::query()
            ->where('employee_id', $record->employee_id)
            ->where('processing_status', AttendanceLog::STATUS_PROCESSED)
            ->whereBetween('scanned_at', [
                Carbon::parse($record->work_date, config('app.timezone'))->startOfDay(),
                Carbon::parse($record->work_date, config('app.timezone'))->addDay()->endOfDay(),
            ])
            ->orderBy('scanned_at')
            ->get()
            ->filter(fn ($log) => in_array($log->scan_type, [
                AttendanceLog::TYPE_CHECK_IN,
                AttendanceLog::TYPE_CHECK_OUT,
                AttendanceLog::TYPE_DUPLICATE,
                AttendanceLog::TYPE_ADDITIONAL,
            ], true))
            ->values();

        $checkIn = $logs->firstWhere('scan_type', AttendanceLog::TYPE_CHECK_IN);
        // Check-out PERTAMA yang diakui; scan sesudahnya tidak menimpa
        $checkOut = $logs->firstWhere('scan_type', AttendanceLog::TYPE_CHECK_OUT);

        $record->check_in_at = $checkIn?->scanned_at;
        $record->check_out_at = $checkOut?->scanned_at;
        $record->first_scan_at = $checkIn?->scanned_at;
        $record->additional_scan_count = $logs->filter(
            fn ($l) => $l->scan_type === AttendanceLog::TYPE_ADDITIONAL
        )->count();

        if ($record->check_in_at && $record->check_out_at) {
            $record->work_duration_minutes = (int) $record->check_in_at->diffInMinutes($record->check_out_at);
        }
    }

    /* Terapkan koreksi waktu yang sudah disetujui setelah nilai raw log di-resolve
      Raw log tetap utuh; koreksi hanya memengaruhi derived attendance record */
    protected function applyApprovedTimeCorrections(AttendanceRecord $record): bool
    {
        $corrections = AttendanceCorrection::query()
            ->where('attendance_record_id', $record->id)
            ->where('status', AttendanceCorrection::STATUS_APPROVED)
            ->whereIn('field', ['check_in_at', 'check_out_at'])
            ->orderBy('id')
            ->get();

        foreach ($corrections as $correction) {
            $record->{$correction->field} = $correction->new_value !== null
                ? Carbon::parse($correction->new_value, config('app.timezone'))
                : null;
        }

        return $corrections->isNotEmpty();
    }

    protected function hasApprovedLeave(int $employeeId, string $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /** @return \Illuminate\Support\Collection<int, PermissionRequest> */
    protected function approvedPermissions(int $employeeId, string $date)
    {
        return PermissionRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', PermissionRequest::STATUS_APPROVED)
            ->whereDate('date', $date)
            ->orderBy('id')
            ->get();
    }

    protected function hasFullDayPermission($permissions): bool
    {
        return $permissions->contains(fn (PermissionRequest $permission) =>
            $permission->start_time === null && $permission->end_time === null
        );
    }

    /**
     * Terapkan izin parsial pada jam kerja. Izin yang overlap awal/akhir shift
     * menggeser batas kewajiban hadir; izin di tengah shift dikurangi dari
     * durasi kerja. Hasil ini tidak membuat izin parsial menjadi izin seharian
     *
     * @return array{effective_start: Carbon,effective_end: Carbon,interior_minutes:int,covers_full_shift:bool}
     */
    protected function applyPartialPermissionToShift($permissions, string $workDate, Carbon $shiftStart, Carbon $shiftEnd): array
    {
        $effectiveStart = $shiftStart->copy();
        $effectiveEnd = $shiftEnd->copy();
        $interiorMinutes = 0;

        foreach ($permissions as $permission) {
            if (! $permission->start_time || ! $permission->end_time) {
                continue;
            }

            $permissionStart = Carbon::parse($workDate.' '.$permission->start_time, config('app.timezone'));
            $permissionEnd = Carbon::parse($workDate.' '.$permission->end_time, config('app.timezone'));
            if ($permissionEnd->lessThanOrEqualTo($permissionStart)) {
                $permissionEnd->addDay();
            }

            $overlapStart = $permissionStart->greaterThan($shiftStart) ? $permissionStart->copy() : $shiftStart->copy();
            $overlapEnd = $permissionEnd->lessThan($shiftEnd) ? $permissionEnd->copy() : $shiftEnd->copy();
            if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                continue;
            }

            if ($permissionStart->lessThanOrEqualTo($shiftStart) && $permissionEnd->greaterThan($shiftStart)) {
                if ($permissionEnd->lessThan($effectiveEnd)) {
                    $effectiveStart = $permissionEnd->copy();
                }
                continue;
            }

            if ($permissionStart->lessThan($shiftEnd) && $permissionEnd->greaterThanOrEqualTo($shiftEnd)) {
                if ($permissionStart->greaterThan($effectiveStart)) {
                    $effectiveEnd = $permissionStart->copy();
                }
                continue;
            }

            // Izin yang berada di tengah shift: kurangi overlap dari durasi
            $interiorMinutes += (int) $overlapStart->diffInMinutes($overlapEnd);
        }

        return [
            'effective_start' => $effectiveStart,
            'effective_end' => $effectiveEnd,
            'interior_minutes' => $interiorMinutes,
            'covers_full_shift' => $effectiveStart->greaterThanOrEqualTo($effectiveEnd),
        ];
    }

    /* Proses seluruh karyawan aktif untuk satu tanggal kerja */
    public function processDateForAll(string $workDate): array
    {
        $processed = 0;
        $failed = 0;

        Employee::query()
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($workDate, &$processed, &$failed) {
                foreach ($employees as $employee) {
                    try {
                        DB::transaction(fn () => $this->processEmployeeDate($employee->id, $workDate));
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        return ['work_date' => $workDate, 'processed' => $processed, 'failed' => $failed];
    }

    public function flagIncomplete(string $workDate): int
    {
        return AttendanceRecord::query()
            ->whereDate('work_date', $workDate)
            ->whereNull('check_out_at')
            ->whereNotNull('check_in_at')
            ->update(['is_incomplete' => true, 'status' => AttendanceRecord::STATUS_INCOMPLETE]);
    }

    /* Proses ulang log gagal / hasil mapping manual (idempoten) */
    public function reprocessFailed(): int
    {
        $total = 0;

        AttendanceLog::query()
            ->whereIn('processing_status', [
                AttendanceLog::STATUS_FAILED,
                AttendanceLog::STATUS_UNMATCHED,
            ])
            ->whereNotNull('employee_id')
            ->chunkById(100, function ($logs) use (&$total): void {
                $total += $logs->count();

                foreach ($logs as $log) {
                    $employee = Employee::find($log->employee_id);

                    if (! $employee) {
                        continue;
                    }

                    $workDate = $this->resolveWorkDate(
                        $employee,
                        Carbon::parse($log->scanned_at)
                    );

                    $this->reclassifyWorkDate($employee, $workDate);
                    $this->processEmployeeDate($employee->id, $workDate);
                }
            });

        return $total;
    }

    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', substr($time, 0, 5)), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
