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
        $client = $this->connectedClient();
        $uid = $this->employeeUid($employee);
        $userId = trim($deviceUserIdentifier);

        if (! preg_match('/\A[0-9]{1,9}\z/', $userId)) {
            throw new \InvalidArgumentException(
                'Device user identifier harus berupa angka maksimal 9 digit.'
            );
        }

        $name = trim((string) $employee->full_name);

        $name = function_exists('mb_strcut')
            ? mb_strcut($name, 0, 24, 'UTF-8')
            : substr($name, 0, 24);

        if ($name === '') {
            $name = $userId;
        }

        if (! method_exists($client, 'setUser')) {
            throw new \RuntimeException(
                'SDK ZKTeco tidak menyediakan metode setUser().'
            );
        }

        $result = call_user_func(
            [$client, 'setUser'],
            $uid,
            $userId,
            $name,
            '',
            0,
            0
        );

        return (bool) $result;
    }

    /* Hapus entri pengguna dari terminal
      Data karyawan di database BioAttend tidak dihapus */
    public function disableEmployee(Employee $employee): bool
    {
        $client = $this->connectedClient();
        $uid = $this->employeeUid($employee);

        if (! method_exists($client, 'removeUser')) {
            throw new \RuntimeException(
                'SDK ZKTeco tidak menyediakan metode removeUser().'
            );
        }

        return (bool) call_user_func(
            [$client, 'removeUser'],
            $uid
        );
    }

    private function employeeUid(Employee $employee): int
    {
        $uid = (int) $employee->getKey();

        if ($uid < 1 || $uid > 65535) {
            throw new \InvalidArgumentException(
                'Employee ID harus berada pada rentang UID perangkat 1–65535.'
            );
        }

        return $uid;
    }

    private function connectedClient(): object
    {
        $this->ensureConnected();

        return $this->zk
            ?? throw ZktecoConnectionException::notConnected();
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
}