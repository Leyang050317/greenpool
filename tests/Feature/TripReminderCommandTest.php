<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripReminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TripReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TripReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_passenger_and_driver_receive_a_reminder(): void
    {
        [$driver, $trip] = $this->trip();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger, 'Accepted');

        Artisan::call('trips:send-reminders');

        $passengerNotification = $passenger->fresh()->notifications()->where('type', TripReminderNotification::class)->firstOrFail();
        $driverNotification = $driver->fresh()->notifications()->where('type', TripReminderNotification::class)->firstOrFail();
        $this->assertSame('Trip Reminder', $passengerNotification->data['title']);
        $this->assertSame($booking->id, $passengerNotification->data['booking_id']);
        $this->assertSame($trip->trip_id, $passengerNotification->data['trip_id']);
        $this->assertSame(route('passenger.bookings.show', $booking), $passengerNotification->data['url']);
        $this->assertNull($driverNotification->data['booking_id']);
        $this->assertSame(route('driver.trips.show', $trip), $driverNotification->data['url']);
        $this->assertDatabaseCount('trip_reminders', 2);
        $this->assertDatabaseHas('trip_reminders', ['trip_id' => $trip->trip_id, 'booking_id' => $booking->id, 'user_id' => $passenger->id, 'reminder_type' => 'departure_30_minutes']);
        $this->assertDatabaseHas('trip_reminders', ['trip_id' => $trip->trip_id, 'booking_id' => null, 'user_id' => $driver->id, 'reminder_type' => 'departure_30_minutes']);
    }

    public function test_multiple_accepted_passengers_each_receive_one_reminder(): void
    {
        [$driver, $trip] = $this->trip();
        $first = User::factory()->create(['role' => 'passenger']);
        $second = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $first, 'Accepted');
        $this->booking($trip, $second, 'Accepted');

        Artisan::call('trips:send-reminders');

        $this->assertSame(1, $first->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(1, $second->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(1, $driver->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertDatabaseCount('trip_reminders', 3);
    }

    public function test_rejected_and_cancelled_bookings_do_not_receive_reminders(): void
    {
        [$driver, $trip] = $this->trip();
        $accepted = User::factory()->create(['role' => 'passenger']);
        $rejected = User::factory()->create(['role' => 'passenger']);
        $cancelled = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $accepted, 'Accepted');
        $this->booking($trip, $rejected, 'Rejected');
        $this->booking($trip, $cancelled, 'Cancelled');

        Artisan::call('trips:send-reminders');

        $this->assertSame(1, $accepted->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(0, $rejected->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(0, $cancelled->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
    }

    public function test_cancelled_and_in_progress_trips_do_not_receive_reminders(): void
    {
        foreach (['Cancelled', 'In Progress'] as $status) {
            [$driver, $trip] = $this->trip(['status' => $status, 'started_at' => $status === 'In Progress' ? now() : null, 'cancelled_at' => $status === 'Cancelled' ? now() : null]);
            $passenger = User::factory()->create(['role' => 'passenger']);
            $this->booking($trip, $passenger, 'Accepted');
        }

        Artisan::call('trips:send-reminders');

        $this->assertDatabaseCount('trip_reminders', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_running_the_command_twice_does_not_create_duplicate_reminders(): void
    {
        [$driver, $trip] = $this->trip();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger, 'Accepted');

        Artisan::call('trips:send-reminders');
        Artisan::call('trips:send-reminders');

        $this->assertSame(1, $passenger->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(1, $driver->fresh()->notifications()->where('type', TripReminderNotification::class)->count());
        $this->assertSame(2, TripReminder::query()->count());
        $this->assertSame(2, TripReminder::query()->whereNotNull('sent_at')->count());
    }

    private function trip(array $overrides = []): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Suria KLCC',
            'destination' => 'KLIA',
            'departure_at' => now()->addMinutes(30),
            'available_seats' => 3,
            'price_per_passenger' => 12,
            'status' => 'Scheduled',
            ...$overrides,
        ]);

        return [$driver, $trip];
    }

    private function booking(Trip $trip, User $passenger, string $status): Booking
    {
        return Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => $status,
            'number_of_seats' => 1,
            'pickup_point' => 'Bukit Bintang',
        ]);
    }
}
