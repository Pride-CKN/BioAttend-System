<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi mapping manual log unmatched oleh HRD (PRD 7.3). */
class MapUnmatchedLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi final tetap di middleware role:HRD pada route.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan tujuan wajib dipilih.',
            'employee_id.exists' => 'Karyawan tujuan tidak ditemukan.',
            'reason.required' => 'Alasan mapping wajib diisi untuk keperluan audit.',
            'reason.min' => 'Alasan mapping minimal 10 karakter.',
        ];
    }
}
