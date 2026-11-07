<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/* Memproses raw log yang SUDAH tersimpan menjadi attendance records.
  Queue terpisah dari pull log: kegagalan job ini tidak pernah menyebabkan
  log ditarik ulang dari perangkat */
class AttendanceProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 180];

    public int $timeout = 120;

    /** @param  int[]  $attendanceLogIds */
    public function __construct(public array $attendanceLogIds) {}

    public function handle(AttendanceProcessor $processor): void
    {
        if ($this->attendanceLogIds === []) {
            return;
        }

        $logs = AttendanceLog::query()
            ->whereIn('id', $this->attendanceLogIds)
            ->whereNotNull('employee_id')
            ->get();

        $pairs = [];

        foreach ($logs as $log) {
            $employee = Employee::find($log->employee_id);

            if (! $employee) {
                // Tetap unmatched; jangan ubah bukti asli
                continue;
            }

            $workDate = $processor->resolveWorkDate($employee, Carbon::parse($log->scanned_at));
            $pairs[$log->employee_id.'|'.$workDate] = [(int) $log->employee_id, $workDate];
        }

        foreach ($pairs as [$employeeId, $workDate]) {
            try {
                DB::transaction(function () use ($processor, $employeeId, $workDate) {
                    $employee = Employee::find($employeeId);
                    $processor->reclassifyWorkDate($employee, $workDate);
                    $processor->processEmployeeDate($employeeId, $workDate);
                });
            } catch (\Throwable $e) {
                // Satu tanggal kerja gagal tidak menggagalkan tanggal lain
                Log::warning('Attendance processing failed for one work date', [
                    'employee_id' => $employeeId, 'work_date' => $workDate,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
