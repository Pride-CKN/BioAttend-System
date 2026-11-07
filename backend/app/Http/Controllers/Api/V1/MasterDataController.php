<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Unit;
use App\Models\Device;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\Employee;
use App\Models\EmployeeCodeHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/*CRUD generik master data
  Menangani: office, Unit, divisions, positions, shifts, holidays, leave-types, devices

  data tidak dihapus secara fisik — hapus = nonaktifkan (is_active = false)*/
class MasterDataController extends Controller
{
    /* Pemetaan resource -> [model, aturan validasi, kolom pencarian] */

protected function map(string $resource): array
{
    return match ($resource) {
        'offices' => [
            Office::class,
            [
                'code' => [
                    'required',
                    'string',
                    'size:1',
                    'regex:/^[0-9]$/',
                    'unique:offices,code',
                ],
                'name' => 'required|string|max:255',
                'is_active' => 'boolean',
            ],
            ['code', 'name'],
        ],

        'divisions' => [
            Division::class,
            [
                'code' => 'required|string|max:30|unique:divisions,code',
                'name' => 'required|string|max:255',
                'head_id' => 'nullable|integer|exists:employees,id',
                'is_active' => 'boolean',
            ],
            ['code', 'name'],
        ],

        'units' => [
            Unit::class,
            [
                'code' => 'required|string|max:30|unique:units,code',
                'name' => 'required|string|max:255',
                'division_id' => 'required|integer|exists:divisions,id',
                'head_id' => 'nullable|integer|exists:employees,id',
                'employee_code_digit' => 'required|integer|between:1,9',
                'is_active' => 'boolean',
            ],
            ['code', 'name'],
        ],

        'positions' => [
            Position::class,
            [
                'code' => 'required|string|max:30|unique:positions,code',
                'name' => 'required|string|max:255',
                'level' => 'nullable|string|max:30',
                'is_active' => 'boolean',
            ],
            ['code', 'name'],
        ],

        'shifts' => [
            Shift::class,
            [
                'code' => 'nullable|string|max:30',
                'name' => 'required|string|max:255',
                'start_time' => 'required|date_format:H:i,H:i:s',
                'end_time' => 'required|date_format:H:i,H:i:s',
                'late_tolerance_minutes' => 'integer|min:0',
                'min_work_hours' => 'numeric|min:0',
                'is_night_shift' => 'boolean',
                'is_active' => 'boolean',
            ],
            ['name', 'code'],
        ],

        'holidays' => [
            Holiday::class,
            [
                'date' => 'required|date|unique:holidays,date',
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:30',
                'is_paid' => 'boolean',
                'year' => 'nullable|integer',
                'is_active' => 'boolean',
             ],
         ['name'],
        ],

        'leave-types' => [
            LeaveType::class,
            [
                'code' => 'required|string|max:30|unique:leave_types,code',
                'name' => 'required|string|max:255',
                'default_quota' => 'integer|min:0',
                'max_days_per_request' => 'integer|min:1',
                'requires_attachment' => 'boolean',
                'is_active' => 'boolean',
            ],
            ['code', 'name'],
        ],

        'devices' => [
            Device::class,
            [
                'name' => 'required|string|max:255',
                'model' => 'nullable|string|max:100',
                'ip_address' => 'required|ip',
                'port' => 'integer|min:1|max:65535',
                'commkey' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ],
            ['name', 'ip_address'],
        ],

        default => abort(404, 'Resource tidak dikenal.'),
    };
}


    public function index(Request $request, string $resource): JsonResponse
    {
        [$modelClass, , $searchFields] = $this->map($resource);

        $query = $modelClass::query();

        if ($q = $request->query('q')) {
            $query->where(fn ($sub) => collect($searchFields)->each(
                fn ($field) => $sub->orWhere($field, 'ilike', "%{$q}%")
            ));
        }

        if ($request->query('is_active') !== null) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($resource === 'units' && $request->filled('division_id')) {
        $query->where('division_id', $request->query('division_id'));
        }

        if ($request->boolean('all')) {
            return $this->success($query->orderBy('name')->get(), 'Daftar master data');
        }

        return $this->paginated(
            $query->orderBy('id', 'desc')->paginate($request->integer('per_page', 15))
        );
    }

public function store(Request $request, string $resource): JsonResponse
{
    [$modelClass, $rules] = $this->map($resource);

    $data = $request->validate($rules);

    if ($resource === 'units') {
        $division = Division::query()->findOrFail($data['division_id']);

        abort_if(
            ! $division->is_active,
            422,
            'Unit tidak dapat dibuat pada divisi yang tidak aktif.'
        );

        $this->assertUnitDigitAvailable(
            (int) $data['division_id'],
            (int) $data['employee_code_digit']
        );
    }

    $model = $modelClass::create($data);

    AuditService::created(strtoupper($resource), $model, $request);

    return $this->created($model, 'Data berhasil ditambahkan');
}

