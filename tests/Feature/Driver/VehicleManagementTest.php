<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_view_only_their_vehicles(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $ownedVehicle = Vehicle::factory()->create(['user_id' => $driver->id]);
        $otherVehicle = Vehicle::factory()->create(['user_id' => $otherDriver->id]);

        $response = $this->actingAs($driver)->get(route('driver.vehicles.index'));

        $response
            ->assertOk()
            ->assertSee($ownedVehicle->plate_number)
            ->assertDontSee($otherVehicle->plate_number);
    }

    public function test_passenger_cannot_access_vehicle_management(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($passenger)
            ->get(route('driver.vehicles.index'))
            ->assertForbidden();
    }

    public function test_driver_can_add_a_vehicle(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($driver)->post(route('driver.vehicles.store'), [
            'plate_number' => 'vab 1234',
            'brand' => 'Perodua',
            'model' => 'Myvi',
            'colour' => 'Silver',
            'seat_capacity' => 4,
            'vehicle_image' => UploadedFile::fake()->image('myvi.jpg'),
        ]);

        $vehicle = Vehicle::query()->firstOrFail();

        $response
            ->assertRedirect(route('driver.vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $driver->id,
            'plate_number' => 'VAB 1234',
            'status' => 'Inactive',
            'verification_status' => 'Pending',
        ]);
        Storage::disk('public')->assertExists($vehicle->vehicle_image_path);
    }

    public function test_vehicle_validation_is_enforced(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->from(route('driver.vehicles.create'))
            ->post(route('driver.vehicles.store'), [
                'plate_number' => '',
                'brand' => '',
                'model' => '',
                'colour' => '',
                'seat_capacity' => 5,
            ])
            ->assertRedirect(route('driver.vehicles.create'))
            ->assertSessionHasErrors([
                'plate_number',
                'brand',
                'model',
                'colour',
                'seat_capacity',
                'vehicle_image',
            ]);
    }

    public function test_driver_can_update_their_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)
            ->put(route('driver.vehicles.update', $vehicle), [
                'plate_number' => $vehicle->plate_number,
                'brand' => 'Toyota',
                'model' => 'Vios',
                'colour' => 'White',
                'seat_capacity' => 3,
            ])
            ->assertRedirect(route('driver.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $vehicle->vehicle_id,
            'brand' => 'Toyota',
            'model' => 'Vios',
            'seat_capacity' => 3,
        ]);
    }

    public function test_driver_cannot_view_or_change_another_drivers_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $otherDriver->id]);

        $this->actingAs($driver)
            ->get(route('driver.vehicles.show', $vehicle))
            ->assertForbidden();

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.activate', $vehicle))
            ->assertForbidden();

        $this->actingAs($driver)
            ->delete(route('driver.vehicles.destroy', $vehicle))
            ->assertForbidden();

        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $vehicle->vehicle_id]);
    }

    public function test_driver_can_activate_and_deactivate_a_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.activate', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $vehicle->vehicle_id,
            'status' => 'Active',
        ]);

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.deactivate', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $vehicle->vehicle_id,
            'status' => 'Inactive',
        ]);
    }

    public function test_activating_a_vehicle_deactivates_the_drivers_previous_active_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $previousActive = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'status' => 'Active',
        ]);
        $newActive = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'status' => 'Inactive',
        ]);

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.activate', $newActive))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $previousActive->vehicle_id,
            'status' => 'Inactive',
        ]);
        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $newActive->vehicle_id,
            'status' => 'Active',
        ]);
        $this->assertSame(
            1,
            Vehicle::query()->where('user_id', $driver->id)->where('status', 'Active')->count()
        );
    }

    public function test_driver_can_delete_their_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)
            ->delete(route('driver.vehicles.destroy', $vehicle))
            ->assertRedirect(route('driver.vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vehicles', ['vehicle_id' => $vehicle->vehicle_id]);
    }

    public function test_json_endpoints_return_vehicle_data(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)
            ->getJson(route('driver.vehicles.show', $vehicle))
            ->assertOk()
            ->assertJsonPath('data.vehicle_id', $vehicle->vehicle_id);

        $this->actingAs($driver)
            ->patchJson(route('driver.vehicles.activate', $vehicle))
            ->assertOk()
            ->assertJsonPath('data.status', 'Active');
    }

    public function test_driver_can_add_vehicles_with_supported_picture_formats(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);
        $files = [
            'JPG' => UploadedFile::fake()->image('vehicle.jpg'),
            'PNG' => UploadedFile::fake()->image('vehicle.png'),
            'WEBP' => UploadedFile::fake()->createWithContent(
                'vehicle.webp',
                base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEALmk0mk0iIiIiIgBoSygABc6zbAAA')
            ),
        ];

        foreach (array_values($files) as $index => $file) {
            $response = $this->actingAs($driver)->post(route('driver.vehicles.store'), [
                ...$this->validVehicleData('IMG '.($index + 1)),
                'vehicle_image' => $file,
            ]);

            $response->assertSessionHasNoErrors();
        }

        $this->assertCount(3, Vehicle::all());
        Vehicle::each(fn (Vehicle $vehicle) => Storage::disk('public')->assertExists($vehicle->vehicle_image_path));
    }

    public function test_vehicle_picture_rejects_unsupported_files_and_files_over_five_megabytes(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)->post(route('driver.vehicles.store'), [
            ...$this->validVehicleData('BAD 1'),
            'vehicle_image' => UploadedFile::fake()->create('vehicle.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('vehicle_image');

        $this->actingAs($driver)->post(route('driver.vehicles.store'), [
            ...$this->validVehicleData('BAD 2'),
            'vehicle_image' => UploadedFile::fake()->create('vehicle.jpg', 5121, 'image/jpeg'),
        ])->assertSessionHasErrors('vehicle_image');
    }

    public function test_vehicle_picture_is_displayed_on_list_and_details_pages(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/example.jpg',
        ]);

        $this->actingAs($driver)->get(route('driver.vehicles.index'))
            ->assertOk()
            ->assertSee('storage/vehicles/example.jpg', false);

        $this->actingAs($driver)->get(route('driver.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('storage/vehicles/example.jpg', false);
    }

    public function test_legacy_vehicle_without_picture_uses_the_placeholder(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Vehicle::factory()->create(['user_id' => $driver->id, 'vehicle_image_path' => null]);

        $this->actingAs($driver)->get(route('driver.vehicles.index'))
            ->assertOk()
            ->assertDontSee('storage/vehicles/', false);
    }

    public function test_driver_can_edit_without_replacing_the_picture(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/original.jpg', 'original');
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/original.jpg',
        ]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'seat_capacity' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $vehicle->vehicle_id,
            'vehicle_image_path' => 'vehicles/original.jpg',
            'verification_status' => 'Verified',
        ]);
        Storage::disk('public')->assertExists('vehicles/original.jpg');
    }

    public function test_driver_can_replace_picture_and_old_picture_is_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/old.jpg', 'old');
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/old.jpg',
        ]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'vehicle_image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors();

        $vehicle->refresh();
        $this->assertNotSame('vehicles/old.jpg', $vehicle->vehicle_image_path);
        $this->assertSame('Pending', $vehicle->verification_status);
        $this->assertNull($vehicle->verified_at);
        Storage::disk('public')->assertMissing('vehicles/old.jpg');
        Storage::disk('public')->assertExists($vehicle->vehicle_image_path);
    }

    public function test_identity_edit_resets_verification_but_seat_only_edit_preserves_it(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'brand' => $vehicle->brand === 'Toyota' ? 'Honda' : 'Toyota',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Pending', $vehicle->refresh()->verification_status);
        $this->assertNull($vehicle->verified_at);

        $vehicle->update(['verification_status' => 'Verified', 'verified_at' => now()]);
        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'seat_capacity' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Verified', $vehicle->refresh()->verification_status);
    }

    public function test_deleting_vehicle_removes_picture_and_missing_picture_does_not_block_deletion(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/delete.jpg', 'image');
        $driver = User::factory()->create(['role' => 'driver']);
        $withImage = Vehicle::factory()->create(['user_id' => $driver->id, 'vehicle_image_path' => 'vehicles/delete.jpg']);
        $missingImage = Vehicle::factory()->create(['user_id' => $driver->id, 'vehicle_image_path' => 'vehicles/missing.jpg']);

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $withImage))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('vehicles/delete.jpg');

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $missingImage))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('vehicles', ['vehicle_id' => $missingImage->vehicle_id]);
    }

    public function test_driver_cannot_replace_another_drivers_vehicle_picture(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);
        $other = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $other->id]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'vehicle_image' => UploadedFile::fake()->image('unauthorized.jpg'),
        ])->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles('vehicles'));
    }

    public function test_only_active_verified_vehicles_are_selectable_for_trips(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $verified = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        Vehicle::factory()->create(['user_id' => $driver->id, 'status' => 'Active', 'verification_status' => 'Pending']);
        Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Inactive']);

        $this->assertTrue($driver->vehicles()->selectableForTrips()->get()->contains($verified));
        $this->assertCount(1, $driver->vehicles()->selectableForTrips()->get());
    }

    public function test_pending_vehicle_cannot_be_used_to_create_a_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'status' => 'Active',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($driver)->post(route('driver.trips.store'), [
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Kuala Lumpur',
            'destination' => 'Putrajaya',
            'departure_date' => now()->addDay()->toDateString(),
            'departure_time' => '10:00',
            'available_seats' => 2,
        ])->assertSessionHasErrors('vehicle_id');

        $this->assertDatabaseCount('trips', 0);
    }

    public function test_vehicle_with_upcoming_trip_cannot_be_deleted(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Kuala Lumpur',
            'destination' => 'Putrajaya',
            'departure_at' => now()->addDay(),
            'available_seats' => 2,
            'status' => 'Scheduled',
        ]);

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $vehicle))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('vehicles', ['vehicle_id' => $vehicle->vehicle_id]);
    }

    public function test_new_picture_is_removed_when_database_update_fails(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/original.jpg', 'original');
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/original.jpg',
        ]);

        Vehicle::updating(fn () => throw new RuntimeException('Forced update failure.'));

        try {
            $this->withoutExceptionHandling()->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
                ...$this->vehicleDataFrom($vehicle),
                'vehicle_image' => UploadedFile::fake()->image('new.jpg'),
            ]);
            $this->fail('The forced update failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced update failure.', $exception->getMessage());
        } finally {
            Vehicle::flushEventListeners();
        }

        $this->assertSame(['vehicles/original.jpg'], Storage::disk('public')->allFiles('vehicles'));
    }

    private function validVehicleData(string $plateNumber): array
    {
        return [
            'plate_number' => $plateNumber,
            'brand' => 'Perodua',
            'model' => 'Myvi',
            'colour' => 'Silver',
            'seat_capacity' => 4,
        ];
    }

    private function vehicleDataFrom(Vehicle $vehicle): array
    {
        return [
            'plate_number' => $vehicle->plate_number,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'colour' => $vehicle->colour,
            'seat_capacity' => $vehicle->seat_capacity,
        ];
    }
}
