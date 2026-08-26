<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use Throwable;

class TripLocationService
{
    public function reverseGeocode(float $latitude, float $longitude, string $field): array
    {
        $this->assertConfigured($field);

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new TripRoutingException($field, 'The selected location does not have valid coordinates.');
        }

        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$latitude},{$longitude}",
                'key' => config('services.google_maps.key'),
                'result_type' => 'street_address|premise|route|neighborhood|locality',
            ]);
        } catch (Throwable) {
            throw new TripRoutingException($field, 'Unable to detect your current location. Please enter it manually.');
        }

        if (! $response->successful() || $response->json('status') !== 'OK') {
            throw new TripRoutingException($field, 'Unable to detect your current location. Please enter it manually.');
        }

        $result = collect($response->json('results', []))
            ->first(fn (array $result) => $this->isMalaysianGeocodeResult($result));

        $display = trim((string) data_get($result, 'formatted_address', ''));
        $placeId = trim((string) data_get($result, 'place_id', ''));

        if ($display === '' || $placeId === '') {
            throw new TripRoutingException($field, 'Unable to detect your current location. Please enter it manually.');
        }

        return [
            'display' => $display,
            'place_id' => $placeId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    public function autocomplete(string $input): array
    {
        $this->assertConfigured('departure_place_id');

        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)->withHeaders([
                'X-Goog-Api-Key' => config('services.google_maps.key'),
                'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text',
            ])->post(rtrim(config('services.google_maps.places_base_url'), '/').'/places:autocomplete', [
                'input' => $input,
                'includedRegionCodes' => ['my'],
                'includeQueryPredictions' => false,
            ]);
        } catch (Throwable) {
            throw new TripRoutingException('departure_place_id', 'Unable to find locations right now. Please try again.');
        }

        if (! $response->successful()) {
            throw new TripRoutingException('departure_place_id', 'Unable to find locations right now. Please try again.');
        }

        return collect($response->json('suggestions', []))->map(fn (array $suggestion) => [
            'place_id' => data_get($suggestion, 'placePrediction.placeId'),
            'text' => data_get($suggestion, 'placePrediction.text.text'),
        ])->filter(fn (array $suggestion) => filled($suggestion['place_id']) && filled($suggestion['text']))->values()->all();
    }

    public function resolve(string $placeId, string $field): array
    {
        $this->assertConfigured($field);

        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)->withHeaders([
                'X-Goog-Api-Key' => config('services.google_maps.key'),
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,addressComponents,location',
            ])->get(rtrim(config('services.google_maps.places_base_url'), '/').'/places/'.rawurlencode($placeId));
        } catch (Throwable) {
            throw new TripRoutingException($field, 'Unable to verify the selected location. Please try again.');
        }

        $place = $response->json();
        if (! $response->successful() || ! is_array($place) || blank($place['id'] ?? null)) {
            throw new TripRoutingException($field, 'The selected location is invalid or no longer available.');
        }
        if (! $this->isMalaysian($place)) {
            throw new TripRoutingException($field, 'The selected location must be in Malaysia.');
        }

        $latitude = data_get($place, 'location.latitude');
        $longitude = data_get($place, 'location.longitude');
        $display = trim((string) (data_get($place, 'displayName.text') ?? $place['formattedAddress'] ?? ''));
        if (! is_numeric($latitude) || ! is_numeric($longitude) || (float) $latitude < -90 || (float) $latitude > 90 || (float) $longitude < -180 || (float) $longitude > 180 || $display === '') {
            throw new TripRoutingException($field, 'The selected location does not have valid coordinates.');
        }

        return ['display' => $display, 'latitude' => (float) $latitude, 'longitude' => (float) $longitude];
    }

    private function isMalaysian(array $place): bool
    {
        return collect($place['addressComponents'] ?? [])->contains(fn (array $component) => in_array('country', $component['types'] ?? [], true) && strtoupper((string) ($component['shortText'] ?? '')) === 'MY');
    }

    private function isMalaysianGeocodeResult(array $result): bool
    {
        return collect($result['address_components'] ?? [])->contains(fn (array $component) => in_array('country', $component['types'] ?? [], true) && strtoupper((string) ($component['short_name'] ?? '')) === 'MY');
    }

    private function assertConfigured(string $field): void
    {
        if (blank(config('services.google_maps.key'))) {
            throw new TripRoutingException($field, 'Google Maps is not configured. Please contact support.');
        }
    }
}
