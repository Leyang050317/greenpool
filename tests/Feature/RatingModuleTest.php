<?php

namespace Tests\Feature;

use App\Events\RatingReceived;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RatingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_dashboard_ratings_navigation_links_to_rating_history(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $response = $this->actingAs($passenger)->get(route('passenger.home'));
        $response->assertOk()->assertSee('href="'.route('ratings.index').'"', false);
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.route('ratings.index').'"'));
        $this->assertLessThan(strpos($response->getContent(), 'href="'.route('profile.edit').'"'), strpos($response->getContent(), 'href="'.route('ratings.index').'"'));
    }

    public function test_passenger_booking_layout_ratings_navigation_links_to_rating_hub(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $response = $this->actingAs($passenger)->get(route('passenger.bookings.history'));
        $response->assertOk()->assertSee('href="'.route('ratings.index').'"', false);
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.route('ratings.index').'"'));
    }

    public function test_ratings_sidebar_opens_the_rating_module_hub(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($passenger)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Driver Profiles &amp; Reviews', false)
            ->assertSee('Rate a Driver')
            ->assertDontSee('Browse reviews for drivers')
            ->assertSee('Rating History')
            ->assertSee('Open navigation menu')
            ->assertSee('M21 15a4 4 0 0 1-4 4H8l-5 3V7', false)
            ->assertSee('M12 7v5l3 2', false)
            ->assertSee(route('ratings.history'), false);
    }

    public function test_rating_hub_prioritizes_grab_style_workflow_order(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($passenger)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSeeInOrder(['Rate a Driver', 'Rating History', 'Driver Profiles &amp; Reviews'], false);
    }

    public function test_rating_history_only_shows_filters_relevant_to_the_users_role(): void
    {
        [, $passenger] = $this->completedBooking();

        $this->actingAs($passenger)
            ->get(route('ratings.history'))
            ->assertOk()
            ->assertSee('Drivers Rated')
            ->assertSee('Newest first')
            ->assertSee('Oldest first')
            ->assertDontSee('All roles')
            ->assertDontSee('Passengers Rated')
            ->assertDontSee('name="role"', false);
    }

    public function test_profiles_link_to_the_selected_users_reviews(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        Rating::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $passenger->id,
            'reviewee_id' => $driver->id,
            'score' => 5,
            'comment' => 'A very safe and punctual driver.',
        ]);

        $hub = $this->actingAs($passenger)->get(route('ratings.index'));
        $hub->assertDontSee('href="'.route('ratings.reviews').'"', false)
            ->assertSee('href="'.route('ratings.people').'"', false);

        $this->actingAs($passenger)->get(route('ratings.people'))
            ->assertOk()
            ->assertSee('Driver Profiles & Reviews', false)
            ->assertSee($driver->name)
            ->assertSee('View Profile & Reviews', false)
            ->assertSee('href="'.route('ratings.received', $driver).'"', false);

        $this->actingAs($passenger)->get(route('ratings.received', $driver))
            ->assertOk()
            ->assertSee('Driver Profile & Reviews', false)
            ->assertSee($driver->name)
            ->assertSee('Rating breakdown')
            ->assertSee('5 star')
            ->assertSee('1 review')
            ->assertSee('A very safe and punctual driver.');
    }

    public function test_reviews_show_average_and_star_breakdown(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5]);

        $otherPassenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $otherBooking = Booking::create([
            'trip_id' => $booking->trip_id,
            'passenger_id' => $otherPassenger->id,
            'booking_status' => 'Accepted',
            'number_of_seats' => 1,
            'pickup_point' => 'Putrajaya Sentral',
        ]);
        Rating::create([
            'booking_id' => $otherBooking->id,
            'reviewer_id' => $otherPassenger->id,
            'reviewee_id' => $driver->id,
            'score' => 3,
        ]);

        $this->actingAs($passenger)
            ->get(route('ratings.reviews'))
            ->assertOk()
            ->assertSee('Rating breakdown', false)
            ->assertSee('4.0')
            ->assertSee('2 reviews')
            ->assertSee('5 star')
            ->assertSee('3 star');
    }

    public function test_connected_people_can_be_opened_from_the_rating_hub(): void
    {
        [$driver, $passenger] = $this->completedBooking();

        $this->actingAs($passenger)->get(route('ratings.people'))->assertOk()->assertSee($driver->name);
        $this->actingAs($driver)->get(route('ratings.people'))->assertOk()->assertSee($passenger->name);
    }

    public function test_driver_rating_module_reuses_driver_sidebar_without_activating_notifications(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);

        $response = $this->actingAs($driver)->get(route('ratings.index'));

        $response->assertOk()->assertSee('GreenPool')->assertSee('Ratings');
        $response->assertSeeInOrder(['<h1 class="truncate', 'Ratings', '</h1>'], false);
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.route('ratings.index').'"'));
        $this->assertStringContainsString('bg-green-50 text-[#2E7D32]', $response->getContent());
    }

    public function test_passenger_can_rate_driver_after_completed_accepted_booking(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $response = $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 5,
            'comment' => 'Friendly and on time.',
        ]);

        $rating = $passenger->ratingsGiven()->firstOrFail();
        $response->assertRedirect(route('ratings.submitted', $rating));

        $this->assertDatabaseHas('ratings', [
            'booking_id' => $booking->id,
            'reviewer_id' => $passenger->id,
            'reviewee_id' => $driver->id,
            'score' => 5,
        ]);
    }

    public function test_rating_form_shows_star_specific_quick_comments_and_total_amount(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->update(['number_of_seats' => 2]);
        $booking->trip->update(['price_per_passenger' => 12.50]);

        $this->actingAs($passenger)
            ->get(route('ratings.create', $booking))
            ->assertOk()
            ->assertSee('Quick feedback')
            ->assertSee('select one or more')
            ->assertSee('toggleFeedback(suggestion)', false)
            ->assertSee('Unsafe driving')
            ->assertSee('Smoke smell in vehicle')
            ->assertSee('Vehicle was dirty')
            ->assertSee('Average driving')
            ->assertSee('Excellent and safe driving')
            ->assertDontSee('Passenger was very late')
            ->assertSee('2 seat(s) × RM 12.50')
            ->assertSee('RM 25.00');
    }

    public function test_driver_can_rate_passenger_for_the_same_booking(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $response = $this->actingAs($driver)->post(route('ratings.store', $booking), ['score' => 4]);
        $rating = $driver->ratingsGiven()->firstOrFail();
        $response->assertRedirect(route('ratings.submitted', $rating));

        $this->assertDatabaseHas('ratings', ['reviewer_id' => $driver->id, 'reviewee_id' => $passenger->id]);
    }

    public function test_driver_rating_form_uses_passenger_specific_quick_feedback(): void
    {
        [$driver, , $booking] = $this->completedBooking();

        $this->actingAs($driver)
            ->get(route('ratings.create', $booking))
            ->assertOk()
            ->assertSee('Passenger was very late')
            ->assertSee('Average passenger')
            ->assertSee('Excellent and respectful passenger')
            ->assertDontSee('Unsafe driving')
            ->assertDontSee('Smoke smell in vehicle')
            ->assertDontSee('Vehicle was dirty');
    }

    public function test_rating_requires_a_completed_trip_and_cannot_be_duplicated(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->trip->update(['status' => 'Scheduled']);
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5])->assertStatus(422);

        $booking->trip->update(['status' => 'Completed']);
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5])->assertRedirect();
        $rating = $passenger->ratingsGiven()->where('booking_id', $booking->id)->firstOrFail();
        $this->actingAs($passenger)
            ->post(route('ratings.store', $booking), ['score' => 3])
            ->assertRedirect(route('ratings.submitted', $rating))
            ->assertSessionHas('success');
        $this->actingAs($passenger)
            ->get(route('ratings.create', $booking))
            ->assertRedirect(route('ratings.submitted', $rating))
            ->assertSessionHas('success');
        $this->assertSame(5, $rating->refresh()->score);
    }

    public function test_rating_submission_expires_seven_days_after_trip_completion(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->trip->update(['completed_at' => now()->subDays(8)]);

        $this->actingAs($passenger)->get(route('ratings.create', $booking))->assertStatus(410);
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5])->assertStatus(410);

        $this->actingAs($passenger)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertSee('Expired')
            ->assertDontSee('href="'.route('ratings.create', $booking).'"', false);

        $this->actingAs($passenger)
            ->get(route('passenger.home'))
            ->assertOk()
            ->assertDontSee('How was your ride?');

        $this->assertDatabaseMissing('ratings', [
            'booking_id' => $booking->id,
            'reviewer_id' => $passenger->id,
        ]);
    }

    public function test_rating_submission_notifies_the_reviewee_and_notification_can_be_opened(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5]);

        $notification = $driver->notifications()->firstOrFail();
        $this->assertSame(5, $notification->data['score']);
        $this->assertSame($passenger->name, $notification->data['reviewer_name']);
        $this->assertNull($notification->read_at);

        $this->actingAs($driver)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('New rating received')
            ->assertSee($passenger->name.' gave you a 5-star rating.')
            ->assertSee('Notifications, 1 unread');

        $this->actingAs($driver)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('ratings.received', $driver));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_rating_submission_is_broadcast_to_the_reviewee_in_real_time(): void
    {
        Event::fake([RatingReceived::class]);
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 5,
            'comment' => 'Excellent driver.',
        ]);

        Event::assertDispatched(RatingReceived::class, function (RatingReceived $event) use ($driver, $passenger): bool {
            $payload = $event->broadcastWith();

            return $event->rating->reviewer_id === $passenger->id
                && $event->rating->reviewee_id === $driver->id
                && (string) $event->broadcastOn() === 'private-driver.'.$driver->id
                && $payload['title'] === 'New rating received'
                && $payload['score'] === 5
                && $payload['url'] === route('ratings.received', $driver);
        });
    }

    public function test_submission_confirmation_page_shows_success_message_and_stars(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 4]);
        $rating = $passenger->ratingsGiven()->firstOrFail();

        $this->actingAs($passenger)
            ->get(route('ratings.submitted', $rating))
            ->assertOk()
            ->assertSee('Rating Submitted!')
            ->assertSee('Rating submitted successfully.')
            ->assertSee('4 out of 5 stars')
            ->assertSee('Done');

        $stranger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $this->actingAs($stranger)->get(route('ratings.submitted', $rating))->assertForbidden();
    }

    public function test_unrelated_user_cannot_rate_a_booking(): void
    {
        [, , $booking] = $this->completedBooking();
        $stranger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $this->actingAs($stranger)->post(route('ratings.store', $booking), ['score' => 5])->assertForbidden();
    }

    public function test_reviewer_can_open_and_update_own_rating(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 3,
            'comment' => 'Original feedback.',
        ]);
        $rating = $passenger->ratingsGiven()->firstOrFail();

        $this->actingAs($passenger)
            ->get(route('ratings.edit', $rating))
            ->assertOk()
            ->assertSee('Update Rating')
            ->assertSee('Original feedback.')
            ->assertSee('Smoke smell in vehicle')
            ->assertSee('Vehicle was dirty');

        $this->actingAs($passenger)
            ->patch(route('ratings.update', $rating), [
                'score' => 5,
                'comment' => 'Updated feedback.',
            ])
            ->assertRedirect(route('ratings.history'));

        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'reviewer_id' => $passenger->id,
            'reviewee_id' => $driver->id,
            'score' => 5,
            'comment' => 'Updated feedback.',
        ]);
    }

    public function test_rating_is_editable_for_seven_days_and_locked_afterwards(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 4,
            'comment' => 'Original feedback.',
        ]);
        $rating = $passenger->ratingsGiven()->firstOrFail();
        $rating->forceFill(['created_at' => now()->subDays(6)])->saveQuietly();

        $this->actingAs($passenger)
            ->get(route('ratings.edit', $rating))
            ->assertOk()
            ->assertSee('Update Rating');

        $rating->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

        $this->actingAs($passenger)
            ->get(route('ratings.history'))
            ->assertOk()
            ->assertSee('Locked')
            ->assertSee('7-day edit period ended')
            ->assertDontSee('href="'.route('ratings.edit', $rating).'"', false);

        $this->actingAs($passenger)
            ->get(route('ratings.edit', $rating))
            ->assertStatus(423);

        $this->actingAs($passenger)
            ->patch(route('ratings.update', $rating), [
                'score' => 1,
                'comment' => 'This must not be saved.',
            ])
            ->assertStatus(423);

        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'score' => 4,
            'comment' => 'Original feedback.',
        ]);
    }

    public function test_user_cannot_edit_or_update_another_users_rating(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 4]);
        $rating = $passenger->ratingsGiven()->firstOrFail();
        $stranger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($stranger)->get(route('ratings.edit', $rating))->assertForbidden();
        $this->actingAs($stranger)->patch(route('ratings.update', $rating), ['score' => 1])->assertForbidden();
        $this->assertDatabaseHas('ratings', ['id' => $rating->id, 'score' => 4]);
    }

    public function test_update_rating_validates_score_and_comment_length(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 4]);
        $rating = $passenger->ratingsGiven()->firstOrFail();

        $this->actingAs($passenger)
            ->from(route('ratings.edit', $rating))
            ->patch(route('ratings.update', $rating), ['score' => 6, 'comment' => str_repeat('a', 301)])
            ->assertRedirect(route('ratings.edit', $rating))
            ->assertSessionHasErrors(['score', 'comment']);

        $this->assertDatabaseHas('ratings', ['id' => $rating->id, 'score' => 4, 'comment' => null]);
    }

    public function test_rating_module_lists_completed_bookings_waiting_for_rating(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($passenger)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertSee($driver->name)
            ->assertSee(route('ratings.create', $booking), false);

        $this->actingAs($driver)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertSee($passenger->name)
            ->assertSee(route('ratings.create', $booking), false);
    }

    public function test_driver_can_see_every_unrated_passenger_from_the_same_completed_trip(): void
    {
        [$driver, $firstPassenger, $firstBooking] = $this->completedBooking();
        $secondPassenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $secondBooking = Booking::create([
            'trip_id' => $firstBooking->trip_id,
            'passenger_id' => $secondPassenger->id,
            'booking_status' => 'Accepted',
            'number_of_seats' => 1,
            'pickup_point' => 'TBS',
        ]);

        $this->actingAs($driver)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertSee($firstPassenger->name)
            ->assertSee($secondPassenger->name)
            ->assertSee(route('ratings.create', $firstBooking), false)
            ->assertSee(route('ratings.create', $secondBooking), false);

        $this->actingAs($driver)->post(route('ratings.store', $firstBooking), ['score' => 5]);
        $rating = $driver->ratingsGiven()->where('booking_id', $firstBooking->id)->firstOrFail();

        $this->actingAs($driver)
            ->get(route('ratings.submitted', $rating))
            ->assertOk()
            ->assertSee('Rate Next Passenger')
            ->assertSee(route('ratings.create', $secondBooking), false);

        $this->actingAs($driver)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertDontSee(route('ratings.create', $firstBooking), false)
            ->assertSee($secondPassenger->name)
            ->assertSee(route('ratings.create', $secondBooking), false);
    }

    public function test_passenger_dashboard_prompts_for_completed_unrated_trip(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($passenger)
            ->get(route('passenger.home'))
            ->assertOk()
            ->assertSee('Trip completed')
            ->assertSee('How was your ride?')
            ->assertSee($driver->name)
            ->assertSee('Rate Now')
            ->assertSee(route('ratings.create', $booking), false);
    }

    public function test_passenger_dashboard_stops_prompting_after_rating_submission(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5]);

        $this->actingAs($passenger)
            ->get(route('passenger.home'))
            ->assertOk()
            ->assertDontSee('How was your ride?')
            ->assertDontSee('Rate Now');
    }

    public function test_completed_trip_ratings_are_reflected_in_booking_and_profile_average_ratings(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 5,
            'comment' => 'Excellent driver.',
        ]);
        $this->actingAs($driver)->post(route('ratings.store', $booking), [
            'score' => 3,
            'comment' => 'Good passenger.',
        ]);

        $driver->trips()->create([
            'vehicle_id' => $booking->trip->vehicle_id,
            'departure_location' => 'Cyberjaya',
            'destination' => 'Kuala Lumpur',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 12,
            'status' => 'Scheduled',
        ]);

        $this->actingAs($passenger)
            ->get(route('passenger.bookings.history'))
            ->assertOk()
            ->assertSee($driver->name)
            ->assertSee('5.0');

        $this->actingAs($driver)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('5.0')
            ->assertSee('(1 review)');

        $this->actingAs($passenger)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('3.0')
            ->assertSee('(1 review)');
    }

    public function test_driver_is_redirected_to_rate_passenger_after_completing_trip(): void
    {
        [$driver, , $booking] = $this->completedBooking();
        $booking->trip->update(['status' => 'In Progress', 'completed_at' => null, 'started_at' => now()->subHour()]);

        $this->actingAs($driver)
            ->patch(route('driver.trips.complete', $booking->trip))
            ->assertRedirect(route('ratings.create', $booking))
            ->assertSessionHas('success', 'Journey completed. Please rate your passenger.');

        $this->assertDatabaseHas('trips', ['trip_id' => $booking->trip_id, 'status' => 'Completed']);
    }

    public function test_submitted_booking_is_removed_from_pending_ratings(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5]);

        $this->actingAs($passenger)
            ->get(route('ratings.pending'))
            ->assertOk()
            ->assertDontSee(route('ratings.create', $booking), false);
    }

    private function completedBooking(): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Kuala Lumpur', 'destination' => 'Putrajaya',
            'departure_at' => now()->subHour(), 'available_seats' => 3,
            'price_per_passenger' => 10, 'status' => 'Completed', 'completed_at' => now(),
        ]);
        $booking = Booking::create([
            'trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id,
            'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'KL Sentral',
        ]);

        return [$driver, $passenger, $booking->load('trip')];
    }
}
