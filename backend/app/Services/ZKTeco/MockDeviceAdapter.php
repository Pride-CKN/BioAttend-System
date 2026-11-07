<?php

namespace App\Services\ZKTeco;

use App\Models\Device;
use App\Models\Employee;
use App\Services\ZKTeco\Contracts\DeviceAdapter;

/* Simulasi adapter */
class MockDeviceAdapter implements DeviceAdapter
{
    public function connect(Device $device): void
    {
        // koneksi fisik
    }

    public function pushEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool {
        return true;
    }

    public function disableEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool {
        return true;
    }

    public function disconnect(Device $device): void
    {
        // koneksi fisik
    }
}