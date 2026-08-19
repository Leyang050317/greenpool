<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class LinkedAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_user_can_unlink_google_account(): void
    {
        $user = User::factory()->create([
            'auth_provider' => 'password',
            'google_id' => 'google-account-id',
        ]);

        $this->actingAs($user)
            ->delete(route('linked-accounts.google.destroy'))
            ->assertSessionHas('status', 'google-account-unlinked');

        $this->assertNull($user->fresh()->google_id);
    }

    public function test_google_only_user_cannot_unlink_google_account(): void
    {
        $user = User::factory()->create([
            'auth_provider' => 'google',
            'google_id' => 'google-account-id',
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('linked-accounts.google.destroy'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error', 'Please set a password in your account settings before unlinking your Google account.');

        $this->assertSame('google-account-id', $user->fresh()->google_id);
    }

    public function test_authenticated_user_can_link_a_google_account_in_the_callback(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'auth_provider' => 'password',
        ]);
        $provider = Mockery::mock();
        $googleUser = Mockery::mock();

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        $googleUser->shouldReceive('getId')->once()->andReturn('linked-google-id');

        $this->actingAs($user)
            ->withSession(['google_linking' => true])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'google-account-linked');

        $this->assertSame('linked-google-id', $user->fresh()->google_id);
    }

    public function test_google_callback_does_not_create_a_duplicate_email_account(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'auth_provider' => 'password',
        ]);
        $provider = Mockery::mock();
        $googleUser = Mockery::mock();

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        $googleUser->shouldReceive('getId')->once()->andReturn('new-google-id');
        $googleUser->shouldReceive('getEmail')->once()->andReturn($existingUser->email);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'error',
                'An account with this email already exists. Sign in with your password and connect Google from Profile settings.'
            );

        $this->assertDatabaseCount('users', 1);
    }

    public function test_passenger_profile_shows_google_connection_action(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Linked Accounts')
            ->assertSee('Not connected')
            ->assertSee('Connect Google');
    }

    public function test_driver_profile_shows_connected_google_account(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
            'auth_provider' => 'password',
            'google_id' => 'connected-google-id',
        ]);

        $this->actingAs($user)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertSee('Disconnect');
    }
}
