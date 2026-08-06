<?php

namespace Tests\Feature;

use App\Models\Booking;
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
            ->assertSee('M21 15a4 4 0 0 1-4 4H8l-5 3V7', false)
            ->assertSee('M12 7v5l3 2', false)
            ->assertSee(route('ratings.history'), false);
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

        $this->actingAs($passenger)->post(route('ratings.store', $booking), [
            'score' => 5,
            'comment' => 'Friendly and on time.',
        ])->assertRedirect(route('ratings.index'));

        $this->assertDatabaseHas('ratings', [
            'booking_id' => $booking->id,
            'reviewer_id' => $passenger->id,
            'reviewee_id' => $driver->id,
            'score' => 5,
        ]);
    }

    public function test_driver_can_rate_passenger_for_the_same_booking(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();

        $this->actingAs($driver)->post(route('ratings.store', $booking), ['score' => 4])
            ->assertRedirect(route('ratings.index'));

        $this->assertDatabaseHas('ratings', ['reviewer_id' => $driver->id, 'reviewee_id' => $passenger->id]);
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

    public function test_unrelated_user_cannot_rate_a_booking(): void
    {
        [, , $booking] = $this->completedBooking();
        $stranger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $this->actingAs($stranger)->post(route('ratings.store', $booking), ['score' => 5])->assertForbidden();
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
