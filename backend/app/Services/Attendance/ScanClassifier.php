<?php

namespace App\Services\Attendance;

use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScanClassifier
{
    /**
     * Klasifikasi seluruh scan pada satu tanggal kerja.
     *
     * @param Collection<int, AttendanceLog> $logs
     * @return array<int, array{
     *     scan_type:string,
     *     is_duplicate:bool,
     *     primary_log_id:?int
     * }>
     */
    public function classify(Collection $logs, int $windowMinutes): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $windowSeconds = max(0, $windowMinutes) * 60;

        // Pastikan urutan scan konsisten, termasuk jika timestamp sama
        $ordered = $logs->sort(function ($left, $right) {
            $leftTime = Carbon::parse($left->scanned_at)->getTimestamp();
            $rightTime = Carbon::parse($right->scanned_at)->getTimestamp();

            if ($leftTime !== $rightTime) {
                return $leftTime <=> $rightTime;
            }

            return ((int) $left->getKey()) <=> ((int) $right->getKey());
        })->values();

        $first = $ordered->first();
        $firstId = (int) $first->getKey();
        $firstTimestamp = Carbon::parse($first->scanned_at);

        $decisions = [];
        $checkoutFound = false;

        foreach ($ordered as $index => $log) {
            $logId = (int) $log->getKey();

            if ($index === 0) {
                $decisions[$logId] = [
                    'scan_type' => AttendanceLog::TYPE_CHECK_IN,
                    'is_duplicate' => false,
                    'primary_log_id' => null,
                ];

                continue;
            }

            // Setelah checkout pertama, scan selanjutnya adalah tambahan
            if ($checkoutFound) {
                $decisions[$logId] = [
                    'scan_type' => AttendanceLog::TYPE_ADDITIONAL,
                    'is_duplicate' => false,
                    'primary_log_id' => null,
                ];

                continue;
            }

            $secondsFromFirst = Carbon::parse($log->scanned_at)
                ->getTimestamp() - $firstTimestamp->getTimestamp();

            if ($secondsFromFirst <= $windowSeconds) {
                $decisions[$logId] = [
                    'scan_type' => AttendanceLog::TYPE_DUPLICATE,
                    'is_duplicate' => true,
                    'primary_log_id' => $firstId,
                ];

                continue;
            }

            $decisions[$logId] = [
                'scan_type' => AttendanceLog::TYPE_CHECK_OUT,
                'is_duplicate' => false,
                'primary_log_id' => null,
            ];

            $checkoutFound = true;
        }

        return $decisions;
    }
}