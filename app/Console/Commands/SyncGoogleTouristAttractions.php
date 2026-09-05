<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Models\AttractionDetail;
use App\Services\Attractions\GooglePlacesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncGoogleTouristAttractions extends Command
{
    protected $signature = 'attractions:sync-google
                            {--state= : Sync one Malaysian state only}
                            {--per-state=8 : Photo-ready attractions to sync per state (1-20)}';

    protected $description = 'Add or update photo-ready Malaysian attractions without deleting existing places or favourites.';

    /** @var list<string> */
    private const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka',
        'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya',
        'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
    ];

    public function handle(GooglePlacesService $googlePlaces): int
    {
        $perState = (int) $this->option('per-state');
        if ($perState < 1 || $perState > 20) {
            $this->error('--per-state must be between 1 and 20.');

            return self::INVALID;
        }

        $requestedState = trim((string) $this->option('state'));
        if ($requestedState !== '' && ! in_array($requestedState, self::STATES, true)) {
            $this->error('The selected state is not in the supported Malaysian state list.');

            return self::INVALID;
        }

        $states = $requestedState === '' ? self::STATES : [$requestedState];
        $created = 0;
        $updated = 0;

        $this->info('Synchronising photo-ready Malaysian attractions from Google Places...');

        foreach ($states as $state) {
            try {
                $places = $googlePlaces->previewTouristAttractions($state, $perState);
            } catch (RuntimeException $exception) {
                $this->error("{$state}: {$exception->getMessage()}");
                $this->warn('Previously saved attractions and favourites were not removed.');

                return self::FAILURE;
            }

            foreach ($places as $place) {
                if (blank(data_get($place, 'id')) || blank(data_get($place, 'displayName.text'))) {
                    continue;
                }

                $wasCreated = $this->syncPlace($state, $place);
                $wasCreated ? $created++ : $updated++;
            }

            $this->line("{$state}: ".count($places).' attractions synchronised');
        }

        $this->newLine();
        $this->info("Attraction sync complete: {$created} added, {$updated} updated.");
        $this->comment('No existing attractions or user favourites were deleted.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $place */
    private function syncPlace(string $state, array $place): bool
    {
        $placeId = trim((string) data_get($place, 'id'));
        $name = trim((string) data_get($place, 'displayName.text'));
        $category = $this->categoryFor((string) data_get($place, 'primaryType'));

        return DB::transaction(function () use ($place, $placeId, $name, $state, $category): bool {
            $detail = AttractionDetail::query()
                ->where('google_place_id', $placeId)
                ->orWhere('source_place_id', $placeId)
                ->first();
            $attraction = $detail?->attraction;

            if (! $attraction) {
                $attraction = Attraction::query()
                    ->where('attraction_name', $name)
                    ->where('state', $state)
                    ->first();
            }

            $wasCreated = ! $attraction;
            $description = $attraction?->description ?: $this->descriptionFor($name, $state, $category);
            $location = mb_substr((string) data_get($place, 'formattedAddress'), 0, 255) ?: $attraction?->location;

            if ($attraction) {
                $attraction->update([
                    'attraction_name' => mb_substr($name, 0, 150),
                    'state' => mb_substr($state, 0, 50),
                    'description' => $description,
                    'location' => $location,
                ]);
            } else {
                $attraction = Attraction::query()->create([
                    'attraction_name' => mb_substr($name, 0, 150),
                    'state' => mb_substr($state, 0, 50),
                    'description' => $description,
                    'location' => $location,
                    'image_url' => null,
                ]);
            }

            $attraction->detail()->updateOrCreate(
                ['attraction_id' => $attraction->getKey()],
                [
                    'category' => $category,
                    'source_name' => 'Google Places',
                    'source_place_id' => $placeId,
                    'google_place_id' => $placeId,
                    'latitude' => data_get($place, 'location.latitude'),
                    'longitude' => data_get($place, 'location.longitude'),
                ]
            );

            return $wasCreated;
        });
    }

    private function categoryFor(string $primaryType): string
    {
        return match ($primaryType) {
            'museum' => 'Museum',
            'historical_landmark', 'historical_place' => 'Historical Landmark',
            'national_park', 'park', 'garden' => 'Nature & Parks',
            'amusement_park' => 'Theme Park',
            'zoo', 'aquarium', 'wildlife_park' => 'Wildlife & Zoo',
            'art_gallery', 'cultural_center' => 'Art & Culture',
            'beach' => 'Beach',
            'place_of_worship', 'church', 'hindu_temple', 'mosque' => 'Religious Site',
            'shopping_mall', 'market' => 'Shopping',
            default => 'Tourist Attraction',
        };
    }

    private function descriptionFor(string $name, string $state, string $category): string
    {
        return "Explore {$name}, a {$category} destination in {$state}, Malaysia. Open the attraction to view current visitor information, directions, and available trip options.";
    }
}
