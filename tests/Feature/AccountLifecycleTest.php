<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_password_user_can_confirm_and_reactivate_within_thirty_days(): void
    {
        $user = User::factory()->create(['account_status' => 'deactivated', 'deactivated_at' => now()]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.reactivate.show'));

        $this->post(route('account.reactivate'))
            ->assertRedirect('/');

        $this->assertSame('active', $user->fresh()->account_status);
        $this->assertNull($user->fresh()->deactivated_at);
    }

    public function test_deactivated_user_cannot_open_active_account_pages_before_reactivation(): void
    {
        $user = User::factory()->create(['account_status' => 'deactivated', 'deactivated_at' => now()]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('account.reactivate.show'));
    }

    public function test_driver_deactivation_inactivates_vehicles_and_cancels_scheduled_trips_and_bookings(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Kuala Lumpur',
            'destination' => 'Shah Alam',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 5,
            'status' => 'Scheduled',
        ]);
        $booking = Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => 'Accepted',
            'number_of_seats' => 1,
            'number_of_luggage' => 0,
            'pickup_point' => 'KL Sentral',
        ]);

        $this->actingAs($driver)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertSame('deactivated', $driver->fresh()->account_status);
        $this->assertSame('Inactive', $vehicle->fresh()->status);
        $this->assertSame('Cancelled', $trip->fresh()->status);
        $this->assertSame('Cancelled', $booking->fresh()->booking_status);
    }

    public function test_accounts_deactivated_for_thirty_days_are_anonymized_but_retained(): void
    {
        $user = User::factory()->create([
            'name' => 'Former User',
            'account_status' => 'deactivated',
            'deactivated_at' => now()->subDays(30),
            'phone_number' => '+60123456789',
        ]);

        Artisan::call('accounts:close-deactivated');

        $user->refresh();

        $this->assertSame('permanently_closed', $user->account_status);
        $this->assertSame('Deactivated User', $user->name);
        $this->assertNull($user->phone_number);
        $this->assertNotNull($user->permanently_closed_at);
    }
}
