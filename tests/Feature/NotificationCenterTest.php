<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_filter_mark_read_and_delete_only_their_notifications(): void
    {
        $user = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $otherUser = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);
        $messageNotification = $this->notificationFor($user, 'message_received', 'New message');
        $this->notificationFor($user, 'payment_due', 'Payment is due', now());
        $otherNotification = $this->notificationFor($otherUser, 'message_received', 'Private notification');

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread', 'type' => 'message_received']))
            ->assertOk()
            ->assertSee('New message')
            ->assertDontSee('Payment is due')
            ->assertDontSee('Private notification');

        $this->actingAs($user)
            ->patch(route('notifications.read', $messageNotification->id))
            ->assertRedirect();
        $this->assertNotNull($messageNotification->fresh()->read_at);

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $messageNotification->id))
            ->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $messageNotification->id]);

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $otherNotification->id))
            ->assertNotFound();
    }

    private function notificationFor(User $user, string $type, string $title, mixed $readAt = null)
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['type' => $type, 'title' => $title, 'message' => 'Test message', 'icon' => 'bell', 'url' => route('notifications.index')],
            'read_at' => $readAt,
        ]);
    }
}
