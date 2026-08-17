<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TripDistanceService
{
    public function calculate(array $departure, array $destination, array $intermediates = [], bool $optimizeWaypointOrder = false): array
    {
        $this->assertConfigured();

        try {
            $shouldOptimize = $optimizeWaypointOrder && $intermediates !== [];
            $fieldMask = 'routes.distanceMeters,routes.duration'.($shouldOptimize ? ',routes.optimizedIntermediateWaypointIndex' : '');
            $payload = [
                'origin' => ['location' => ['latLng' => $this->latLng($departure)]],
                'destination' => ['location' => ['latLng' => $this->latLng($destination)]],
                'travelMode' => 'DRIVE',
            ];
            if ($intermediates !== []) {
                $payload['intermediates'] = array_map(fn (array $location) => ['location' => ['latLng' => $this->latLng($location)]], $intermediates);
            }
            if ($shouldOptimize) {
                $payload['optimizeWaypointOrder'] = true;
            }

            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)
                ->withHeaders([
                    'X-Goog-Api-Key' => config('services.google_maps.key'),
                    'X-Goog-FieldMask' => $fieldMask,
                ])->post(rtrim(config('services.google_maps.routes_base_url'), '/').':computeRoutes', $payload);
        } catch (Throwable) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
        }

        if (! $response->successful()) {
            $body = $this->sanitizedResponseBody($response->json(), $response->body());

            Log::warning('Google Routes API request failed.', [
                'http_status' => $response->status(),
                'google_error_status' => is_array($body) ? data_get($body, 'error.status') : null,
                'google_error_message' => is_array($body) ? data_get($body, 'error.message') : null,
                'google_error_details' => is_array($body) ? data_get($body, 'error.details') : null,
                'response_body' => $body,
            ]);
        }

        $route = $response->json('routes.0');
        if ($response->successful() && ! $route) {
            throw new TripRoutingException('destination', 'No driving route available between these locations.');
        }
        if (! $response->successful() || ! is_array($route) || ! isset($route['distanceMeters'], $route['duration'])) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
        }

        $duration = $this->durationSeconds($route['duration']);
        if (! is_numeric($route['distanceMeters']) || (float) $route['distanceMeters'] < 0 || $duration === null) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
        }

        $result = [
            'estimated_distance_km' => round(((float) $route['distanceMeters']) / 1000, 2),
            'estimated_duration_seconds' => $duration,
        ];
        if ($optimizeWaypointOrder && $intermediates !== []) {
            $indexes = $route['optimizedIntermediateWaypointIndex'] ?? [];
            if (! is_array($indexes) || count($indexes) !== count($intermediates) || collect($indexes)->contains(fn (mixed $index) => ! is_int($index) && ! ctype_digit((string) $index))) {
                throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
            }
            $result['optimized_intermediate_waypoint_index'] = array_map('intval', $indexes);
        }

        return $result;
    }

    private function assertConfigured(): void
    {
        if (blank(config('services.google_maps.key'))) {
            throw new TripRoutingException('destination', 'Google Maps is not configured. Please contact support.');
        }
    }

    private function latLng(array $location): array
    {
        return ['latitude' => (float) $location['latitude'], 'longitude' => (float) $location['longitude']];
    }

    private function durationSeconds(mixed $duration): ?int
    {
        if (! is_string($duration) || ! preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $matches)) {
            return null;
        }

        return (int) round((float) $matches[1]);
    }

    private function sanitizedResponseBody(mixed $json, string $rawBody): mixed
    {
        return $this->sanitize($json ?? $rawBody);
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->mapWithKeys(function (mixed $item, string|int $key): array {
                if (is_string($key) && preg_match('/(?:api[_-]?key|x-goog-api-key|authorization)/i', $key)) {
                    return [$key => '[REDACTED]'];
                }

                return [$key => $this->sanitize($item)];
            })->all();
        }

        if (! is_string($value)) {
            return $value;
        }

        return preg_replace([
            '/AIza[A-Za-z0-9_-]+/',
            '/((?:api[_-]?key|x-goog-api-key|authorization)\s*[:=]\s*["\']?)[^"\'\s,&}]+/i',
            '/([?&](?:key|api[_-]?key)=)[^&\s]+/i',
        ], ['[REDACTED]', '$1[REDACTED]', '$1[REDACTED]'], $value) ?? '[UNAVAILABLE]';
    }
}
