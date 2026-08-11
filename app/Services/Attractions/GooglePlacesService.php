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
    public function detailsFor(Attraction $attraction): array
    {
        $attraction->loadMissing('detail');
        $placeId = $attraction->detail?->google_place_id ?? $this->findAndSavePlaceId($attraction);
        if (! $placeId) {
            throw new RuntimeException('No verified Malaysian Google Place match was found for this attraction.');
        }

        return $this->get('/places/'.$placeId, [], 'displayName,formattedAddress,photos,editorialSummary,regularOpeningHours,nationalPhoneNumber,internationalPhoneNumber,websiteUri,googleMapsUri')->json();
    }

    public function photo(string $photoName): Response
    {
        return $this->get('/'.$photoName.'/media', ['maxWidthPx' => 1200], null);
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

    private function findAndSavePlaceId(Attraction $attraction): ?string
    {
        $detail = $attraction->detail;
        if (! $detail) {
            return null;
        }
        $payload = ['textQuery' => "{$attraction->attraction_name}, {$attraction->state}, Malaysia", 'maxResultCount' => 5];
        if ($detail?->latitude !== null && $detail?->longitude !== null) {
            $payload['locationBias'] = ['circle' => ['center' => ['latitude' => $detail->latitude, 'longitude' => $detail->longitude], 'radius' => 5000.0]];
        }

        $response = $this->post('/places:searchText', $payload, 'places.id,places.displayName,places.addressComponents,places.location');
        $place = collect($response->json('places', []))
            ->filter(fn (array $place) => $this->isMalaysian($place) && $this->nameScore($attraction->attraction_name, data_get($place, 'displayName.text', '')) >= 75)
            ->sortByDesc(fn (array $place) => $this->nameScore($attraction->attraction_name, data_get($place, 'displayName.text', '')))
            ->first();

        if (! $place) {
            return null;
        }

        $placeId = $place['id'];
        $attraction->detail->update(['google_place_id' => $placeId]);

        return $placeId;
    }

    private function isMalaysian(array $place): bool
    {
        return collect($place['addressComponents'] ?? [])
            ->contains(fn (array $component) => in_array('country', $component['types'] ?? [], true) && strtoupper((string) ($component['shortText'] ?? '')) === 'MY');
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

    private function nameScore(string $left, string $right): float
    {
        $left = preg_replace('/[^a-z0-9]+/', '', strtolower($left)) ?? '';
        $right = preg_replace('/[^a-z0-9]+/', '', strtolower($right)) ?? '';
        if ($left === '' || $right === '') {
            return 0;
        }
        if (str_contains($left, $right) || str_contains($right, $left)) {
            return 100;
        }
        similar_text($left, $right, $score);

        return $score;
    }
}
