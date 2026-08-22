<?php

namespace Tests\Feature;

use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingRequestNotification;
use App\Notifications\BookingStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_view_available_trips(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip();

        $this->actingAs($passenger)
            ->get(route('passenger.booking'))
            ->assertOk()
            ->assertSee($trip->destination);
    }

    public function test_passenger_does_not_see_trips_they_already_requested(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $requestedTrip = $this->createTrip(['destination' => 'Mid Valley Megamall']);
        $availableTrip = $this->createTrip(['destination' => 'KL Sentral']);

        $this->createBooking($passenger, $requestedTrip);

        $this->actingAs($passenger)
            ->get(route('passenger.booking'))
            ->assertOk()
            ->assertDontSee($requestedTrip->destination)
            ->assertSee($availableTrip->destination);
    }

    public function test_passenger_sees_rejected_trips_again(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $rejectedTrip = $this->createTrip(['destination' => 'Mid Valley Megamall']);

        $this->createBooking($passenger, $rejectedTrip, bookingStatus: 'Rejected');

        $this->actingAs($passenger)
            ->get(route('passenger.booking'))
            ->assertOk()
            ->assertSee($rejectedTrip->destination);
    }

    public function test_passenger_destination_search_matches_related_keywords(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $matchingTrip = $this->createTrip(['destination' => 'Kuala Lumpur City Centre']);
        $otherTrip = $this->createTrip(['destination' => 'Penang Hill']);

        $this->actingAs($passenger)
            ->get(route('passenger.booking', ['destination' => 'Kuala Lumpur, Malaysia']))
            ->assertOk()
            ->assertSee($matchingTrip->destination)
            ->assertDontSee($otherTrip->destination);
    }

    public function test_passenger_destination_search_ranks_stronger_matches_first(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $keywordMatch = $this->createTrip([
            'destination' => 'Kuala Lumpur',
            'departure_at' => now()->addHour(),
        ]);
        $strongerMatch = $this->createTrip([
            'destination' => 'Kuala Lumpur City Centre',
            'departure_at' => now()->addDay(),
        ]);

        $this->actingAs($passenger)
            ->get(route('passenger.booking', ['destination' => 'Kuala Lumpur City']))
            ->assertOk()
            ->assertSeeInOrder([
                $strongerMatch->destination,
                $keywordMatch->destination,
            ]);
    }

    public function test_passenger_destination_search_uses_driver_rating_as_tiebreaker(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $reviewer = User::factory()->create(['role' => 'passenger']);
        $unratedTrip = $this->createTrip([
            'destination' => 'KL Sentral',
            'departure_at' => now()->addHour(),
        ]);
        $ratedTrip = $this->createTrip([
            'destination' => 'KL Sentral',
            'departure_at' => now()->addDay(),
        ]);
        $booking = $this->createBooking($reviewer, $ratedTrip);

        Rating::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $ratedTrip->user_id,
            'score' => 5,
            'comment' => 'Excellent driver.',
        ]);

        $this->actingAs($passenger)
            ->get(route('passenger.booking', ['destination' => 'KL Sentral']))
            ->assertOk()
            ->assertSeeInOrder([
                $ratedTrip->user->name,
                $unratedTrip->user->name,
            ]);
    }

    public function test_passenger_history_shows_trip_lifecycle_status_for_accepted_booking(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['status' => 'In Progress', 'started_at' => now()]);
        $this->createBooking($passenger, $trip, bookingStatus: 'Accepted');

        $this->actingAs($passenger)
            ->get(route('passenger.bookings.history'))
            ->assertOk()
            ->assertSee('In Progress')
            ->assertDontSee('Accepted</span>', false);
    }

    public function test_passenger_can_submit_booking_request(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['available_seats' => 3]);

        Http::fake([
            'https://places.googleapis.com/v1/places/main-gate-place' => Http::response([
                'id' => 'main-gate-place',
                'displayName' => ['text' => 'Main Gate'],
                'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']],
                'location' => ['latitude' => 3.1185, 'longitude' => 101.6770],
            ]),
        ]);

        $this->actingAs($passenger)
            ->post(route('passenger.bookings.store'), [
                'trip_id' => $trip->trip_id,
                'pickup_point' => 'Main Gate',
                'pickup_place_id' => 'main-gate-place',
                'number_of_seats' => 2,
                'number_of_luggage' => 0,
            ])
            ->assertRedirect(route('passenger.bookings.history'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => 'Pending',
            'number_of_seats' => 2,
            'pickup_point' => 'Main Gate',
            'pickup_place_id' => 'main-gate-place',
        ]);
    }

    public function test_driver_receives_notification_when_passenger_submits_booking_request(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger', 'name' => 'Passenger One']);
        $trip = $this->createTrip(['user_id' => $driver->id, 'destination' => 'Suria KLCC']);

        Http::fake([
            'https://places.googleapis.com/v1/places/pickup-place' => Http::response([
                'id' => 'pickup-place',
                'displayName' => ['text' => 'Campus Main Gate'],
                'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']],
                'location' => ['latitude' => 3.1185, 'longitude' => 101.6770],
            ]),
        ]);

        $this->actingAs($passenger)
            ->post(route('passenger.bookings.store'), [
                'trip_id' => $trip->trip_id,
                'pickup_point' => 'Campus',
                'pickup_place_id' => 'pickup-place',
                'number_of_seats' => 1,
                'number_of_luggage' => 0,
            ])
            ->assertRedirect(route('passenger.bookings.history'));

        $notification = $driver->unreadNotifications()->where('type', BookingRequestNotification::class)->firstOrFail();

        $this->assertSame('New booking request', $notification->data['title']);
        $this->assertStringContainsString('Passenger One submitted a booking request to Suria KLCC.', $notification->data['message']);
        $this->assertSame(route('driver.booking-requests.show', Booking::query()->firstOrFail()), $notification->data['url']);
    }

    public function test_passenger_cannot_submit_duplicate_booking_request_for_same_trip(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip();

        Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => 'Pending',
            'number_of_seats' => 1,
            'pickup_point' => 'Library',
        ]);

        $this->actingAs($passenger)
            ->from(route('passenger.bookings.create', ['trip_id' => $trip->trip_id]))
            ->post(route('passenger.bookings.store'), [
                'trip_id' => $trip->trip_id,
                'pickup_point' => 'Main Gate',
                'number_of_seats' => 1,
            ])
            ->assertRedirect(route('passenger.bookings.create', ['trip_id' => $trip->trip_id]))
            ->assertSessionHasErrors('trip_id');

        $this->assertSame(1, Booking::query()->where('trip_id', $trip->trip_id)->where('passenger_id', $passenger->id)->count());
    }

    public function test_passenger_can_resubmit_a_rejected_booking_request(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip();
        $booking = $this->createBooking($passenger, $trip, bookingStatus: 'Rejected');

        Http::fake([
            'https://places.googleapis.com/v1/places/new-pickup-place' => Http::response([
                'id' => 'new-pickup-place',
                'displayName' => ['text' => 'New Pickup Point'],
                'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']],
                'location' => ['latitude' => 3.1185, 'longitude' => 101.6770],
            ]),
        ]);

        $this->actingAs($passenger)
            ->post(route('passenger.bookings.store'), [
                'trip_id' => $trip->trip_id,
                'pickup_point' => 'Typed pickup',
                'pickup_place_id' => 'new-pickup-place',
                'number_of_seats' => 1,
                'number_of_luggage' => 2,
            ])
            ->assertRedirect(route('passenger.bookings.history'))
            ->assertSessionHas('success');

        $this->assertSame(1, Booking::query()->where('trip_id', $trip->trip_id)->where('passenger_id', $passenger->id)->count());
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Pending',
            'pickup_point' => 'New Pickup Point',
            'pickup_place_id' => 'new-pickup-place',
            'number_of_luggage' => 2,
        ]);
    }

    public function test_passenger_cannot_request_more_seats_than_available(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['available_seats' => 1]);

        $this->actingAs($passenger)
            ->post(route('passenger.bookings.store'), [
                'trip_id' => $trip->trip_id,
                'pickup_point' => 'Main Gate',
                'number_of_seats' => 2,
            ])
            ->assertSessionHasErrors('number_of_seats');

        $this->assertDatabaseMissing('bookings', [
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
        ]);
    }

    public function test_passenger_can_cancel_only_pending_booking(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $passenger = User::factory()->create(['role' => 'passenger']);
        $pendingBooking = $this->createBooking($passenger, bookingStatus: 'Pending');
        $acceptedBooking = $this->createBooking($passenger, bookingStatus: 'Accepted');

        $this->actingAs($passenger)
            ->patch(route('passenger.bookings.cancel', $pendingBooking))
            ->assertRedirect(route('passenger.bookings.history'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $pendingBooking->id,
            'booking_status' => 'Cancelled',
        ]);
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->booking->is($pendingBooking)
                && $event->booking->booking_status === 'Cancelled'
                && collect($event->broadcastOn())->contains(fn ($channel) => (string) $channel === 'private-driver.'.$pendingBooking->trip->user_id)
        );

        $this->actingAs($passenger)
            ->patch(route('passenger.bookings.cancel', $acceptedBooking))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', [
            'id' => $acceptedBooking->id,
            'booking_status' => 'Accepted',
        ]);
    }

    public function test_driver_receives_notification_when_passenger_cancels_booking_request(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger', 'name' => 'Passenger Two']);
        $trip = $this->createTrip(['user_id' => $driver->id, 'destination' => 'Mid Valley Megamall']);
        $booking = $this->createBooking($passenger, $trip, bookingStatus: 'Pending');

        $this->actingAs($passenger)
            ->patch(route('passenger.bookings.cancel', $booking))
            ->assertRedirect(route('passenger.bookings.history'));

        $notification = $driver->unreadNotifications()->where('type', BookingRequestNotification::class)->firstOrFail();

        $this->assertSame('Booking request cancelled', $notification->data['title']);
        $this->assertStringContainsString('Passenger Two cancelled their booking request to Mid Valley Megamall.', $notification->data['message']);
        $this->assertSame(route('driver.booking-requests.show', $booking), $notification->data['url']);
    }

    public function test_driver_can_accept_booking_for_their_trip_and_seats_decrease(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $driver->id, 'available_seats' => 3]);
        $booking = $this->createBooking($passenger, $trip, 2);

        $this->actingAs($driver)
            ->patch(route('driver.booking-requests.accept', $booking))
            ->assertRedirect(route('driver.booking-requests.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Accepted',
        ]);
        $this->assertDatabaseHas('trips', [
            'trip_id' => $trip->trip_id,
            'available_seats' => 1,
        ]);
    }

    public function test_passenger_receives_notification_when_driver_accepts_booking_request(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = User::factory()->create(['role' => 'driver', 'name' => 'Driver One']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip([
            'user_id' => $driver->id,
            'destination' => 'Suria KLCC',
            'available_seats' => 3,
        ]);
        $booking = $this->createBooking($passenger, $trip, 2);

        $this->actingAs($driver)
            ->patch(route('driver.booking-requests.accept', $booking))
            ->assertRedirect(route('driver.booking-requests.index'));

        $notification = $passenger->unreadNotifications()->where('type', BookingStatusNotification::class)->firstOrFail();

        $this->assertSame('Booking request accepted', $notification->data['title']);
        $this->assertStringContainsString('Driver One accepted your booking request to Suria KLCC.', $notification->data['message']);
        $this->assertSame(route('passenger.bookings.history'), $notification->data['url']);
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->passengerNotificationType === 'booking_request_accepted'
        );
    }

    public function test_driver_can_view_booking_request_details_for_their_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $driver->id]);
        $booking = $this->createBooking($passenger, $trip, 2);

        $this->actingAs($driver)
            ->get(route('driver.booking-requests.show', $booking))
            ->assertOk()
            ->assertSee('Booking Request Details')
            ->assertSee($passenger->name)
            ->assertSee($booking->pickup_point)
            ->assertSee($trip->destination);
    }

    public function test_driver_can_filter_booking_requests_by_status(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $pendingPassenger = User::factory()->create(['role' => 'passenger', 'name' => 'Pending Passenger']);
        $rejectedPassenger = User::factory()->create(['role' => 'passenger', 'name' => 'Rejected Passenger']);
        $trip = $this->createTrip(['user_id' => $driver->id]);

        $this->createBooking($pendingPassenger, $trip, bookingStatus: 'Pending');
        $this->createBooking($rejectedPassenger, $trip, bookingStatus: 'Rejected');

        $this->actingAs($driver)
            ->get(route('driver.booking-requests.index', ['status' => 'Rejected']))
            ->assertOk()
            ->assertSee('Rejected Passenger')
            ->assertDontSee('Pending Passenger');
    }

    public function test_booking_requests_show_the_passengers_real_database_rating(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $driver->id]);
        $booking = $this->createBooking($passenger, $trip, 1);
        Rating::create([
            'booking_id' => $booking->id,
            'reviewer_id' => $driver->id,
            'reviewee_id' => $passenger->id,
            'score' => 4,
            'comment' => 'Good passenger.',
        ]);

        $this->actingAs($driver)
            ->get(route('driver.booking-requests.index'))
            ->assertOk()
            ->assertSee('4.0')
            ->assertSee('(1)')
            ->assertDontSeeText('4.7');

        $this->actingAs($driver)
            ->get(route('driver.booking-requests.show', $booking))
            ->assertOk()
            ->assertSee('4.0')
            ->assertSee('(1 review)')
            ->assertDontSeeText('4.7');
    }

    public function test_driver_can_reject_booking_without_changing_available_seats(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $driver->id, 'available_seats' => 3]);
        $booking = $this->createBooking($passenger, $trip, 2);

        $this->actingAs($driver)
            ->patch(route('driver.booking-requests.reject', $booking))
            ->assertRedirect(route('driver.booking-requests.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Rejected',
        ]);
        $this->assertDatabaseHas('trips', [
            'trip_id' => $trip->trip_id,
            'available_seats' => 3,
        ]);
    }

    public function test_passenger_receives_notification_when_driver_rejects_booking_request(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = User::factory()->create(['role' => 'driver', 'name' => 'Driver Two']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip([
            'user_id' => $driver->id,
            'destination' => 'Mid Valley Megamall',
            'available_seats' => 3,
        ]);
        $booking = $this->createBooking($passenger, $trip, 2);

        $this->actingAs($driver)
            ->patch(route('driver.booking-requests.reject', $booking))
            ->assertRedirect(route('driver.booking-requests.index'));

        $notification = $passenger->unreadNotifications()->where('type', BookingStatusNotification::class)->firstOrFail();

        $this->assertSame('Booking request rejected', $notification->data['title']);
        $this->assertStringContainsString('Driver Two rejected your booking request to Mid Valley Megamall.', $notification->data['message']);
        $this->assertSame(route('passenger.bookings.history'), $notification->data['url']);
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->passengerNotificationType === 'booking_request_rejected'
        );
    }

    public function test_driver_cannot_accept_booking_for_another_drivers_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $otherDriver->id, 'available_seats' => 3]);
        $booking = $this->createBooking($passenger, $trip);

        $this->actingAs($driver)
            ->patch(route('driver.booking-requests.accept', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'Pending',
        ]);
        $this->assertDatabaseHas('trips', [
            'trip_id' => $trip->trip_id,
            'available_seats' => 3,
        ]);
    }

    public function test_driver_cannot_view_booking_details_for_another_drivers_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $trip = $this->createTrip(['user_id' => $otherDriver->id]);
        $booking = $this->createBooking($passenger, $trip);

        $this->actingAs($driver)
            ->get(route('driver.booking-requests.show', $booking))
            ->assertForbidden();
    }

    private function createTrip(array $attributes = []): Trip
    {
        $driver = isset($attributes['user_id'])
            ? User::findOrFail($attributes['user_id'])
            : User::factory()->create(['role' => 'driver']);

        $vehicle = Vehicle::factory()->create([
            'user_id' => $driver->id,
            'seat_capacity' => 4,
            'status' => 'Active',
        ]);

        $tripAttributes = [
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Campus Main Gate',
            'destination' => 'Kuala Lumpur City Centre',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 10,
            'description' => 'Meet near the security post.',
            'status' => 'Scheduled',
            ...$attributes,
        ];

        unset($tripAttributes['user_id']);

        return $driver->trips()->create($tripAttributes);
    }

    private function createBooking(?User $passenger = null, ?Trip $trip = null, int $seats = 1, string $bookingStatus = 'Pending'): Booking
    {
        $passenger ??= User::factory()->create(['role' => 'passenger']);
        $trip ??= $this->createTrip();

        return Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => $bookingStatus,
            'number_of_seats' => $seats,
            'pickup_point' => 'Library',
        ]);
    }
}
