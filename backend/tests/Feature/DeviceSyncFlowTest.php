<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Jobs\DeviceSyncJob;
use App\Models\AttendanceLog;
use App\Models\DeviceSyncHistory;
use App\Services\AttendanceProcessor;
use App\Services\Device\DevicePullService;
use App\Services\Device\DeviceSyncService;
use App\Services\Device\ZktecoClient;
use App\Services\Zkteco\Exceptions\ZktecoConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeZkDevice;
use Tests\Support\InteractsWithFakeDevice;
use Tests\TestCase;

/**
 * Exercises the real job + service + pull service against a fake vendor object.
 * Requires the current append-only attendance_logs schema.
 */
class DeviceSyncFlowTest extends TestCase
{
    use InteractsWithFakeDevice;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zkteco.retries' => 3,
            'zkteco.timeout_seconds' => 30,
        ]);
    }

    private function runJob(int $deviceId, string $trigger = 'manual'): void
    {
        (new DeviceSyncJob($deviceId, $trigger))->handle(app(DeviceSyncService::class));
    }

    public function test_successful_sync_stores_logs_and_records_history(): void
    {
        $fake = $this->useFakeZk();
        $fake->attendanceRows = [
            FakeZkDevice::punch(1, '101', '2026-11-06 08:00:00'),
            FakeZkDevice::punch(2, '102', '2026-11-06 08:05:00'),
        ];
        $device = $this->makeDevice(['status' => DeviceStatus::Offline, 'consecutive_failures' => 3]);

        $this->runJob($device->id);

        $this->assertSame(2, AttendanceLog::count());

        $history = DeviceSyncHistory::firstOrFail();
        $this->assertSame(DeviceSyncHistory::STATUS_SUCCESS, $history->status);
        $this->assertSame('manual', $history->trigger_source);
        $this->assertSame(2, $history->total_fetched);
        $this->assertSame(2, $history->total_inserted);
        $this->assertNotNull($history->completed_at);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame(0, $device->consecutive_failures);
        $this->assertNotNull($device->last_sync_at);
    }

    public function test_pulling_the_same_logs_twice_does_not_create_duplicates(): void
    {
        $fake = $this->useFakeZk();
        $fake->attendanceRows = [
            FakeZkDevice::punch(1, '101', '2026-11-06 08:00:00'),
            FakeZkDevice::punch(2, '102', '2026-11-06 08:05:00'),
        ];
        $device = $this->makeDevice();

        $this->runJob($device->id);
        $this->runJob($device->id);

        $this->assertSame(2, AttendanceLog::count());

        $second = DeviceSyncHistory::latest('id')->first();
        $this->assertSame(2, $second->total_fetched);
        $this->assertSame(0, $second->total_inserted);
    }

    public function test_new_punches_are_added_on_the_next_pull(): void
    {
        $fake = $this->useFakeZk();
        $fake->attendanceRows = [FakeZkDevice::punch(1, '101', '2026-11-06 08:00:00')];
        $device = $this->makeDevice();

        $this->runJob($device->id);

        $fake->attendanceRows[] = FakeZkDevice::punch(2, '101', '2026-11-06 17:00:00');
        $this->runJob($device->id);

        $this->assertSame(2, AttendanceLog::count());
        $this->assertSame(1, DeviceSyncHistory::latest('id')->first()->total_inserted);
    }

    public function test_pull_continues_after_one_record_fails_to_ingest(): void
    {
        $device = $this->makeDevice();
        $fake = new FakeZkDevice;
        $fake->attendanceRows = [
            FakeZkDevice::punch(1, '101', '2026-11-06 08:00:00'),
            FakeZkDevice::punch(2, '102', '2026-11-06 08:05:00'),
            FakeZkDevice::punch(3, '103', '2026-11-06 08:10:00'),
        ];
        $fake->users = [
            '101' => ['uid' => 1, 'userid' => '101', 'name' => 'One', 'role' => 0, 'password' => '', 'cardno' => '0'],
            '102' => ['uid' => 2, 'userid' => '102', 'name' => 'Two', 'role' => 0, 'password' => '', 'cardno' => '0'],
            '103' => ['uid' => 3, 'userid' => '103', 'name' => 'Three', 'role' => 0, 'password' => '', 'cardno' => '0'],
        ];
        $processor = $this->mock(AttendanceProcessor::class);
        $attempt = 0;
        $processor->shouldReceive('ingest')
            ->times(3)
            ->andReturnUsing(function () use (&$attempt): AttendanceLog {
                $attempt++;

                if ($attempt === 2) {
                    throw new \RuntimeException('Unable to store this record.');
                }

                $log = new AttendanceLog;
                $log->wasRecentlyCreated = true;

                return $log;
            });
        $pull = new DevicePullService(
            $processor,
            fn () => new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn () => $fake),
        );

        $result = $pull->pull($device);

        $this->assertSame(3, $result['fetched']);
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(0, $result['duplicate']);
        $this->assertSame(3, $result['unmatched']);
        $this->assertSame(1, $result['failed']);
    }

    public function test_due_for_pull_uses_the_device_interval_without_changing_last_sync_at(): void
    {
        $this->travelTo('2026-11-06 09:10:00');
        $fastDevice = $this->makeDevice([
            'last_sync_at' => '2026-11-06 09:00:00',
            'sync_interval_minutes' => 5,
        ]);
        $slowDevice = $this->makeDevice([
            'ip_address' => '192.168.1.202',
            'last_sync_at' => '2026-11-06 09:00:00',
            'sync_interval_minutes' => 15,
        ]);
        $fastLastSyncAt = $fastDevice->last_sync_at->toIso8601String();

        $this->assertTrue($fastDevice->isDueForPull());
        $this->assertFalse($slowDevice->isDueForPull());
        $this->assertSame($fastLastSyncAt, $fastDevice->last_sync_at->toIso8601String());

        $this->travelBack();
    }

    public function test_unknown_pins_are_kept_as_unmapped_not_dropped(): void
    {
        $fake = $this->useFakeZk();
        $fake->attendanceRows = [FakeZkDevice::punch(1, '999', '2026-11-06 08:00:00')];
        $fake->users = [
            '999' => ['uid' => 1, 'userid' => '999', 'name' => 'Unknown', 'role' => 0, 'password' => '', 'cardno' => '0'],
        ];
        $device = $this->makeDevice();

        $this->runJob($device->id);

        $log = AttendanceLog::firstOrFail();
        $this->assertNull($log->employee_id);
        $this->assertSame('999', $log->device_user_identifier);
        $this->assertSame(1, DeviceSyncHistory::firstOrFail()->total_unmatched);
    }

    public function test_malformed_rows_produce_partial_success(): void
    {
        $fake = $this->useFakeZk();
        $fake->attendanceRows = [
            FakeZkDevice::punch(1, '101', '2026-11-06 08:00:00'),
            FakeZkDevice::punch(2, '102', 'not-a-date'),
        ];
        $device = $this->makeDevice();

        $this->runJob($device->id);

        $history = DeviceSyncHistory::firstOrFail();
        $this->assertSame(DeviceSyncHistory::STATUS_PARTIAL_SUCCESS, $history->status);
        $this->assertSame(1, $history->total_inserted);
        $this->assertSame(1, $history->total_failed);
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_device_is_re_enabled_even_when_the_pull_fails(): void
    {
        $fake = $this->useFakeZk(new class extends FakeZkDevice
        {
            public function getAttendance(): array
            {
                $this->calls[] = 'getAttendance';
                throw new \RuntimeException('socket dropped');
            }
        });
        $device = $this->makeDevice();

        try {
            $this->runJob($device->id);
        } catch (\Throwable) {
        }

        $this->assertContains('enableDevice', $fake->calls);
        $this->assertSame('disconnect', end($fake->calls));
    }

    public function test_failed_connection_is_recorded_and_rethrown_for_queue_retry(): void
    {
        $fake = $this->useFakeZk();
        $fake->connectable = false;
        $device = $this->makeDevice();

        try {
            $this->runJob($device->id);
            $this->fail('Expected a ZktecoConnectionException so the queue can retry.');
        } catch (ZktecoConnectionException $e) {
            $this->assertSame('CONNECTION_FAILED', $e->errorCode);
        }

        $history = DeviceSyncHistory::firstOrFail();
        $this->assertSame(DeviceSyncHistory::STATUS_FAILED, $history->status);
        $this->assertSame('CONNECTION_FAILED', $history->error_code);

        $device->refresh();
        $this->assertSame(1, $device->consecutive_failures);
        $this->assertSame('CONNECTION_FAILED', $device->last_error_code);
        $this->assertSame(DeviceStatus::Online, $device->status, 'One failure must not flip the device offline.');
    }

    public function test_device_goes_offline_after_three_failures_and_recovers_on_success(): void
    {
        $fake = $this->useFakeZk();
        $fake->connectable = false;
        $device = $this->makeDevice();

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->runJob($device->id);
            } catch (ZktecoConnectionException) {
            }
        }

        $device->refresh();
        $this->assertSame(DeviceStatus::Offline, $device->status);
        $this->assertSame(3, $device->consecutive_failures);
        $this->assertSame(3, DeviceSyncHistory::where('status', DeviceSyncHistory::STATUS_FAILED)->count());

        $fake->connectable = true;
        $this->runJob($device->id);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame(0, $device->consecutive_failures);
        $this->assertNull($device->last_error);
    }

    public function test_job_skips_inactive_devices(): void
    {
        $this->useFakeZk();
        $device = $this->makeDevice(['is_active' => false]);

        $this->runJob($device->id);

        $this->assertSame(0, DeviceSyncHistory::count());
        $this->assertNotContains('connect', $this->fakeZk->calls);
    }

    public function test_job_ignores_a_device_that_was_deleted(): void
    {
        $this->useFakeZk();

        $this->runJob(999999);

        $this->assertSame(0, DeviceSyncHistory::count());
    }

    public function test_commkey_is_encrypted_at_rest_and_hidden_from_json(): void
    {
        $device = $this->makeDevice(['commkey_encrypted' => 'secret-key']);

        $raw = \DB::table('devices')->where('id', $device->id)->value('commkey_encrypted');

        $this->assertNotSame('secret-key', $raw);
        $this->assertSame('secret-key', $device->fresh()->commkey_encrypted);
        $this->assertArrayNotHasKey('commkey_encrypted', $device->fresh()->toArray());
    }
}
