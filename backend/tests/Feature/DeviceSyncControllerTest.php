<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Jobs\DeviceSyncJob;
use App\Models\DeviceCheckpoint;
use App\Models\DeviceSyncHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Tests\Support\InteractsWithFakeDevice;
use Tests\TestCase;

class DeviceSyncControllerTest extends TestCase
{
    use InteractsWithFakeDevice;
    use RefreshDatabase;

    private const BASE = '/api/v1/devices';

    protected function setUp(): void
    {
        parent::setUp();

        config(['zkteco.queue' => 'device-sync']);

        $hrd = Role::firstOrCreate(
            ['name' => Role::HRD],
            ['description' => 'HRD', 'scope' => 'GLOBAL', 'is_system' => true],
        );
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->sync([$hrd->id]);

        $this->actingAs($user);
    }

    // ---------------------------------------------------------------
    // POST /devices/{id}/test
    // ---------------------------------------------------------------

    public function test_connection_success_reports_ready_and_marks_device_online(): void
    {
        $this->useFakeZk();
        $device = $this->makeDevice(['status' => DeviceStatus::Offline]);
        foreach (range(1, 3) as $i) {
            $this->makeHistory($device, DeviceSyncHistory::STATUS_FAILED, now()->subMinutes(10 - $i), 'CONNECTION_FAILED');
        }
        $this->assertSame(3, $device->consecutive_failures);

        $this->postJson(self::BASE."/{$device->id}/test")
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.integration_status', 'READY')
            ->assertJsonPath('data.firmware', 'Ver 6.60 Apr 14 2020')
            ->assertJsonPath('data.error_code', null)
            ->assertJsonPath('data.note', null);

        $device->refresh();
        $this->assertSame(DeviceStatus::Online, $device->status);
        $this->assertSame(0, $device->consecutive_failures);
        $this->assertNull($device->last_error_code);
        $this->assertNotNull($device->last_successful_connection_at);

        $health = DeviceSyncHistory::where('sync_type', DeviceSyncHistory::TYPE_HEALTH)->sole();
        $this->assertSame(DeviceSyncHistory::STATUS_SUCCESS, $health->status);
        $this->assertSame('manual', $health->trigger_source);
        $this->assertNotNull($health->completed_at);

        $this->assertDatabaseHas('integration_logs', [
            'service' => 'DEVICE',
            'operation' => 'HEALTH',
            'device_id' => $device->id,
            'status' => 'SUCCESS',
        ]);
    }

    public function test_connection_failure_reports_failed_and_records_error_code(): void
    {
        $fake = $this->useFakeZk();
        $fake->connectable = false;
        $device = $this->makeDevice();

        $this->postJson(self::BASE."/{$device->id}/test")
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.integration_status', 'FAILED')
            ->assertJsonPath('data.error_code', 'CONNECTION_FAILED');

        $health = DeviceSyncHistory::where('sync_type', DeviceSyncHistory::TYPE_HEALTH)->sole();
        $this->assertSame(DeviceSyncHistory::STATUS_FAILED, $health->status);
        $this->assertSame('CONNECTION_FAILED', $health->error_code);

        $device->refresh();
        $this->assertSame('CONNECTION_FAILED', $device->last_error_code);
        $this->assertSame(DeviceStatus::Online, $device->status, 'A manual test must not flip the status.');

        $this->assertDatabaseHas('integration_logs', [
            'operation' => 'HEALTH',
            'status' => 'FAILED',
            'device_id' => $device->id,
        ]);
    }

    public function test_connection_reports_blocked_when_sdk_is_not_installed(): void
    {
        if (class_exists(ZKTeco::class)) {
            $this->markTestSkipped('jmrashed/zkteco is installed in this environment.');
        }

        $device = $this->makeDevice();

        $this->postJson(self::BASE."/{$device->id}/test")
            ->assertOk()
            ->assertJsonPath('data.integration_status', 'BLOCKED')
            ->assertJsonPath('data.error_code', 'SDK_UNAVAILABLE');

        // A missing SDK says nothing about the device, so it is SKIPPED, not blamed.
        $health = DeviceSyncHistory::where('sync_type', DeviceSyncHistory::TYPE_HEALTH)->sole();
        $this->assertSame(DeviceSyncHistory::STATUS_SKIPPED, $health->status);
        $this->assertNull($device->refresh()->last_error_code);
        $this->assertSame(0, $device->consecutive_failures);
    }

