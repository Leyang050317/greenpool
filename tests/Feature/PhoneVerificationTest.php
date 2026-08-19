<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_phone_verification_code(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'phone_number' => null,
            'phone_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('phone-verification.send'), [
                'phone_number' => '+60 12-345 6789',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'phone-otp-sent');

        $this->assertSame('+60 12-345 6789', $user->fresh()->phone_number);
        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertSame('+60 12-345 6789', Cache::get('phone-verification:'.$user->id)['phone_number']);
    }

    public function test_user_can_verify_a_phone_number_with_a_valid_code(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'phone_number' => '+60 12-345 6789',
            'phone_verified_at' => null,
        ]);
        Cache::put('phone-verification:'.$user->id, [
            'code' => '123456',
            'phone_number' => $user->phone_number,
        ], now()->addMinutes(5));

        $this->actingAs($user)
            ->post(route('phone-verification.verify'), [
                'otp' => '123456',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'phone-verified');

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertNull(Cache::get('phone-verification:'.$user->id));
    }

    public function test_invalid_phone_verification_code_does_not_verify_the_number(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'phone_number' => '+60 12-345 6789',
            'phone_verified_at' => null,
        ]);
        Cache::put('phone-verification:'.$user->id, [
            'code' => '123456',
            'phone_number' => $user->phone_number,
        ], now()->addMinutes(5));

        $this->actingAs($user)
            ->post(route('phone-verification.verify'), [
                'otp' => '000000',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('otp');

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_verified_phone_number_requires_confirmation_before_it_can_be_changed(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'phone_number' => '+60 12-345 6789',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('phone-verification.send'), [
                'phone_number' => '+60 19-876 5432',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('phone_number');

        $this->assertSame('+60 12-345 6789', $user->fresh()->phone_number);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_verified_phone_number_can_be_changed_after_confirmation(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger',
            'phone_number' => '+60 12-345 6789',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('phone-verification.send'), [
                'phone_number' => '+60 19-876 5432',
                'confirm_phone_change' => '1',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'phone-otp-sent');

        $this->assertSame('+60 19-876 5432', $user->fresh()->phone_number);
        $this->assertNull($user->fresh()->phone_verified_at);
    }
}
