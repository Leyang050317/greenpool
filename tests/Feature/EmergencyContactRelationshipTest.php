<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyContactRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_emergency_contacts(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+60123456789',
            'phone_verified_at' => now(),
        ]);

        $contact = $user->emergencyContacts()->create([
            'name' => 'Jamie Tan',
            'relationship' => 'Friend',
            'phone_number' => '+60129876543',
        ]);

        $this->assertTrue($contact->user->is($user));
        $this->assertSame('+60123456789', $user->fresh()->phone_number);
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertCount(1, $user->fresh()->emergencyContacts);
    }
}