    public function test_connection_returns_404_for_unknown_device(): void
    {
        $this->postJson(self::BASE.'/999999/test')->assertNotFound();
    }

    public function test_user_without_the_hrd_role_is_forbidden(): void
    {
        Queue::fake();
        $device = $this->makeDevice();

        $this->actingAs(User::factory()->create()); // no roles attached

        $this->postJson(self::BASE."/{$device->id}/test")->assertForbidden();
        $this->postJson(self::BASE."/{$device->id}/sync")->assertForbidden();
        $this->postJson(self::BASE."/{$device->id}/retry")->assertForbidden();
        $this->getJson(self::BASE."/{$device->id}/checkpoint")->assertForbidden();

        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------
    // POST /devices/{id}/sync
    // ---------------------------------------------------------------

    public function test_sync_queues_a_manual_job_on_the_configured_queue(): void
    {
        Queue::fake();
        $device = $this->makeDevice();

        $response = $this->postJson(self::BASE."/{$device->id}/sync")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'QUEUED')
            ->assertJsonPath('data.queue', 'device-sync');

        $correlationId = $response->json('data.correlation_id');
        $this->assertNotEmpty($correlationId);

        Queue::assertPushedOn(
            'device-sync',
            DeviceSyncJob::class,
            fn (DeviceSyncJob $job) => $job->deviceId === $device->id
                && $job->triggerSource === 'manual'
                && $job->correlationId === $correlationId,
        );
    }

    public function test_sync_does_not_fake_last_sync_at(): void
    {
        Queue::fake();
        $device = $this->makeDevice(['last_sync_at' => null]);

        $this->postJson(self::BASE."/{$device->id}/sync")->assertStatus(202);

        $this->assertNull($device->refresh()->last_sync_at);
    }

