<?php

namespace App\Services\Device;

final class ConnectionResult
{
    public const STATUS_CONNECTED = 'CONNECTED';
    public const STATUS_FAILED = 'FAILED';

    public function __construct(
        public readonly string $status,
        public readonly ?int $latencyMs = null,
        public readonly ?string $firmware = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}

    public static function connected(int $latencyMs, ?string $firmware): self
    {
        return new self(self::STATUS_CONNECTED, $latencyMs, $firmware);
    }

    public static function failed(string $errorCode, string $message, ?int $latencyMs = null): self
    {
        return new self(self::STATUS_FAILED, $latencyMs, null, $errorCode, $message);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }
}