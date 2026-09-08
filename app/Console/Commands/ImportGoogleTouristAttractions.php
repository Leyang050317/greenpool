<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Services\Attractions\GooglePlacesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportGoogleTouristAttractions extends Command
{
    protected $signature = 'attractions:import-google
                            {--replace : Delete the existing attraction catalogue before importing Google Places results}
                            {--per-state=10 : Photo-ready attractions to keep per Malaysian state (1-20)}';

    protected $description = 'Replace the attraction catalogue with photo-ready Malaysian tourist attractions from Google Places.';

    /** @var list<string> */
    private const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka',
        'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya',
        'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
    ];

    public function handle(GooglePlacesService $googlePlaces): int
    {
        if (! $this->option('replace')) {
            $this->error('This command replaces the attraction catalogue. Re-run it with --replace after backing up any favourites you need.');

            return self::INVALID;
        }

        $perState = (int) $this->option('per-state');
        if ($perState < 1 || $perState > 20) {
            $this->error('--per-state must be between 1 and 20.');

            return self::INVALID;
        }

        $this->info('Getting photo-ready Malaysian tourist attractions from Google Places before changing MySQL...');
        $candidates = [];

        foreach (self::STATES as $state) {
            try {
                $places = $googlePlaces->previewTouristAttractions($state, $perState);
            } catch (RuntimeException $exception) {
                $this->error("{$state}: {$exception->getMessage()}");
                $this->warn('No existing attraction data was deleted. Fix the Google API limit/configuration and run the command again.');

                return self::FAILURE;
            }

            foreach ($places as $place) {
                $candidates[] = ['state' => $state, 'place' => $place];
            }

            $this->line("{$state}: ".count($places).' photo-ready attractions selected');
        }

        $candidates = collect($candidates)
            ->unique(fn (array $candidate) => data_get($candidate, 'place.id'))
            ->sortByDesc(fn (array $candidate) => $this->popularityScore($candidate['place']))
            ->values();

        if ($candidates->isEmpty()) {
            $this->error('Google Places returned no verified, photo-ready Malaysian tourist attractions. No data was changed.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($candidates): void {
            // Foreign-key cascading removes stale attraction_details and favourites.
            Attraction::query()->delete();

            foreach ($candidates as $candidate) {
                $place = $candidate['place'];
                $name = trim((string) data_get($place, 'displayName.text'));
                $state = $candidate['state'];
                $attraction = Attraction::query()->create([
                    'attraction_name' => mb_substr($name, 0, 150),
                    'state' => $state,
                    'description' => mb_substr(
                        (string) data_get($place, 'editorialSummary.text') ?: "Discover {$name} in {$state}, Malaysia.",
                        0,
                        1000,
                    ),
                    'location' => mb_substr((string) data_get($place, 'formattedAddress'), 0, 255) ?: null,
                    // Google photo names are not cached; cards load the current photo via the Place ID.
                    'image_url' => null,
                ]);

                $attraction->detail()->create([
                    'category' => $this->categoryFor((string) data_get($place, 'primaryType')),
                    'source_name' => 'Google Places',
                    'source_place_id' => data_get($place, 'id'),
                    'google_place_id' => data_get($place, 'id'),
                    'latitude' => data_get($place, 'location.latitude'),
                    'longitude' => data_get($place, 'location.longitude'),
                    'rating' => data_get($place, 'rating'),
                    'user_rating_count' => data_get($place, 'userRatingCount'),
                ]);
            }
        });

        $this->newLine();
        $this->info("Imported {$candidates->count()} Google Places attractions. Old dummy and legacy API records were removed.");
        $this->comment('All imported results have a Google photo candidate. The homepage and state pages will therefore prioritise real photos instead of the green placeholder.');

        return self::SUCCESS;
    }

    private function categoryFor(string $primaryType): string
    {
        return match ($primaryType) {
            'museum' => 'Museum',
            'historical_landmark' => 'Historical Landmark',
            'national_park' => 'National Park',
            'amusement_park' => 'Theme Park',
            'zoo' => 'Wildlife & Zoo',
            'art_gallery' => 'Art & Culture',
            default => 'Tourist Attraction',
        };
    }

    private function popularityScore(array $place): float
    {
        $rating = (float) data_get($place, 'rating', 0);
        $reviews = max(0, (int) data_get($place, 'userRatingCount', 0));

        return ($rating * 100) + min(log10($reviews + 1) * 45, 200);
    }
}
