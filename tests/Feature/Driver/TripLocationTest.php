<?php

namespace Tests\Feature\Driver;

use App\Events\TripLocationUpdated;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TripLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_owner_can_store_and_broadcast_a_live_location_for_an_active_trip(): void
    {
        [$driver, $trip] = $this->trip();
        Event::fake([TripLocationUpdated::class]);

        $this->actingAs($driver)->postJson(route('driver.trips.location.store', $trip), $this->location())
            ->assertCreated()
            ->assertJsonPath('stored', true);

        $this->assertDatabaseHas('trip_locations', ['trip_id' => $trip->trip_id, 'driver_id' => $driver->id, 'latitude' => 3.1390000, 'longitude' => 101.6869000]);
        Event::assertDispatched(TripLocationUpdated::class, fn (TripLocationUpdated $event) => (string) $event->broadcastOn() === 'private-trip.'.$trip->trip_id);
    }

    public function test_passenger_and_another_driver_cannot_submit_a_driver_location(): void
    {
        [$driver, $trip] = $this->trip();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $otherDriver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($passenger)->postJson(route('driver.trips.location.store', $trip), $this->location())->assertForbidden();
        $this->actingAs($otherDriver)->postJson(route('driver.trips.location.store', $trip), $this->location())->assertForbidden();
        $this->assertDatabaseCount('trip_locations', 0);
    }

    public function test_location_updates_require_an_in_progress_trip_and_valid_coordinates(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'Scheduled', 'started_at' => null]);
        $this->actingAs($driver)->postJson(route('driver.trips.location.store', $trip), $this->location())->assertStatus(422);

        $trip->update(['status' => 'In Progress', 'started_at' => now()]);
        $this->actingAs($driver)->postJson(route('driver.trips.location.store', $trip), [...$this->location(), 'latitude' => 91])->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->assertDatabaseCount('trip_locations', 0);
    }

    public function test_insignificant_rapid_updates_do_not_create_another_location_or_broadcast(): void
    {
        [$driver, $trip] = $this->trip();
        Event::fake([TripLocationUpdated::class]);

        $this->actingAs($driver)->postJson(route('driver.trips.location.store', $trip), $this->location())->assertCreated();
        $this->actingAs($driver)->postJson(route('driver.trips.location.store', $trip), [...$this->location(), 'longitude' => 101.68691])->assertOk()->assertJsonPath('stored', false);

        $this->assertDatabaseCount('trip_locations', 1);
        Event::assertDispatched(TripLocationUpdated::class, 1);
    }

    private function trip(array $overrides = []): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create([...[
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Suria KLCC',
            'destination' => 'KLIA',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 12,
            'status' => 'In Progress',
            'started_at' => now(),
        ], ...$overrides]);

        return [$driver, $trip];
    }

    private function booking(Trip $trip, User $passenger): Booking
    {
        return Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Bukit Bintang']);
    }

    private function location(): array
    {
        return ['latitude' => 3.139, 'longitude' => 101.6869, 'accuracy_meters' => 12, 'heading' => 90, 'speed_mps' => 8];
    }
}
