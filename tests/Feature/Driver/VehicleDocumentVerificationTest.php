<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use Tests\Concerns\CreatesVehicleValidationTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesVehicleValidationTokens;

    public function test_matching_normalized_geran_owner_name_can_be_saved(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $this->addValidDrivingLicence($driver);

        $response = $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'registered_owner_name' => ' ER   KIM WEN ',
        ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('driver.vehicles.index'));
        $this->assertDatabaseHas('vehicles', [
            'registered_owner_name' => 'ER KIM WEN',
            'owner_identity_no' => '991109040290',
            'brand' => 'PROTON',
            'model' => 'SAGA 1.3 PREMIUM',
            'verification_status' => 'Verified',
        ]);
    }

    public function test_different_owner_name_is_rejected_and_not_saved(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $this->addValidDrivingLicence($driver);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'registered_owner_name' => 'ER KIM WEI',
        ]))->assertSessionHasErrors('registered_owner_name');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_malformed_geran_identity_number_is_rejected(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $this->addValidDrivingLicence($driver);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'owner_identity_no' => '991109-04-0290',
        ]))->assertSessionHasErrors('owner_identity_no');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_geran_name_must_match_driver_account_name(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ANOTHER DRIVER']);
        $this->addValidDrivingLicence($driver);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload())
            ->assertSessionHasErrors(['registered_owner_name']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_vehicle_photo_plate_must_match_geran_registration_number(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);
        $this->addValidDrivingLicence($driver);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'plate_number' => 'VAB 1234',
            'geran_plate_number' => 'WXY 9876',
        ]))->assertSessionHasErrors('geran_plate_number');

        $this->assertDatabaseCount('vehicles', 0);
    }

    private function payload(array $overrides = []): array
    {
        return $this->withVehicleValidationTokens(array_replace([
            'plate_number' => 'VAB 1234', 'brand' => 'Proton', 'model' => 'Saga',
            'colour' => 'Silver', 'seat_capacity' => 4,
            'front_image' => UploadedFile::fake()->image('front.jpg', 800, 450),
            'rear_image' => UploadedFile::fake()->image('rear.jpg', 800, 450),
            'side_image' => UploadedFile::fake()->image('side.jpg', 800, 450),
            'vehicle_geran' => UploadedFile::fake()->image('geran.jpg', 500, 300),
            'geran_plate_number' => 'VAB 1234',
            'registered_owner_name' => 'ER KIM WEN', 'owner_identity_no' => '991109040290',
            'manufacturer' => 'PROTON', 'model_name' => 'SAGA 1.3 PREMIUM',
        ], $overrides));
    }

    private function addValidDrivingLicence(User $driver): void
    {
        $driver->driverLicence()->create([
            'image_path' => 'driver-licences/test.jpg',
            'holder_name' => $driver->name,
            'identity_no' => '991109040290',
            'licence_class' => 'D',
            'valid_from' => now()->subYear(),
            'valid_until' => now()->addYears(5),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);
    }
}
