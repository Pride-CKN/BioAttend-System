<?php

namespace App\Services\Attendance;

use App\Jobs\WarningNotificationJob;
use App\Models\AttendanceRecord;
use App\Models\AttendanceWarningEvent;
use App\Models\LeaveRequest;
use App\Models\PermissionRequest;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

/*
  PULANG CEPAT = WARNING SAJA, TANPA POTONGAN

  Service ini TIDAK pernah menulis ke deduction_events / deduction_records
  Satu kejadian logis -> satu baris attendance_warning_events (unique key),
  sehingga reprocessing attendance tidak mengirim notifikasi ganda
 */
class EarlyDepartureService
{
    public function __construct(protected \App\Services\NotificationService $notifications) {}

    /*
      Catat warning bila record memenuhi syarat. Mengembalikan null bila
      tidak ada pelanggaran atau ada pengecualian yang sah
     */
    public function handle(AttendanceRecord $record): ?AttendanceWarningEvent
    {
        if (! $record->hasViolation(AttendanceRecord::EVENT_EARLY_DEPARTURE)) {
            $this->revoke($record);

            return null;
        }

        if ($this->hasValidExemption($record)) {
            $this->revoke($record);

            return null;
        }

        $key = $this->idempotencyKey($record);

        $event = DB::transaction(function () use ($record, $key) {
            $existing = AttendanceWarningEvent::query()
                ->where('idempotency_key', $key)
                ->first();

            if ($existing) {
                if ($existing->event_status === AttendanceWarningEvent::STATUS_REVOKED) {
                    $existing->update([
                        'event_status' => AttendanceWarningEvent::STATUS_ACTIVE,
                        'scheduled_end_at' => $record->scheduled_end_at,
                        'actual_check_out_at' => $record->check_out_at,
                        'minutes_early' => (int) $record->early_departure_minutes,
                        'delivery_status' => $existing->delivery_status,
                    ]);
                }

                return $existing->refresh();
            }

            $employee = $record->employee()
    ->with(['office', 'division', 'unit'])
    ->first();
            $shift = $record->shift;

            return AttendanceWarningEvent::create([
                'attendance_record_id' => $record->id,
                'employee_id' => $record->employee_id,
                'event_type' => 'EARLY_DEPARTURE',
                'idempotency_key' => $key,
                'event_date' => $record->work_date->toDateString(),
                'scheduled_end_at' => $record->scheduled_end_at,
                'actual_check_out_at' => $record->check_out_at,
                'minutes_early' => (int) $record->early_departure_minutes,
                'delivery_status' => AttendanceWarningEvent::DELIVERY_PENDING,
                'event_status' => AttendanceWarningEvent::STATUS_ACTIVE,
                'payload' => [
                    'employee_name' => $employee?->full_name,
                    'nip' => $employee?->nip,
                    'office' => $employee?->office?->name,
                    'unit' => $employee?->unit?->name,
                    'division' => $employee?->division?->name,
                    'event_date' => $record->work_date->toDateString(),
                    'shift' => $shift?->name,
                    'scheduled_end' => $record->scheduled_end_at?->format('H:i'),
                    'actual_check_out' => $record->check_out_at?->format('H:i'),
                    'minutes_early' => (int) $record->early_departure_minutes,
                    'status' => 'EARLY_DEPARTURE',
                    'deduction_percentage' => 0,
                ],
            ]);
        });

        if ($event->wasRecentlyCreated) {
            AuditService::record(
                'ATTENDANCE', 'EARLY_DEPARTURE_WARNING',
                'attendance_records', $record->id,
                null, ['status' => 'EARLY_DEPARTURE', 'minutes_early' => $record->early_departure_minutes]
            );

            // Pengiriman lewat queue: kegagalan notifikasi tidak pernah
            // membatalkan record attendance
            WarningNotificationJob::dispatch($event->id)->onQueue('notifications')->afterCommit();
        }

        return $event;
    }

    /* Batalkan warning bila kondisi berubah (misal koreksi disetujui) */
    public function revoke(AttendanceRecord $record): void
    {
        AttendanceWarningEvent::query()
            ->where('attendance_record_id', $record->id)
            ->where('event_type', 'EARLY_DEPARTURE')
            ->where('event_status', AttendanceWarningEvent::STATUS_ACTIVE)
            ->update(['event_status' => AttendanceWarningEvent::STATUS_REVOKED]);
    }

    protected function hasValidExemption(AttendanceRecord $record): bool
    {
        $date = $record->work_date->toDateString();

        if (LeaveRequest::query()
            ->where('employee_id', $record->employee_id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists()) {
            return true;
        }

        $checkoutTime = $record->check_out_at?->format('H:i:s');
        $permission = PermissionRequest::query()
            ->where('employee_id', $record->employee_id)
            ->where('status', PermissionRequest::STATUS_APPROVED)
            ->whereDate('date', $date)
            ->where(function ($query) use ($checkoutTime) {
                // Izin tanpa rentang waktu dianggap izin satu hari.
                $query->whereNull('start_time')->orWhereNull('end_time');

                if ($checkoutTime !== null) {
                    $query->orWhere(function ($range) use ($checkoutTime) {
                        $range->whereTime('start_time', '<=', $checkoutTime)
                            ->whereTime('end_time', '>=', $checkoutTime);
                    });
                }
            })
            ->exists();

        return $permission;
    }

    public function idempotencyKey(AttendanceRecord $record): string
    {
        return hash('sha256', implode('|', [
            'EARLY_DEPARTURE',
            $record->employee_id,
            $record->work_date?->toDateString(),
            $record->check_out_at?->toIso8601String(),
            $record->scheduled_end_at?->toIso8601String(),
        ]));
    }
}
