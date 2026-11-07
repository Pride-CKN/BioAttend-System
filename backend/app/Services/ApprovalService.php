<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/* Persetujuan berjenjang yang ketat
  SUBMITTED -> PENDING_KADIV -> PENDING_HRD -> APPROVED
  Tidak ada role yang dapat melewati tahap sebelumnya */
class ApprovalService
{
    public const STAGE_KADIV = 'KADIV';
    public const STAGE_HRD = 'HRD';

    public function approve(Model $model, User $user, ?string $note = null): Model
    {
        return DB::transaction(function () use ($model, $user, $note): Model {
            /** @var Model $locked */
            $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $this->assertWithinScope($locked, $user);

            if ($locked->status === $this->approvedStatus()) {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah disetujui.']);
            }

            if ($locked->status === $this->rejectedStatus()) {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah ditolak.']);
            }

            $columns = $this->approvalColumns($locked);

            if ($user->isKadiv()) {
                if ($locked->status !== $this->pendingKadivStatus()) {
                    throw ValidationException::withMessages([
                        'status' => 'Pengajuan tidak berada pada tahap persetujuan KADIV.',
                    ]);
                }

                $locked->forceFill([
                    'status' => $this->pendingHrdStatus(),
                    $columns['kadiv_by'] => $user->id,
                    $columns['kadiv_at'] => Carbon::now(),
                ])->save();

                return $locked->refresh();
            }

            if ($user->isHrd()) {
                if ($locked->status !== $this->pendingHrdStatus()) {
                    throw ValidationException::withMessages([
                        'status' => 'HRD hanya dapat menyetujui pengajuan yang sudah melewati tahap KADIV.',
                    ]);
                }

                $locked->forceFill([
                    'status' => $this->approvedStatus(),
                    $columns['hrd_by'] => $user->id,
                    $columns['hrd_at'] => Carbon::now(),
                ])->save();

                return $locked->refresh();
            }

            throw ValidationException::withMessages([
                'role' => 'Hanya KADIV atau HRD yang dapat menyetujui pengajuan.',
            ]);
        }, 3);
    }

    public function reject(Model $model, User $user, ?string $note): Model
    {
        if (blank($note)) {
            throw ValidationException::withMessages(['note' => 'Catatan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($model, $user, $note): Model {
            /** @var Model $locked */
            $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $this->assertWithinScope($locked, $user);

            if ($locked->status === $this->approvedStatus() || $locked->status === $this->rejectedStatus()) {
                throw ValidationException::withMessages(['status' => 'Pengajuan sudah selesai diproses.']);
            }

            if ($user->isKadiv() && $locked->status !== $this->pendingKadivStatus()) {
                throw ValidationException::withMessages(['status' => 'KADIV hanya dapat menolak pada tahap KADIV.']);
            }

            if ($user->isHrd() && $locked->status !== $this->pendingHrdStatus()) {
                throw ValidationException::withMessages(['status' => 'HRD hanya dapat menolak setelah tahap KADIV.']);
            }

            if (! $user->isHrd() && ! $user->isKadiv()) {
                throw ValidationException::withMessages(['role' => 'Hanya KADIV atau HRD yang dapat menolak pengajuan.']);
            }

            $locked->forceFill([
                'status' => $this->rejectedStatus(),
                'rejection_note' => trim($note),
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    /* KADIV hanya boleh mengambil keputusan untuk karyawan divisinya */
    protected function assertWithinScope(Model $model, User $user): void
    {
        if ($user->isHrd()) {
            return;
        }

        if (! $user->isKadiv()) {
            throw ValidationException::withMessages(['role' => 'Role tidak memiliki kewenangan approval.']);
        }

        if (! method_exists($model, 'employee')) {
            throw ValidationException::withMessages(['scope' => 'Pengajuan tidak memiliki relasi karyawan.']);
        }

        $employee = $model->employee()->first();
        if (! $employee || ! $user->divisionId() || (int) $employee->division_id !== (int) $user->divisionId()) {
            abort(403, 'Pengajuan berada di luar divisi Anda.');
        }
    }

    /* Kolom approval correction berbeda penamaannya dari tabel request lainnya */
    protected function approvalColumns(Model $model): array
    {
        if ($model instanceof AttendanceCorrection) {
            return [
                'kadiv_by' => 'approved_by_kadiv',
                'kadiv_at' => 'kadiv_approved_at',
                'hrd_by' => 'approved_by_hrd',
                'hrd_at' => 'hrd_approved_at',
            ];
        }

        return [
            'kadiv_by' => 'kadiv_approved_by',
            'kadiv_at' => 'kadiv_approved_at',
            'hrd_by' => 'hrd_approved_by',
            'hrd_at' => 'hrd_approved_at',
        ];
    }

    protected function pendingKadivStatus(): string { return 'PENDING_KADIV'; }
    protected function pendingHrdStatus(): string { return 'PENDING_HRD'; }
    protected function approvedStatus(): string { return 'APPROVED'; }
    protected function rejectedStatus(): string { return 'REJECTED'; }
}
