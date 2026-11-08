<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Jobs\PushEmployeeToDeviceJob;
use App\Models\Device;
use App\Models\DeviceEmployeePush;
use App\Models\Employee;
use App\Services\Device\DeviceEmployeePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeviceEmployeePushServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_one_upsert_for_each_active_device(): void
    {
        Queue::fake([PushEmployeeToDeviceJob::class]);
        $employee = Employee::factory()->create([
            'device_user_identifier' => 'EMP-1001',
        ]);
        $firstDevice = $this->makeDevice('North Gate');
        $secondDevice = $this->makeDevice('South Gate');
        $this->makeDevice('Maintenance', false);

        $queued = app(DeviceEmployeePushService::class)->queueFor(
            $employee,
            DeviceEmployeePush::OPERATION_UPSERT,
        );

        $this->assertSame(2, $queued);
        $this->assertDatabaseCount('device_employee_pushes', 2);
        $this->assertDatabaseHas('device_employee_pushes', [
            'device_id' => $firstDevice->id,
            'employee_id' => $employee->id,
            'operation' => DeviceEmployeePush::OPERATION_UPSERT,
            'status' => DeviceEmployeePush::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('device_employee_pushes', [
            'device_id' => $secondDevice->id,
            'employee_id' => $employee->id,
            'operation' => DeviceEmployeePush::OPERATION_UPSERT,
            'status' => DeviceEmployeePush::STATUS_PENDING,
        ]);
        Queue::assertPushed(PushEmployeeToDeviceJob::class, 2);
    }

    public function test_does_not_queue_an_already_successful_identical_push(): void
    {
        Queue::fake([PushEmployeeToDeviceJob::class]);
        $employee = Employee::factory()->create([
            'device_user_identifier' => 'EMP-1002',
        ]);
        $device = $this->makeDevice('Lobby');

        $service = app(DeviceEmployeePushService::class);
        $this->assertSame(1, $service->queueFor($employee, DeviceEmployeePush::OPERATION_UPSERT));

        DeviceEmployeePush::query()->sole()->update([
            'status' => DeviceEmployeePush::STATUS_SUCCESS,
            'pushed_at' => now(),
        ]);
        Queue::fake([PushEmployeeToDeviceJob::class]);

        $queued = $service->queueFor($employee, DeviceEmployeePush::OPERATION_UPSERT);

        $this->assertSame(0, $queued);
        $this->assertDatabaseHas('device_employee_pushes', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
            'status' => DeviceEmployeePush::STATUS_SUCCESS,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_does_not_queue_an_upsert_without_a_device_identifier(): void
    {
        Queue::fake([PushEmployeeToDeviceJob::class]);
        $employee = Employee::factory()->create([
            'device_user_identifier' => null,
        ]);
        $this->makeDevice('Lobby');

        $queued = app(DeviceEmployeePushService::class)->queueFor(
            $employee,
            DeviceEmployeePush::OPERATION_UPSERT,
        );

        $this->assertSame(0, $queued);
        $this->assertDatabaseCount('device_employee_pushes', 0);
        Queue::assertNothingPushed();
    }

    private function makeDevice(string $name, bool $isActive = true): Device
    {
        return Device::create([
            'name' => $name,
            'ip_address' => '192.168.100.'.fake()->unique()->numberBetween(1, 254),
            'port' => 4370,
            'commkey_encrypted' => '0',
            'status' => DeviceStatus::Online,
            'is_active' => $isActive,
        ]);
    }
}
