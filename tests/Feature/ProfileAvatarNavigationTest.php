<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAvatarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_navigation_uses_the_uploaded_profile_photo(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'photo' => 'profile-photos/passenger-avatar.jpg',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('/storage/profile-photos/passenger-avatar.jpg');
    }

    public function test_driver_navigation_uses_the_uploaded_profile_photo(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
            'photo' => 'profile-photos/driver-avatar.jpg',
        ]);

        $this->actingAs($user)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertSee('/storage/profile-photos/driver-avatar.jpg');
    }
}
