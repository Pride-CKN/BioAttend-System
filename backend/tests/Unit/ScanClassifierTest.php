<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Services\Attendance\ScanClassifier;
use Carbon\Carbon;
use Tests\TestCase;

class ScanClassifierTest extends TestCase
{
    private function log(int $id, string $time): AttendanceLog
    {
        $log = new AttendanceLog();
        $log->setAttribute('id', $id);
        $log->setAttribute('scanned_at', Carbon::parse($time));

        return $log;
    }

    public function test_scan_classification_respects_60_minute_boundary(): void
    {
        $logs = collect([
            $this->log(5, '2026-11-06 10:00:00'),
            $this->log(3, '2026-11-06 09:00:00'),
            $this->log(1, '2026-11-06 08:00:00'),
            $this->log(4, '2026-11-06 09:00:01'),
            $this->log(2, '2026-11-06 08:30:00'),
        ]);

        $decisions = (new ScanClassifier())->classify($logs, 60);

        // Scan pertama.
        $this->assertSame(
            AttendanceLog::TYPE_CHECK_IN,
            $decisions[1]['scan_type']
        );

        // 30 menit dan tepat 60 menit = DUPLICATE.
        $this->assertSame(
            AttendanceLog::TYPE_DUPLICATE,
            $decisions[2]['scan_type']
        );
        $this->assertSame(
            AttendanceLog::TYPE_DUPLICATE,
            $decisions[3]['scan_type']
        );

        $this->assertTrue($decisions[2]['is_duplicate']);
        $this->assertSame(1, $decisions[2]['primary_log_id']);
        $this->assertSame(1, $decisions[3]['primary_log_id']);

        // 60 menit 1 detik = CHECK_OUT.
        $this->assertSame(
            AttendanceLog::TYPE_CHECK_OUT,
            $decisions[4]['scan_type']
        );

        // Scan sesudah checkout = ADDITIONAL.
        $this->assertSame(
            AttendanceLog::TYPE_ADDITIONAL,
            $decisions[5]['scan_type']
        );

        $this->assertFalse($decisions[4]['is_duplicate']);
        $this->assertFalse($decisions[5]['is_duplicate']);
    }

    public function test_empty_collection_returns_no_decisions(): void
    {
        $decisions = (new ScanClassifier())->classify(collect(), 60);

        $this->assertSame([], $decisions);
    }
}
