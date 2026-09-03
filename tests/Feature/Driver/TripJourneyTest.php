<?php

namespace Tests\Feature\Driver;

use App\Models\Booking;
use App\Events\BookingStatusUpdated;
use App\Models\Emergency;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TripJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google_maps.key', 'test-key');
        config()->set('services.google_maps.browser_key', null);
    }

    public function test_active_journey_displays_timeline_pickups_avatar_and_estimates(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, [
            'estimated_distance_km' => 25.4,
            'estimated_duration_seconds' => 2100,
            'started_at' => now()->setTime(10, 10),
        ]);
        $pickedUp = $this->booking($trip, ['picked_up_at' => now()->subMinutes(5)]);
        $next = $this->booking($trip, ['passenger_id' => $this->passenger(['name' => 'Sarah Rider', 'photo' => 'profile-photos/sarah.jpg'])->id, 'pickup_point' => 'KL Sentral']);

        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('Active Journey')
            ->assertSee($pickedUp->passenger->name)
            ->assertSee('Picked up')
            ->assertSee('Sarah Rider')
            ->assertSee('KL Sentral')
            ->assertSee('Estimated Arrival')
            ->assertSee('25.40 km')
            ->assertSee('35 min')
            ->assertSee('Report Emergency')
            ->assertSee('Use this only for a genuine emergency')
            ->assertSee('Safety assistance')
            ->assertSee('tel:999', false)
            ->assertSee('Call emergency contact')
            ->assertSee(route('trips.emergencies.store', $trip), false)
            ->assertSee('<option value="safety_risk">', false);
    }

    public function test_active_journey_shows_the_latest_live_driver_location_state(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver);
        TripLocation::create(['trip_id' => $trip->trip_id, 'driver_id' => $driver->id, 'latitude' => 3.139, 'longitude' => 101.6869, 'recorded_at' => now()]);

        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('Live driver location is shown when available.')
            ->assertSee('Live location updated');
    }

    public function test_live_map_uses_pickup_sequence_statuses_and_static_focus_points(): void
    {
        config()->set('services.google_maps.browser_key', 'browser-key');
        $driver = $this->driver();
        $trip = $this->trip($driver);
        $this->booking($trip, ['pickup_sequence' => 2, 'picked_up_at' => now()->subMinute()]);
        $this->booking($trip, ['pickup_sequence' => 1]);

        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('data-focus-points', false)
            ->assertSee('"sequence":2', false)
            ->assertSee('"status":"Picked Up"', false)
            ->assertSee('"status":"Next Pickup"', false);
    }

    public function test_active_journey_shows_the_current_emergency_report_and_location_link(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver);
        $emergency = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $driver->id,
            'role' => 'driver',
            'issue_type' => 'traffic_delay',
            'latitude' => $trip->departure_latitude,
            'longitude' => $trip->departure_longitude,
            'status' => 'Active',
            'triggered_at' => now(),
        ]);

        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('Report Emergency')
            ->assertSee('Trip emergency reports')
            ->assertSee('Traffic Delay')
            ->assertSee('Call 999')
            ->assertSee('Reported by')
            ->assertSee('Open location in Maps');

        $this->assertSame('Active', $emergency->fresh()->status);
    }

    public function test_acknowledged_emergency_shows_its_status_and_resolution_action(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver);
        Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $driver->id,
            'role' => 'driver',
            'latitude' => $trip->departure_latitude,
            'longitude' => $trip->departure_longitude,
            'issue_type' => 'road_hazard',
            'status' => 'Acknowledged',
            'triggered_at' => now()->subMinutes(10),
            'acknowledged_at' => now(),
            'acknowledged_by' => $driver->id,
        ]);

        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('Report Emergency')
            ->assertSee('Trip emergency reports')
            ->assertSee('Acknowledged by')
            ->assertSee('Mark as resolved');
    }

    public function test_picking_up_a_booking_advances_the_timeline_without_changing_trip_lifecycle_times(): void
    {
        Event::fake([BookingStatusUpdated::class]);
        $driver = $this->driver();
        $startedAt = now()->subMinutes(10);
        $trip = $this->trip($driver, ['started_at' => $startedAt]);
        $first = $this->booking($trip, ['pickup_point' => 'Bukit Bintang']);
        $second = $this->booking($trip, ['pickup_point' => 'KL Sentral']);
        Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [['distanceMeters' => 1200, 'duration' => '90s']]])]);

        $this->actingAs($driver)->patch(route('driver.trips.bookings.pickup', [$trip, $first]))
            ->assertRedirect(route('driver.trips.journey'));

        $this->assertNotNull($first->refresh()->picked_up_at);
        $this->assertSame('In Progress', $trip->refresh()->status);
        Event::assertDispatched(BookingStatusUpdated::class, function (BookingStatusUpdated $event) use ($first) {
            $payload = $event->broadcastWith();
            return $event->pickedUpBookingId === $first->id
                && collect($payload['pickup_progress'])->firstWhere('booking_id', $first->id)['picked_up_at'] !== null;
        });
        $this->assertSame($startedAt->format('Y-m-d H:i:s'), $trip->started_at->format('Y-m-d H:i:s'));
        $this->assertNull($trip->completed_at);
        $this->actingAs($driver)->get(route('driver.trips.journey'))
            ->assertSee('Bukit Bintang')
            ->assertSee('Picked up')
            ->assertSee('KL Sentral')
            ->assertSee('Next pickup');
        $this->assertNull($second->refresh()->picked_up_at);
        $this->assertNotNull($trip->refresh()->estimated_arrival_at);
        Http::assertSentCount(1);
        $this->actingAs($driver)->get(route('driver.trips.journey'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_start_optimizes_and_persists_pickup_sequence_with_one_routes_request(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        $first = $this->booking($trip, ['pickup_point' => 'First pickup']);
        $second = $this->booking($trip, ['pickup_point' => 'Second pickup', 'pickup_latitude' => 3.1390, 'pickup_longitude' => 101.6869]);
        Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [[
            'distanceMeters' => 35000,
            'duration' => '2400s',
            'optimizedIntermediateWaypointIndex' => [1, 0],
        ]]])]);

        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertRedirect(route('driver.trips.journey'));

        $this->assertSame(2, $first->refresh()->pickup_sequence);
        $this->assertSame(1, $second->refresh()->pickup_sequence);
        $this->assertSame('In Progress', $trip->refresh()->status);
        $this->assertSame(35.0, (float) $trip->estimated_distance_km);
        $this->assertSame(2400, $trip->estimated_duration_seconds);
        $this->assertNotNull($trip->estimated_arrival_at);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
                && $request['optimizeWaypointOrder'] === true
                && count($request['intermediates']) === 2
                && str_contains($request->header('X-Goog-FieldMask')[0], 'routes.optimizedIntermediateWaypointIndex');
        });
        Http::assertSentCount(1);
    }

    public function test_start_without_accepted_bookings_does_not_require_an_optimized_waypoint_order(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [[
            'distanceMeters' => 35000,
            'duration' => '2400s',
        ]]])]);

        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip))
            ->assertRedirect(route('driver.trips.journey'));

        $this->assertSame('In Progress', $trip->refresh()->status);
        $this->assertSame(35.0, (float) $trip->estimated_distance_km);
        Http::assertSent(fn ($request) => ! isset($request['intermediates']) && ! isset($request['optimizeWaypointOrder']));
    }

    public function test_expired_scheduled_trip_cannot_be_started_from_journey(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, [
            'status' => 'Scheduled',
            'started_at' => null,
            'departure_location' => 'Batu Pahat',
            'destination' => 'Johor Bahru',
            'departure_at' => now()->subDay(),
        ]);

        $this->booking($trip);
        Http::fake();

        $this->actingAs($driver)
            ->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSee('Expired')
            ->assertSee('data-journey-expiry-card', false)
            ->assertSee('data-journey-expiry-actions', false)
            ->assertSee('The scheduled departure time has passed. Edit this trip to choose a future time, or cancel it.')
            ->assertSee('Edit Trip')
            ->assertSee('Cancel Trip');

        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip))
            ->assertStatus(422);

        $this->assertSame('Scheduled', $trip->refresh()->status);
        $this->assertNull($trip->started_at);
        Http::assertNothingSent();
    }

    public function test_journey_lists_expired_scheduled_trips_after_upcoming_trips(): void
    {
        $driver = $this->driver();
        $expiredTrip = $this->trip($driver, [
            'status' => 'Scheduled',
            'started_at' => null,
            'destination' => 'Expired Johor Trip',
            'departure_at' => now()->subDay(),
        ]);
        $upcomingTrip = $this->trip($driver, [
            'status' => 'Scheduled',
            'started_at' => null,
            'destination' => 'Upcoming KL Trip',
            'departure_at' => now()->addHour(),
        ]);

        $this->actingAs($driver)
            ->get(route('driver.trips.journey'))
            ->assertOk()
            ->assertSeeInOrder([$upcomingTrip->destination, $expiredTrip->destination]);
    }

    public function test_passenger_booking_persists_resolved_pickup_coordinates_and_place_id_without_routes_call(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        $passenger = $this->passenger();
        Http::fake([
            'https://places.googleapis.com/v1/places/pickup-place' => Http::response([
                'id' => 'pickup-place', 'displayName' => ['text' => 'Mid Valley Megamall'], 'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']], 'location' => ['latitude' => 3.1185, 'longitude' => 101.6770],
            ]),
        ]);

        $this->actingAs($passenger)->post(route('passenger.bookings.store'), [
            'trip_id' => $trip->trip_id, 'pickup_point' => 'Mid Valley', 'pickup_place_id' => 'pickup-place', 'number_of_seats' => 1,
        ])->assertRedirect(route('passenger.bookings.history'));

        $this->assertDatabaseHas('bookings', ['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'pickup_place_id' => 'pickup-place', 'pickup_latitude' => 3.1185, 'pickup_longitude' => 101.6770]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'routes.googleapis.com'));
    }

    public function test_start_rejects_accepted_bookings_without_persisted_pickup_coordinates_before_calling_routes(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        $this->booking($trip, ['pickup_latitude' => null, 'pickup_longitude' => null]);
        Http::fake();

        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasErrors('booking');

        $this->assertSame('Scheduled', $trip->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_routes_failure_does_not_start_the_trip_or_persist_pickup_sequence(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        $booking = $this->booking($trip);
        Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasErrors('destination');

        $this->assertSame('Scheduled', $trip->refresh()->status);
        $this->assertNull($trip->started_at);
        $this->assertNull($booking->refresh()->pickup_sequence);
    }

    public function test_trip_history_shows_accepted_passenger_seats_and_income(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Completed', 'completed_at' => now(), 'price_per_passenger' => 12.50]);
        $this->booking($trip, ['number_of_seats' => 2]);
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $this->passenger()->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Pending pickup']);

        $this->actingAs($driver)->get(route('driver.trips.history'))
            ->assertOk()
            ->assertSee('RM 25.00');
    }

    public function test_booking_cannot_be_picked_up_twice_or_when_trip_is_not_active(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'Scheduled', 'started_at' => null]);
        $booking = $this->booking($trip);

        $this->actingAs($driver)->patch(route('driver.trips.bookings.pickup', [$trip, $booking]))->assertStatus(422);

        $trip->update(['status' => 'Completed', 'completed_at' => now()]);
        $this->actingAs($driver)->patch(route('driver.trips.bookings.pickup', [$trip, $booking]))->assertStatus(422);

        $trip->update(['status' => 'In Progress', 'completed_at' => null]);
        $booking->update(['picked_up_at' => now()]);
        $this->actingAs($driver)->patch(route('driver.trips.bookings.pickup', [$trip, $booking]))->assertStatus(422);
    }

    public function test_driver_cannot_pick_up_a_booking_from_another_trip(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver);
        $otherTrip = $this->trip($this->driver());
        $otherBooking = $this->booking($otherTrip);

        $this->actingAs($driver)->patch(route('driver.trips.bookings.pickup', [$trip, $otherBooking]))->assertNotFound();
        $this->assertNull($otherBooking->refresh()->picked_up_at);
    }

    private function driver(): User
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $driver->driverLicence()->create([
            'image_path' => 'driver-licences/test.jpg',
            'holder_name' => $driver->name,
            'identity_no' => '991109040290',
            'licence_class' => 'D',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYears(5),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);

        return $driver;
    }

    private function passenger(array $overrides = []): User
    {
        return User::factory()->create(['role' => 'passenger', ...$overrides]);
    }

    private function trip(User $driver, array $overrides = []): Trip
    {
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active', 'seat_capacity' => 4]);

        return $driver->trips()->create([...[
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Suria KLCC',
            'destination' => 'KLIA',
            'departure_latitude' => 3.1578,
            'departure_longitude' => 101.7123,
            'destination_latitude' => 2.7456,
            'destination_longitude' => 101.7072,
            'departure_at' => now()->addDay(),
            'available_seats' => 2,
            'status' => 'In Progress',
            'started_at' => now()->subMinutes(10),
        ], ...$overrides]);
    }

    private function booking(Trip $trip, array $overrides = []): Booking
    {
        return Booking::create([...[
            'trip_id' => $trip->trip_id,
            'passenger_id' => $this->passenger()->id,
            'booking_status' => 'Accepted',
            'number_of_seats' => 1,
            'pickup_point' => 'Bukit Bintang',
            'pickup_place_id' => 'pickup-'.uniqid(),
            'pickup_latitude' => 3.1466,
            'pickup_longitude' => 101.7109,
        ], ...$overrides]);
    }
}
