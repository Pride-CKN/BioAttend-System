<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Models\AttendanceLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdmsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_adms_options_for_a_registered_device(): void
    {
        $device = $this->makeDevice('ADMS-001');

        $this->get('/iclock/cdata?SN='.$device->serial_number)
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSeeText('GET OPTION FROM:ADMS-001')
            ->assertSeeText('TransFlag=TransData AttLog');
    }

    public function test_returns_403_and_stores_no_log_for_an_unknown_device(): void
    {
        $this->admsPost('UNKNOWN-001', "1001\t2026-11-12 08:00:00\t0\t1")
            ->assertForbidden()
            ->assertSeeText('ERROR: unknown device');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_stores_adms_attendance_logs_and_ignores_replayed_payloads(): void
    {
        $device = $this->makeDevice('ADMS-001');
        $payload = "1001\t2026-11-12 08:00:00\t0\t1\n1002\tinvalid\t0\t1\n";

        $this->admsPost((string) $device->serial_number, $payload)
            ->assertOk()
            ->assertSeeText('OK:1');

        $this->admsPost((string) $device->serial_number, $payload)
            ->assertOk()
            ->assertSeeText('OK:1');

        $this->assertDatabaseCount('attendance_logs', 1);
        $this->assertDatabaseHas('attendance_logs', [
            'device_id' => $device->id,
            'device_user_identifier' => '1001',
            'processing_status' => AttendanceLog::STATUS_UNMATCHED,
        ]);
    }

    private function admsPost(string $serialNumber, string $payload): TestResponse
    {
        return $this->call(
            'POST',
            '/iclock/cdata?SN='.$serialNumber.'&table=ATTLOG',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $payload,
        );
    }

    private function makeDevice(string $serialNumber): Device
    {
        return Device::create([
            'name' => 'ADMS Terminal',
            'model' => 'MB10-VL',
            'serial_number' => $serialNumber,
            'ip_address' => '192.168.110.137',
            'port' => 4370,
            'status' => DeviceStatus::Online,
            'is_active' => true,
        ]);
    }
}
