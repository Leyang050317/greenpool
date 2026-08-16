<?php

namespace Tests\Feature\Driver;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Routing\TripDistanceService;
use App\Services\Routing\TripLocationService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripGoogleMapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google_maps.key', 'test-key');
    }

    public function test_autocomplete_returns_google_place_suggestions(): void
    {
        Http::fake(['https://places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [['placePrediction' => ['placeId' => 'place-kl', 'text' => ['text' => 'Kuala Lumpur, Malaysia']]]],
        ])]);

        $driver = User::factory()->create(['role' => 'driver']);
        $this->actingAs($driver)->getJson(route('driver.trips.locations.autocomplete', ['input' => 'Kuala']))
            ->assertOk()->assertJsonPath('data.0.place_id', 'place-kl');
    }

    public function test_non_malaysian_place_and_invalid_coordinates_are_rejected(): void
    {
        Http::fake(['https://places.googleapis.com/v1/places/outside' => Http::response($this->place('outside', 'SG', 1.3, 103.8))]);
        try {
            app(TripLocationService::class)->resolve('outside', 'departure_place_id');
            $this->fail('A non-Malaysian place should be rejected.');
        } catch (TripRoutingException $exception) {
            $this->assertSame('The selected location must be in Malaysia.', $exception->getMessage());
        }

        Http::fake(['https://places.googleapis.com/v1/places/invalid-coordinates' => Http::response($this->place('invalid-coordinates', 'MY', 99, 101.7))]);
        $this->expectException(TripRoutingException::class);
        app(TripLocationService::class)->resolve('invalid-coordinates', 'departure_place_id');
    }

    public function test_missing_or_invalid_place_id_is_rejected_before_a_trip_is_saved(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->post(route('driver.trips.store'), $this->tripData($vehicle, ['departure_place_id' => '']))
            ->assertSessionHasErrors('departure_place_id');

        Http::fake(['https://places.googleapis.com/v1/places/missing' => Http::response([], 404)]);
        $this->actingAs($driver)->post(route('driver.trips.store'), $this->tripData($vehicle, ['departure_place_id' => 'missing']))
            ->assertSessionHasErrors('departure_place_id');
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_routes_failure_and_successful_distance_duration_parsing(): void
    {
        $service = app(TripDistanceService::class);
        Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([], 500)]);
        $this->expectException(TripRoutingException::class);
        try {
            $service->calculate(['latitude' => 3.1, 'longitude' => 101.7], ['latitude' => 3.2, 'longitude' => 101.8]);
        } finally {
            Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [['distanceMeters' => 12345, 'duration' => '987.6s']]])]);
            $result = $service->calculate(['latitude' => 3.1, 'longitude' => 101.7], ['latitude' => 3.2, 'longitude' => 101.8]);
            $this->assertSame(12.35, $result['estimated_distance_km']);
            $this->assertSame(988, $result['estimated_duration_seconds']);
        }
    }

    public function test_trip_creation_resolves_places_and_calculates_google_route(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $this->fakeGooglePlacesAndRoute();

        $this->actingAs($driver)->post(route('driver.trips.store'), $this->tripData($vehicle))
            ->assertRedirect(route('driver.trips.index'));

        $this->assertDatabaseHas('trips', ['departure_location' => 'Departure Place', 'destination' => 'Destination Place', 'estimated_distance_km' => 12.35, 'estimated_duration_seconds' => 988]);
    }

    public function test_trip_update_recalculates_only_when_a_selected_place_changes(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $trip = $this->trip($driver, $vehicle);
        $this->fakeGooglePlacesAndRoute();
        $this->actingAs($driver)->patch(route('driver.trips.update', $trip), $this->tripData($vehicle, ['departure_place_id' => null, 'destination_place_id' => null]))->assertRedirect(route('driver.trips.index'));
        Http::assertNothingSent();

        $this->actingAs($driver)->patch(route('driver.trips.update', $trip), $this->tripData($vehicle, ['departure_place_id' => 'departure', 'destination_place_id' => null]))->assertRedirect(route('driver.trips.index'));
        $this->assertSame(988, $trip->refresh()->estimated_duration_seconds);
        Http::assertSentCount(2);
    }

    public function test_estimated_duration_remains_separate_from_actual_journey_duration(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $trip = $this->trip($driver, $this->vehicle($driver), ['estimated_duration_seconds' => 900, 'started_at' => now()->subMinutes(75), 'completed_at' => now()->subMinutes(15)]);
        $this->assertSame(900, $trip->estimated_duration_seconds);
        $this->assertSame('1h 0m', $trip->duration);
    }

    private function fakeGooglePlacesAndRoute(): void
    {
        Http::fake([
            'https://places.googleapis.com/v1/places/departure' => Http::response($this->place('departure', 'MY', 3.139, 101.6869, 'Departure, Malaysia')),
            'https://places.googleapis.com/v1/places/destination' => Http::response($this->place('destination', 'MY', 3.1578, 101.7123, 'Destination, Malaysia')),
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [['distanceMeters' => 12345, 'duration' => '987.6s']]]),
        ]);
    }

    private function place(string $id, string $country, float $latitude, float $longitude, string $address = 'A Place, Malaysia'): array
    {
        return ['id' => $id, 'displayName' => ['text' => str_replace(', Malaysia', ' Place', $address)], 'formattedAddress' => $address, 'addressComponents' => [['types' => ['country'], 'shortText' => $country]], 'location' => ['latitude' => $latitude, 'longitude' => $longitude]];
    }

    private function vehicle(User $driver): Vehicle
    {
        return Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active', 'seat_capacity' => 4]);
    }

    private function tripData(Vehicle $vehicle, array $overrides = []): array
    {
        return [...['vehicle_id' => $vehicle->vehicle_id, 'departure_place_id' => 'departure', 'destination_place_id' => 'destination', 'departure_date' => now()->addDay()->toDateString(), 'departure_time' => '10:00', 'available_seats' => 2, 'price_per_passenger' => 5], ...$overrides];
    }

    private function trip(User $driver, Vehicle $vehicle, array $overrides = []): Trip
    {
        return $driver->trips()->create([...['vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'Old Departure', 'destination' => 'Old Destination', 'departure_latitude' => 3.1, 'departure_longitude' => 101.7, 'destination_latitude' => 3.2, 'destination_longitude' => 101.8, 'departure_at' => now()->addDay(), 'available_seats' => 2, 'estimated_duration_seconds' => 600, 'status' => 'Scheduled'], ...$overrides]);
    }
}
