<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/* Pencatatan audit 
  Append-only: sekali ditulis tidak pernah diubah */
class AuditService
{
    /* Kunci yang nilainya wajib disamarkan sebelum ditulis ke audit log
      kata sandi tidak boleh tersimpan atau tercatat dalam bentuk
      apa pun — termasuk hash-nya, agar tidak bisa dipakai untuk serangan
      offline terhadap audit log  */
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'new_password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'commkey',
    ];

    public static function record(
        string $module,
        string $action,
        ?string $objectType = null,
        mixed $objectId = null,
        mixed $oldValues = null,
        mixed $newValues = null,
        ?Request $request = null
    ): AuditLog {
        $user = $request?->user() ?? Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'role' => $user?->primaryRole(),
            'module' => $module,
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId !== null ? (string) $objectId : null,
            'old_values' => self::redact($oldValues),
            'new_values' => self::redact($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /* Catat operasi pembuatan data */
    public static function created(string $module, mixed $model, ?Request $request = null): AuditLog
    {
        return self::record(
            $module, 'CREATE',
            $model->getTable(), $model->getKey(),
            null, $model->getAttributes(), $request
        );
    }

    /* Catat operasi pembaruan data beserta nilai lama & baru */
    public static function updated(string $module, mixed $model, array $oldValues, ?Request $request = null): AuditLog
    {
        return self::record(
            $module, 'UPDATE',
            $model->getTable(), $model->getKey(),
            $oldValues, $model->getAttributes(), $request
        );
    }

    /* Samarkan nilai sensitif pada data yang akan ditulis ke audit log
      Bekerja rekursif supaya struktur bersarang ikut terlindungi */
    protected static function redact(mixed $values): mixed
    {
        if (! is_array($values)) {
            return $values;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $redacted;
    }

    protected static function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    /* Catat perubahan status material (konfirmasi, finalisasi, persetujuan) */
    public static function status(string $module, string $action, mixed $model, ?string $note = null, ?Request $request = null): AuditLog
    {
        return self::record(
            $module, $action,
            $model->getTable(), $model->getKey(),
            null, ['status' => $model->status ?? null, 'note' => $note], $request
        );
    }
}
