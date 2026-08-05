<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\User;
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
}
