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

    public function test_driver_can_check_for_a_duplicate_plate_before_continuing(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        Vehicle::factory()->create(['plate_number' => 'VNU 8601']);

        $this->actingAs($driver)
            ->postJson(route('driver.vehicles.plate-availability'), ['plate_number' => 'vnu-8601'])
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('plate_number', 'VNU-8601');

        $this->actingAs($driver)
            ->postJson(route('driver.vehicles.plate-availability'), ['plate_number' => 'JQK1234'])
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('plate_number', 'JQK1234');
    }

    public function test_driver_can_add_a_vehicle(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);
        $this->addValidDrivingLicence($driver);

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

    public function test_driver_requires_a_valid_driving_licence_to_add_a_vehicle(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->get(route('driver.vehicles.create'))
            ->assertRedirect(route('driver.profile.edit', ['section' => 'licence']))
            ->assertSessionHas('error');

        $this->actingAs($driver)
            ->post(route('driver.vehicles.store'), [
                'plate_number' => 'VAB 1234',
                'brand' => 'Perodua',
                'model' => 'Myvi',
                'colour' => 'Silver',
                'seat_capacity' => 4,
                'vehicle_image' => UploadedFile::fake()->image('myvi.jpg'),
            ])
            ->assertSessionHasErrors('driver_licence');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_ajax_vehicle_creation_flashes_success_and_returns_the_vehicle_list_redirect(): void
    {
        Storage::fake('public');
        $driver = User::factory()->create(['role' => 'driver']);
        $this->addValidDrivingLicence($driver);

        $this->actingAs($driver)
            ->postJson(route('driver.vehicles.store'), [
                'plate_number' => 'JQK 8821',
                'brand' => 'Perodua',
                'model' => 'Myvi',
                'colour' => 'Silver',
                'seat_capacity' => 4,
                'vehicle_image' => UploadedFile::fake()->image('myvi.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('redirect_url', route('driver.vehicles.index', ['created' => 1]))
            ->assertSessionHas('success', 'Vehicle added successfully.');
    }

    public function test_vehicle_validation_is_enforced(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $this->addValidDrivingLicence($driver);

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
                ...$this->vehicleDataFrom($vehicle),
                'seat_capacity' => 3,
            ])
            ->assertRedirect(route('driver.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'vehicle_id' => $vehicle->vehicle_id,
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

        $this->assertSoftDeleted('vehicles', ['vehicle_id' => $vehicle->vehicle_id]);
        $this->assertSame('Inactive', $vehicle->fresh()->status);
        $this->assertNull(Vehicle::find($vehicle->vehicle_id));
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
        $this->addValidDrivingLicence($driver);
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
        $this->addValidDrivingLicence($driver);

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

    public function test_driver_can_revalidate_identity_changes_and_old_evidence_is_removed(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        foreach (['front', 'rear', 'side'] as $view) {
            Storage::disk('public')->put("vehicles/old-{$view}.jpg", 'old');
        }
        Storage::disk('local')->put('vehicle-documents/old-geran.jpg', 'old');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'TEST DRIVER']);
        $vehicle = Vehicle::factory()->verified()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/old-front.jpg',
            'front_image_path' => 'vehicles/old-front.jpg',
            'rear_image_path' => 'vehicles/old-rear.jpg',
            'side_image_path' => 'vehicles/old-side.jpg',
            'vehicle_geran_path' => 'vehicle-documents/old-geran.jpg',
        ]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->revalidationData($driver, $vehicle, ['plate_number' => 'NEW 1234']),
        ])->assertSessionHasNoErrors();

        $vehicle->refresh();
        $this->assertSame('NEW 1234', $vehicle->plate_number);
        $this->assertSame('Verified', $vehicle->verification_status);
        $this->assertNotNull($vehicle->verified_at);
        Storage::disk('public')->assertMissing('vehicles/old-front.jpg');
        Storage::disk('public')->assertMissing('vehicles/old-rear.jpg');
        Storage::disk('public')->assertMissing('vehicles/old-side.jpg');
        Storage::disk('local')->assertMissing('vehicle-documents/old-geran.jpg');
        Storage::disk('public')->assertExists($vehicle->front_image_path);
        Storage::disk('local')->assertExists($vehicle->vehicle_geran_path);
    }

    public function test_model_edit_requires_new_geran_but_seat_only_edit_preserves_verification(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'model' => $vehicle->model === 'Vios' ? 'Myvi' : 'Vios',
        ])->assertSessionHasErrors('vehicle_geran')
            ->assertSessionDoesntHaveErrors(['front_image', 'rear_image', 'side_image']);

        $this->assertSame('Verified', $vehicle->refresh()->verification_status);
        $this->assertNotNull($vehicle->verified_at);

        $vehicle->update(['verification_status' => 'Verified', 'verified_at' => now()]);
        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'seat_capacity' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Verified', $vehicle->refresh()->verification_status);
    }

    public function test_vehicle_brand_cannot_be_updated(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'brand' => $vehicle->brand === 'Toyota' ? 'Honda' : 'Toyota',
        ])->assertSessionHasErrors('brand');

        $this->assertSame($vehicle->brand, $vehicle->fresh()->brand);
    }

    public function test_colour_edit_requires_vehicle_photos_but_not_geran(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id]);

        $this->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
            ...$this->vehicleDataFrom($vehicle),
            'colour' => mb_strtoupper($vehicle->colour) === 'BLACK' ? 'WHITE' : 'BLACK',
        ])->assertSessionHasErrors(['front_image', 'rear_image', 'side_image'])
            ->assertSessionDoesntHaveErrors('vehicle_geran');

        $this->assertSame('Verified', $vehicle->refresh()->verification_status);
    }

    public function test_soft_deleting_vehicle_retains_picture_and_hides_vehicle_from_normal_access(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicles/delete.jpg', 'image');
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id, 'vehicle_image_path' => 'vehicles/delete.jpg']);

        $this->actingAs($driver)->delete(route('driver.vehicles.destroy', $vehicle))->assertSessionHasNoErrors();

        $this->assertSoftDeleted('vehicles', ['vehicle_id' => $vehicle->vehicle_id]);
        Storage::disk('public')->assertExists('vehicles/delete.jpg');
        $this->actingAs($driver)->get(route('driver.vehicles.index'))->assertDontSee($vehicle->plate_number);
        $this->actingAs($driver)->get(route('driver.vehicles.show', $vehicle->vehicle_id))->assertNotFound();
    }

    public function test_soft_deleted_plate_number_remains_unavailable(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id, 'plate_number' => 'VNU 8601']);
        $vehicle->delete();

        $this->actingAs($driver)
            ->postJson(route('driver.vehicles.plate-availability'), ['plate_number' => 'vnu-8601'])
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_completed_trip_retains_its_soft_deleted_vehicle_relationship(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id]);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Kuala Lumpur',
            'destination' => 'Putrajaya',
            'departure_at' => now()->subDay(),
            'available_seats' => 2,
            'status' => 'Completed',
        ]);

        $vehicle->delete();

        $this->assertSame($vehicle->vehicle_id, $trip->fresh()->vehicle->vehicle_id);
        $this->assertNotNull($trip->vehicle->deleted_at);
    }

    public function test_driver_can_view_and_restore_their_archived_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create([
            'user_id' => $driver->id,
            'status' => 'Active',
        ]);
        $vehicle->delete();

        $this->actingAs($driver)
            ->get(route('driver.vehicles.archived'))
            ->assertOk()
            ->assertSee($vehicle->plate_number);

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.restore', $vehicle->vehicle_id))
            ->assertRedirect(route('driver.vehicles.index'))
            ->assertSessionHas('success');

        $restored = Vehicle::findOrFail($vehicle->vehicle_id);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('Inactive', $restored->status);
        $this->assertSame('Verified', $restored->verification_status);
    }

    public function test_restoring_vehicle_does_not_use_legacy_vehicle_licence_fields(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create([
            'user_id' => $driver->id,
            'licence_valid_until' => now()->subDay()->toDateString(),
        ]);
        $vehicle->delete();

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.restore', $vehicle->vehicle_id))
            ->assertRedirect(route('driver.vehicles.index'));

        $restored = Vehicle::findOrFail($vehicle->vehicle_id);
        $this->assertSame('Verified', $restored->verification_status);
        $this->assertNotNull($restored->verified_at);
    }

    public function test_driver_cannot_view_or_restore_another_drivers_archived_vehicle(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->create(['user_id' => $otherDriver->id]);
        $vehicle->delete();

        $this->actingAs($driver)
            ->get(route('driver.vehicles.archived'))
            ->assertDontSee($vehicle->plate_number);

        $this->actingAs($driver)
            ->patch(route('driver.vehicles.restore', $vehicle->vehicle_id))
            ->assertForbidden();
        $this->assertTrue($vehicle->fresh()->trashed());
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

    public function test_pending_vehicle_can_be_selected_when_creating_a_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $this->addValidDrivingLicence($driver);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'status' => 'Active',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($driver)
            ->get(route('driver.trips.create'))
            ->assertOk()
            ->assertSee($vehicle->plate_number);
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
        Storage::fake('local');
        Storage::disk('public')->put('vehicles/original.jpg', 'original');
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'TEST DRIVER']);
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'vehicle_image_path' => 'vehicles/original.jpg',
        ]);

        Vehicle::updating(fn () => throw new RuntimeException('Forced update failure.'));

        try {
            $this->withoutExceptionHandling()->actingAs($driver)->put(route('driver.vehicles.update', $vehicle), [
                ...$this->revalidationData($driver, $vehicle, ['plate_number' => 'FAIL 123']),
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

    private function revalidationData(User $driver, Vehicle $vehicle, array $overrides = []): array
    {
        $geran = UploadedFile::fake()->image('geran.jpg', 500, 300);
        $data = [
            'plate_number' => 'ABC 1234',
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'colour' => $vehicle->colour,
            'seat_capacity' => 4,
            'front_image' => UploadedFile::fake()->image('front.jpg', 800, 450),
            'rear_image' => UploadedFile::fake()->image('rear.jpg', 800, 450),
            'side_image' => UploadedFile::fake()->image('side.jpg', 800, 450),
            'vehicle_geran' => $geran,
            'registered_owner_name' => $driver->name,
            'owner_identity_no' => '991109040290',
            'manufacturer' => $vehicle->brand,
            'model_name' => $vehicle->model,
        ];
        $data = [...$data, ...$overrides];
        $data['geran_plate_number'] = $data['plate_number'];
        $this->withSession(['vehicle_geran_ocr' => [
            'hash' => hash_file('sha256', $geran->getRealPath()),
            'fields' => [
                'registered_owner_name' => $data['registered_owner_name'],
                'owner_identity_no' => $data['owner_identity_no'],
                'manufacturer' => $data['manufacturer'],
                'model_name' => $data['model_name'],
                'registration_no' => $data['geran_plate_number'],
            ],
        ]]);

        return $data;
    }
}
