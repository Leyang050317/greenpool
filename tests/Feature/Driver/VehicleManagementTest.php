<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $driver = User::factory()->create(['role' => 'driver']);

        $response = $this->actingAs($driver)->post(route('driver.vehicles.store'), [
            'plate_number' => 'vab 1234',
            'brand' => 'Perodua',
            'model' => 'Myvi',
            'colour' => 'Silver',
            'seat_capacity' => 4,
        ]);

        $vehicle = Vehicle::query()->firstOrFail();

        $response
            ->assertRedirect(route('driver.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $driver->id,
            'plate_number' => 'VAB 1234',
            'status' => 'Inactive',
        ]);
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
}
