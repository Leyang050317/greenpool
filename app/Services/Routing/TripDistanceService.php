<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use Throwable;

class TripDistanceService
{
    public function calculate(array $departure, array $destination): array
    {
        $this->assertConfigured();

        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)
                ->withHeaders([
                    'X-Goog-Api-Key' => config('services.google_maps.key'),
                    'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration',
                ])->post(rtrim(config('services.google_maps.routes_base_url'), '/').':computeRoutes', [
                    'origin' => ['location' => ['latLng' => $this->latLng($departure)]],
                    'destination' => ['location' => ['latLng' => $this->latLng($destination)]],
                    'travelMode' => 'DRIVE',
                ]);
        } catch (Throwable) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
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

        return [
            'estimated_distance_km' => round(((float) $route['distanceMeters']) / 1000, 2),
            'estimated_duration_seconds' => $duration,
        ];
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
}
