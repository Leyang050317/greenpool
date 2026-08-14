<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttractionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_browse_search_and_filter_attractions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $penangAttraction = Attraction::query()->create([
            'attraction_name' => 'Penang Hill',
            'state' => 'Penang',
            'description' => 'A scenic hilltop destination.',
            'location' => 'Air Itam',
        ]);
        $melakaAttraction = Attraction::query()->create([
            'attraction_name' => 'Jonker Street',
            'state' => 'Melaka',
            'description' => 'Heritage shopping street.',
            'location' => 'Melaka City',
        ]);

        $this->actingAs($user)
            ->get(route('attractions.index', ['search' => 'hill', 'state' => 'Penang']))
            ->assertOk()
            ->assertSee($penangAttraction->attraction_name)
            ->assertDontSee($melakaAttraction->attraction_name);

        $this->actingAs($user)
            ->get(route('attractions.show', $penangAttraction))
            ->assertOk()
            ->assertSee($penangAttraction->location);
    }

    public function test_user_can_save_and_remove_an_attraction_from_favourites(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $attraction = Attraction::query()->create([
            'attraction_name' => 'Batu Caves',
            'state' => 'Selangor',
        ]);

        $this->actingAs($user)
            ->post(route('attractions.favourites.store', $attraction))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('favourites', [
            'attraction_id' => $attraction->attraction_id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('attractions.favourites.destroy', $attraction))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('favourites', [
            'attraction_id' => $attraction->attraction_id,
            'user_id' => $user->id,
        ]);
    }

    public function test_passenger_can_continue_from_an_attraction_to_a_destination_filtered_ride_search(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);
        $attraction = Attraction::query()->create([
            'attraction_name' => 'Petronas Twin Towers',
            'state' => 'Kuala Lumpur',
            'location' => 'Kuala Lumpur City Centre',
        ]);

        $this->actingAs($user)
            ->get(route('attractions.show', $attraction))
            ->assertOk()
            ->assertSee(route('passenger.booking', ['destination' => $attraction->attraction_name]), false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=Kuala%20Lumpur%20City%20Centre', false);
    }

    public function test_driver_can_create_a_trip_with_an_attraction_prefilled_as_the_destination(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'driver']);
        Vehicle::factory()->verified()->create(['user_id' => $user->id, 'status' => 'Active']);
        $attraction = Attraction::query()->create([
            'attraction_name' => 'Batu Caves',
            'state' => 'Selangor',
        ]);
        $attraction->detail()->create(['google_place_id' => 'ChIJ-attraction-place-id']);

        $this->actingAs($user)
            ->get(route('attractions.show', $attraction))
            ->assertOk()
            ->assertSee('Create a Trip')
            ->assertSee('ChIJ-attraction-place-id');

        $this->get(route('driver.trips.create', [
                'destination' => $attraction->attraction_name,
                'destination_place_id' => $attraction->detail->google_place_id,
            ]))
            ->assertOk()
            ->assertSee('Batu Caves')
            ->assertSee('Destination was added from Tourist Attractions.');
    }
}
