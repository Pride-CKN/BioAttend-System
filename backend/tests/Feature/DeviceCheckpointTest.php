<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCheckpoint;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceCheckpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_a_full_scan_checkpoint_with_a_persisted_attendance_log(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(
            ['name' => Role::HRD],
            ['description' => 'HRD', 'scope' => 'GLOBAL', 'is_system' => true],
        );
        $user->roles()->sync([$role->id]);
        $this->actingAs($user);

        $device = Device::create([
            'name' => 'Lobby Terminal',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'commkey_encrypted' => '0',
            'status' => DeviceStatus::Online,
            'is_active' => true,
        ]);
        $log = AttendanceLog::create([
            'device_id' => $device->id,
            'scanned_at' => now(),
        ]);
        DeviceCheckpoint::create([
            'device_id' => $device->id,
            'strategy' => DeviceCheckpoint::STRATEGY_FULL_SCAN,
            'last_scan_at' => now(),
            'last_inserted_log_id' => $log->id,
        ]);

        $this->getJson("/api/v1/devices/{$device->id}/checkpoint")
            ->assertOk()
            ->assertJsonPath('data.checkpoint.strategy', DeviceCheckpoint::STRATEGY_FULL_SCAN)
            ->assertJsonPath('data.checkpoint.last_inserted_log_id', $log->id)
            ->assertJsonPath('data.incremental', false);
    }
}