    public function show(string $resource, int $id): JsonResponse
    {
        [$modelClass] = $this->map($resource);

        return $this->success($modelClass::findOrFail($id));
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        [$modelClass, $rules] = $this->map($resource);

        $model = $modelClass::findOrFail($id);

        // Perbarui hanya field yang memang diizinkan oleh schema resource
        // Jangan menggunakan request->all() sebagai allow-list
        $submittedKeys = array_flip($request->keys());
        $rules = array_intersect_key($rules, $submittedKeys);
        abort_if($rules === [], 422, 'Tidak ada field master data yang dapat diperbarui.');

        foreach ($rules as $field => $rule) {
            $parts = is_array($rule) ? $rule : explode('|', $rule);
            $normalized = [];

            foreach ($parts as $part) {
                if (is_string($part) && str_starts_with($part, 'required')) {
                    $normalized[] = 'sometimes';
                    $normalized[] = $part;
                    continue;
                }

                if (is_string($part) && str_starts_with($part, 'unique:')) {
                    $definition = explode(',', substr($part, 7));
                    $table = $definition[0] ?? $model->getTable();
                    $column = $definition[1] ?? $field;
                    $normalized[] = Rule::unique($table, $column)->ignore($model->getKey());
                    continue;
                }

                $normalized[] = $part;
            }

            $rules[$field] = $normalized;
        }

$data = $request->validate($rules);

// Kode kantor menjadi bagian dari Employee ID
// Jangan mengubahnya setelah pernah digunakan

if (
    $resource === 'offices'
    && array_key_exists('code', $data)
    && $data['code'] !== $model->code
) {
    $officeIsUsed =
        Employee::query()->where('office_id', $id)->exists()
        || EmployeeCodeHistory::query()->where('office_id', $id)->exists();

    abort_if(
        $officeIsUsed,
        422,
        'Kode kantor tidak dapat diubah karena sudah digunakan dalam Employee ID atau histori.'
    );
}

// Digit unit dan induk divisinya tidak boleh berubah
// setelah unit pernah digunakan dalam penempatan karyawan

if ($resource === 'units') {
    $newDivisionId = (int) ($data['division_id'] ?? $model->division_id);
    $newDigit = (int) ($data['employee_code_digit'] ?? $model->employee_code_digit);

    $placementIdentifierChanged =
        $newDivisionId !== (int) $model->division_id
        || $newDigit !== (int) $model->employee_code_digit;

    if ($placementIdentifierChanged) {
        $unitIsUsed =
            Employee::query()->where('unit_id', $id)->exists()
            || EmployeeCodeHistory::query()->where('unit_id', $id)->exists();

        abort_if(
            $unitIsUsed,
            422,
            'Divisi atau digit Employee ID unit tidak dapat diubah karena sudah digunakan karyawan atau histori.'
        );
    }

    $this->assertUnitDigitAvailable(
        $newDivisionId,
        $newDigit,
        (int) $id
    );

    if (array_key_exists('division_id', $data)) {
        $division = Division::query()->findOrFail($newDivisionId);

        abort_if(
            ! $division->is_active,
            422,
            'Unit tidak dapat dipindahkan ke divisi yang tidak aktif.'
        );
    }
}

        $old = $model->only(array_keys($data));

        $model->update($data);

        AuditService::updated(strtoupper($resource), $model, $old, $request);

        return $this->success($model->refresh(), 'Data berhasil diperbarui');
    }

    /* Memastikan digit Employee ID unik dalam satu divisi */
        protected function assertUnitDigitAvailable(
        int $divisionId,
        int $digit, ?int $ignoreId = null
        ): void {
    $exists = Unit::query()
        ->where('division_id', $divisionId)
        ->where('employee_code_digit', $digit)
        ->when(
            $ignoreId !== null,
            fn ($query) => $query->where('id', '!=', $ignoreId)
        )
        ->exists();

    if ($exists) {
        throw ValidationException::withMessages([
            'employee_code_digit' =>
                'Digit Employee ID tersebut sudah digunakan oleh unit lain dalam divisi ini.',
        ]);
    }
}

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        [$modelClass] = $this->map($resource);

        $model = $modelClass::findOrFail($id);

        // penonaktifan memakai flag, bukan hapus fisik
        $model->update(['is_active' => false]);

        AuditService::record('MASTER', 'DEACTIVATE', $model->getTable(), $model->id, ['is_active' => true], ['is_active' => false], $request);

        return $this->success(null, 'Data berhasil dinonaktifkan');
    }
}
