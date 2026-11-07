<?php

namespace App\Services\Device;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class ZktecoAttendanceRecord
{
    public function __construct(
        public ?string $deviceUserIdentifier,
        public Carbon $scannedAt,
        public array $raw,
    ) {}

    /* Format baris SDK: uid, id, state, timestamp, type
      UID dipetakan ke userid melalui getUser() */
    public static function fromLibraryRow(
        array $row,
        string $timezone,
        array $identifiersByUid
    ): self {
        $uid = filter_var(
            $row['uid'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($uid === false || $uid < 1) {
            throw new InvalidArgumentException(
                'UID attendance dari perangkat tidak valid.'
            );
        }

        $timestamp = $row['timestamp'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            throw new InvalidArgumentException(
                'Timestamp attendance dari perangkat tidak valid.'
            );
        }

        $identifier = $identifiersByUid[(int) $uid] ?? null;

        if ($identifier !== null) {
            $identifier = trim((string) $identifier);
            $identifier = $identifier !== '' ? $identifier : null;
        }

        $raw = $row;

        // Hindari masalah JSON jika ID kartu berupa data biner
        if (
            isset($raw['id'])
            && is_string($raw['id'])
            && preg_match('//u', $raw['id']) !== 1
        ) {
            $raw['id_base64'] = base64_encode($raw['id']);
            unset($raw['id']);
        }

        return new self(
            deviceUserIdentifier: $identifier,
            scannedAt: Carbon::parse($timestamp, $timezone)->utc(),
            raw: $raw,
        );
    }
}
