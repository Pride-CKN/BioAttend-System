<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponse, AuthorizesRequests;

    /**
     * Batasi data sesuai scope role (PRD 20.2):
     *   HRD      -> seluruh karyawan
     *   KADIV    -> hanya divisinya
     *   Employee -> hanya dirinya sendiri (BR-10)
     */
    protected function scopeEmployees(Builder $query, ?int $explicitEmployeeId = null): Builder
    {
        $user = auth()->user();

        if ($user->isHrd()) {
            return $explicitEmployeeId
                ? $query->where('id', $explicitEmployeeId)
                : $query;
        }

        if ($user->isKadiv()) {
            return $query->where('division_id', $user->divisionId());
        }

        // Employee — wajib dirinya sendiri
        return $query->where('id', $explicitEmployeeId ?? $user->employee_id);
    }

    /** Pastikan pengguna boleh mengakses data karyawan tertentu. */
    protected function assertCanAccessEmployee(int $employeeId): void
    {
        $user = auth()->user();

        if ($user->isHrd()) {
            return;
        }

        if ($user->isKadiv()) {
            $employee = Employee::findOrFail($employeeId);

            abort_if($employee->division_id !== $user->divisionId(), 403, 'Karyawan berada di luar divisi Anda.');

            return;
        }

        abort_if($user->employee_id !== $employeeId, 403, 'Anda hanya dapat mengakses data Anda sendiri.');
    }

    /** Query dasar karyawan yang sudah dibatasi scope. */
    protected function employeesQuery(): Builder
    {
        return $this->scopeEmployees(Employee::query());
    }
}
