<?php

namespace App\Services\Device;

use App\Jobs\PushEmployeeToDeviceJob;
use App\Models\Device;
use App\Models\DeviceEmployeePush;
use App\Models\Employee;
use InvalidArgumentException;

class DeviceEmployeePushService
{
    /**
     * Antrekan perubahan karyawan ke seluruh perangkat aktif
     *
     * @return int Jumlah job yang diantrekan.
     */
    public function queueFor(
        Employee $employee,
        string $operation = DeviceEmployeePush::OPERATION_UPSERT
    ): int {
        $operation = strtoupper(trim($operation));

        if (! in_array($operation, [
            DeviceEmployeePush::OPERATION_UPSERT,
            DeviceEmployeePush::OPERATION_DISABLE,
        ], true)) {
            throw new InvalidArgumentException(
                'Operasi push karyawan tidak dikenal.'
            );
        }

        if (! $employee->exists || ! $employee->getKey()) {
            return 0;
        }

        $identifier = trim((string) $employee->device_user_identifier);

        // Jangan kirim karyawan tidak aktif atau PIN yang tidak valid
        if ($operation === DeviceEmployeePush::OPERATION_UPSERT) {
            if (! $employee->isActive()) {
                return 0;
            }

            if (! preg_match('/\A[0-9]{1,9}\z/', $identifier)) {
                return 0;
            }
        }

        $devices = Device::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $queued = 0;

        foreach ($devices as $device) {
            $payloadHash = hash('sha256', json_encode([
                'operation' => $operation,
                'employee_id' => (int) $employee->getKey(),
                'device_user_identifier' => $identifier,
                'full_name' => (string) $employee->full_name,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            $push = DeviceEmployeePush::query()->firstOrNew([
                'device_id' => $device->id,
                'employee_id' => $employee->id,
            ]);

            // Jangan mengirim ulang payload identik yang sudah berhasil
            if (
                $push->exists
                && $push->status === DeviceEmployeePush::STATUS_SUCCESS
                && hash_equals((string) $push->payload_hash, $payloadHash)
            ) {
                continue;
            }

            $push->fill([
                'device_user_identifier' => $identifier !== '' ? $identifier : null,
                'operation' => $operation,
                'payload_hash' => $payloadHash,
                'status' => DeviceEmployeePush::STATUS_PENDING,
                'error_summary' => null,
                'pushed_at' => null,
            ]);

            $push->save();

            PushEmployeeToDeviceJob::dispatch($push->id)
                ->onQueue((string) config('zkteco.employee_push_queue', 'device-push'))
                ->afterCommit();

            $queued++;
        }

        return $queued;
    }
}
