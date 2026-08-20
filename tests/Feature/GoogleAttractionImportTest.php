<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Services\Attractions\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleAttractionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_import_replaces_legacy_attractions_only_after_all_states_are_loaded(): void
    {
        Attraction::query()->create(['attraction_name' => 'Old dummy attraction', 'state' => 'Selangor']);

        $googlePlaces = Mockery::mock(GooglePlacesService::class);
        $googlePlaces->shouldReceive('previewTouristAttractions')
            ->times(16)
            ->andReturnUsing(function (string $state): array {
                return [[
                    'id' => 'places/'.$state,
                    'displayName' => ['text' => $state.' Heritage Site'],
                    'formattedAddress' => $state.', Malaysia',
                    'primaryType' => 'tourist_attraction',
                    'location' => ['latitude' => 3.1, 'longitude' => 101.7],
                    'photos' => [['name' => 'places/'.$state.'/photos/photo-1']],
                    'rating' => 4.8,
                    'userRatingCount' => 2000,
                ]];
            });
        $this->app->instance(GooglePlacesService::class, $googlePlaces);

        $this->artisan('attractions:import-google', ['--replace' => true, '--per-state' => 1])
            ->assertSuccessful();

        $this->assertDatabaseMissing('tourist_attractions', ['attraction_name' => 'Old dummy attraction']);
        $this->assertDatabaseCount('tourist_attractions', 16);
        $this->assertDatabaseHas('attraction_details', [
            'source_name' => 'Google Places',
            'google_place_id' => 'places/Johor',
        ]);
    }

    public function test_google_import_requires_explicit_replace_option(): void
    {
        $this->artisan('attractions:import-google')
            ->assertExitCode(2);

        $this->assertDatabaseCount('tourist_attractions', 0);
    }
}
