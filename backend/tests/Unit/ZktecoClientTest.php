<?php

namespace Tests\Unit;

use App\Services\Device\ZktecoClient;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeZkDevice;

class ZktecoClientTest extends TestCase
{
    public function test_upsert_reuses_the_uid_from_the_sdk_user_structure(): void
    {
        $device = new FakeZkDevice;
        $device->users = [
            '1001' => [
                'uid' => 42,
                'userid' => '1001',
                'name' => 'Previous Name',
                'role' => 0,
                'password' => '',
                'cardno' => '00000000000',
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

    public function test_remove_finds_a_user_by_its_sdk_userid_value(): void
    {
        $device = new FakeZkDevice;
        $device->users = [
            'device-key' => [
                'uid' => 42,
                'userid' => '1001',
                'name' => 'Employee Name',
                'role' => 0,
                'password' => '',
                'cardno' => '00000000000',
            ],
        ];
        $client = new ZktecoClient('127.0.0.1', 4370, 'Asia/Jakarta', fn (): FakeZkDevice => $device);
        $client->connect();

        $this->assertTrue($client->removeEmployee('1001'));
        $this->assertSame([], $device->users);
        $this->assertContains('removeUser', $device->calls);

        $client->disconnect();
    }
}