    public function test_sync_rejects_inactive_device(): void
    {
        Queue::fake();
        $device = $this->makeDevice(['is_active' => false]);

        $this->postJson(self::BASE."/{$device->id}/sync")->assertStatus(409);

        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------
    // POST /devices/{id}/retry
    // ---------------------------------------------------------------

    public function test_retry_is_rejected_when_there_is_no_history(): void
    {
        Queue::fake();
        $device = $this->makeDevice();

        $this->postJson(self::BASE."/{$device->id}/retry")->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_retry_is_rejected_when_last_cycle_succeeded(): void
    {
        Queue::fake();
        $device = $this->makeDevice();
        $this->makeHistory($device, DeviceSyncHistory::STATUS_SUCCESS);

        $this->postJson(self::BASE."/{$device->id}/retry")->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_retry_ignores_a_cycle_that_is_still_running(): void
    {
        Queue::fake();
        $device = $this->makeDevice();
        $this->makeHistory($device, DeviceSyncHistory::STATUS_SUCCESS, now()->subMinutes(10));
        $this->makeHistory($device, DeviceSyncHistory::STATUS_RUNNING, now());

        // The last finished cycle succeeded, so there is nothing to retry.
        $this->postJson(self::BASE."/{$device->id}/retry")->assertStatus(409);
    }

    public function test_retry_is_rejected_when_last_cycle_was_skipped(): void
    {
        Queue::fake();
        $device = $this->makeDevice();
        $this->makeHistory($device, DeviceSyncHistory::STATUS_SKIPPED, null, 'SDK_UNAVAILABLE');

        $this->postJson(self::BASE."/{$device->id}/retry")->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_retry_queues_job_after_a_failed_cycle(): void
    {
        $this->assertRetryQueuedAfter(DeviceSyncHistory::STATUS_FAILED);
    }

    public function test_retry_queues_job_after_a_timeout(): void
    {
        $this->assertRetryQueuedAfter(DeviceSyncHistory::STATUS_TIMEOUT);
    }

    public function test_retry_queues_job_after_a_partial_success(): void
    {
        $this->assertRetryQueuedAfter(DeviceSyncHistory::STATUS_PARTIAL_SUCCESS);
    }

    private function assertRetryQueuedAfter(string $status): void
    {
        Queue::fake();
        $device = $this->makeDevice();
        $history = $this->makeHistory($device, $status, null, 'CONNECTION_FAILED');

        $this->postJson(self::BASE."/{$device->id}/retry")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'QUEUED')
            ->assertJsonPath('data.retried_from_history_id', $history->id)
            ->assertJsonPath('data.previous_status', $status)
            ->assertJsonPath('data.previous_error_code', 'CONNECTION_FAILED');

        Queue::assertPushedOn(
            'device-sync',
            DeviceSyncJob::class,
            fn (DeviceSyncJob $job) => $job->deviceId === $device->id && $job->triggerSource === 'retry',
        );
    }

    // ---------------------------------------------------------------
    // GET /devices/{id}/checkpoint
    // ---------------------------------------------------------------

    public function test_checkpoint_is_null_and_not_incremental_when_nothing_was_synced(): void
    {
        $device = $this->makeDevice();

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.device_id', $device->id)
            ->assertJsonPath('data.checkpoint', null)
            ->assertJsonPath('data.incremental', false);
    }

    public function test_checkpoint_with_strategy_none_is_not_incremental(): void
    {
        $device = $this->makeDevice();
        DeviceCheckpoint::create(['device_id' => $device->id]); // DB default: NONE

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.checkpoint.strategy', 'NONE')
            ->assertJsonPath('data.incremental', false);
    }

    public function test_checkpoint_reports_timestamp_fallback_as_not_incremental(): void
    {
        $device = $this->makeDevice();
        DeviceCheckpoint::create([
            'device_id' => $device->id,
            'strategy' => DeviceCheckpoint::STRATEGY_TIMESTAMP_FALLBACK,
            'last_scan_at' => now(),
        ]);

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.incremental', false);
    }

    public function test_checkpoint_reports_sdk_cursor_as_incremental(): void
    {
        $device = $this->makeDevice();
        DeviceCheckpoint::create([
            'device_id' => $device->id,
            'strategy' => DeviceCheckpoint::STRATEGY_SDK_CURSOR,
            'cursor' => 'abc123',
        ]);

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.checkpoint.cursor', 'abc123')
            ->assertJsonPath('data.incremental', true);
    }

    public function test_checkpoint_exposes_device_health_derived_from_histories(): void
    {
        $device = $this->makeDevice(['last_sync_at' => now()->subHour()]);

        $this->makeHistory($device, DeviceSyncHistory::STATUS_SUCCESS, now()->subHours(3));
        $this->makeHistory($device, DeviceSyncHistory::STATUS_FAILED, now()->subHours(2), 'CONNECTION_FAILED');
        $this->makeHistory($device, DeviceSyncHistory::STATUS_TIMEOUT, now()->subHour(), 'TIMEOUT');

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.consecutive_failures', 2)
            ->assertJsonPath('data.last_error_code', 'TIMEOUT')
            ->assertJsonStructure(['data' => ['last_sync_at', 'last_successful_connection_at']]);
    }

    public function test_checkpoint_health_is_clean_after_a_successful_cycle(): void
    {
        $device = $this->makeDevice();

        $this->makeHistory($device, DeviceSyncHistory::STATUS_FAILED, now()->subHours(2), 'CONNECTION_FAILED');
        $this->makeHistory($device, DeviceSyncHistory::STATUS_SUCCESS, now()->subHour());

        $this->getJson(self::BASE."/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.consecutive_failures', 0)
            ->assertJsonPath('data.last_error_code', null);
    }
}
