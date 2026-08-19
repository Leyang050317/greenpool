<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_has_one_preference_record(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $preference = $driver->driverPreference()->create([
            'smoking_allowed' => true,
            'pets_allowed' => false,
            'conversation_preference' => 'Quiet',
        ]);

        $this->assertTrue($preference->user->is($driver));
        $this->assertTrue($driver->fresh()->driverPreference->smoking_allowed);
        $this->assertSame('Quiet', $driver->fresh()->driverPreference->conversation_preference);
    }
}
