<?php

namespace Tests\Feature\Driver;

use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripLifecycleRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google_maps.key', 'test-key');
    }

    public function test_departed_scheduled_trip_without_bookings_is_cancelled_idempotently(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute()]);

        Artisan::call('trips:cancel-unbooked-departed');
        $trip->refresh();
        $cancelledAt = $trip->cancelled_at;
        $this->assertSame('Cancelled', $trip->status);
        $this->assertNotNull($cancelledAt);
        $this->assertNull($trip->started_at);
        $this->assertNull($trip->completed_at);

        Artisan::call('trips:cancel-unbooked-departed');
        $this->assertTrue($cancelledAt->equalTo($trip->refresh()->cancelled_at));
    }

    public function test_departed_scheduled_trip_with_a_booking_is_not_cancelled(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute()]);
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $this->passenger()->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);

        Artisan::call('trips:cancel-unbooked-departed');
        $this->assertSame('Scheduled', $trip->refresh()->status);
    }

    public function test_driver_cannot_create_a_second_scheduled_trip_at_the_same_time_but_other_drivers_and_cancelled_trips_do_not_conflict(): void
    {
        $driver = $this->driver();
        $departureAt = now()->addDay()->setTime(10, 0);
        $this->trip($driver, ['departure_at' => $departureAt]);
        $vehicle = $this->vehicle($driver);

        $this->actingAs($driver)->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))->assertSessionHasErrors('departure_time');

        $other = $this->driver();
        $this->fakeGoogle();
        $this->actingAs($other)->post(route('driver.trips.store'), $this->requestData($this->vehicle($other), $departureAt))->assertSessionHasNoErrors();

        $this->trip($driver, ['departure_at' => $departureAt->copy()->addHour(), 'status' => 'Cancelled']);
        $this->fakeGoogle();
        $this->actingAs($driver)->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt->copy()->addHour()))->assertSessionHasNoErrors();
    }

    public function test_trip_edit_rejects_another_scheduled_trip_time_but_allows_its_own_time(): void
    {
        $driver = $this->driver();
        $first = $this->trip($driver, ['departure_at' => now()->addDay()->setTime(9, 0)]);
        $second = $this->trip($driver, ['departure_at' => now()->addDay()->setTime(10, 0)]);
        $vehicle = $this->vehicle($driver);

        $this->actingAs($driver)->patch(route('driver.trips.update', $second), $this->requestData($vehicle, $first->departure_at))->assertSessionHasErrors('departure_time');
        $this->actingAs($driver)->patch(route('driver.trips.update', $second), [
            ...$this->requestData($vehicle, $second->departure_at),
            'departure_place_id' => null,
            'destination_place_id' => null,
        ])->assertSessionHasNoErrors();
    }

    public function test_start_trip_lifecycle_remains_unchanged(): void
    {
        $this->fakeGoogle();
        $driver = $this->driver();
        $inProgress = $this->trip($driver, ['status' => 'In Progress', 'started_at' => now()]);
        $next = $this->trip($driver);
        $this->actingAs($driver)->patch(route('driver.trips.start', $next))->assertStatus(422);

        $this->actingAs($driver)->patch(route('driver.trips.complete', $inProgress))->assertRedirect();
        $this->actingAs($driver)->patch(route('driver.trips.start', $next))->assertSessionHasNoErrors();

        $cancelled = $this->trip($driver, ['status' => 'Cancelled']);
        $this->actingAs($driver)->patch(route('driver.trips.start', $cancelled))->assertStatus(422);
        $completed = $this->trip($driver, ['status' => 'Completed', 'completed_at' => now()]);
        $this->actingAs($driver)->patch(route('driver.trips.start', $completed))->assertStatus(422);
    }

    public function test_start_trip_notifies_accepted_passengers_in_real_time(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = $this->driver();
        $trip = $this->trip($driver);
        $acceptedPassenger = $this->passenger();
        $pendingPassenger = $this->passenger();
        $acceptedBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $acceptedPassenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate', 'pickup_place_id' => 'main-gate', 'pickup_latitude' => 3.1390, 'pickup_longitude' => 101.6869]);
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $pendingPassenger->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Library']);
        $this->fakeGoogle([0]);

        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip))
            ->assertRedirect(route('driver.trips.journey'));

        $this->assertSame('In Progress', $trip->refresh()->status);
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->booking->is($acceptedBooking)
                && $event->booking->passenger_id === $acceptedPassenger->id
                && $event->booking->trip->status === 'In Progress'
        );
        Event::assertDispatchedTimes(BookingStatusUpdated::class, 1);
    }

    private function fakeGoogle(array $optimizedIndexes = []): void
    {
        $route = ['distanceMeters' => 1000, 'duration' => '60s'];
        if ($optimizedIndexes !== []) {
            $route['optimizedIntermediateWaypointIndex'] = $optimizedIndexes;
        }

        Http::fake([
            'https://places.googleapis.com/v1/places/departure' => Http::response($this->place('departure', 3.139, 101.6869)),
            'https://places.googleapis.com/v1/places/destination' => Http::response($this->place('destination', 3.1578, 101.7123)),
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [$route]]),
        ]);
    }

    private function place(string $id, float $latitude, float $longitude): array
    {
        return ['id' => $id, 'displayName' => ['text' => ucfirst($id)], 'formattedAddress' => ucfirst($id).' Malaysia', 'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']], 'location' => ['latitude' => $latitude, 'longitude' => $longitude]];
    }

    private function requestData(Vehicle $vehicle, Carbon $departureAt): array
    {
        return ['vehicle_id' => $vehicle->vehicle_id, 'departure_place_id' => 'departure', 'destination_place_id' => 'destination', 'departure_date' => $departureAt->toDateString(), 'departure_time' => $departureAt->format('H:i'), 'available_seats' => 2, 'price_per_passenger' => 5];
    }

    private function driver(): User
    {
        return User::factory()->create(['role' => 'driver']);
    }

    private function passenger(): User
    {
        return User::factory()->create(['role' => 'passenger']);
    }

    private function vehicle(User $driver): Vehicle
    {
        return Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active', 'seat_capacity' => 4]);
    }

    private function trip(User $driver, array $overrides = []): Trip
    {
        $vehicle = $this->vehicle($driver);

        return $driver->trips()->create([...['vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'Departure', 'destination' => 'Destination', 'departure_latitude' => 3.1, 'departure_longitude' => 101.7, 'destination_latitude' => 3.2, 'destination_longitude' => 101.8, 'departure_at' => now()->addDay(), 'available_seats' => 2, 'status' => 'Scheduled'], ...$overrides]);
    }
}
