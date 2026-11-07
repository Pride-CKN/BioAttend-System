<?php

namespace App\Services\ZKTeco\Contracts;

use App\Models\Device;
use App\Models\Employee;

interface DeviceAdapter
{
    public function connect(Device $device): void;

    public function pushEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool;

    public function disableEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool;

    public function disconnect(Device $device): void;
}