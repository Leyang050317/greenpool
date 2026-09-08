<?php

namespace App\Services\Attractions;

use App\Models\Attraction;
use App\Models\GooglePlacesUsage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

class GooglePlacesService
{
    /** @return array<int, array<string, mixed>> */
    public function previewTouristAttractions(string $state, int $pageSize = 20): array
    {
        $response = $this->post('/places:searchText', [
            'textQuery' => "tourist attractions in {$state}, Malaysia",
            'includedType' => 'tourist_attraction',
            'strictTypeFiltering' => true,
            'pageSize' => min(max($pageSize, 1), 20),
            'regionCode' => 'MY',
        ], $this->touristAttractionFieldMask());

        return collect($response->json('places', []))
            ->filter(fn (array $place) => $this->isMalaysian($place) && $this->isInState($place, $state))
            ->filter(fn (array $place) => filled(data_get($place, 'photos.0.name')))
            ->sortByDesc(fn (array $place) => $this->popularityScore($place))
            ->values()
            ->all();
    }

    public function detailsFor(Attraction $attraction): array
    {
        $attraction->loadMissing('detail');
        $placeId = $attraction->detail?->google_place_id;
        if (! $placeId) {
            throw new RuntimeException('This attraction does not have a verified Google Place ID. Run the Google attraction import again.');
        }

        return $this->detailsByPlaceId($placeId);
    }

