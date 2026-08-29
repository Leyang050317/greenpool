<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettingsAndHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_are_saved_for_the_signed_in_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->put(route('settings.notifications.update'), [
                'trip_updates' => '0',
                'booking_updates' => '1',
                'payment_updates' => '0',
                'message_alerts' => '1',
                'rating_reminders' => '0',
                'attraction_updates' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Notification preferences saved.');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'trip_updates' => false,
            'booking_updates' => true,
            'payment_updates' => false,
            'message_alerts' => true,
            'rating_reminders' => false,
            'attraction_updates' => true,
        ]);
    }

    public function test_help_page_displays_active_database_faqs(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Faq::create([
            'question' => 'Where are payment receipts?',
            'answer' => 'Open Payments.',
            'category' => 'Payments',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('Where are payment receipts?')
            ->assertSee('Open Payments.');
    }

    public function test_an_optional_notification_is_not_delivered_when_its_setting_is_disabled(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->notificationPreference()->create([
            'trip_updates' => false,
            'booking_updates' => true,
            'payment_updates' => true,
            'message_alerts' => true,
            'rating_reminders' => true,
            'attraction_updates' => true,
        ]);

        $user->notify(new TripReminderNotification(new Trip));

        Notification::assertNothingSent();
    }
}
