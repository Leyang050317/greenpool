<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_profile_has_a_specific_page_title(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('<title>Profile - GreenPool</title>', false);
    }

    public function test_archived_vehicles_has_a_specific_page_title(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->get(route('driver.vehicles.archived'))
            ->assertOk()
            ->assertSee('<title>Archived Vehicles - GreenPool</title>', false);
    }

    public function test_passenger_notifications_has_the_notifications_page_title(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($passenger)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('<title>Notifications - GreenPool</title>', false)
            ->assertDontSee('<title>Ratings - GreenPool</title>', false);
    }
}
