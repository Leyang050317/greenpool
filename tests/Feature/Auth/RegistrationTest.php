<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'passenger',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_rejects_an_impractical_email_local_part(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => '!!@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'passenger',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => '!!@gmail.com']);
    }

    public function test_a_newly_registered_user_can_log_out_from_the_verification_notice(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'valid.user+test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'passenger',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticatedAs(User::where('email', 'valid.user+test@example.com')->firstOrFail());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_an_expired_csrf_session_recovers_to_login_instead_of_showing_a_419_page(): void
    {
        Route::middleware('web')->post('/testing/expired-session', function (): never {
            throw new TokenMismatchException;
        });
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/testing/expired-session')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your session expired. Please sign in and try again.');

        $this->assertGuest();
    }
}
