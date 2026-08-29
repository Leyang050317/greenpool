<?php

namespace App\Services;

use App\Models\Vehicle;

class FareRecommendationService
{
    public function recommend(float $distanceKm, ?Vehicle $vehicle = null): array
    {
        $distanceKm = max(0, $distanceKm);
        $fuelPrice = (float) config('fare.ron95_price', 3.82);
        $efficiency = $this->estimatedEfficiency($vehicle?->engine_capacity);
        $estimatedLitres = $distanceKm * $efficiency / 100;
        $fuelCost = $estimatedLitres * $fuelPrice;
        $maintenanceRate = (float) config('fare.maintenance_rate_per_km', 0.20);
        $maintenanceCost = $distanceKm * $maintenanceRate;
        $baseFare = (float) config('fare.base_fare', 2.00);
        $minimumFare = (float) config('fare.minimum_fare', 3.00);
        $distanceCost = $fuelCost + $maintenanceCost;
        $fare = max($minimumFare, $baseFare + $distanceCost);
        $recommended = round($fare * 2) / 2;

        return [
            'recommended_price' => $recommended,
            'minimum_price' => floor(max($minimumFare, $recommended * 0.8) * 2) / 2,
            'maximum_price' => ceil($recommended * 1.2 * 2) / 2,
            'base_fare' => $baseFare,
            'rate_per_km' => $distanceKm > 0 ? round($distanceCost / $distanceKm, 2) : 0,
            'distance_cost' => round($distanceCost, 2),
            'fuel_grade' => 'RON95',
            'fuel_price_per_litre' => $fuelPrice,
            'fuel_efficiency_l_per_100km' => $efficiency,
            'estimated_fuel_litres' => round($estimatedLitres, 2),
            'fuel_cost' => round($fuelCost, 2),
            'maintenance_rate_per_km' => $maintenanceRate,
            'maintenance_cost' => round($maintenanceCost, 2),
        ];
    }

    private function estimatedEfficiency(?int $engineCapacity): float
    {
        return match (true) {
            $engineCapacity === null || $engineCapacity <= 0 => 7.5,
            $engineCapacity <= 1300 => 6.5,
            $engineCapacity <= 1600 => 7.5,
            $engineCapacity <= 2000 => 9.0,
            default => 11.0,
        };
    }
}
