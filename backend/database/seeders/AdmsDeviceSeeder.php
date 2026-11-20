<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Database\Seeder;

class AdmsDeviceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'A5KN223060151' => 'Office Singgasana',
            'CMYD213960746' => 'Produksi Soetta',
            'CMYD213960743' => 'Stokis Kembar',
            'CMYD213960510' => 'Stokis Cibolerang',
            'CMYD213960509' => 'Stokis Cileunyi',
        ] as $serialNumber => $placeName) {
            Device::query()->updateOrCreate(
                ['serial_number' => $serialNumber],
                [
                    'name' => $placeName,
                    'location' => $placeName,
                    'ip_address' => null,
                    'port' => 4370,
                    'status' => DeviceStatus::Offline,
                    'is_active' => true,
                ],
            );
        }
    }
}
