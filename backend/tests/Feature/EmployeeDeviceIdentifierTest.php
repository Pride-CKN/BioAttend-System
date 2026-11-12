<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDeviceIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_nip_is_mirrored_to_the_device_user_identifier(): void
    {
        $employee = Employee::create([
            'nip' => '251001',
            'device_user_identifier' => '999999',
            'full_name' => 'Deri Mulyana',
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        $this->assertSame('251001', $employee->device_user_identifier);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'nip' => '251001',
            'device_user_identifier' => '251001',
        ]);
    }

    public function test_changing_nip_updates_the_device_user_identifier(): void
    {
        $employee = Employee::factory()->create(['nip' => '251001']);

        $employee->update(['nip' => '251002']);

        $this->assertSame('251002', $employee->refresh()->device_user_identifier);
    }
}
