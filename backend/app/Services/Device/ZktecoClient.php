<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Models\Employee;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Closure;
use TahaRafi\ZKTeco\ZKTeco;
use Throwable;

class ZktecoClient
{
    private ?object $zk = null;

    private int $skippedRows = 0;

    /**
     * @param  Closure(string,int,int,string,int):object|null  $factory  Builds the vendor object; override in tests
     */
    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly string $timezone = 'Asia/Jakarta',
        private readonly ?Closure $factory = null,
        private readonly int $commKey = 0,
        private readonly string $protocol = 'auto',
        private readonly int $timeoutSeconds = 5,
    ) {}

    public static function forDevice(Device $device, ?Closure $factory = null): self
    {
        return new self(
            ip: $device->ip_address,
            port: $device->port ?? (int) config('zkteco.port'),
            timezone: config('app.timezone'),
            factory: $factory,
            commKey: self::commKeyFor($device),
            protocol: (string) config('zkteco.protocol', 'auto'),
            timeoutSeconds: (int) config('zkteco.socket_timeout_seconds', 5),
        );
    }

    private static function commKeyFor(Device $device): int
    {
        $value = $device->commkey_encrypted ?? config('zkteco.default_commkey', 0);

        if (! is_scalar($value) || ! preg_match('/\A\d+\z/', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ZktecoConnectionException::invalidCommKey();
        }

        return (int) $value;
    }

    public static function sdkAvailable(): bool
    {
        return class_exists(ZKTeco::class);
    }

    public function connect(): void
    {
        if ($this->factory === null && ! self::sdkAvailable()) {
            throw ZktecoConnectionException::sdkUnavailable();
        }

        try {
            $this->zk = ($this->factory ?? fn (string $ip, int $port, int $commKey, string $protocol, int $timeoutSeconds) => new ZKTeco($ip, $port, $commKey, $protocol, $timeoutSeconds))(
                $this->ip,
                $this->port,
                $this->commKey,
                $this->protocol,
                $this->timeoutSeconds,
            );
            $connected = $this->zk->connect();
        } catch (Throwable $e) {
            $this->zk = null;
            throw ZktecoConnectionException::failedToConnect($this->ip, $this->port, $e);
        }

        if (! $connected) {
            $this->zk = null;
            throw ZktecoConnectionException::failedToConnect($this->ip, $this->port);
        }
    }

    public function firmwareVersion(): ?string
    {
        $this->ensureConnected();

        try {
            $version = $this->zk->getVersion();

            return is_string($version) && $version !== '' ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<ZktecoAttendanceRecord>
     */
    public function fetchAttendance(): array
    {
        $this->ensureConnected();
        $this->skippedRows = 0;

        $this->zk->disableDevice();

        try {
            $rows = $this->zk->getAttendanceStructured();
        } finally {
            $this->zk->enableDevice();
        }

        if ($rows === false) {
            throw new \RuntimeException('The device did not return attendance records.');
        }

        if ($rows === []) {
            return [];
        }

        $records = [];

        foreach ($rows as $row) {
            try {
                $records[] = ZktecoAttendanceRecord::fromLibraryRow(
                    (array) $row,
                    $this->timezone,
                );
            } catch (Throwable) {
                $this->skippedRows++;
            }
        }

        return $records;
    }

    public function skippedRows(): int
    {
        return $this->skippedRows;
    }

    /* Kirim atau perbarui data pengguna di terminal
      Employee ID dipakai sebagai UID perangkat */
    public function pushEmployee(
        Employee $employee,
        string $deviceUserIdentifier
    ): bool {
        $userId = trim($deviceUserIdentifier);

        if (! preg_match('/\A[0-9]{1,9}\z/', $userId)) {
            throw new \InvalidArgumentException(
                'Device user identifier harus berupa angka maksimal 9 digit.'
            );
        }

        return $this->upsertEmployee($userId, (string) $employee->full_name);
    }

    /* Hapus entri pengguna dari terminal
      Data karyawan di database BioAttend tidak dihapus */
    public function disableEmployee(Employee $employee, string $deviceUserIdentifier): bool
    {
        return $this->removeEmployee($deviceUserIdentifier);
    }

    private function connectedClient(): object
    {
        $this->ensureConnected();

        return $this->zk
            ?? throw ZktecoConnectionException::notConnected();
    }

    public function upsertEmployee(string $identifier, string $name): bool
    {
        $this->ensureConnected();

        if (strlen($identifier) > 9 || strlen($name) > 24) {
            throw new \InvalidArgumentException(
                'ZKTeco employee identifiers are limited to 9 bytes and employee names to 24 bytes.'
            );
        }

        $identifier = trim($identifier);
        $name = trim($name);

        if ($identifier === '' || $name === '') {
            throw new \InvalidArgumentException('ZKTeco employee identifier and name are required.');
        }

        $users = $this->usersFromDevice();
        $existingUser = $this->findUserByIdentifier($users, $identifier);
        $uid = $existingUser !== null
            ? (int) $existingUser['uid']
            : $this->nextAvailableUserId($users);

        return $this->zk->setUser($uid, $identifier, $name, '') !== false;
    }

    public function removeEmployee(string $identifier): bool
    {
        $this->ensureConnected();

        $user = $this->findUserByIdentifier($this->usersFromDevice(), trim($identifier));

        if ($user === null) {
            return true;
        }

        return $this->zk->deleteUser((int) $user['uid']) !== false;
    }

    public function disconnect(): void
    {
        if ($this->zk === null) {
            return;
        }

        try {
            $this->zk->disconnect();
        } catch (Throwable) {
        } finally {
            $this->zk = null;
        }
    }

    private function ensureConnected(): void
    {
        if ($this->zk === null) {
            throw ZktecoConnectionException::notConnected();
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function usersFromDevice(): array
    {
        $users = $this->zk->getUsersStructured();

        if ($users === false) {
            throw new \RuntimeException('The device did not return its enrolled users.');
        }

        return $users;
    }

    /**
     * @param  array<string, array<string, mixed>>  $users
     * @return array<string, mixed>|null
     */
    private function findUserByIdentifier(array $users, string $identifier): ?array
    {
        foreach ($users as $user) {
            if (is_array($user) && (string) ($user['user_id'] ?? '') === $identifier) {
                return $user;
            }
        }

        return null;
    }

    /** @param array<string, array<string, mixed>> $users */
    private function nextAvailableUserId(array $users): int
    {
        $usedIds = [];

        foreach ($users as $user) {
            $uid = is_array($user) ? (int) ($user['uid'] ?? 0) : 0;

            if ($uid > 0 && $uid <= 65535) {
                $usedIds[$uid] = true;
            }
        }

        for ($uid = 1; $uid <= 65535; $uid++) {
            if (! isset($usedIds[$uid])) {
                return $uid;
            }
        }

        throw new \RuntimeException('The ZKTeco device has no available employee user IDs.');
    }
}
