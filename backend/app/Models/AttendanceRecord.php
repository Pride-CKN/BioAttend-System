<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    // Status kehadiran.
    public const STATUS_PRESENT = 'PRESENT';
    public const STATUS_LATE = 'LATE';
    public const STATUS_EARLY_DEPARTURE = 'EARLY_DEPARTURE';
    public const STATUS_INCOMPLETE = 'INCOMPLETE';
    public const STATUS_ABSENT = 'ABSENT';
    public const STATUS_LEAVE = 'LEAVE';
    public const STATUS_PERMISSION = 'PERMISSION';
    public const STATUS_BUSINESS_TRIP = 'BUSINESS_TRIP';
    public const STATUS_HOLIDAY = 'HOLIDAY';

    // Jenis pelanggaran yang disimpan dalam violation_types (CSV).
    public const EVENT_LATE = 'LATE';
    public const EVENT_ABSENT = 'ABSENT';
    public const EVENT_EARLY_DEPARTURE = 'EARLY_DEPARTURE';

    protected $fillable = [
        'employee_id',
        'work_date',
        'shift_id',
        'check_in_at',
        'check_out_at',
        'first_scan_at',
        'scheduled_end_at',
        'work_duration_minutes',
        'late_minutes',
        'early_departure_minutes',
        'additional_scan_count',
        'status',
        'violation_types',
        'is_incomplete',
        'is_verified',
        'verified_by',
        'verified_at',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'first_scan_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'verified_at' => 'datetime',
            'work_duration_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'additional_scan_count' => 'integer',
            'is_incomplete' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /* Ubah violation_types CSV menjadi array jenis pelanggaran.

      Contoh: "LATE,EARLY_DEPARTURE"
      menjadi ["LATE", "EARLY_DEPARTURE"]

      Status INCOMPLETE tidak otomatis menjadi pelanggaran */
    public function violations(): array
    {
        $value = trim((string) $this->violation_types);

        if ($value === '') {
            return [];
        }

        $violations = array_map(
            static fn (string $item): string => strtoupper(trim($item)),
            explode(',', $value)
        );

        return array_values(array_unique(array_filter(
            $violations,
            static fn (string $item): bool => $item !== ''
        )));
    }

    public function hasViolation(string $type): bool
    {
        return in_array(
            strtoupper(trim($type)),
            $this->violations(),
            true
        );
    }
}
