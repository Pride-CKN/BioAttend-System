<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Jobs\PushEmployeeToDeviceJob;
use App\Models\Device;
use App\Models\DeviceEmployeePush;
use App\Models\Employee;
use App\Services\ZKTeco\Contracts\DeviceAdapter;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushEmployeeToDeviceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_failed_push_and_rethrows_the_connection_error_for_retry(): void
    {
        config(['zkteco.retries' => 3, 'zkteco.backoff_seconds' => [10, 30, 60]]);
        $push = $this->makePush();
        $adapter = new class implements DeviceAdapter
        {
            public bool $disconnected = false;

            public function connect(Device $device): void
            {
                throw ZktecoConnectionException::failedToConnect($device->ip_address, $device->port);
            }

            public function pushEmployee(Device $device, Employee $employee, string $deviceUserIdentifier): bool
            {
                return true;
            }

            public function disableEmployee(Device $device, Employee $employee, string $deviceUserIdentifier): bool
            {
                return true;
            }

            public function disconnect(Device $device): void
            {
                $this->disconnected = true;
            }
        };
        $job = new PushEmployeeToDeviceJob($push->id);

        try {
            $job->handle($adapter);
            $this->fail('The job must rethrow a connection failure for the queue retry mechanism.');
        } catch (ZktecoConnectionException $exception) {
            $this->assertSame(ZktecoConnectionException::CONNECTION_FAILED, $exception->errorCode);
        }

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertTrue($adapter->disconnected);
        $this->assertDatabaseHas('device_employee_pushes', [
            'id' => $push->id,
            'status' => DeviceEmployeePush::STATUS_FAILED,
        ]);
        $this->assertDatabaseHas('integration_logs', [
            'operation' => 'PUSH_EMPLOYEE',
            'device_id' => $push->device_id,
            'status' => 'FAILED',
        ]);
    }

    public function test_marks_the_push_successful_after_the_device_accepts_the_employee(): void
    {
        $push = $this->makePush();
        $adapter = new class implements DeviceAdapter
        {
            public bool $disconnected = false;

            public function connect(Device $device): void {}

            public function pushEmployee(Device $device, Employee $employee, string $deviceUserIdentifier): bool
            {
                return true;
            }

            public function disableEmployee(Device $device, Employee $employee, string $deviceUserIdentifier): bool
            {
                return true;
            }

            public function disconnect(Device $device): void
            {
                $this->disconnected = true;
            }
        };

        (new PushEmployeeToDeviceJob($push->id))->handle($adapter);

        $this->assertTrue($adapter->disconnected);
        $this->assertDatabaseHas('device_employee_pushes', [
            'id' => $push->id,
            'status' => DeviceEmployeePush::STATUS_SUCCESS,
        ]);
        $this->assertDatabaseHas('integration_logs', [
            'operation' => 'PUSH_EMPLOYEE',
            'device_id' => $push->device_id,
            'status' => 'SUCCESS',
        ]);
    }

    private function makePush(): DeviceEmployeePush
    {
        $device = Device::create([
            'name' => 'Lobby',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'commkey_encrypted' => '0',
            'status' => DeviceStatus::Online,
            'is_active' => true,
        ]);
        $employee = Employee::factory()->create(['nip' => '001001']);

        return DeviceEmployeePush::create([
            'device_id' => $device->id,
            'employee_id' => $employee->id,
            'device_user_identifier' => '001001',
            'operation' => DeviceEmployeePush::OPERATION_UPSERT,
            'payload_hash' => hash('sha256', 'push-'.$device->id.'-'.$employee->id),
            'status' => DeviceEmployeePush::STATUS_PENDING,
        ]);
    }
}
