<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_root_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_passenger_is_redirected_to_passenger_dashboard(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($passenger)->get('/')->assertRedirect(route('passenger.home'));
    }

    public function test_authenticated_driver_is_redirected_to_driver_dashboard(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)->get('/')->assertRedirect(route('driver.home'));
    }
}
