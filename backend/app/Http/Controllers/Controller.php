<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use ApiResponse, AuthorizesRequests;

    /*
      Batasi data sesuai scope role:
        HRD      -> seluruh karyawan
        KADIV    -> hanya divisinya
        Employee -> hanya dirinya sendiri
     */

/*
  Batasi data sesuai scope role:
    HRD      -> seluruh karyawan
    KADIV    -> hanya karyawan dalam divisinya
    EMPLOYEE -> hanya dirinya sendiri
 */
protected function scopeEmployees(
    Builder $query,
    ?int $explicitEmployeeId = null
): Builder {
    $user = auth()->user();

    abort_unless($user, 401, 'Tidak terotentikasi.');

    if ($user->isHrd()) {
        return $explicitEmployeeId !== null
            ? $query->where('id', $explicitEmployeeId)
            : $query;
    }

    if ($user->isKadiv()) {
        $divisionId = $user->divisionId();

        abort_if(
            $divisionId === null,
            403,
            'Akun KADIV belum terhubung dengan divisi.'
        );

        $query->where('division_id', $divisionId);

        if ($explicitEmployeeId !== null) {
            $query->where('id', $explicitEmployeeId);
        }

        return $query;
    }

    if ($user->isEmployee()) {
        abort_if(
            ! $user->employee_id,
            403,
            'Akun tidak terhubung dengan data karyawan.'
        );

        // Employee tidak boleh memilih ID karyawan lain.
        $query->where('id', $user->employee_id);

        if ($explicitEmployeeId !== null) {
            $query->where('id', $explicitEmployeeId);
        }

        return $query;
    }

    abort(403, 'Akun tidak memiliki role yang valid.');
}

/* Pastikan pengguna boleh mengakses data karyawan tertentu. */
protected function assertCanAccessEmployee(int $employeeId): void
{
    $user = auth()->user();

    abort_unless($user, 401, 'Tidak terotentikasi.');

    if ($user->isHrd()) {
        return;
    }

    if ($user->isKadiv()) {
        $divisionId = $user->divisionId();

        abort_if(
            $divisionId === null,
            403,
            'Akun KADIV belum terhubung dengan divisi.'
        );

        $employee = Employee::findOrFail($employeeId);

        abort_if(
            $employee->division_id === null
                || (int) $employee->division_id !== $divisionId,
            403,
            'Karyawan berada di luar divisi Anda.'
        );

        return;
    }

    if ($user->isEmployee()) {
        abort_if(
            ! $user->employee_id || (int) $user->employee_id !== $employeeId,
            403,
            'Anda hanya dapat mengakses data Anda sendiri.'
        );

        return;
    }

    abort(403, 'Akun tidak memiliki role yang valid.');
}


    /* Query dasar karyawan yang sudah dibatasi scope. */
    protected function employeesQuery(): Builder
    {
        return $this->scopeEmployees(Employee::query());
    }
}
