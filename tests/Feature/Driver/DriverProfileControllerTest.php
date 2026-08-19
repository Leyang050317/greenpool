<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_view_their_profile_and_default_preferences_are_created(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertViewHas('user', fn (User $user) => $user->is($driver))
            ->assertSee('Personal Info')
            ->assertSee('My Vehicles')
            ->assertSee('Driving Preferences');

        $this->assertDatabaseHas('driver_preferences', [
            'user_id' => $driver->id,
            'smoking_allowed' => false,
            'pets_allowed' => false,
            'conversation_preference' => 'Moderate',
        ]);
    }

    public function test_passenger_cannot_access_driver_profile_routes(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($passenger)
            ->get(route('driver.profile.edit'))
            ->assertForbidden();
    }

    public function test_driver_can_update_profile_name_without_submitting_email(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver@example.com',
        ]);

        $this->actingAs($driver)
            ->patch(route('driver.profile.update'), ['name' => 'Updated Driver'])
            ->assertRedirect(route('driver.profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'name' => 'Updated Driver',
            'email' => 'driver@example.com',
        ]);
    }

    public function test_driver_can_update_driving_preferences(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->put(route('driver.profile.preferences.update'), [
                'smoking_allowed' => true,
                'pets_allowed' => true,
                'conversation_preference' => 'Chatty',
            ])
            ->assertRedirect(route('driver.profile.edit'));

        $this->assertDatabaseHas('driver_preferences', [
            'user_id' => $driver->id,
            'smoking_allowed' => true,
            'pets_allowed' => true,
            'conversation_preference' => 'Chatty',
        ]);
    }
}
