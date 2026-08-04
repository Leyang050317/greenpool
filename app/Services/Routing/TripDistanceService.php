<?php

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TripDistanceService
{
    public function calculate(string $departure, string $destination): array
    {
        $from = $this->geocode($departure, 'departure_location');
        $to = $this->geocode($destination, 'destination');

        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(12)
                ->get(rtrim(config('services.routing.osrm_url'), '/')."/route/v1/driving/{$from['longitude']},{$from['latitude']};{$to['longitude']},{$to['latitude']}", [
                    'overview' => 'false',
                ]);
        } catch (ConnectionException) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
        }

        $route = $response->json('routes.0');
        if ($response->json('code') === 'NoRoute' || ($response->successful() && ! $route)) {
            throw new TripRoutingException('destination', 'No driving route available between these locations.');
        }
        if (! $response->successful() || ! isset($route['distance'])) {
            throw new TripRoutingException('destination', 'Unable to calculate route distance. Please try again.');
        }

        return [
            'departure_latitude' => $from['latitude'],
            'departure_longitude' => $from['longitude'],
            'destination_latitude' => $to['latitude'],
            'destination_longitude' => $to['longitude'],
            'estimated_distance_km' => round(((float) $route['distance']) / 1000, 2),
        ];
    }

    private function geocode(string $address, string $field): array
    {
        $key = 'trip-geocode:'.sha1(mb_strtolower(trim($address)));

        return Cache::remember($key, now()->addDays(30), function () use ($address, $field) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['User-Agent' => config('services.routing.user_agent')])
                    ->connectTimeout(5)->timeout(12)
                    ->get(rtrim(config('services.routing.nominatim_url'), '/').'/search', [
                        'q' => $address,
                        'format' => 'jsonv2',
                        'limit' => 1,
                        'addressdetails' => 1,
                        'countrycodes' => 'my',
                    ]);
            } catch (ConnectionException) {
                throw new TripRoutingException($field, 'Unable to process location information. Please try again later.');
            }

            if (! $response->successful()) {
                throw new TripRoutingException($field, 'Unable to process location information. Please try again later.');
            }

            $result = $response->json('0');
            if (! $result || data_get($result, 'address.country_code') !== 'my' || ! isset($result['lat'], $result['lon'])) {
                throw new TripRoutingException($field, 'Unable to find the selected location. Please enter a valid address.');
            }

            return ['latitude' => (float) $result['lat'], 'longitude' => (float) $result['lon']];
        });
    }
}
