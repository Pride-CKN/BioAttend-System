<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUser(string $email): User
    {
        return User::create([
            'name' => 'User Test',
            'email' => $email,
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);
    }

    private function createNotification(
        int $userId,
        bool $isRead = false,
        ?string $title = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title ?? 'Notifikasi Test',
            'body' => 'Isi notifikasi test',
            'category' => 'SYSTEM',
            'is_read' => $isRead,
            'read_at' => $isRead ? now() : null,
            'channel' => 'DATABASE',
            'delivery_status' => 'SENT',
            'attempts' => 1,
        ]);
    }

    public function test_user_hanya_melihat_notifikasinya_sendiri(): void
    {
        $user = $this->createTestUser('user1@example.test');
        $otherUser = $this->createTestUser('user2@example.test');

        $ownNotification = $this->createNotification($user->id);
        $this->createNotification($otherUser->id);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNotification->id);
    }

    public function test_user_dapat_memfilter_notifikasi_yang_belum_dibaca(): void
    {
        $user = $this->createTestUser('filter@example.test');

        $unread = $this->createNotification($user->id, false);
        $this->createNotification($user->id, true);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?is_read=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    }

    public function test_user_dapat_memfilter_notifikasi_yang_sudah_dibaca(): void
    {
        $user = $this->createTestUser('read-filter@example.test');

        $read = $this->createNotification($user->id, true);
        $this->createNotification($user->id, false);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?is_read=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $read->id);
    }

    public function test_user_dapat_menandai_notifikasinya_sudah_dibaca(): void
    {
        $user = $this->createTestUser('read-one@example.test');
        $notification = $this->createNotification($user->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_tidak_dapat_membaca_notifikasi_milik_user_lain(): void
    {
        $user = $this->createTestUser('owner@example.test');
        $otherUser = $this->createTestUser('stranger@example.test');

        $notification = $this->createNotification($otherUser->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_user_dapat_menandai_semua_notifikasinya_sudah_dibaca(): void
    {
        $user = $this->createTestUser('read-all@example.test');
        $otherUser = $this->createTestUser('other@example.test');

        $first = $this->createNotification($user->id);
        $second = $this->createNotification($user->id);
        $other = $this->createNotification($otherUser->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated', 2);

        $this->assertTrue($first->fresh()->is_read);
        $this->assertTrue($second->fresh()->is_read);
        $this->assertFalse($other->fresh()->is_read);
    }

    public function test_notification_service_mencegah_duplikasi_idempotency_key(): void
    {
        $user = $this->createTestUser('idempotency@example.test');

        $service = app(NotificationService::class);

        $payload = [
            'title' => 'Absensi berhasil',
            'body' => 'Absensi berhasil diproses',
            'category' => 'ATTENDANCE',
            'idempotency_key' => 'attendance-test-001',
        ];

        $first = $service->notify($user->id, $payload);
        $second = $service->notify($user->id, $payload);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);

        $this->assertDatabaseCount('notifications', 1);
    }
}
