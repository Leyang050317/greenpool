<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_normalized_names_and_identity_numbers_can_be_saved(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $response = $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'registered_owner_name' => ' ER   KIM WEN ',
            'licence_name' => 'er kim wen',
        ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('driver.vehicles.index'));
        $this->assertDatabaseHas('vehicles', [
            'registered_owner_name' => 'ER KIM WEN',
            'licence_name' => 'ER KIM WEN',
            'owner_identity_no' => '991109040290',
            'verification_status' => 'Verified',
        ]);
    }

    public function test_different_owner_name_is_rejected_and_not_saved(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'registered_owner_name' => 'ER KIM WEI',
        ]))->assertSessionHasErrors('registered_owner_name');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_different_or_malformed_identity_number_is_rejected(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'owner_identity_no' => '991109-04-0290',
            'licence_identity_no' => '991109040291',
        ]))->assertSessionHasErrors('owner_identity_no');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_expired_driving_licence_is_rejected_with_clear_error(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ER KIM WEN']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload([
            'licence_valid_from' => '2018-04-20',
            'licence_valid_until' => '2024-04-06',
        ]))->assertSessionHasErrors([
            'licence_valid_until' => 'Driving licence has expired. Upload a renewed licence and scan it again.',
        ]);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_document_names_must_match_driver_account_name(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'ANOTHER DRIVER']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), $this->payload())
            ->assertSessionHasErrors(['registered_owner_name', 'licence_name']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'plate_number' => 'VAB 1234', 'brand' => 'Proton', 'model' => 'Saga',
            'colour' => 'Silver', 'seat_capacity' => 4,
            'front_image' => UploadedFile::fake()->image('front.jpg', 800, 450),
            'rear_image' => UploadedFile::fake()->image('rear.jpg', 800, 450),
            'side_image' => UploadedFile::fake()->image('side.jpg', 800, 450),
            'vehicle_geran' => UploadedFile::fake()->image('geran.jpg', 500, 300),
            'driving_licence' => UploadedFile::fake()->image('licence.jpg', 500, 300),
            'registered_owner_name' => 'ER KIM WEN', 'owner_identity_no' => '991109040290',
            'licence_name' => 'ER KIM WEN', 'licence_identity_no' => '991109040290',
        ], $overrides);
    }
}
