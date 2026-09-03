<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_authenticated_user_cannot_update_a_password(): void
    {
        $user = User::factory()->create(['auth_provider' => 'google']);
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->withSession(['auth_provider' => 'google'])
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden();

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_google_authenticated_user_does_not_see_password_controls(): void
    {
        $user = User::factory()->create([
            'role' => 'driver',
            'auth_provider' => 'google',
        ]);

        $this->actingAs($user)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertDontSee('Change Password')
            ->assertDontSee('Update Password')
            ->assertSee('Account Type')
            ->assertSee('Google account');
    }

    public function test_password_authenticated_user_can_still_update_a_password(): void
    {
        $user = User::factory()->create(['auth_provider' => 'password']);

        $this->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
