<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Device;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * CRUD generik master data
 * Menangani: departments, divisions, positions, shifts, holidays, leave-types, devices.
 *
 * BR-06: data tidak dihapus secara fisik — hapus = nonaktifkan (is_active = false).
 */
class MasterDataController extends Controller
{
    /** Pemetaan resource -> [model, aturan validasi, kolom pencarian]. */
    protected function map(string $resource): array
    {
        return match ($resource) {
            'departments' => [
                Department::class,
                ['code' => 'required|string|max:30|unique:departments,code', 'name' => 'required|string|max:255', 'head_id' => 'nullable|integer|exists:employees,id', 'is_active' => 'boolean'],
                ['code', 'name'],
            ],
            'divisions' => [
                Division::class,
                ['code' => 'required|string|max:30|unique:divisions,code', 'name' => 'required|string|max:255', 'department_id' => 'nullable|exists:departments,id', 'head_id' => 'nullable|integer|exists:employees,id', 'is_active' => 'boolean'],
                ['code', 'name'],
            ],
            'positions' => [
                Position::class,
                ['code' => 'required|string|max:30|unique:positions,code', 'name' => 'required|string|max:255', 'level' => 'nullable|string|max:30', 'is_active' => 'boolean'],
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
                ['date' => 'required|date|unique:holidays,date', 'name' => 'required|string|max:255', 'type' => 'nullable|string|max:30', 'is_paid' => 'boolean', 'year' => 'nullable|integer'],
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

        if ($request->query('department_id')) {
            $query->where('department_id', $request->query('department_id'));
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

        // Perbarui hanya field yang memang diizinkan oleh schema resource.
        // Jangan menggunakan request->all() sebagai allow-list.
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
        $old = $model->only(array_keys($data));

        $model->update($data);

        AuditService::updated(strtoupper($resource), $model, $old, $request);

        return $this->success($model->refresh(), 'Data berhasil diperbarui');
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        [$modelClass] = $this->map($resource);

        $model = $modelClass::findOrFail($id);

        // BR-06: penonaktifan memakai flag, bukan hapus fisik
        $model->update(['is_active' => false]);

        AuditService::record('MASTER', 'DEACTIVATE', $model->getTable(), $model->id, ['is_active' => true], ['is_active' => false], $request);

        return $this->success(null, 'Data berhasil dinonaktifkan');
    }
}
