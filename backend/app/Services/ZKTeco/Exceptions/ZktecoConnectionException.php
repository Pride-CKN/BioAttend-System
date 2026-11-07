<?php

namespace App\Services\ZKTeco\Exceptions;

use RuntimeException;
use Throwable;

class ZktecoConnectionException extends RuntimeException
{
    public const SDK_UNAVAILABLE = 'SDK_UNAVAILABLE';
    public const CONNECTION_FAILED = 'CONNECTION_FAILED';
    public const NOT_CONNECTED = 'NOT_CONNECTED';
    public const TIMEOUT = 'TIMEOUT';

    public function __construct(
        string $message,
        public readonly string $errorCode = self::CONNECTION_FAILED,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function sdkUnavailable(): self
    {
        return new self(
            'ZKTeco vendor SDK (jmrashed/zkteco) is not installed.',
            self::SDK_UNAVAILABLE,
        );
    }

    public static function failedToConnect(string $ip, int $port, ?Throwable $previous = null): self
    {
        // No commkey / credentials message
        return new self(
            "Unable to connect to ZKTeco device at {$ip}:{$port}.",
            self::CONNECTION_FAILED,
            $previous,
        );
    }

    public static function notConnected(): self
    {
        return new self('ZKTeco client is not connected.', self::NOT_CONNECTED);
    }
}