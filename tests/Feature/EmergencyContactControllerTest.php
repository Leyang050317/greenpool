<?php

namespace Tests\Feature;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyContactControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_create_update_and_delete_an_emergency_contact(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($user)
            ->post(route('emergency-contacts.store'), [
                'name' => 'Jamie Tan',
                'relationship' => 'Friend',
                'phone_number' => '+60 12-345 6789',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'emergency-contact-created');

        $contact = EmergencyContact::firstOrFail();

        $this->patch(route('emergency-contacts.update', $contact), [
            'name' => 'Jamie Tan',
            'relationship' => 'Sibling',
            'phone_number' => '+60 19-876 5432',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'emergency-contact-updated');

        $this->assertDatabaseHas('emergency_contacts', [
            'id' => $contact->id,
            'relationship' => 'Sibling',
            'phone_number' => '+60 19-876 5432',
        ]);

        $this->delete(route('emergency-contacts.destroy', $contact))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'emergency-contact-deleted');

        $this->assertDatabaseMissing('emergency_contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_add_more_than_three_emergency_contacts(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);
        $user->emergencyContacts()->createMany([
            ['name' => 'Contact One', 'relationship' => 'Friend', 'phone_number' => '+60 11-111 1111'],
            ['name' => 'Contact Two', 'relationship' => 'Friend', 'phone_number' => '+60 12-222 2222'],
            ['name' => 'Contact Three', 'relationship' => 'Friend', 'phone_number' => '+60 13-333 3333'],
        ]);

        $this->actingAs($user)
            ->post(route('emergency-contacts.store'), [
                'name' => 'Contact Four',
                'relationship' => 'Friend',
                'phone_number' => '+60 14-444 4444',
            ])
            ->assertSessionHasErrorsIn('emergencyContact', 'emergency_contacts');

        $this->assertDatabaseCount('emergency_contacts', 3);
    }

    public function test_user_cannot_update_another_users_emergency_contact(): void
    {
        $owner = User::factory()->create();
        $contact = $owner->emergencyContacts()->create([
            'name' => 'Owner Contact',
            'relationship' => 'Parent',
            'phone_number' => '+60 12-345 6789',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->patch(route('emergency-contacts.update', $contact), [
                'name' => 'Changed Name',
                'relationship' => 'Friend',
                'phone_number' => '+60 19-876 5432',
            ])
            ->assertForbidden();
    }
}
