<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripLocation;

class TripLocationService
{
    private const MINIMUM_SECONDS_BETWEEN_UPDATES = 10;
    private const MINIMUM_DISTANCE_METERS = 25;

    public function record(Trip $trip, int $driverId, array $location): ?TripLocation
    {
        $latest = $trip->latestLocation;

        if ($latest && $latest->recorded_at->diffInSeconds(now()) < self::MINIMUM_SECONDS_BETWEEN_UPDATES
            && $this->distanceMeters((float) $latest->latitude, (float) $latest->longitude, (float) $location['latitude'], (float) $location['longitude']) < self::MINIMUM_DISTANCE_METERS) {
            return null;
        }

        return TripLocation::create([
            'trip_id' => $trip->trip_id,
            'driver_id' => $driverId,
            ...$location,
            'recorded_at' => now(),
        ]);
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
