<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* Validasi push event-driven data karyawan ke perangkat  */
class PushEmployeeToDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'operation' => ['sometimes', 'string', Rule::in(['UPSERT', 'DISABLE'])],
        ];
    }
}
