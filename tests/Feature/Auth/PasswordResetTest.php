<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\GreenPoolResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GreenPoolResetPassword::class);
    }

    public function test_reset_password_response_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'unknown@example.test']);

        $known->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'If an account exists for this email address, we have sent a password reset link.')
            ->assertSessionHasNoErrors();
        $unknown->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'If an account exists for this email address, we have sent a password reset link.')
            ->assertSessionHasNoErrors();
        Notification::assertSentTo($user, GreenPoolResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GreenPoolResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, GreenPoolResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
