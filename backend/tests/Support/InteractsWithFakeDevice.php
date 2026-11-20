<?php

namespace Tests\Support;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceSyncHistory;
use App\Services\AttendanceProcessor;
use App\Services\Device\DevicePullService;
use App\Services\Device\ZktecoClient;
use Illuminate\Support\Str;

trait InteractsWithFakeDevice
{
    protected FakeZkDevice $fakeZk;

    /**
     * Route every ZktecoClient in the container to the fake vendor object.
     */
    protected function useFakeZk(?FakeZkDevice $fake = null): FakeZkDevice
    {
        $this->fakeZk = $fake ?? new FakeZkDevice;
        $fake = $this->fakeZk;

        $this->app->bind(DevicePullService::class, fn () => new DevicePullService(
            $this->app->make(AttendanceProcessor::class),
            fn (Device $d) => new ZktecoClient(
                $d->ip_address,
                $d->port ?? 4370,
                'Asia/Jakarta',
                fn () => $fake,
            ),
        ));

        return $fake;
    }

    protected function makeDevice(array $overrides = []): Device
    {
        return Device::create(array_merge([
            'name' => 'Lobby Terminal',
            'model' => 'MB460',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'commkey_encrypted' => '0',
            'location' => 'Lobby',
            'status' => DeviceStatus::Online,
            'is_active' => true,
        ], $overrides));
    }

    protected function makeHistory(
        Device $device,
        string $status,
        $startedAt = null,
        ?string $errorCode = null,
        string $syncType = DeviceSyncHistory::TYPE_PULL_LOGS,
    ): DeviceSyncHistory {
        $startedAt ??= now();

        return DeviceSyncHistory::create([
            'device_id' => $device->id,
            'device_name' => $device->name,
            'sync_type' => $syncType,
            'trigger_source' => DeviceSyncHistory::TRIGGER_SCHEDULED,
            'started_at' => $startedAt,
            'completed_at' => $status === DeviceSyncHistory::STATUS_RUNNING ? null : $startedAt,
            'status' => $status,
            'error_code' => $errorCode,
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
