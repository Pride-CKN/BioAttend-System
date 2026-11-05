<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_tidak_dapat_melewati_tahap_persetujuan_kadiv(): void
    {
        [$employee, $kadiv, $hrd, $leave] = $this->approvalFixture();
        $service = app(ApprovalService::class);

        try {
            $service->approve($leave, $hrd);
            $this->fail('HRD tidak boleh melewati tahap KADIV.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(LeaveRequest::STATUS_PENDING_KADIV, $leave->fresh()->status);

        $afterKadiv = $service->approve($leave->fresh(), $kadiv);
        $this->assertSame(LeaveRequest::STATUS_PENDING_HRD, $afterKadiv->status);

        $afterHrd = $service->approve($afterKadiv, $hrd);
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $afterHrd->status);
        $this->assertSame((int) $kadiv->id, (int) $afterHrd->kadiv_approved_by);
        $this->assertSame((int) $hrd->id, (int) $afterHrd->hrd_approved_by);
    }

    public function test_kadiv_tidak_dapat_menyetujui_pengajuan_divisi_lain(): void
    {
        [$employee, $kadiv] = $this->approvalFixture();

    $otherDivision = Division::create([
        'code' => 'OTHER-DIV',
        'name' => 'Divisi Lain',
        'is_active' => true,
        ]);
    $otherEmployee = Employee::create([
        'nip' => 'OTHER-001',
        'full_name' => 'Karyawan Divisi Lain',
        'division_id' => $otherDivision->id,
        'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $leaveType = LeaveType::create([
            'code' => 'OTHER-LEAVE', 'name' => 'Cuti Lain', 'default_quota' => 10,
            'max_days_per_request' => 10, 'is_active' => true,
        ]);
        $foreignLeave = LeaveRequest::create([
            'employee_id' => $otherEmployee->id, 'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
            'total_days' => 1, 'reason' => 'Uji scope',
            'status' => LeaveRequest::STATUS_PENDING_KADIV,
        ]);

        try {
            app(ApprovalService::class)->approve($foreignLeave, $kadiv);
            $this->fail('KADIV tidak boleh menyetujui pengajuan divisi lain.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(LeaveRequest::STATUS_PENDING_KADIV, $foreignLeave->fresh()->status);
    }

    /** @return array{Employee, User, User, LeaveRequest} */
    private function approvalFixture(): array
    {

        $division = Division::create([
            'code' => 'PROD',
            'name' => 'Produksi',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'nip' => 'APP-001',
            'full_name' => 'Karyawan Uji',
            'division_id' => $division->id,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);
        $leaveType = LeaveType::create([
            'code' => 'ANNUAL', 'name' => 'Cuti Tahunan', 'default_quota' => 12,
            'max_days_per_request' => 12, 'is_active' => true,
        ]);

        $kadivRole = Role::firstOrCreate(['name' => Role::KADIV], ['scope' => 'DIVISION', 'is_system' => true]);
        $hrdRole = Role::firstOrCreate(['name' => Role::HRD], ['scope' => 'GLOBAL', 'is_system' => true]);

        $kadiv = User::create([
            'employee_id' => $employee->id, 'name' => 'Kepala Divisi',
            'email' => 'kadiv-approval@example.test', 'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
        $kadiv->roles()->sync([$kadivRole->id]);

        $hrd = User::create([
            'name' => 'HRD', 'email' => 'hrd-approval@example.test',
            'password' => Hash::make('Password123'), 'is_active' => true,
        ]);
        $hrd->roles()->sync([$hrdRole->id]);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
            'total_days' => 1, 'reason' => 'Uji approval',
            'status' => LeaveRequest::STATUS_PENDING_KADIV,
        ]);

        return [$employee, $kadiv, $hrd, $leave];
    }
}
