<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceEmployeePush;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceEmployeePush>
 */
class DeviceEmployeePushFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fn (): int => Device::query()->create([
                'name' => fake()->unique()->words(2, true),
                'ip_address' => fake()->unique()->ipv4(),
                'port' => 4370,
                'commkey_encrypted' => '0',
                'status' => DeviceStatus::Online,
                'is_active' => true,
            ])->id,
            'employee_id' => Employee::factory(),
            'device_user_identifier' => 'EMP-'.fake()->unique()->numerify('####'),
            'operation' => DeviceEmployeePush::OPERATION_UPSERT,
            'payload_hash' => hash('sha256', fake()->uuid()),
            'status' => DeviceEmployeePush::STATUS_PENDING,
            'error_summary' => null,
            'pushed_at' => null,
        ];
    }
}
