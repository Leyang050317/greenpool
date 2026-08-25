<?php

namespace App\Services;

class FareRecommendationService
{
    private const BASE_FARE = 2.00;

    private const RATE_PER_KM = 0.45;

    private const MINIMUM_FARE = 3.00;

    public function recommend(float $distanceKm): array
    {
        $fare = max(self::MINIMUM_FARE, self::BASE_FARE + max(0, $distanceKm) * self::RATE_PER_KM);
        $recommended = round($fare * 2) / 2;

        return [
            'recommended_price' => $recommended,
            'minimum_price' => floor(max(self::MINIMUM_FARE, $recommended * 0.8) * 2) / 2,
            'maximum_price' => ceil($recommended * 1.2 * 2) / 2,
            'base_fare' => self::BASE_FARE,
            'rate_per_km' => self::RATE_PER_KM,
            'distance_cost' => round(max(0, $distanceKm) * self::RATE_PER_KM, 2),
        ];
    }
}
