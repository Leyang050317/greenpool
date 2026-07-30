<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'driver']),
            'plate_number' => strtoupper(fake()->unique()->bothify('??? ####')),
            'brand' => fake()->randomElement(['Perodua', 'Proton', 'Toyota', 'Honda']),
            'model' => fake()->randomElement(['Myvi', 'Saga', 'Vios', 'City']),
            'colour' => fake()->safeColorName(),
            'seat_capacity' => fake()->numberBetween(1, 4),
            'status' => 'Inactive',
        ];
    }
}
