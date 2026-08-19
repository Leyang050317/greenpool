<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_without_optional_data_is_forty_percent_complete(): void
    {
        $user = User::factory()->create();

        $this->assertSame([
            'percentage' => 40,
            'next_action_hint' => 'Upload a profile photo to reach 60%.',
        ], $user->profileCompleteness());
    }

    public function test_profile_hint_moves_to_phone_number_after_photo_is_added(): void
    {
        $user = User::factory()->create(['photo' => 'profile-photos/avatar.jpg']);

        $this->assertSame([
            'percentage' => 60,
            'next_action_hint' => 'Add your phone number to reach 80%.',
        ], $user->profileCompleteness());
    }

    public function test_google_account_or_emergency_contact_completes_the_final_twenty_percent(): void
    {
        $user = User::factory()->create([
            'photo' => 'profile-photos/avatar.jpg',
            'phone_number' => '+60123456789',
            'phone_verified_at' => now(),
        ]);

        $user->emergencyContacts()->create([
            'name' => 'Taylor Lee',
            'relationship' => 'Friend',
            'phone_number' => '+60129876543',
        ]);

        $this->assertSame([
            'percentage' => 100,
            'next_action_hint' => null,
        ], $user->profileCompleteness());
    }

    public function test_unverified_phone_number_does_not_count_toward_profile_completeness(): void
    {
        $user = User::factory()->create([
            'photo' => 'profile-photos/avatar.jpg',
            'phone_number' => '+60123456789',
            'google_id' => 'google-account-id',
            'phone_verified_at' => null,
        ]);

        $this->assertSame([
            'percentage' => 80,
            'next_action_hint' => 'Verify your phone number to reach 100%.',
        ], $user->profileCompleteness());
    }

    public function test_passenger_profile_controller_passes_completeness_data_to_the_view(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile Completeness')
            ->assertSee('40%')
            ->assertViewHas('profileCompleteness', [
                'percentage' => 40,
                'next_action_hint' => 'Upload a profile photo to reach 60%.',
            ]);
    }

    public function test_driver_profile_controller_passes_completeness_data_to_the_view(): void
    {
        $user = User::factory()->create(['role' => 'driver']);

        $this->actingAs($user)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertSee('Profile Completeness')
            ->assertSee('40%')
            ->assertViewHas('profileCompleteness', [
                'percentage' => 40,
                'next_action_hint' => 'Upload a profile photo to reach 60%.',
            ]);
    }
}
