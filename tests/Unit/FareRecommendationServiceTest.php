<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\FareRecommendationService;
use Tests\TestCase;

class FareRecommendationServiceTest extends TestCase
{
    public function test_fare_is_calculated_from_fuel_and_maintenance_costs(): void
    {
        $vehicle = new Vehicle(['engine_capacity' => 1600]);
        $fare = app(FareRecommendationService::class)->recommend(12.35, $vehicle);

        $this->assertSame('RON95', $fare['fuel_grade']);
        $this->assertSame(3.82, $fare['fuel_price_per_litre']);
        $this->assertSame(7.5, $fare['fuel_efficiency_l_per_100km']);
        $this->assertSame(3.54, $fare['fuel_cost']);
        $this->assertSame(2.47, $fare['maintenance_cost']);
        $this->assertSame(8.0, $fare['recommended_price']);
    }

    public function test_a_larger_engine_produces_a_higher_recommendation(): void
    {
        $service = app(FareRecommendationService::class);

        $smallEngine = $service->recommend(20, new Vehicle(['engine_capacity' => 1300]));
        $largeEngine = $service->recommend(20, new Vehicle(['engine_capacity' => 2500]));

        $this->assertGreaterThan($smallEngine['recommended_price'], $largeEngine['recommended_price']);
    }
}
