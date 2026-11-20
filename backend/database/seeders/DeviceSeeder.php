<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): Device
    {
        return Device::query()->firstOrCreate(
            ['ip_address' => '192.168.110.137'],
            [
                'name' => 'Fingerprint Utama',
                'model' => 'MB10-VL',
                'port' => 4370,
                'commkey_encrypted' => env('ZKTECO_DEMO_COMMKEY'),
                'location' => 'Office',
                'status' => DeviceStatus::Online,
                'is_active' => true,
            ]
        );
    }
}
