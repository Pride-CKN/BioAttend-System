<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeductionRecord;
use App\Services\AuditService;
use App\Services\DeductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*Modul deduction

  ATURAN ALPHA: tanpa keterangan dikenakan 4% LANGSUNG sejak kejadian
  pertama dan tidak mendapat jatah toleransi (liat DeductionService) */
class DeductionController extends Controller
{
    public function __construct(protected DeductionService $deductions) {}

    /* GET /deductions — daftar perhitungan (HRD semua, KADIV divisinya) */
    public function index(Request $request): JsonResponse
    {
        $employeeIds = $this->employeesQuery()->pluck('id');

        $query = DeductionRecord::query()
            ->with(['employee:id,nip,full_name,division_id', 'employee.division:id,name'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('period'), fn ($q, $p) => $q->where('period', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));

        return $this->paginated($query->orderByDesc('period')->orderBy('employee_id')->paginate($request->integer('per_page', 15)));
    }

    /* GET /deductions/my — data deduction sendiri (Employee, read-only) */
    public function my(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->employee_id, 403, 'Akun tidak terhubung dengan data karyawan.');

        $query = DeductionRecord::query()
            ->where('employee_id', $user->employee_id)
            ->when($request->query('period'), fn ($q, $p) => $q->where('period', $p));

        return $this->paginated($query->orderByDesc('period')->paginate($request->integer('per_page', 12)));
    }

    public function show(int $id): JsonResponse
    {
        $record = DeductionRecord::with(['employee', 'events.attendanceRecord'])->findOrFail($id);

        $this->assertCanAccessEmployee($record->employee_id);

        return $this->success($this->transform($record));
    }

