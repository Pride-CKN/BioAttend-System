<?php

namespace App\Jobs;

use App\Models\AttendanceWarningEvent;
use App\Models\Role;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/* Kirim peringatan EARLY_DEPARTURE ke HRD dan karyawan terkait.
  Idempoten: NotificationService memakai unique idempotency_key */
class WarningNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /* Exponential backoff (detik) */
    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(public int $warningEventId) {}

    public function handle(NotificationService $notifications): void
    {
        $event = AttendanceWarningEvent::find($this->warningEventId);

        if (! $event || $event->event_status !== AttendanceWarningEvent::STATUS_ACTIVE) {
            return;
        }

        $p = $event->payload ?? [];
        $key = $event->idempotency_key;
        $detail = sprintf(
            'Karyawan %s (Kode Karyawan %s) melakukan pulang lebih awal. Jadwal pulang %s, sedangkan scan pulang tercatat pukul %s. Selisih %d menit.',
            $p['employee_name'] ?? '-',
            $p['employee_code'] ?? $p['nip'] ?? '-',
            $p['scheduled_end'] ?? '-',
            $p['actual_check_out'] ?? '-',
            (int) ($p['minutes_early'] ?? 0)
        );

        $base = [
            'category' => 'EARLY_DEPARTURE',
            'idempotency_key' => $key,
            'channel' => 'DATABASE',
        ];

        // HRD — pesan berorientasi pengawasan
        $hrdSent = $notifications->notifyRole(Role::HRD, array_merge($base, [
            'title' => 'Peringatan Pulang Cepat: '.($p['employee_name'] ?? '-'),
            'body' => $detail.' Status EARLY_DEPARTURE. Kantor: '.($p['office'] ?? '-')
                .', Divisi: '.($p['division'] ?? '-')
                .', Unit: '.($p['unit'] ?? '-')
                .', Tanggal: '.($p['event_date'] ?? '-')
                .', Shift: '.($p['shift'] ?? '-').'. Tidak ada potongan gaji.',
        ]));

        // Karyawan terkait — pesan berorientasi pembinaan
        $employee = $event->employee;
        $employeeSent = 0;

        if ($employee?->user) {
            $employeeNotification = $notifications->notify(
                $employee->user->id,
                array_merge($base, [
                    'title' => 'Catatan Pulang Cepat pada '.($p['event_date'] ?? '-'),
                    'body' => 'Anda tercatat pulang lebih awal. '.$detail
                        .' Ini merupakan peringatan, tidak ada potongan gaji.',
                ])
            );

            $employeeSent = $employeeNotification?->delivery_status === 'SENT'
                ? 1
                : 0;
        }

        // Tentukan status berdasarkan keberhasilan pengiriman.
        if ($hrdSent > 0 && $employeeSent > 0) {
            $deliveryStatus = AttendanceWarningEvent::DELIVERY_SENT;
        } elseif ($hrdSent > 0 || $employeeSent > 0) {
            $deliveryStatus = AttendanceWarningEvent::DELIVERY_PARTIAL;
        } else {
            $deliveryStatus = AttendanceWarningEvent::DELIVERY_FAILED;
        }

        $event->update([
            'delivery_status' => $deliveryStatus,
        ]);
    }

    public function failed(Throwable $e): void
    {
        AttendanceWarningEvent::whereKey($this->warningEventId)
            ->update(['delivery_status' => AttendanceWarningEvent::DELIVERY_FAILED]);

        Log::warning('EARLY_DEPARTURE notification delivery failed', [
            'warning_event_id' => $this->warningEventId,
            'error' => $e->getMessage(),
        ]);
    }
}
