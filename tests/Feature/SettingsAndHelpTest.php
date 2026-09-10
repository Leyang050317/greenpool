<?php

namespace Tests\Feature;

use App\Events\InAppNotificationCreated;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\BookingStatusNotification;
use App\Notifications\TripReminderNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_help_page_omits_the_unused_faq_section_and_displays_the_correct_header(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('aria-label="Current page: Help &amp; Support"', false)
            ->assertSee('Quick guides')
            ->assertDontSee('Frequently asked questions');
    }

    public function test_settings_page_displays_the_correct_header(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('aria-label="Current page: Settings"', false);
    }

    public function test_notification_settings_explain_booking_updates_for_each_role_and_hide_unused_attraction_updates(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($driver)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('New passenger booking requests and cancellations of pending requests.')
            ->assertDontSee('Tourist attraction updates');

        $this->actingAs($passenger)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Updates when a driver accepts or rejects your booking request.')
            ->assertDontSee('Tourist attraction updates');
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

    public function test_disabled_notification_category_also_suppresses_the_realtime_event(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->notificationPreference()->create(['trip_updates' => false]);

        app(NotificationDeliveryService::class)->send($user, new TripReminderNotification(new Trip));

        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(InAppNotificationCreated::class);
    }

    public function test_booking_decisions_and_trip_lifecycle_updates_use_separate_preferences(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->notificationPreference()->create([
            'booking_updates' => true,
            'trip_updates' => false,
        ]);

        $this->assertTrue($user->allowsInAppNotification(new BookingStatusNotification(new Booking, 'Accepted')));
        $this->assertFalse($user->allowsInAppNotification(new BookingStatusNotification(new Booking, 'Started')));
    }
}
