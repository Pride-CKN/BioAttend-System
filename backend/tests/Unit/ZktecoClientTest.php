<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Device\ZktecoClient;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Tests\Support\FakeZkDevice;
use Tests\TestCase;

class ZktecoClientTest extends TestCase
{
    public function test_structured_attendance_uses_the_sdk_user_id_without_loading_users(): void
    {
        $device = new FakeZkDevice;
        $device->attendanceRows = [FakeZkDevice::punch(42, '1001', '2026-11-06 08:00:00')];
        $client = new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn (): FakeZkDevice => $device);
        $client->connect();

        $records = $client->fetchAttendance();

        $this->assertCount(1, $records);
        $this->assertSame('1001', $records[0]->deviceUserIdentifier);
        $this->assertContains('getAttendanceStructured', $device->calls);
        $this->assertNotContains('getUsersStructured', $device->calls);
    }

    public function test_failed_attendance_read_is_not_treated_as_an_empty_pull(): void
    {
        $device = new class extends FakeZkDevice
        {
            public function getAttendanceStructured(): array|false
            {
                return false;
            }
        };
        $client = new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn (): FakeZkDevice => $device);
        $client->connect();

        $this->expectException(\RuntimeException::class);

        $client->fetchAttendance();
    }

    public function test_device_connection_passes_its_encrypted_commkey_and_transport_options_to_the_sdk(): void
    {
        config(['zkteco.protocol' => 'udp', 'zkteco.socket_timeout_seconds' => 12]);
        $device = new Device(['ip_address' => '10.20.0.8', 'port' => 4370, 'commkey_encrypted' => '12345']);
        $arguments = [];
        $fake = new FakeZkDevice;
        $client = ZktecoClient::forDevice($device, function (...$received) use (&$arguments, $fake): FakeZkDevice {
            $arguments = $received;

            return $fake;
        });

        $client->connect();

        $this->assertSame(['10.20.0.8', 4370, 12345, 'udp', 12], $arguments);
        $this->assertSame('12345', $device->commkey_encrypted);
        $this->assertArrayNotHasKey('commkey_encrypted', $device->toArray());
    }

    public function test_invalid_commkey_is_rejected_before_connecting(): void
    {
        $device = new Device(['ip_address' => '10.20.0.8', 'commkey_encrypted' => 'secret-key']);

        $this->expectException(ZktecoConnectionException::class);

        ZktecoClient::forDevice($device);
    }

    public function test_missing_device_commkey_uses_the_shared_default(): void
    {
        config(['zkteco.default_commkey' => 77]);
        $device = new Device(['ip_address' => '10.20.0.8']);
        $arguments = [];
        $client = ZktecoClient::forDevice($device, function (...$received) use (&$arguments): FakeZkDevice {
            $arguments = $received;

            return new FakeZkDevice;
        });

        $client->connect();

        $this->assertSame(77, $arguments[2]);
    }

    public function test_upsert_reuses_the_uid_from_the_sdk_user_structure(): void
    {
        $device = new FakeZkDevice;
        $device->users = [
            '1001' => [
                'uid' => 42,
                'user_id' => '1001',
                'name' => 'Previous Name',
                'role' => 0,
                'password' => '',
                'card_no' => 0,
            ],
        ];
        $client = new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn (): FakeZkDevice => $device);
        $client->connect();

        $this->assertTrue($client->upsertEmployee('1001', 'Updated Name'));
        $this->assertSame(42, $device->users['1001']['uid']);
        $this->assertSame('Updated Name', $device->users['1001']['name']);
        $this->assertContains('setUser', $device->calls);

        $client->disconnect();
    }

    public function test_remove_finds_a_user_by_its_sdk_user_id_value(): void
    {
        $device = new FakeZkDevice;
        $device->users = [
            'device-key' => [
                'uid' => 42,
                'user_id' => '1001',
                'name' => 'Employee Name',
                'role' => 0,
                'password' => '',
                'card_no' => 0,
            ],
        ];
        $client = new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn (): FakeZkDevice => $device);
        $client->connect();

        $this->assertTrue($client->removeEmployee('1001'));
        $this->assertSame([], $device->users);
        $this->assertContains('deleteUser', $device->calls);

        $client->disconnect();
    }
}
