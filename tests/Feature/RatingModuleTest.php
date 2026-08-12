<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_dashboard_ratings_navigation_links_to_rating_history(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $response = $this->actingAs($passenger)->get(route('passenger.home'));
        $response->assertOk()->assertSee('href="'.route('ratings.index').'"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('ratings.index').'"'));
        $this->assertLessThan(strpos($response->getContent(), 'href="'.route('profile.edit').'"'), strpos($response->getContent(), 'href="'.route('ratings.index').'"'));
    }

    public function test_passenger_booking_layout_ratings_navigation_links_to_rating_hub(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $response = $this->actingAs($passenger)->get(route('passenger.bookings.history'));
        $response->assertOk()->assertSee('href="'.route('ratings.index').'"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('ratings.index').'"'));
    }

    public function test_ratings_sidebar_opens_the_rating_module_hub(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($passenger)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Driver Profile')
            ->assertSee('Rate a Driver')
            ->assertSee('Driver Reviews')
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
            ->assertSeeInOrder(['Rate a Driver', 'Rating History', 'Driver Reviews', 'Driver Profile']);
    }

    public function test_reviews_and_profiles_are_separate_rating_features(): void
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
        $hub->assertSee('href="'.route('ratings.reviews').'"', false)
            ->assertSee('href="'.route('ratings.people').'"', false);

        $this->actingAs($passenger)->get(route('ratings.reviews'))
            ->assertOk()->assertSee('Driver Reviews')
            ->assertSee('A very safe and punctual driver.')
            ->assertSee('reviewed by '.$passenger->name);

        $this->actingAs($passenger)->get(route('ratings.people'))
            ->assertOk()->assertSee('Driver Profiles')->assertSee($driver->name)
            ->assertDontSee('A very safe and punctual driver.');
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
        $response->assertSee('href="#"', false);
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
            ->assertDontSee('Unsafe driving');
    }

    public function test_rating_requires_a_completed_trip_and_cannot_be_duplicated(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->trip->update(['status' => 'Scheduled']);
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5])->assertStatus(422);

        $booking->trip->update(['status' => 'Completed']);
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 5])->assertRedirect();
        $this->actingAs($passenger)->post(route('ratings.store', $booking), ['score' => 3])->assertStatus(409);
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
            ->assertSee('Original feedback.');

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
