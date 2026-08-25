<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_participants_can_view_chat(): void
    {
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'Driver Chat']);
        $passenger = User::factory()->create(['role' => 'passenger', 'name' => 'Passenger Chat']);
        $trip = $this->createTrip($driver);
        $booking = $this->createBooking($passenger, $trip);

        $booking->messages()->create([
            'sender_id' => $passenger->id,
            'receiver_id' => $driver->id,
            'message' => 'Hi driver.',
        ]);

        $this->actingAs($passenger)
            ->get(route('bookings.chat.show', $booking))
            ->assertOk()
            ->assertSee('Chat with Driver Chat')
            ->assertSee('Hi driver.');

        $this->actingAs($driver)
            ->get(route('bookings.chat.show', $booking))
            ->assertOk()
            ->assertSee('Chat with Passenger Chat')
            ->assertSee('Hi driver.');
    }

    public function test_booking_participant_can_send_message(): void
    {
        Event::fake([MessageSent::class]);

        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip($driver);
        $booking = $this->createBooking($passenger, $trip);

        $this->actingAs($passenger)
            ->postJson(route('bookings.chat.store', $booking), [
                'message' => 'Can I wait at the main gate?',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message', 'Can I wait at the main gate?');

        $this->assertDatabaseHas('messages', [
            'booking_id' => $booking->id,
            'sender_id' => $passenger->id,
            'receiver_id' => $driver->id,
            'message' => 'Can I wait at the main gate?',
        ]);

        Event::assertDispatched(
            MessageSent::class,
            fn (MessageSent $event) => $event->message->booking_id === $booking->id
        );
    }

    public function test_non_participant_cannot_access_booking_chat(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $outsider = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip($driver);
        $booking = $this->createBooking($passenger, $trip);

        $this->actingAs($outsider)
            ->get(route('bookings.chat.show', $booking))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson(route('bookings.chat.store', $booking), ['message' => 'Hello'])
            ->assertForbidden();
    }

    public function test_inactive_booking_chat_is_read_only(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip($driver);
        $booking = $this->createBooking($passenger, $trip, 'Rejected');

        $this->actingAs($passenger)
            ->get(route('bookings.chat.show', $booking))
            ->assertOk()
            ->assertSee('This chat is read-only');

        $this->actingAs($passenger)
            ->postJson(route('bookings.chat.store', $booking), ['message' => 'Can I try again?'])
            ->assertUnprocessable();
    }

    public function test_messages_inbox_shows_conversations_and_unread_indicator(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip($driver);
        $booking = $this->createBooking($passenger, $trip);
        $emptyBooking = $this->createBooking(
            User::factory()->create(['role' => 'passenger', 'name' => 'No Message Passenger']),
            $this->createTrip($driver)
        );

        $booking->messages()->create([
            'sender_id' => $passenger->id,
            'receiver_id' => $driver->id,
            'message' => 'I am waiting near the lobby.',
        ]);

        $this->actingAs($driver)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Messages')
            ->assertSee('I am waiting near the lobby.')
            ->assertSee('No Message Passenger')
            ->assertSee('No messages yet.')
            ->assertSee(route('bookings.chat.show', $booking))
            ->assertSee(route('bookings.chat.show', $emptyBooking))
            ->assertSee('bg-red-600');
    }

    private function createTrip(User $driver): Trip
    {
        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'seat_capacity' => 4,
            'status' => 'Active',
        ]);

        return $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Campus Main Gate',
            'destination' => 'Mid Valley Megamall',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 10,
            'description' => 'Meet near the entrance.',
            'status' => 'Scheduled',
        ]);
    }

    private function createBooking(User $passenger, Trip $trip, string $status = 'Pending'): Booking
    {
        return Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => $status,
            'number_of_seats' => 1,
            'number_of_luggage' => 0,
            'pickup_point' => 'Library',
        ]);
    }
}
