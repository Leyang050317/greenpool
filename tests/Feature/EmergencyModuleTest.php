<?php

namespace Tests\Feature;

use App\Events\EmergencyAcknowledged;
use App\Events\EmergencyResolved;
use App\Events\EmergencyTriggered;
use App\Models\Booking;
use App\Models\Emergency;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\EmergencyAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmergencyModuleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('emergencyTypes')]
    public function test_driver_can_report_each_emergency_type(string $issueType, string $message): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => $issueType,
            'description' => 'Please be aware.',
        ])->assertRedirect()->assertSessionHas('success', 'Emergency reported successfully.');

        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => $issueType, 'status' => 'Active']);
        $notification = $passenger->notifications()->where('type', EmergencyAlertNotification::class)->firstOrFail();
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertSame(route('passenger.bookings.show', $booking).'#emergency-1', $notification->data['url']);
        $this->assertSame('Emergency Alert', $notification->data['title']);
        $this->assertSame($message, $notification->data['message']);
        $this->assertSame('Location unavailable', $notification->data['location_source']);
        $this->assertNull($notification->data['map_url']);
        $this->assertSame(0, $driver->notifications()->where('type', EmergencyAlertNotification::class)->count());
    }

    public static function emergencyTypes(): array
    {
        return [
            ['medical_emergency', 'A trip participant reported a medical emergency.'],
            ['safety_risk', 'A trip participant reported an immediate safety risk.'],
            ['accident_road_danger', 'A trip participant reported an accident or road danger.'],
            ['other_emergency', 'A trip participant reported an emergency.'],
        ];
    }

    #[DataProvider('emergencyTypes')]
    public function test_passenger_can_report_each_emergency_type(string $issueType, string $message): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger, ['pickup_latitude' => 3.14, 'pickup_longitude' => 101.69]);

        $this->actingAs($passenger)->post(route('trips.emergencies.store', $trip), ['issue_type' => $issueType])
            ->assertRedirect()->assertSessionHas('success', 'Emergency reported successfully.');

        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'user_id' => $passenger->id, 'role' => 'passenger', 'issue_type' => $issueType, 'status' => 'Active', 'latitude' => 3.14, 'longitude' => 101.69, 'location_source' => 'pickup']);
        $this->assertSame(1, $driver->notifications()->where('type', EmergencyAlertNotification::class)->count());
        $this->assertSame($message, $driver->notifications()->where('type', EmergencyAlertNotification::class)->firstOrFail()->data['message']);
        $this->assertSame(0, $passenger->notifications()->where('type', EmergencyAlertNotification::class)->count());
    }

    public function test_unauthorized_user_cannot_report_for_another_trip(): void
    {
        [, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $otherPassenger = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($otherPassenger)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'medical_emergency'])->assertForbidden();
        $this->assertDatabaseCount('emergencies', 0);
    }

    public function test_scheduled_trip_cannot_receive_an_issue_report(): void
    {
        [$driver, $trip] = $this->trip();

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'medical_emergency'])->assertStatus(422);
        $this->assertDatabaseCount('emergencies', 0);
    }

    public function test_device_coordinates_are_saved_as_the_current_location(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => 'safety_risk',
            'latitude' => 3.1390,
            'longitude' => 101.6869,
            'location_source' => 'device',
        ])->assertRedirect();

        $this->assertDatabaseHas('emergencies', [
            'trip_id' => $trip->trip_id,
            'latitude' => 3.1390,
            'longitude' => 101.6869,
            'location_source' => 'device',
        ]);
    }

    public function test_unavailable_device_location_still_reports_with_an_honest_fallback(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now(), 'departure_latitude' => 3.1500, 'departure_longitude' => 101.7000]);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => 'other_emergency',
            'location_source' => 'unavailable',
        ])->assertRedirect();

        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'location_source' => 'departure', 'latitude' => 3.1500, 'longitude' => 101.7000]);
    }

    public function test_driver_emergency_uses_a_recent_live_tracking_location_before_departure_fallback(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now(), 'departure_latitude' => 3.1500, 'departure_longitude' => 101.7000]);
        TripLocation::create([
            'trip_id' => $trip->trip_id,
            'driver_id' => $driver->id,
            'latitude' => 3.1390,
            'longitude' => 101.6869,
            'recorded_at' => now()->subSeconds(30),
        ]);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => 'other_emergency',
            'location_source' => 'unavailable',
        ])->assertRedirect();

        $this->assertDatabaseHas('emergencies', [
            'trip_id' => $trip->trip_id,
            'location_source' => 'live_tracking',
            'latitude' => 3.1390,
            'longitude' => 101.6869,
        ]);
    }

    public function test_driver_emergency_does_not_use_a_stale_live_tracking_location(): void
    {
        config()->set('trips.emergency_live_location_max_age_seconds', 60);
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now(), 'departure_latitude' => 3.1500, 'departure_longitude' => 101.7000]);
        TripLocation::create([
            'trip_id' => $trip->trip_id,
            'driver_id' => $driver->id,
            'latitude' => 3.1390,
            'longitude' => 101.6869,
            'recorded_at' => now()->subSeconds(61),
        ]);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => 'other_emergency',
            'location_source' => 'unavailable',
        ])->assertRedirect();

        $this->assertDatabaseHas('emergencies', [
            'trip_id' => $trip->trip_id,
            'location_source' => 'departure',
            'latitude' => 3.1500,
            'longitude' => 101.7000,
        ]);
    }

    public function test_multiple_reports_are_stored_without_overwriting_each_other(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'safety_risk']);
        $this->actingAs($passenger)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'medical_emergency']);
        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'accident_road_danger']);

        $this->assertDatabaseCount('emergencies', 3);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'safety_risk']);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'medical_emergency']);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'accident_road_danger']);
    }

    public function test_reporter_cannot_acknowledge_their_own_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'safety_risk', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($driver)->patch(route('emergencies.acknowledge', $report))->assertForbidden();
        $this->assertSame('Active', $report->refresh()->status);
        $this->assertNull($report->acknowledged_by);
    }

    public function test_another_authorized_participant_can_acknowledge_a_report(): void
    {
        Event::fake([EmergencyAcknowledged::class]);
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'safety_risk', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($passenger)->patch(route('emergencies.acknowledge', $report))->assertRedirect();
        $this->assertSame('Acknowledged', $report->refresh()->status);
        $this->assertSame($passenger->id, $report->acknowledged_by);
        $this->assertNull($report->resolved_at);
        Event::assertDispatched(EmergencyAcknowledged::class, fn (EmergencyAcknowledged $event) => $event->emergency->is($report) && $event->emergency->status === 'Acknowledged');
        $this->assertTrue(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => $route->getName() === 'emergencies.resolve'));
    }

    public function test_acknowledged_emergency_can_be_resolved_by_a_trip_participant(): void
    {
        Event::fake([EmergencyResolved::class]);
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $report = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $driver->id,
            'role' => 'driver',
            'issue_type' => 'safety_risk',
            'status' => 'Acknowledged',
            'triggered_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $passenger->id,
        ]);

        $this->actingAs($driver)->patch(route('emergencies.resolve', $report))
            ->assertRedirect()
            ->assertSessionHas('success', 'Emergency marked as resolved.');

        $this->assertSame('Resolved', $report->refresh()->status);
        $this->assertSame($driver->id, $report->resolved_by);
        $this->assertNotNull($report->resolved_at);
        Event::assertDispatched(EmergencyResolved::class, fn (EmergencyResolved $event) => $event->emergency->is($report));
    }

    public function test_passenger_cannot_resolve_a_driver_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $report = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $driver->id,
            'role' => 'driver',
            'issue_type' => 'safety_risk',
            'status' => 'Acknowledged',
            'triggered_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $passenger->id,
        ]);

        $this->actingAs($passenger)->patch(route('emergencies.resolve', $report))->assertForbidden();
        $this->assertSame('Acknowledged', $report->fresh()->status);
    }

    public function test_passenger_can_resolve_their_own_acknowledged_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $report = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $passenger->id,
            'role' => 'passenger',
            'issue_type' => 'medical_emergency',
            'status' => 'Acknowledged',
            'triggered_at' => now(),
            'acknowledged_at' => now(),
            'acknowledged_by' => $driver->id,
        ]);

        $this->actingAs($passenger)->patch(route('emergencies.resolve', $report))->assertRedirect();
        $report->refresh();
        $this->assertSame('Resolved', $report->status);
        $this->assertSame($passenger->id, $report->resolved_by);
    }

    public function test_driver_can_end_an_emergency_trip_before_any_pickup(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger);
        Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'accident_road_danger', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($driver)->patch(route('driver.trips.emergency-cancel', $trip))->assertRedirect(route('driver.trips.journey'));

        $this->assertSame('Cancelled', $trip->fresh()->status);
        $this->assertSame('Cancelled', $booking->fresh()->booking_status);
    }

    public function test_driver_cannot_end_an_emergency_trip_after_a_passenger_has_boarded(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger, ['picked_up_at' => now()]);
        Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'accident_road_danger', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($driver)->patch(route('driver.trips.emergency-cancel', $trip))->assertStatus(422);
        $this->assertSame('In Progress', $trip->fresh()->status);
    }

    public function test_active_emergency_cannot_be_resolved_before_acknowledgement(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'safety_risk', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($driver)->patch(route('emergencies.resolve', $report))->assertStatus(422);
        $this->assertSame('Active', $report->refresh()->status);
    }

    public function test_unauthorized_user_cannot_acknowledge_a_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'safety_risk', 'status' => 'Active', 'triggered_at' => now()]);
        $outsider = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($outsider)->patch(route('emergencies.acknowledge', $report))->assertForbidden();
        $this->assertSame('Active', $report->refresh()->status);
    }

    public function test_each_recipient_receives_exactly_one_database_notification(): void
    {
        Event::fake([EmergencyTriggered::class]);
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'safety_risk']);

        $this->assertSame(1, $passenger->notifications()->where('type', EmergencyAlertNotification::class)->count());
        $this->assertSame(0, $driver->notifications()->where('type', EmergencyAlertNotification::class)->count());
        Event::assertDispatched(EmergencyTriggered::class, 1);
    }

    public function test_duplicate_submission_creates_only_one_emergency_and_one_notification(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);

        $payload = ['issue_type' => 'safety_risk'];

        // First submission — creates the emergency + notification.
        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), $payload)->assertRedirect();

        // Second submission within 60 s — deduplication guard returns the existing emergency.
        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), $payload)->assertRedirect();

        // Third submission as JSON — same deduplication.
        $this->actingAs($driver)->postJson(route('trips.emergencies.store', $trip), $payload)->assertOk();

        $this->assertDatabaseCount('emergencies', 1);
        $this->assertSame(1, $passenger->notifications()->where('type', EmergencyAlertNotification::class)->count());
    }

    public function test_json_report_returns_the_panel_url_needed_for_realtime_refresh(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);

        $response = $this->actingAs($driver)
            ->postJson(route('trips.emergencies.store', $trip), ['issue_type' => 'safety_risk'])
            ->assertOk()
            ->assertJsonPath('trip_id', $trip->trip_id)
            ->assertJsonPath('panel_url', route('trips.emergencies.panel', $trip));

        $this->assertSame(Emergency::firstOrFail()->id, $response->json('emergency_id'));
    }

    public function test_emergency_event_includes_the_panel_url_for_the_recipient(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger);
        $emergency = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $driver->id,
            'role' => 'driver',
            'issue_type' => 'safety_risk',
            'status' => 'Active',
            'triggered_at' => now(),
        ]);

        $payload = (new EmergencyTriggered($emergency, $passenger->id, 'passenger', $booking))->broadcastWith();

        $this->assertSame(route('trips.emergencies.panel', $trip), $payload['panel_url']);
    }

    public function test_trip_participants_can_refresh_the_emergency_panel_but_outsiders_cannot(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $outsider = User::factory()->create(['role' => 'passenger']);
        $emergency = Emergency::create([
            'trip_id' => $trip->trip_id,
            'user_id' => $passenger->id,
            'role' => 'passenger',
            'issue_type' => 'medical_emergency',
            'status' => 'Active',
            'triggered_at' => now(),
        ]);

        foreach ([$driver, $passenger] as $participant) {
            $this->actingAs($participant)
                ->get(route('trips.emergencies.panel', $trip))
                ->assertOk()
                ->assertSee('data-emergency-panel', false)
                ->assertSee('emergency-'.$emergency->id, false)
                ->assertSee('Medical Emergency');
        }

        $this->actingAs($outsider)
            ->get(route('trips.emergencies.panel', $trip))
            ->assertForbidden();
    }

    private function trip(array $overrides = []): array
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::factory()->verified()->create(['user_id' => $driver->id, 'status' => 'Active']);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id,
            'departure_location' => 'Suria KLCC',
            'destination' => 'KLIA',
            'departure_at' => now()->addDay(),
            'available_seats' => 3,
            'price_per_passenger' => 12,
            'status' => 'Scheduled',
            ...$overrides,
        ]);

        return [$driver, $trip];
    }

    private function booking(Trip $trip, User $passenger, array $overrides = []): Booking
    {
        return Booking::create([
            'trip_id' => $trip->trip_id,
            'passenger_id' => $passenger->id,
            'booking_status' => 'Accepted',
            'number_of_seats' => 1,
            'pickup_point' => 'Bukit Bintang',
            ...$overrides,
        ]);
    }
}
