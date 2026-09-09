<?php

namespace Tests\Feature\Driver;

use App\Events\BookingStatusUpdated;
use App\Events\InAppNotificationCreated;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingStatusNotification;
use App\Notifications\TripUpdatedNotification;
use App\Notifications\TripAutoCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripLifecycleRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_creation_requires_a_valid_licence_covering_the_departure_date(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $departureAt = now()->addDays(10)->setTime(10, 0);

        $this->actingAs($driver)
            ->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))
            ->assertSessionHasErrors('driver_licence');

        $driver->driverLicence()->create([
            'image_path' => 'driver-licences/test.jpg',
            'holder_name' => $driver->name,
            'identity_no' => '991109040290',
            'valid_until' => now()->addDays(5),
            'verification_status' => 'Verified',
            'verified_at' => now(),
        ]);

        $this->actingAs($driver)
            ->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))
            ->assertSessionHasErrors('departure_date');

        $driver->driverLicence()->update(['valid_until' => now()->addDays(15)]);
        $this->fakeGoogle();

        $this->actingAs($driver)
            ->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))
            ->assertSessionHasNoErrors();
    }

    public function test_expired_licence_cannot_create_or_update_a_trip(): void
    {
        $driver = $this->driver();
        $driver->driverLicence()->update(['valid_until' => now()->subDay()]);
        $vehicle = $this->vehicle($driver);
        $departureAt = now()->addDay()->setTime(10, 0);

        $this->actingAs($driver)
            ->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))
            ->assertSessionHasErrors('driver_licence');

        $trip = $this->trip($driver, ['vehicle_id' => $vehicle->vehicle_id, 'departure_at' => $departureAt]);
        $this->actingAs($driver)
            ->patch(route('driver.trips.update', $trip), $this->requestData($vehicle, $departureAt->copy()->addHour()))
            ->assertSessionHasErrors('driver_licence');
    }

    public function test_create_trip_page_redirects_to_profile_when_driving_licence_is_missing(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($driver)
            ->get(route('driver.trips.create'))
            ->assertRedirect(route('driver.profile.edit', ['section' => 'licence']))
            ->assertSessionHas('error', 'Upload your driving licence before creating a trip.');
    }

    public function test_driver_cannot_start_trip_with_missing_or_expired_licence(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $trip = $this->trip($driver);

        $this->actingAs($driver)
            ->patchJson(route('driver.trips.start', $trip))
            ->assertUnprocessable()
            ->assertSee('driving licence');

        $this->assertSame('Scheduled', $trip->refresh()->status);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google_maps.key', 'test-key');
    }

    public function test_departed_scheduled_trip_without_bookings_is_cancelled_idempotently(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute()]);

        Artisan::call('trips:expire-departed');
        $trip->refresh();
        $cancelledAt = $trip->cancelled_at;
        $this->assertSame('Cancelled', $trip->status);
        $this->assertNotNull($cancelledAt);
        $this->assertNull($trip->started_at);
        $this->assertNull($trip->completed_at);

        Artisan::call('trips:expire-departed');
        $this->assertTrue($cancelledAt->equalTo($trip->refresh()->cancelled_at));
    }

    public function test_departed_scheduled_trip_with_pending_bookings_only_is_cancelled(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute()]);
        $booking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $this->passenger()->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);

        Artisan::call('trips:expire-departed');
        $this->assertSame('Cancelled', $trip->refresh()->status);
        $this->assertSame('Cancelled', $booking->refresh()->booking_status);
    }

    public function test_departure_boundary_keeps_a_scheduled_trip_startable_for_the_full_departure_minute(): void
    {
        $departureAt = Carbon::parse('2026-08-29 20:00:00');
        Carbon::setTestNow($departureAt->copy()->subSecond());
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => $departureAt]);
        $this->fakeGoogle();

        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $trip->refresh()->status);

        $trip->update(['status' => 'Scheduled', 'started_at' => null]);
        Carbon::setTestNow($departureAt);
        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $trip->refresh()->status);

        $trip->update(['status' => 'Scheduled', 'started_at' => null]);
        Carbon::setTestNow($departureAt->copy()->addSeconds(30));
        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $trip->refresh()->status);

        $trip->update(['status' => 'Scheduled', 'started_at' => null]);
        Carbon::setTestNow($departureAt->copy()->addSeconds(59));
        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $trip->refresh()->status);

        $trip->update(['status' => 'Scheduled', 'started_at' => null]);
        Carbon::setTestNow($departureAt->copy()->addMinute());
        $this->actingAs($driver)->patch(route('driver.trips.start', $trip))->assertStatus(422);
        $this->assertSame('Scheduled', $trip->refresh()->status);
        Carbon::setTestNow();
    }

    public function test_my_trips_displays_expired_after_the_departure_minute_and_exposes_the_realtime_badge(): void
    {
        $departureAt = Carbon::parse('2026-08-29 01:28:00');
        Carbon::setTestNow($departureAt->copy()->addSeconds(59));
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => $departureAt]);

        $this->actingAs($driver)
            ->get(route('driver.trips.index'))
            ->assertOk()
            ->assertSee('Scheduled')
            ->assertSee('data-trip-expiry-badge', false)
            ->assertSee($departureAt->toIso8601String(), false);

        Carbon::setTestNow($departureAt->copy()->addMinute());
        $this->actingAs($driver)
            ->get(route('driver.trips.index'))
            ->assertOk()
            ->assertSee('Expired');

        $this->assertSame('Scheduled', $trip->refresh()->status);
        Carbon::setTestNow();
    }

    public function test_automatic_cancellation_waits_until_after_the_exact_departure_time(): void
    {
        $departureAt = Carbon::parse('2026-08-29 20:00:00');
        Carbon::setTestNow($departureAt);
        $trip = $this->trip($this->driver(), ['departure_at' => $departureAt]);

        Artisan::call('trips:expire-departed');
        $this->assertSame('Scheduled', $trip->refresh()->status);

        Carbon::setTestNow($departureAt->copy()->addSeconds(30));
        Artisan::call('trips:expire-departed');
        $this->assertSame('Scheduled', $trip->refresh()->status);

        Carbon::setTestNow($departureAt->copy()->addSeconds(59));
        Artisan::call('trips:expire-departed');
        $this->assertSame('Scheduled', $trip->refresh()->status);

        Carbon::setTestNow($departureAt->copy()->addMinute());
        Artisan::call('trips:expire-departed');
        $this->assertSame('Cancelled', $trip->refresh()->status);
        Carbon::setTestNow();
    }

    public function test_automatic_cancellation_notifies_the_driver_in_real_time(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => now()->subMinute()]);

        Artisan::call('trips:expire-departed');

        $this->assertSame('Cancelled', $trip->refresh()->status);
        $notification = $driver->notifications()->where('type', TripAutoCancelledNotification::class)->firstOrFail();
        $this->assertSame($trip->trip_id, $notification->data['trip_id']);
        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($driver) && $event->notification['type'] === 'trip_auto_cancelled');
    }

    public function test_departed_trip_with_accepted_bookings_is_cancelled_with_expired_notification(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => now()->subMinute()]);
        $passenger = $this->passenger();
        $booking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);

        Artisan::call('trips:expire-departed');

        $this->assertSame('Cancelled', $trip->refresh()->status);
        $this->assertNotNull($trip->cancelled_at);
        $this->assertSame('Cancelled', $booking->refresh()->booking_status);

        // Driver receives "expired" notification (distinct from regular cancellation)
        $driverNotification = $driver->notifications()->where('type', TripAutoCancelledNotification::class)->firstOrFail();
        $this->assertSame('Trip Expired', $driverNotification->data['title']);
        $this->assertSame('trip_auto_expired', $driverNotification->data['type']);

        // Accepted passenger receives "expired" notification
        $passengerNotification = $passenger->notifications()->where('type', BookingStatusNotification::class)->firstOrFail();
        $this->assertSame('Trip Expired', $passengerNotification->data['title']);
        $this->assertSame('trip_expired', $passengerNotification->data['type']);
        $this->assertStringContainsString('did not start the scheduled trip', $passengerNotification->data['message']);
    }

    public function test_departed_trip_with_accepted_and_pending_bookings_is_cancelled_with_expired_notification(): void
    {
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => now()->subMinute()]);
        $acceptedBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $this->passenger()->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);
        $pendingBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $this->passenger()->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Library']);

        Artisan::call('trips:expire-departed');

        $this->assertSame('Cancelled', $trip->refresh()->status);
        $this->assertSame('Cancelled', $acceptedBooking->refresh()->booking_status);
        $this->assertSame('Cancelled', $pendingBooking->refresh()->booking_status);

        // Driver gets expired notification since there was an accepted booking
        $driverNotification = $driver->notifications()->where('type', TripAutoCancelledNotification::class)->firstOrFail();
        $this->assertSame('trip_auto_expired', $driverNotification->data['type']);
    }

    public function test_expired_trip_notifies_accepted_passengers_and_driver(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => now()->subMinute()]);
        $acceptedPassenger = $this->passenger();
        $pendingPassenger = $this->passenger();
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $acceptedPassenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $pendingPassenger->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Library']);

        Artisan::call('trips:expire-departed');

        $this->assertSame('Cancelled', $trip->refresh()->status);

        // Accepted passenger receives expired notification
        $passengerNotification = $acceptedPassenger->notifications()->where('type', BookingStatusNotification::class)->firstOrFail();
        $this->assertSame('Trip Expired', $passengerNotification->data['title']);
        $this->assertSame('trip_expired', $passengerNotification->data['type']);

        // Pending passenger receives cancellation notification
        $pendingNotification = $pendingPassenger->notifications()->where('type', BookingStatusNotification::class)->firstOrFail();
        $this->assertSame('Trip Cancelled', $pendingNotification->data['title']);
        $this->assertStringContainsString('no longer active', $pendingNotification->data['message']);

        // Driver receives auto-expired notification
        $driverNotification = $driver->notifications()->where('type', TripAutoCancelledNotification::class)->firstOrFail();
        $this->assertSame('Trip Expired', $driverNotification->data['title']);
        $this->assertSame('trip_auto_expired', $driverNotification->data['type']);

        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($driver) && $event->notification['type'] === 'trip_auto_expired');
        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($acceptedPassenger) && $event->notification['type'] === 'trip_expired');
        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($pendingPassenger) && $event->notification['type'] === 'trip_cancelled');
    }

    public function test_command_does_not_affect_in_progress_trips(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute(), 'status' => 'In Progress', 'started_at' => now()->subMinutes(2)]);

        Artisan::call('trips:expire-departed');
        $this->assertSame('In Progress', $trip->refresh()->status);
    }

    public function test_command_does_not_affect_completed_trips(): void
    {
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute(), 'status' => 'Completed', 'completed_at' => now()]);

        Artisan::call('trips:expire-departed');
        $this->assertSame('Completed', $trip->refresh()->status);
    }

    public function test_command_does_not_affect_already_cancelled_trips(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $trip = $this->trip($this->driver(), ['departure_at' => now()->subMinute(), 'status' => 'Cancelled', 'cancelled_at' => now()]);

        Artisan::call('trips:expire-departed');
        $this->assertSame('Cancelled', $trip->refresh()->status);

        // No new notifications should be sent for already-cancelled trips
        Event::assertNotDispatched(InAppNotificationCreated::class);
    }

    public function test_driver_cannot_start_an_auto_cancelled_trip(): void
    {
        $driver = $this->driver();
        $cancelledTrip = $this->trip($driver, ['status' => 'Cancelled']);

        $this->actingAs($driver)->patchJson(route('driver.trips.start', $cancelledTrip))->assertStatus(422);
        $this->assertSame('Cancelled', $cancelledTrip->refresh()->status);
    }

    public function test_expired_trip_recovery_only_requires_a_future_departure_time_and_notifies_accepted_passengers(): void
    {
        Event::fake([InAppNotificationCreated::class]);
        $driver = $this->driver();
        $vehicle = $this->vehicle($driver);
        $trip = $this->trip($driver, ['vehicle_id' => $vehicle->vehicle_id, 'departure_at' => now()->subMinute(), 'available_seats' => 3, 'price_per_passenger' => 17]);
        $passenger = $this->passenger();
        $booking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);
        $newDeparture = now()->addHour()->startOfMinute();
        $this->actingAs($driver)->get(route('driver.trips.edit', $trip))->assertOk()->assertSee('Edit Departure Time')->assertDontSee('Available seats');
        $this->actingAs($driver)->patch(route('driver.trips.update', $trip), ['departure_date' => $newDeparture->toDateString(), 'departure_time' => $newDeparture->format('H:i')])->assertSessionHasNoErrors();

        $this->assertSame('Scheduled', $trip->refresh()->status);
        $this->assertSame(3, $trip->available_seats);
        $this->assertSame(17.0, (float) $trip->price_per_passenger);
        $notification = $passenger->notifications()->where('type', TripUpdatedNotification::class)->firstOrFail();
        $this->assertSame('Trip Departure Time Updated', $notification->data['title']);
        $this->assertSame($newDeparture->format('d M Y, g:i A'), $notification->data['departure_at_label']);
        Event::assertDispatched(InAppNotificationCreated::class, fn (InAppNotificationCreated $event) => $event->recipient->is($passenger) && $event->notification['booking_id'] === $booking->id);
    }

    public function test_trip_update_notification_includes_the_visible_route_and_vehicle_details(): void
    {
        $driver = $this->driver();
        $vehicle = $this->vehicle($driver);
        $trip = $this->trip($driver, ['vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'KL Sentral', 'destination' => 'KLIA']);
        $passenger = $this->passenger();
        $booking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);

        $payload = (new TripUpdatedNotification($booking, false, ['destination']))->toArray($passenger);

        $this->assertTrue($payload['locations_changed']);
        $this->assertSame('KL Sentral', $payload['trip_details']['departure_location']);
        $this->assertSame('KLIA', $payload['trip_details']['destination']);
        $this->assertSame(trim($vehicle->brand.' '.$vehicle->model), $payload['trip_details']['vehicle_label']);
    }

    public function test_driver_cannot_create_a_second_scheduled_trip_at_the_same_time_but_other_drivers_and_cancelled_trips_do_not_conflict(): void
    {
        $driver = $this->driver();
        $departureAt = now()->addDay()->setTime(10, 0);
        $this->trip($driver, ['departure_at' => $departureAt]);
        $vehicle = $this->vehicle($driver);

        $this->actingAs($driver)->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt))->assertSessionHasErrors('departure_time');

        $other = $this->driver();
        $this->fakeGoogle();
        $this->actingAs($other)->post(route('driver.trips.store'), $this->requestData($this->vehicle($other), $departureAt))->assertSessionHasNoErrors();

        $this->trip($driver, ['departure_at' => $departureAt->copy()->addHour(), 'status' => 'Cancelled']);
        $this->fakeGoogle();
        $this->actingAs($driver)->post(route('driver.trips.store'), $this->requestData($vehicle, $departureAt->copy()->addHour()))->assertSessionHasNoErrors();
    }

    public function test_trip_edit_rejects_another_scheduled_trip_time_but_allows_its_own_time(): void
    {
        $driver = $this->driver();
        $first = $this->trip($driver, ['departure_at' => now()->addDay()->setTime(9, 0)]);
        $second = $this->trip($driver, ['departure_at' => now()->addDay()->setTime(10, 0)]);
        $vehicle = $this->vehicle($driver);

        $this->actingAs($driver)->patch(route('driver.trips.update', $second), $this->requestData($vehicle, $first->departure_at))->assertSessionHasErrors('departure_time');
        $this->actingAs($driver)->patch(route('driver.trips.update', $second), [
            ...$this->requestData($vehicle, $second->departure_at),
            'departure_place_id' => null,
            'destination_place_id' => null,
        ])->assertSessionHasNoErrors();
    }

    public function test_start_trip_lifecycle_remains_unchanged(): void
    {
        $this->fakeGoogle();
        $driver = $this->driver();
        $inProgress = $this->trip($driver, ['status' => 'In Progress', 'started_at' => now()]);
        $onboardPassenger = $this->passenger();
        Booking::create(['trip_id' => $inProgress->trip_id, 'passenger_id' => $onboardPassenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate', 'picked_up_at' => now()]);
        $next = $this->trip($driver, ['departure_at' => now()->addMinutes(15)]);
        $this->actingAs($driver)->patch(route('driver.trips.start', $next))->assertStatus(422);

        $this->actingAs($driver)->patch(route('driver.trips.complete', $inProgress))->assertRedirect();
        $this->actingAs($driver)->patch(route('driver.trips.start', $next))->assertSessionHasNoErrors();

        $cancelled = $this->trip($driver, ['status' => 'Cancelled']);
        $this->actingAs($driver)->patch(route('driver.trips.start', $cancelled))->assertStatus(422);
        $completed = $this->trip($driver, ['status' => 'Completed', 'completed_at' => now()]);
        $this->actingAs($driver)->patch(route('driver.trips.start', $completed))->assertStatus(422);
    }

    public function test_start_trip_notifies_accepted_passengers_in_real_time(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = $this->driver();
        $trip = $this->trip($driver, ['departure_at' => now()->addMinutes(15)]);
        $acceptedPassenger = $this->passenger();
        $pendingPassenger = $this->passenger();
        $acceptedBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $acceptedPassenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate', 'pickup_place_id' => 'main-gate', 'pickup_latitude' => 3.1390, 'pickup_longitude' => 101.6869]);
        Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $pendingPassenger->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Library']);
        $this->fakeGoogle([0]);

        $this->actingAs($driver)
            ->patch(route('driver.trips.start', $trip))
            ->assertRedirect(route('driver.trips.journey'));

        $this->assertSame('In Progress', $trip->refresh()->status);
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->booking->is($acceptedBooking)
                && $event->booking->passenger_id === $acceptedPassenger->id
                && $event->booking->trip->status === 'In Progress'
                && $event->passengerNotificationType === 'trip_started'
        );
        Event::assertDispatchedTimes(BookingStatusUpdated::class, 1);
        $notification = $acceptedPassenger->unreadNotifications()->where('type', BookingStatusNotification::class)->firstOrFail();
        $this->assertSame('Trip Started', $notification->data['title']);
        $this->assertSame('trip_started', $notification->data['type']);
        $this->assertSame($acceptedBooking->id, $notification->data['booking_id']);
        $this->assertSame($trip->trip_id, $notification->data['trip_id']);
        $this->assertSame(route('passenger.bookings.show', $acceptedBooking), $notification->data['url']);
    }

    public function test_cancel_trip_marks_open_bookings_cancelled_and_notifies_passengers(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = User::factory()->create(['role' => 'driver', 'name' => 'Driver Cancel']);
        $trip = $this->trip($driver, ['destination' => 'Suria KLCC']);
        $acceptedPassenger = $this->passenger();
        $pendingPassenger = $this->passenger();
        $rejectedPassenger = $this->passenger();
        $acceptedBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $acceptedPassenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate']);
        $pendingBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $pendingPassenger->id, 'booking_status' => 'Pending', 'number_of_seats' => 1, 'pickup_point' => 'Library']);
        $rejectedBooking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $rejectedPassenger->id, 'booking_status' => 'Rejected', 'number_of_seats' => 1, 'pickup_point' => 'Cafeteria']);

        $this->actingAs($driver)
            ->patch(route('driver.trips.cancel', $trip))
            ->assertRedirect(route('driver.trips.index'));

        $this->assertSame('Cancelled', $trip->refresh()->status);
        $this->assertSame('Cancelled', $acceptedBooking->refresh()->booking_status);
        $this->assertSame('Cancelled', $pendingBooking->refresh()->booking_status);
        $this->assertSame('Rejected', $rejectedBooking->refresh()->booking_status);

        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->booking->getKey() === $acceptedBooking->getKey()
                && $event->passengerNotificationType === 'trip_cancelled'
        );
        Event::assertDispatched(
            BookingStatusUpdated::class,
            fn (BookingStatusUpdated $event) => $event->booking->getKey() === $pendingBooking->getKey()
                && $event->passengerNotificationType === null
        );
        Event::assertDispatchedTimes(BookingStatusUpdated::class, 2);

        $acceptedNotification = $acceptedPassenger->unreadNotifications()->where('type', BookingStatusNotification::class)->firstOrFail();

        $this->assertSame('Trip Cancelled', $acceptedNotification->data['title']);
        $this->assertSame('trip_cancelled', $acceptedNotification->data['type']);
        $this->assertStringContainsString('Your booked trip from Departure → Suria KLCC has been cancelled.', $acceptedNotification->data['message']);
        $this->assertSame(route('passenger.bookings.show', $acceptedBooking), $acceptedNotification->data['url']);
        $this->assertSame(0, $pendingPassenger->unreadNotifications()->count());
        $this->assertSame(0, $rejectedPassenger->unreadNotifications()->count());
    }

    public function test_complete_trip_notifies_accepted_passengers_once_after_completion(): void
    {
        Event::fake([BookingStatusUpdated::class]);

        $driver = $this->driver();
        $trip = $this->trip($driver, ['status' => 'In Progress', 'started_at' => now()->subMinutes(10)]);
        $passenger = $this->passenger();
        $booking = Booking::create(['trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id, 'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'Main Gate', 'picked_up_at' => now()->subMinutes(5)]);

        $this->actingAs($driver)->patch(route('driver.trips.complete', $trip))->assertRedirect();

        $notification = $passenger->unreadNotifications()->where('type', BookingStatusNotification::class)->firstOrFail();
        $this->assertSame('Trip Completed', $notification->data['title']);
        $this->assertSame('trip_completed', $notification->data['type']);
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertSame(route('passenger.bookings.show', $booking), $notification->data['url']);

        $this->actingAs($driver)->patch(route('driver.trips.complete', $trip))->assertStatus(422);
        $this->assertSame(1, $passenger->fresh()->notifications()->where('type', BookingStatusNotification::class)->count());
    }

    private function fakeGoogle(array $optimizedIndexes = []): void
    {
        $route = ['distanceMeters' => 1000, 'duration' => '60s'];
        if ($optimizedIndexes !== []) {
            $route['optimizedIntermediateWaypointIndex'] = $optimizedIndexes;
        }

        Http::fake([
            'https://places.googleapis.com/v1/places/departure' => Http::response($this->place('departure', 3.139, 101.6869)),
            'https://places.googleapis.com/v1/places/destination' => Http::response($this->place('destination', 3.1578, 101.7123)),
            'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(['routes' => [$route]]),
        ]);
    }

    private function place(string $id, float $latitude, float $longitude): array
    {
        return ['id' => $id, 'displayName' => ['text' => ucfirst($id)], 'formattedAddress' => ucfirst($id).' Malaysia', 'addressComponents' => [['types' => ['country'], 'shortText' => 'MY']], 'location' => ['latitude' => $latitude, 'longitude' => $longitude]];
    }

    private function requestData(Vehicle $vehicle, Carbon $departureAt): array
    {
        return ['vehicle_id' => $vehicle->vehicle_id, 'departure_place_id' => 'departure', 'destination_place_id' => 'destination', 'departure_date' => $departureAt->toDateString(), 'departure_time' => $departureAt->format('H:i'), 'available_seats' => 2, 'price_per_passenger' => 5];
    }

    private function driver(): User
    {
        $driver = User::factory()->create(['role' => 'driver']);
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

        return $driver;
    }

    private function passenger(): User
    {
        return User::factory()->create(['role' => 'passenger']);
    }

    private function vehicle(User $driver): Vehicle
    {
        return Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active', 'seat_capacity' => 4]);
    }

    private function trip(User $driver, array $overrides = []): Trip
    {
        $vehicle = $this->vehicle($driver);

        return $driver->trips()->create([...['vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'Departure', 'destination' => 'Destination', 'departure_latitude' => 3.1, 'departure_longitude' => 101.7, 'destination_latitude' => 3.2, 'destination_longitude' => 101.8, 'departure_at' => now()->addDay(), 'available_seats' => 2, 'status' => 'Scheduled'], ...$overrides]);
    }
}
