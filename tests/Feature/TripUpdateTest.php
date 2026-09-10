<?php

namespace Tests\Feature;

use App\Events\InAppNotificationCreated;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TripUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TripUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_trip_update_notifies_accepted_passengers_without_creating_chat_messages(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        [$driver, $trip] = $this->trip();
        $accepted = User::factory()->create(['role' => 'passenger']);
        $pending = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $accepted, 'Accepted');
        $this->booking($trip, $pending, 'Pending');

        $this->actingAs($driver)->post(route('trips.updates.store', $trip), ['update_type' => 'traffic_delay'])
            ->assertRedirect()->assertSessionHas('success', 'Update sent.');

        $notification = $accepted->notifications()->where('type', TripUpdateNotification::class)->firstOrFail();
        $this->assertSame('Trip Update', $notification->data['title']);
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertStringContainsString('Traffic Delay', $notification->data['message']);
        $this->assertSame(0, $pending->notifications()->count());
        $this->assertDatabaseCount('messages', 0);
        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($accepted));
    }

    public function test_accepted_passenger_trip_update_notifies_driver(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        [$driver, $trip] = $this->trip();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger, 'Accepted');

        $this->actingAs($passenger)->post(route('trips.updates.store', $trip), ['update_type' => 'waiting'])->assertRedirect();

        $notification = $driver->notifications()->where('type', TripUpdateNotification::class)->firstOrFail();
        $this->assertStringContainsString("I'm Waiting", $notification->data['message']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_active_journey_exposes_inline_quick_replies_without_a_fixed_dialog(): void
    {
        [$driver, $trip] = $this->trip();
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger, 'Accepted');

        $this->actingAs($passenger)
            ->get(route('passenger.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Quick replies')
            ->assertSee('Need Pickup Clarification')
            ->assertSee('Trip quick replies')
            ->assertDontSee('Send Trip Update');
    }

    private function trip(): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create(['vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'Suria KLCC', 'destination' => 'KLIA', 'departure_at' => now(), 'available_seats' => 3, 'price_per_passenger' => 12, 'status' => 'In Progress', 'started_at' => now()]);

        return [$driver, $trip];
    }

    private function booking(Trip $trip, User $passenger, string $status): Booking
    {
        return Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => $status, 'number_of_seats' => 1, 'pickup_point' => 'Bukit Bintang']);
    }
}
