<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;

/* Notifikasi in-app + ledger idempotency
 
  Idempotency dijamin oleh unique constraint `notifications.idempotency_key`
  Satu kejadian logis hanya menghasilkan satu notifikasi per penerima,
  termasuk ketika attendance diproses ulang berkali-kali */
class NotificationService
{
    /* Kirim notifikasi ke satu pengguna. */
    public function notify(int $userId, array $data): ?Notification
    {
        $key = $data['idempotency_key'] ?? null;

        $payload = $this->payload($userId, $data);

        if ($key === null) {
            return Notification::create($payload);
        }

        // Buat idempotency key khusus untuk setiap penerima
        $recipientKey = substr(
            hash('sha256', $key.'|'.$userId),
            0,
            64
        );

        $payload['idempotency_key'] = $recipientKey;

        // Pencarian berdasarkan penerima dan idempotency key
        return Notification::firstOrCreate(
            [
                'user_id' => $userId,
                'idempotency_key' => $recipientKey,
            ],
            $payload
        );
    }

    /* Kirim notifikasi ke seluruh pengguna dengan role tertentu */
    public function notifyRole(string $role, array $data): int
    {
        $userIds = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->pluck('id');

        $sent = 0;

        foreach ($userIds as $id) {
            $notification = $this->notify($id, $data);

            if ($notification?->delivery_status === 'SENT') {
                $sent++;
            }
        }

        return $sent;
    }

    /* Notifikasi ke KADIV pada divisi tertentu */
    public function notifyDivision(int $divisionId, array $data): int
    {
        $userIds = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::KADIV))
            ->whereHas('employee', fn ($q) => $q->where('division_id', $divisionId))
            ->pluck('id');

        foreach ($userIds as $id) {
            $this->notify($id, $data);
        }

        return $userIds->count();
    }

    protected function payload(int $userId, array $data): array
    {
        return [
            'user_id' => $userId,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'category' => $data['category'] ?? 'SYSTEM',
            'link' => $data['link'] ?? null,
            'is_read' => false,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'channel' => $data['channel'] ?? 'DATABASE',
            'delivery_status' => 'SENT',
            'attempts' => 1,
        ];
    }
}
