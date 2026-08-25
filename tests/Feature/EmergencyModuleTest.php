<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Emergency;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\EmergencyAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmergencyModuleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('driverIssueTypes')]
    public function test_driver_can_report_each_issue_type(string $issueType, string $message): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $booking = $this->booking($trip, $passenger);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), [
            'issue_type' => $issueType,
            'description' => 'Please be aware.',
        ])->assertRedirect()->assertSessionHas('success', 'Issue reported successfully.');

        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => $issueType, 'status' => 'Active']);
        $notification = $passenger->notifications()->where('type', EmergencyAlertNotification::class)->firstOrFail();
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertSame(route('passenger.bookings.show', $booking), $notification->data['url']);
        $this->assertSame('Issue Report', $notification->data['title']);
        $this->assertSame($message, $notification->data['message']);
        $this->assertSame(0, $driver->notifications()->where('type', EmergencyAlertNotification::class)->count());
    }

    public static function driverIssueTypes(): array
    {
        return [
            ['traffic_delay', 'Driver reported a traffic delay. Your trip may be delayed.'],
            ['vehicle_problem', 'Driver reported a vehicle problem for your trip.'],
            ['road_hazard', 'Driver reported a road hazard.'],
            ['other', 'Driver reported an issue.'],
        ];
    }

    #[DataProvider('passengerIssueTypes')]
    public function test_passenger_can_report_each_issue_type(string $issueType, string $message): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger, ['pickup_latitude' => 3.14, 'pickup_longitude' => 101.69]);

        $this->actingAs($passenger)->post(route('trips.emergencies.store', $trip), ['issue_type' => $issueType])
            ->assertRedirect()->assertSessionHas('success', 'Issue reported successfully.');

        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'user_id' => $passenger->id, 'role' => 'passenger', 'issue_type' => $issueType, 'status' => 'Active', 'latitude' => 3.14, 'longitude' => 101.69]);
        $this->assertSame(1, $driver->notifications()->where('type', EmergencyAlertNotification::class)->count());
        $this->assertSame($message, $driver->notifications()->where('type', EmergencyAlertNotification::class)->firstOrFail()->data['message']);
        $this->assertSame(0, $passenger->notifications()->where('type', EmergencyAlertNotification::class)->count());
    }

    public static function passengerIssueTypes(): array
    {
        return [
            ['personal_emergency', 'Passenger reported a personal emergency.'],
            ['medical_emergency', 'Passenger reported a medical emergency during the trip.'],
            ['other', 'Passenger reported an issue.'],
        ];
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

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'traffic_delay'])->assertStatus(422);
        $this->assertDatabaseCount('emergencies', 0);
    }

    public function test_multiple_reports_are_stored_without_overwriting_each_other(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);

        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'traffic_delay']);
        $this->actingAs($passenger)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'medical_emergency']);
        $this->actingAs($driver)->post(route('trips.emergencies.store', $trip), ['issue_type' => 'vehicle_problem']);

        $this->assertDatabaseCount('emergencies', 3);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'traffic_delay']);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'medical_emergency']);
        $this->assertDatabaseHas('emergencies', ['trip_id' => $trip->trip_id, 'issue_type' => 'vehicle_problem']);
    }

    public function test_reporter_cannot_acknowledge_their_own_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'traffic_delay', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($driver)->patch(route('emergencies.acknowledge', $report))->assertForbidden();
        $this->assertSame('Active', $report->refresh()->status);
        $this->assertNull($report->acknowledged_by);
    }

    public function test_another_authorized_participant_can_acknowledge_a_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $this->booking($trip, $passenger);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'traffic_delay', 'status' => 'Active', 'triggered_at' => now()]);

        $this->actingAs($passenger)->patch(route('emergencies.acknowledge', $report))->assertRedirect();
        $this->assertSame('Acknowledged', $report->refresh()->status);
        $this->assertSame($passenger->id, $report->acknowledged_by);
        $this->assertNull($report->resolved_at);
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => $route->getName() === 'emergencies.resolve'));
    }

    public function test_unauthorized_user_cannot_acknowledge_a_report(): void
    {
        [$driver, $trip] = $this->trip(['status' => 'In Progress', 'started_at' => now()]);
        $report = Emergency::create(['trip_id' => $trip->trip_id, 'user_id' => $driver->id, 'role' => 'driver', 'issue_type' => 'traffic_delay', 'status' => 'Active', 'triggered_at' => now()]);
        $outsider = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($outsider)->patch(route('emergencies.acknowledge', $report))->assertForbidden();
        $this->assertSame('Active', $report->refresh()->status);
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
