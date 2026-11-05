<?php

namespace App\Services\Device;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use Closure;
use Illuminate\Support\Facades\DB;

class DevicePullService
{
    public function __construct(private readonly ?Closure $clientFactory = null) {}

    public function makeClient(Device $device): ZktecoClient
    {
        return $this->clientFactory
            ? ($this->clientFactory)($device)
            : ZktecoClient::forDevice($device);
    }

    /**
     * Pull logs from one device and store the new ones.
     * Throws ZktecoConnectionException on connection failure.
     * Device status / history bookkeeping belongs to DeviceSyncService.
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
            'fetched'     => count($records),
            'inserted'    => $stored['inserted'],
            'unmapped'    => $stored['unmapped'],
            'skipped'     => $skipped,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /**
     * Idempotent insert: unique key is (device_id, device_pin, punched_at).
     *
     * @param  list<AttendanceRecord>  $records
     * @return array{inserted:int, unmapped:int}
     */
    private function store(Device $device, array $records): array
    {
        if ($records === []) {
            return ['inserted' => 0, 'unmapped' => 0];
        }

        $pins = array_values(array_unique(array_map(fn ($r) => $r->pin, $records)));
        $employees = Employee::whereIn('fingerprint_pin', $pins)
            ->pluck('id', 'fingerprint_pin');

        $unmapped = 0;
        $rows = [];

        foreach ($records as $r) {
            $employeeId = $employees[$r->pin] ?? null;
            $unmapped += $employeeId === null ? 1 : 0;

            $rows[] = [
                'device_id'   => $device->id,
                'device_pin'  => $r->pin,
                'employee_id' => $employeeId,
                'punched_at'  => $r->punchedAt->utc(),
                'verify_type' => $r->type,
                'raw_payload' => json_encode($r->raw),
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        $inserted = 0;

        DB::transaction(function () use ($rows, &$inserted) {
            foreach (array_chunk($rows, 500) as $chunk) {
                $inserted += AttendanceLog::insertOrIgnore($chunk);
            }
        });

        return ['inserted' => $inserted, 'unmapped' => $unmapped];
    }
}