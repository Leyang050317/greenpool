<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_login_histories(): void
    {
        $user = User::factory()->create();

        $history = $user->loginHistories()->create([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'login_at' => now(),
        ]);

        $this->assertTrue($history->user->is($user));
        $this->assertSame('127.0.0.1', $user->fresh()->loginHistories->first()->ip_address);
    }

    public function test_passenger_profile_loads_only_the_five_most_recent_login_records(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        foreach (range(1, 6) as $number) {
            $user->loginHistories()->create([
                'ip_address' => '203.0.113.'.$number,
                'user_agent' => 'Mozilla/5.0 Chrome/'.$number,
                'login_at' => now()->subMinutes($number),
            ]);
        }

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()->assertSee('Recent Login Activity');
        $this->assertCount(5, $response->viewData('recentLogins'));
        $this->assertSame('203.0.113.1', $response->viewData('recentLogins')->first()->ip_address);
    }
}
