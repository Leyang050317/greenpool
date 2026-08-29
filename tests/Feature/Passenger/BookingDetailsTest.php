<?php

namespace Tests\Feature\Passenger;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_view_their_own_booking_details(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($passenger, $trip, 'Accepted');

        $this->actingAs($passenger)->get(route('passenger.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking Details')
            ->assertSee($trip->departure_location)
            ->assertSee($booking->pickup_point)
            ->assertSee('Upcoming trip')
            ->assertDontSee('Trip map');
    }

    public function test_scheduled_booking_after_the_departure_minute_displays_the_derived_expired_status(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $trip->update(['departure_at' => now()->subMinute()]);
        $booking = $this->booking($passenger, $trip, 'Accepted');

        $this->actingAs($passenger)->get(route('passenger.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Expired');
        $this->actingAs($passenger)->get(route('passenger.bookings.history'))
            ->assertOk()
            ->assertSee('Expired');
        $this->assertSame('Scheduled', $trip->refresh()->status);
    }

    public function test_my_trips_keeps_the_full_departure_minute_scheduled_before_expiring(): void
    {
        $departureAt = Carbon::parse('2026-08-30 01:28:00');
        Carbon::setTestNow($departureAt);
        [$passenger, $trip] = $this->passengerAndTrip();
        $trip->update(['departure_at' => $departureAt]);
        $this->booking($passenger, $trip, 'Accepted');

        foreach ([0, 30, 59] as $second) {
            Carbon::setTestNow($departureAt->copy()->addSeconds($second));
            $this->actingAs($passenger)->get(route('passenger.bookings.history'))
                ->assertOk()->assertSee('Scheduled')->assertDontSee('Expired');
        }

        Carbon::setTestNow($departureAt->copy()->addMinute());
        $this->actingAs($passenger)->get(route('passenger.bookings.history'))
            ->assertOk()->assertSee('Expired');
        Carbon::setTestNow();
    }

    public function test_passenger_cannot_view_another_passengers_booking(): void
    {
        [$owner, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($owner, $trip);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($otherPassenger)->get(route('passenger.bookings.show', $booking))->assertForbidden();
    }

    public function test_guest_cannot_view_booking_details(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($passenger, $trip);

        $this->get(route('passenger.bookings.show', $booking))->assertRedirect(route('login'));
    }

    public function test_dashboard_and_history_link_to_the_booking_details_page(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($passenger, $trip, 'Accepted');

        $this->actingAs($passenger)->get(route('passenger.home'))
            ->assertOk()
            ->assertSee(route('passenger.bookings.show', $booking), false);
        $this->actingAs($passenger)->get(route('passenger.bookings.history'))
            ->assertOk()
            ->assertSee(route('passenger.bookings.show', $booking), false)
            ->assertDontSee('View Details');
    }

    public function test_details_adapt_to_pending_in_progress_completed_cancelled_and_rejected_states(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $pending = $this->booking($passenger, $trip, 'Pending');
        $this->actingAs($passenger)->get(route('passenger.bookings.show', $pending))->assertOk()->assertSee('Cancel booking request')->assertDontSee('Trip map');

        $trip->update(['status' => 'In Progress', 'started_at' => now()]);
        $pending->update(['booking_status' => 'Accepted']);
        $active = $pending;
        $this->actingAs($passenger)->get(route('passenger.bookings.show', $active))->assertOk()->assertSee('Trip in progress')->assertSee('Journey progress')->assertSee('Trip map');

        $trip->update(['status' => 'Completed', 'completed_at' => now()]);
        $this->actingAs($passenger)->get(route('passenger.bookings.show', $active))->assertOk()->assertSee('Completed trip')->assertDontSee('Trip map');

        $active->update(['booking_status' => 'Cancelled']);
        $this->actingAs($passenger)->get(route('passenger.bookings.show', $active))->assertOk()->assertSee('Cancelled')->assertDontSee('Trip map');

        $active->update(['booking_status' => 'Rejected']);
        $this->actingAs($passenger)->get(route('passenger.bookings.show', $active))->assertOk()->assertSee('Rejected')->assertDontSee('Trip map');
    }

    public function test_accepted_passenger_sees_the_latest_live_driver_location_only_during_an_active_trip(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($passenger, $trip, 'Accepted');
        config(['services.google_maps.browser_key' => 'test-browser-key']);
        $trip->update([
            'status' => 'In Progress',
            'started_at' => now(),
            'departure_latitude' => 3.1579,
            'departure_longitude' => 101.7123,
            'destination_latitude' => 2.7456,
            'destination_longitude' => 101.7072,
        ]);
        TripLocation::create(['trip_id' => $trip->trip_id, 'driver_id' => $trip->user_id, 'latitude' => 3.139, 'longitude' => 101.6869, 'recorded_at' => now()]);

        $this->actingAs($passenger)->get(route('passenger.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Live driver location is active.')
            ->assertSee('Live driver location is shown when available.')
            ->assertSee('Live location updated')
            ->assertSee('data-trip-static-map', false)
            ->assertSee('data-live-tracking="true"', false)
            ->assertSee('data-trip-id="'.$trip->trip_id.'"', false);
    }

    public function test_booking_status_notification_links_to_its_booking_details(): void
    {
        [$passenger, $trip] = $this->passengerAndTrip();
        $booking = $this->booking($passenger, $trip, 'Accepted');

        $passenger->notify(new BookingStatusNotification($booking, 'Accepted'));

        $this->assertSame(route('passenger.bookings.show', $booking), $passenger->fresh()->notifications()->first()->data['url']);
    }

    private function passengerAndTrip(): array
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Suria KLCC',
            'destination' => 'KLIA',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 12,
            'status' => 'Scheduled',
        ]);

        return [$passenger, $trip];
    }

    private function booking(User $passenger, Trip $trip, string $status = 'Pending'): Booking
    {
        return Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => $status,
            'number_of_seats' => 1,
            'pickup_point' => 'Bukit Bintang',
        ]);
    }
}
