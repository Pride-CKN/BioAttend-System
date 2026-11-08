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
     * @return array{fetched:int, inserted:int, duplicate:int, unmatched:int, failed:int, duration_ms:int}
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
            'fetched' => count($records) + $skipped,
            'inserted' => $stored['inserted'],
            'duplicate' => count($records) - $stored['inserted'],
            // Parsed rows whose device user identifier matches no employee.
            'unmatched' => $stored['unmapped'],
            // Rows that could not be parsed.
            'failed' => $skipped,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /**
     * Store device scans through the canonical attendance processor.
     *
     * @param  list<ZktecoAttendanceRecord>  $records
     * @return array{inserted:int, unmapped:int}
     */
    private function store(Device $device, array $records): array
    {
        if ($records === []) {
            return ['inserted' => 0, 'unmapped' => 0];
        }

        $identifiers = array_values(array_unique(array_filter(
            array_map(
                fn (ZktecoAttendanceRecord $record): ?string => $record->deviceUserIdentifier,
                $records,
            ),
            static fn (?string $identifier): bool => $identifier !== null,
        )));
        $employeeIdsByIdentifier = Employee::query()
            ->whereIn('device_user_identifier', $identifiers)
            ->pluck('id', 'device_user_identifier');

        $unmapped = 0;
        $inserted = 0;

        foreach ($records as $record) {
            $employeeId = $employeeIdsByIdentifier[$record->deviceUserIdentifier] ?? null;
            $unmapped += $employeeId === null ? 1 : 0;

            $log = $this->attendanceProcessor->ingest(
                $device,
                $employeeId,
                $record->scannedAt,
                $record->raw,
                ['device_user_identifier' => $record->deviceUserIdentifier],
            );

            if ($log->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'unmapped' => $unmapped];
    }
}
