<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Closure;

class DevicePullService
{
    public function __construct(
        private readonly AttendanceProcessor $attendanceProcessor,
        private readonly ?Closure $clientFactory = null,
    ) {}

    public function makeClient(Device $device): ZktecoClient
    {
        return $this->clientFactory
            ? ($this->clientFactory)($device)
            : ZktecoClient::forDevice($device);
    }

    /**
     * Pull log dari satu perangkat.
     *
     * @return array{fetched:int, inserted:int, unmapped:int, skipped:int, duration_ms:int}
     */
    public function pull(Device $device): array
    {
        $startedAt = hrtime(true);
        $client = $this->makeClient($device);

        try {
            $client->connect();

            $records = $client->fetchAttendance();
            $skipped = $client->skippedRows();
        } finally {
            $client->disconnect();
        }

        $stored = $this->store($device, $records);

        return [
            'fetched' => count($records),
            'inserted' => $stored['inserted'],
            'duplicate' => $stored['duplicate'],
            'unmapped' => $stored['unmapped'],
            'skipped' => $skipped,
            'duration_ms' => (int) (
        (hrtime(true) - $startedAt) / 1_000_000
            ),
        ];
    }

/**
 * @param list<ZktecoAttendanceRecord> $records
 * @return array{inserted:int, duplicate:int, unmapped:int}
 */
private function store(Device $device, array $records): array
{
    if ($records === []) {
        return [
            'inserted' => 0,
            'duplicate' => 0,
            'unmapped' => 0,
        ];
    }

    $identifiers = array_values(array_unique(array_filter(
        array_map(
            fn (ZktecoAttendanceRecord $record) =>
                $record->deviceUserIdentifier,
            $records
        ),
        fn (?string $identifier) =>
            $identifier !== null && $identifier !== ''
    )));

    $employees = $identifiers === []
        ? collect()
        : Employee::query()
            ->whereIn('device_user_identifier', $identifiers)
            ->get()
            ->keyBy(
                fn (Employee $employee) =>
                    (string) $employee->device_user_identifier
            );

    // Inisialisasi penghitung sebelum foreach.
    $inserted = 0;
    $duplicate = 0;
    $unmapped = 0;

    foreach ($records as $record) {
        $identifier = $record->deviceUserIdentifier;

        $employee = $identifier !== null
            ? $employees->get($identifier)
            : null;

        $log = $this->attendanceProcessor->ingest(
            device: $device,
            employeeId: $employee?->id,
            scannedAt: $record->scannedAt,
            rawPayload: $record->raw,
            options: [
                'device_user_identifier' => $identifier,
            ],
        );

        if ($log->wasRecentlyCreated) {
            $inserted++;
        } else {
            $duplicate++;
        }

        if ($employee === null) {
            $unmapped++;
        }
    }

    return [
        'inserted' => $inserted,
        'duplicate' => $duplicate,
        'unmapped' => $unmapped,
    ];
}
}
