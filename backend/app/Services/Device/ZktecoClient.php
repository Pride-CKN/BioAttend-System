<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Models\Employee;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Closure;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Throwable;

class ZktecoClient
{
    private ?object $zk = null;

    private int $skippedRows = 0;

    /**
     * @param  Closure(string,int):object|null  $factory  Builds the vendor object; override in tests
     */
    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly string $timezone = 'Asia/Jakarta',
        private readonly ?Closure $factory = null,
    ) {}

    public static function forDevice(Device $device): self
    {
        return new self(
            ip: $device->ip_address,
            port: $device->port ?? (int) config('zkteco.port'),
            timezone: config('app.timezone'),
        );
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
            $this->zk = ($this->factory ?? fn (string $ip, int $port) => new ZKTeco($ip, $port))(
                $this->ip,
                $this->port,
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
            $version = $this->zk->version();

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
            $rows = $this->zk->getAttendance();
        } finally {
            $this->zk->enableDevice();
        }

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        if (! method_exists($this->zk, 'getUser')) {
            throw new \RuntimeException(
                'SDK ZKTeco tidak menyediakan metode getUser().'
            );
        }

        // Map UID terminal ke userid/PIN yang dipakai BioAttend
        $users = $this->zk->getUser();

        if (! is_array($users)) {
            throw new \UnexpectedValueException(
                'SDK tidak mengembalikan daftar pengguna perangkat yang valid.'
            );
        }

        $identifiersByUid = [];

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }

            $uid = filter_var(
                $user['uid'] ?? null,
                FILTER_VALIDATE_INT
            );

            $identifier = trim((string) ($user['userid'] ?? ''));

            if (
                $uid !== false
                && $uid > 0
                && $identifier !== ''
            ) {
                $identifiersByUid[(int) $uid] = $identifier;
            }
        }

        $records = [];

        foreach ($rows as $row) {
            try {
                $records[] = ZktecoAttendanceRecord::fromLibraryRow(
                    (array) $row,
                    $this->timezone,
                    $identifiersByUid
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

        return (bool) $this->zk->setUser($uid, $identifier, $name, '');
    }

    public function removeEmployee(string $identifier): bool
    {
        $this->ensureConnected();

        $user = $this->findUserByIdentifier($this->usersFromDevice(), trim($identifier));

        if ($user === null) {
            return true;
        }

        return (bool) $this->zk->removeUser((int) $user['uid']);
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
        $users = $this->zk->getUser();

        return is_array($users) ? $users : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $users
     * @return array<string, mixed>|null
     */
    private function findUserByIdentifier(array $users, string $identifier): ?array
    {
        foreach ($users as $user) {
            if (is_array($user) && (string) ($user['userid'] ?? '') === $identifier) {
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
