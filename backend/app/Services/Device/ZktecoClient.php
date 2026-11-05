<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Services\ZKTeco\Exceptions\ZktecoConnectionException;
use Closure;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Throwable;

class ZktecoClient
{
    private ?object $zk = null;

    private int $skippedRows = 0;

    /**
     * @param  Closure(string,int):object|null  $factory  Builds the vendor object; override in tests.
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
     * @return list<AttendanceRecord>
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

        if (! is_array($rows)) {
            return [];
        }

        $records = [];

        foreach ($rows as $row) {
            try {
                $records[] = AttendanceRecord::fromLibraryRow((array) $row, $this->timezone);
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