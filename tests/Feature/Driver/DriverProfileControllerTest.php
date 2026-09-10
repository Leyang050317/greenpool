<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_view_their_profile_and_default_preferences_are_created(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->get(route('driver.profile.edit'))
            ->assertOk()
            ->assertViewHas('user', fn (User $user) => $user->is($driver))
            ->assertSee('<title>Profile - GreenPool</title>', false)
            ->assertSee('Personal Info')
            ->assertSee('My Vehicles')
            ->assertSee('Driving Preferences');

        $this->assertDatabaseHas('driver_preferences', [
            'user_id' => $driver->id,
            'smoking_allowed' => false,
            'pets_allowed' => false,
            'conversation_preference' => 'Moderate',
        ]);
    }

    public function test_passenger_cannot_access_driver_profile_routes(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($passenger)
            ->get(route('driver.profile.edit'))
            ->assertForbidden();
    }

    public function test_driver_can_update_profile_name_without_submitting_email(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver@example.com',
        ]);

        $this->actingAs($driver)
            ->patch(route('driver.profile.update'), ['name' => 'Updated Driver'])
            ->assertRedirect(route('driver.profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'name' => 'Updated Driver',
            'email' => 'driver@example.com',
        ]);
    }

    public function test_driver_can_update_driving_preferences(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->put(route('driver.profile.preferences.update'), [
                'smoking_allowed' => true,
                'pets_allowed' => true,
                'conversation_preference' => 'Chatty',
            ])
            ->assertRedirect(route('driver.profile.edit'));

        $this->assertDatabaseHas('driver_preferences', [
            'user_id' => $driver->id,
            'smoking_allowed' => true,
            'pets_allowed' => true,
            'conversation_preference' => 'Chatty',
        ]);
    }

    public function test_driver_can_upload_and_verify_driving_licence(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $licenceImage = UploadedFile::fake()->image('licence.jpg', 800, 500);

        $this->actingAs($driver)
            ->withSession(['driver_licence_ocr' => [
                'hash' => hash_file('sha256', $licenceImage->getRealPath()),
                'fields' => [
                    'name' => 'ER KIM WEN',
                    'identity_no' => '991109040290',
                    'licence_class' => 'D',
                    'valid_from' => now()->subYear()->toDateString(),
                    'valid_until' => now()->addYear()->toDateString(),
                ],
            ]])
            ->put(route('driver.profile.driving-licence.update'), [
                'driving_licence' => $licenceImage,
                'holder_name' => ' er   kim wen ',
                'identity_no' => '991109040290',
                'licence_class' => 'D',
                'valid_from' => now()->subYear()->toDateString(),
                'valid_until' => now()->addYear()->toDateString(),
            ])->assertRedirect(route('driver.profile.edit', ['section' => 'licence']));

        $licence = $driver->driverLicence()->firstOrFail();
        $this->assertSame('Verified', $licence->verification_status);
        $this->assertTrue($licence->isValidOn(now()));
        Storage::disk('local')->assertExists($licence->image_path);
        $this->actingAs($driver)
            ->get(route('driver.profile.driving-licence.image'))
            ->assertOk();
    }

    public function test_new_driver_cannot_register_an_identity_number_used_by_another_driver(): void
    {
        Storage::fake('local');
        $existingDriver = User::factory()->create(['role' => 'driver', 'name' => 'EXISTING DRIVER']);
        $existingDriver->driverLicence()->create([
            'image_path' => 'driver-licences/existing.jpg',
            'holder_name' => 'EXISTING DRIVER',
            'identity_no' => '991109040290',
            'licence_class' => 'D',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYear(),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'NEW DRIVER']);
        $licenceImage = UploadedFile::fake()->image('licence.jpg', 800, 500);
        $fields = $this->licenceFields($driver, '991109040290');
        $profileUrl = route('driver.profile.edit', ['section' => 'licence']);

        $this->actingAs($driver)
            ->from($profileUrl)
            ->withSession(['driver_licence_ocr' => $this->licenceScan($licenceImage, $fields)])
            ->put(route('driver.profile.driving-licence.update'), [
                'driving_licence' => $licenceImage,
                ...$fields,
            ])
            ->assertRedirect($profileUrl)
            ->assertSessionHasErrors([
                'identity_no' => 'This identity number has already been registered by another driver.',
            ]);

        $this->assertDatabaseCount('driver_licences', 1);
        $this->assertFalse($driver->driverLicence()->exists());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_driver_can_update_their_own_licence_without_changing_identity_number(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        Storage::disk('local')->put('driver-licences/original.jpg', 'old licence');
        $licence = $driver->driverLicence()->create([
            'image_path' => 'driver-licences/original.jpg',
            'holder_name' => $driver->name,
            'identity_no' => '991109040290',
            'licence_class' => 'D',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYear(),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);
        $licenceImage = UploadedFile::fake()->image('renewed-licence.jpg', 800, 500);
        $fields = $this->licenceFields($driver, '991109040290');

        $this->actingAs($driver)
            ->withSession(['driver_licence_ocr' => $this->licenceScan($licenceImage, $fields)])
            ->put(route('driver.profile.driving-licence.update'), [
                'driving_licence' => $licenceImage,
                ...$fields,
            ])
            ->assertRedirect(route('driver.profile.edit', ['section' => 'licence']))
            ->assertSessionHasNoErrors();

        $freshLicence = $licence->fresh();
        $this->assertDatabaseCount('driver_licences', 1);
        $this->assertSame('991109040290', $freshLicence->identity_no);
        $this->assertNotSame('driver-licences/original.jpg', $freshLicence->image_path);
        Storage::disk('local')->assertMissing('driver-licences/original.jpg');
        Storage::disk('local')->assertExists($freshLicence->image_path);
    }

    public function test_driver_can_register_a_new_unique_identity_number(): void
    {
        Storage::fake('local');
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $otherDriver->driverLicence()->create([
            'image_path' => 'driver-licences/other.jpg',
            'holder_name' => $otherDriver->name,
            'identity_no' => '880808080808',
            'licence_class' => 'D',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYear(),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'UNIQUE DRIVER']);
        $licenceImage = UploadedFile::fake()->image('unique-licence.jpg', 800, 500);
        $fields = $this->licenceFields($driver, '991109040290');

        $this->actingAs($driver)
            ->withSession(['driver_licence_ocr' => $this->licenceScan($licenceImage, $fields)])
            ->put(route('driver.profile.driving-licence.update'), [
                'driving_licence' => $licenceImage,
                ...$fields,
            ])
            ->assertRedirect(route('driver.profile.edit', ['section' => 'licence']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('driver_licences', 2);
        $this->assertDatabaseHas('driver_licences', [
            'user_id' => $driver->id,
            'identity_no' => '991109040290',
        ]);
    }

    public function test_driver_must_scan_a_new_driving_licence_before_saving(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $this->actingAs($driver)->put(route('driver.profile.driving-licence.update'), [
            'driving_licence' => UploadedFile::fake()->image('licence.jpg', 800, 500),
            'holder_name' => 'ER KIM WEN',
            'identity_no' => '991109040290',
            'licence_class' => 'D',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
        ])->assertSessionHasErrors('driving_licence');

        $this->assertDatabaseCount('driver_licences', 0);
    }

    public function test_driver_cannot_change_details_extracted_from_the_licence_scan(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $licenceImage = UploadedFile::fake()->image('licence.jpg', 800, 500);
        $validFrom = now()->subYear()->toDateString();
        $validUntil = now()->addYear()->toDateString();

        $this->actingAs($driver)
            ->withSession(['driver_licence_ocr' => [
                'hash' => hash_file('sha256', $licenceImage->getRealPath()),
                'fields' => [
                    'name' => 'ER KIM WEN',
                    'identity_no' => '991109040290',
                    'licence_class' => 'D',
                    'valid_from' => $validFrom,
                    'valid_until' => $validUntil,
                ],
            ]])
            ->put(route('driver.profile.driving-licence.update'), [
                'driving_licence' => $licenceImage,
                'holder_name' => 'ER KIM WEN',
                'identity_no' => '991109040290',
                'licence_class' => 'B2',
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ])
            ->assertSessionHasErrors('licence_class');

        $this->assertDatabaseCount('driver_licences', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_expired_or_mismatched_driving_licence_is_rejected(): void
    {
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $this->actingAs($driver)->put(route('driver.profile.driving-licence.update'), [
            'driving_licence' => UploadedFile::fake()->image('licence.jpg', 800, 500),
            'holder_name' => 'ANOTHER DRIVER',
            'identity_no' => '991109040290',
            'valid_from' => '2020-01-01',
            'valid_until' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors(['holder_name', 'valid_until']);

        $this->assertDatabaseCount('driver_licences', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function licenceFields(User $driver, string $identityNumber): array
    {
        return [
            'holder_name' => $driver->name,
            'identity_no' => $identityNumber,
            'licence_class' => 'D',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
        ];
    }

    private function licenceScan(UploadedFile $image, array $fields): array
    {
        return [
            'hash' => hash_file('sha256', $image->getRealPath()),
            'fields' => [
                'name' => $fields['holder_name'],
                'identity_no' => $fields['identity_no'],
                'licence_class' => $fields['licence_class'],
                'valid_from' => $fields['valid_from'],
                'valid_until' => $fields['valid_until'],
            ],
        ];
    }
}
