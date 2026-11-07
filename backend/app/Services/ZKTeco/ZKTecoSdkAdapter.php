<?php

namespace App\Services\ZKTeco;

use App\Models\Device;
use App\Models\Employee;
use App\Services\Device\ZktecoClient;
use App\Services\ZKTeco\Contracts\DeviceAdapter;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;

class ZKTecoSdkAdapter implements DeviceAdapter
{
    /** @var array<int, ZktecoClient> */
    private array $clients = [];

    public function connect(Device $device): void
    {
        $this->disconnect($device);

        $client = ZktecoClient::forDevice($device);
        $client->connect();

        $this->clients[(int) $device->getKey()] = $client;
    }

    public function pushEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool {
        return $this->clientFor($device)->pushEmployee(
            $employee,
            $deviceUserIdentifier
        );
    }

    public function disableEmployee(
        Device $device,
        Employee $employee,
        string $deviceUserIdentifier
    ): bool {
        // SDK tidak menyediakan perintah disable khusus
        // Pengguna dihapus dari daftar pengguna terminal
        return $this->clientFor($device)->disableEmployee($employee);
    }

    public function disconnect(Device $device): void
    {
        $key = (int) $device->getKey();

        if (! isset($this->clients[$key])) {
            return;
        }

        try {
            $this->clients[$key]->disconnect();
        } finally {
            unset($this->clients[$key]);
        }
    }

    private function clientFor(Device $device): ZktecoClient
    {
        $key = (int) $device->getKey();

        if (! isset($this->clients[$key])) {
            throw ZktecoConnectionException::notConnected();
        }

        return $this->clients[$key];
    }
}