    /** @return array<int, array{place_id: string, text: string}> */
    public function autocompleteTouristAttractions(string $input): array
    {
        $response = $this->post('/places:autocomplete', [
            'input' => $input,
            'includedRegionCodes' => ['my'],
            'includedPrimaryTypes' => ['tourist_attraction'],
            'includeQueryPredictions' => false,
        ], 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text');

        return collect($response->json('suggestions', []))
            ->map(fn (array $suggestion) => [
                'place_id' => (string) data_get($suggestion, 'placePrediction.placeId'),
                'text' => (string) data_get($suggestion, 'placePrediction.text.text'),
            ])
            ->filter(fn (array $suggestion) => filled($suggestion['place_id']) && filled($suggestion['text']))
            ->take(5)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function detailsByPlaceId(string $placeId): array
    {
        $place = $this->get($this->placePath($placeId), [], 'id,displayName,formattedAddress,addressComponents,location,primaryType,photos,rating,userRatingCount,editorialSummary,regularOpeningHours,nationalPhoneNumber,internationalPhoneNumber,websiteUri,googleMapsUri')->json();

        if (! $this->isMalaysian($place)) {
            throw new RuntimeException('The selected attraction must be in Malaysia.');
        }

        return $place;
    }

    public function stateFor(array $place): ?string
    {
        $state = collect($place['addressComponents'] ?? [])
            ->first(fn (array $component) => in_array('administrative_area_level_1', $component['types'] ?? [], true));
        $raw = trim((string) ($state['longText'] ?? $state['shortText'] ?? ''));
        $normalised = $this->normalisePlaceText($raw);

        return match ($normalised) {
            'malacca' => 'Melaka',
            'wilayah persekutuan kuala lumpur', 'federal territory of kuala lumpur' => 'Kuala Lumpur',
            'wilayah persekutuan putrajaya', 'federal territory of putrajaya' => 'Putrajaya',
            'wilayah persekutuan labuan', 'federal territory of labuan' => 'Labuan',
            '' => null,
            default => $raw,
        };
    }

    public function photo(string $photoName): Response
    {
        return $this->get('/'.$photoName.'/media', ['maxWidthPx' => 1200], null);
    }

    private function placePath(string $placeId): string
    {
        $placeId = ltrim(trim($placeId), '/');

        return str_starts_with($placeId, 'places/') ? '/'.$placeId : '/places/'.$placeId;
    }

    public function photoFor(Attraction $attraction): Response
    {
        $place = $this->detailsFor($attraction);
        $photoName = data_get($place, 'photos.0.name');
        if (! $photoName) {
            throw new RuntimeException('No Google photo is available for this attraction.');
        }

        return $this->photo($photoName);
    }

    private function isMalaysian(array $place): bool
    {
        $hasCountryComponent = collect($place['addressComponents'] ?? [])
            ->contains(fn (array $component) => in_array('country', $component['types'] ?? [], true) && strtoupper((string) ($component['shortText'] ?? '')) === 'MY');

        return $hasCountryComponent || str_contains($this->normalisePlaceText((string) data_get($place, 'formattedAddress')), 'malaysia');
    }

    private function isInState(array $place, string $state): bool
    {
        $expected = $this->normalisePlaceText($state);
        $aliases = match ($expected) {
            'melaka' => ['melaka', 'malacca'],
            'kuala lumpur' => ['kuala lumpur', 'wilayah persekutuan kuala lumpur', 'federal territory of kuala lumpur'],
            'putrajaya' => ['putrajaya', 'wilayah persekutuan putrajaya', 'federal territory of putrajaya'],
            'labuan' => ['labuan', 'wilayah persekutuan labuan', 'federal territory of labuan'],
            default => [$expected],
        };

        $componentMatches = collect($place['addressComponents'] ?? [])
            ->filter(fn (array $component) => in_array('administrative_area_level_1', $component['types'] ?? [], true))
            ->flatMap(fn (array $component) => [$component['longText'] ?? '', $component['shortText'] ?? ''])
            ->map(fn (mixed $value) => $this->normalisePlaceText((string) $value))
            ->filter()
            ->contains(fn (string $actual) => collect($aliases)->contains(fn (string $alias) => str_contains($actual, $alias) || str_contains($alias, $actual)));

        if ($componentMatches) {
            return true;
        }

        $address = $this->normalisePlaceText((string) data_get($place, 'formattedAddress'));

        return collect($aliases)->contains(fn (string $alias) => str_contains($address, $alias));
    }

    private function normalisePlaceText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtolower($value)));
    }

    private function get(string $path, array $parameters, ?string $fieldMask): Response
    {
        return $this->request('get', $path, $parameters, $fieldMask);
    }

    private function post(string $path, array $payload, string $fieldMask): Response
    {
        return $this->request('post', $path, $payload, $fieldMask);
    }

    private function request(string $method, string $path, array $data, ?string $fieldMask): Response
    {
        $key = config('services.google_places.key');
        if (blank($key)) {
            throw new RuntimeException('GOOGLE_MAPS_API_KEY is not configured.');
        }
        $this->reserveRequest();

        $client = Http::acceptJson()->withHeaders(array_filter([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => $fieldMask,
        ]));

        try {
            $response = $method === 'post'
                ? $client->post(rtrim(config('services.google_places.base_url'), '/').$path, $data)
                : $client->get(rtrim(config('services.google_places.base_url'), '/').$path, $data);
        } catch (Throwable) {
            throw new RuntimeException('Google Places request could not be completed.');
        }
        if (! $response->successful()) {
            throw new RuntimeException("Google Places request failed with HTTP {$response->status()}.");
        }

        return $response;
    }

    private function reserveRequest(): void
    {
        $minuteLimit = config('services.google_places.max_calls_per_minute');
        $minuteKey = 'google-places:'.now()->format('YmdHi');
        if (RateLimiter::tooManyAttempts($minuteKey, $minuteLimit)) {
            throw new RuntimeException('Google Places per-minute request limit reached. Please try again shortly.');
        }

        DB::transaction(function () use ($minuteKey) {
            $usage = GooglePlacesUsage::query()->lockForUpdate()->firstOrCreate(['usage_date' => today()], ['request_count' => 0]);
            if ($usage->request_count >= config('services.google_places.max_calls_per_day')) {
                throw new RuntimeException('Google Places daily request limit reached.');
            }
            $usage->increment('request_count');
            RateLimiter::hit($minuteKey, 60);
        });
    }

    private function touristAttractionFieldMask(): string
    {
        return 'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.location,places.primaryType,places.photos,places.rating,places.userRatingCount,places.editorialSummary';
    }

    private function popularityScore(array $place): float
    {
        $rating = (float) data_get($place, 'rating', 0);
        $reviews = max(0, (int) data_get($place, 'userRatingCount', 0));

        return ($rating * 100) + min(log10($reviews + 1) * 45, 200);
    }
}