    /* POST /deductions/calculate — hitung deduction periode (HRD) */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'office_id' => 'nullable|integer|exists:offices,id',
            'division_id' => 'nullable|integer|exists:divisions,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'recalculate' => 'boolean',
        ]);

            $result = $this->deductions->calculatePeriod($data['period'], [
            'office_id' => $data['office_id'] ?? null,
            'division_id' => $data['division_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'employee_ids' => $data['employee_ids'] ?? null,
            'recalculate' => $data['recalculate'] ?? false,
            'actor_user_id' => $request->user()->id,
        ]);

        AuditService::record('DEDUCTION', 'CALCULATE', 'deduction_records', null, null, $result, $request);

        return $this->accepted($result, 'Perhitungan deduction selesai');
    }

    /* POST /deductions/{id}/confirm — konfirmasi (HRD), final setelah cut-off */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $record = $this->deductions->confirm(
                $id,
                $request->user()->id,
                $data['note'] ?? null
            );
        } catch (RuntimeException $e) {
            // ModelNotFoundException juga merupakan RuntimeException. Jangan
            // menelan 404 menjadi 409, hanya konflik state (code 409) yang
            // dikonversi, lainnya diteruskan ke handler terpusat
            if ($e->getCode() !== 409
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw $e;
            }

            return $this->conflict($e->getMessage());
        }

        AuditService::status('DEDUCTION', 'CONFIRM', $record, $data['note'] ?? null, $request);

        return $this->success($this->transform($record), 'Perhitungan berhasil dikonfirmasi');
    }

    /* POST /deductions/{id}/nominal — input / ubah nominal dasar (HRD) */
    public function nominal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'base_amount' => 'required|numeric|gt:0',
            'note' => 'nullable|string',
        ]);

        try {
            $record = $this->deductions->inputBaseAmount($id, (string) $data['base_amount'], [
                'actor_user_id' => $request->user()->id,
                'note' => $data['note'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 409
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw $e;
            }

            return $this->conflict($e->getMessage());
        }

        AuditService::record('DEDUCTION', 'INPUT_NOMINAL', 'deduction_records', $record->id, null, [
            'base_amount' => $data['base_amount'],
        ], $request);

        return $this->success($this->transform($record), 'Nominal dasar berhasil disimpan');
    }

    /* GET /deductions/{id}/histories — riwayat perhitungan */
    public function histories(int $id): JsonResponse
    {
        $record = DeductionRecord::findOrFail($id);

        $this->assertCanAccessEmployee($record->employee_id);

        return $this->success($record->histories()->orderByDesc('created_at')->get());
    }

    /* GET /deductions/preview — simulasi hitung tanpa menyimpan */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'base_amount' => 'nullable|numeric|min:0',
        ]);

        $this->assertCanAccessEmployee($data['employee_id']);

        return $this->success(
            $this->deductions->preview($data['employee_id'], $data['period'], (float) ($data['base_amount'] ?? 0))
        );
    }

    /* GET /deductions/export — ekspor CSV (HRD / KADIV divisi) */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $employeeIds = $this->employeesQuery()->pluck('id');

        $records = DeductionRecord::query()
            ->with(['employee:id,nip,full_name', 'employee.division:id,name'])
            ->whereIn('employee_id', $employeeIds)
            ->where('period', $data['period'])
            ->orderBy('employee_id')
            ->get();

        $filename = "deduction-{$data['period']}.csv";

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'NIP', 'Nama', 'Divisi', 'Periode', 'Total Event', 'Toleransi',
            'Event Kena Potongan', 'Event Alpha', 'Tarif Alpha %', 'Total %',
            'Nominal Dasar', 'Nominal Potongan', 'Nominal Akhir', 'Status',
        ]);

        foreach ($records as $r) {
            fputcsv($handle, [
                $r->employee?->nip,
                $r->employee?->full_name,
                $r->employee?->division?->name,
                $r->period,
                $r->total_events,
                $r->tolerated_events,
                $r->charged_events,
                $r->alpha_events,
                $r->alpha_rate,
                $r->total_percentage,
                $r->base_amount,
                $r->deduction_amount,
                $r->final_amount,
                $r->status,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put("exports/{$filename}", $csv);

        AuditService::record('DEDUCTION', 'EXPORT', 'deduction_records', null, null, [
            'period' => $data['period'], 'rows' => $records->count(),
        ], $request);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* Format ringkas untuk ditampilkan; menyertakan rincian alpha */
    protected function transform(DeductionRecord $record): array
    {
        $record->loadMissing(['employee:id,nip,full_name', 'employee.division:id,name', 'events']);

        return [
            'id' => $record->id,
            'employee' => $record->employee,
            'period' => $record->period,
            'status' => $record->status,
            'events' => [
                'total' => $record->total_events,
                'tolerated' => $record->tolerated_events,
                'charged' => $record->charged_events,
                'rate_per_event' => $record->rate_per_event,
            ],
            'alpha' => [
                'events' => $record->alpha_events,
                'rate' => $record->alpha_rate,
                'percentage' => $record->alpha_percentage,
                'note' => 'Alpha dipotong langsung 4% per kejadian tanpa toleransi.',
            ],
            'early_departure' => [
                'events' => $record->early_departure_events ?? 0,
                'percentage' => 0,
                'note' => 'Pulang Cepat hanya peringatan, tidak pernah masuk deduction.',
            ],
            'amounts' => [
                'total_percentage' => $record->total_percentage,
                'base_amount' => $record->base_amount,
                'deduction_amount' => $record->deduction_amount,
                'raw_final_amount' => $record->raw_final_amount,
                'rounding_adjustment' => $record->rounding_adjustment,
                'pph_amount' => $record->pph_amount,
                'final_amount' => $record->final_amount,
                'rounding_rule' => 'Jika > 0, dibulatkan ke atas ke kelipatan Rp1.000.',
            ],
            'pph_details' => $record->pph_details,
            'calculated_at' => $record->calculated_at,
            'confirmed_at' => $record->confirmed_at,
            'confirmation_note' => $record->confirmation_note,
            'detail_events' => $record->events->map(fn ($e) => [
                'date' => $e->event_date->toDateString(),
                'type' => $e->event_type,
                'label' => DeductionService::eventLabel($e->event_type),
                'sequence' => $e->sequence,
                'is_tolerated' => $e->is_tolerated,
                'percentage' => $e->percentage,
            ]),
        ];
    }
}
