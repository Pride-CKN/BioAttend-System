<?php

namespace App\Services\Device;

use App\Models\Device;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use Carbon\Carbon;

class AdmsAttendanceService
{
    public function __construct(private readonly AttendanceProcessor $attendanceProcessor) {}

    /**
     * @return array{received:int, created:int, discarded:int}
     */
    public function ingest(Device $device, string $serialNumber, string $payload): array
    {
        $scans = $this->parseAttendanceLogs($payload);

        if ($scans['rows'] === []) {
            return ['received' => 0, 'created' => 0, 'discarded' => $scans['discarded']];
        }

        $employeeIdsByIdentifier = Employee::query()
            ->whereIn('device_user_identifier', array_column($scans['rows'], 'user_id'))
            ->pluck('id', 'device_user_identifier');

        $created = 0;
        $employeeWorkDates = [];

        foreach ($scans['rows'] as $scan) {
            $employeeId = $employeeIdsByIdentifier[$scan['user_id']] ?? null;
            $deviceLogId = hash('sha256', implode("\0", [
                $serialNumber,
                $scan['user_id'],
                $scan['scanned_at']->format('Y-m-d H:i:s'),
            ]));

            $log = $this->attendanceProcessor->ingest(
                $device,
                $employeeId,
                $scan['scanned_at'],
                [
                    'source' => 'ADMS',
                    'serial_number' => $serialNumber,
                    'line' => $scan['line'],
                    'status' => $scan['status'],
                    'verification' => $scan['verification'],
                    'workcode' => $scan['workcode'],
                ],
                [
                    'device_log_id' => $deviceLogId,
                    'device_user_identifier' => $scan['user_id'],
                ],
            );

            if (! $log->wasRecentlyCreated) {
                continue;
            }

            $created++;

            if ($employeeId === null) {
                continue;
            }

            $employee = Employee::find($employeeId);

            if ($employee === null) {
                continue;
            }

            $workDate = $this->attendanceProcessor->resolveWorkDate($employee, $scan['scanned_at']);
            $employeeWorkDates[$employeeId.'|'.$workDate] = [$employeeId, $workDate];
        }

        foreach ($employeeWorkDates as [$employeeId, $workDate]) {
            $this->attendanceProcessor->processEmployeeDate($employeeId, $workDate);
        }

        return [
            'received' => count($scans['rows']),
            'created' => $created,
            'discarded' => $scans['discarded'],
        ];
    }

    /**
     * @return array{rows:list<array{user_id:string, scanned_at:Carbon, line:string, status:?string, verification:?string, workcode:?string}>, discarded:int}
     */
    private function parseAttendanceLogs(string $payload): array
    {
        $rows = [];
        $discarded = 0;

        foreach (preg_split('/\R/', $payload) ?: [] as $line) {
            $columns = explode("\t", trim($line));

            if (count($columns) < 2) {
                continue;
            }

            $userId = preg_replace('/[^0-9A-Za-z]/', '', $columns[0]);

            if (! Carbon::hasFormatWithModifiers(trim($columns[1]), 'Y-m-d H:i:s')) {
                $discarded++;

                continue;
            }

            try {
                $scannedAt = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    trim($columns[1]),
                    config('app.timezone'),
                );
            } catch (\Throwable) {
                $scannedAt = false;
            }

            if ($userId === '' || $scannedAt === false) {
                $discarded++;

                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'scanned_at' => $scannedAt,
                'line' => $line,
                'status' => $this->optionalColumn($columns, 2),
                'verification' => $this->optionalColumn($columns, 3),
                'workcode' => $this->optionalColumn($columns, 4),
            ];
        }

        return ['rows' => $rows, 'discarded' => $discarded];
    }

    /** @param list<string> $columns */
    private function optionalColumn(array $columns, int $index): ?string
    {
        $value = trim($columns[$index] ?? '');

        return $value === '' ? null : $value;
    }
}
