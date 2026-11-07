<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSED = 'PROCESSED';
    public const STATUS_UNMATCHED = 'UNMATCHED';
    public const STATUS_FAILED = 'FAILED';

    public const TYPE_CHECK_IN = 'CHECK_IN';
    public const TYPE_CHECK_OUT = 'CHECK_OUT';
    public const TYPE_DUPLICATE = 'DUPLICATE';
    public const TYPE_ADDITIONAL = 'ADDITIONAL';
    public const TYPE_UNKNOWN = 'UNKNOWN';

    protected $fillable = [
        'device_id',
        'employee_id',
        'device_user_identifier',
        'device_log_id',
        'fingerprint',
        'sync_batch_id',
        'scanned_at',
        'scan_type',
        'raw_payload',
        'processing_status',
        'is_duplicate',
        'primary_log_id',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
            'raw_payload' => 'array',
            'is_duplicate' => 'boolean',
        ];
    }

    /* Fingerprint deterministik untuk mencegah raw log masuk dua kali.
      sync_batch_id sengaja tidak dimasukkan agar sinkronisasi ulang
      menghasilkan fingerprint yang sama */
    public static function fingerprintFor(
        ?int $deviceId,
        ?string $deviceLogId,
        ?string $deviceUserIdentifier,
        string $scannedAt,
        ?array $rawPayload
    ): string {
        $identity = [
            'device_id' => $deviceId,
            'device_log_id' => $deviceLogId,
            'device_user_identifier' => $deviceUserIdentifier,
            'scanned_at' => $scannedAt,
            'raw_payload' => $rawPayload,
        ];

        $encoded = json_encode(
            $identity,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return hash('sha256', $encoded);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function primaryLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'primary_log_id');
    }
}
