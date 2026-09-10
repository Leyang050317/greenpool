<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\TripUpdateNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripUpdateController extends Controller
{
    public function __construct(private readonly NotificationDeliveryService $notificationDelivery) {}

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->status === 'In Progress', 422, 'Trip updates are available only during an active trip.');

        $isDriver = $trip->user_id === $request->user()->id;
        $booking = $isDriver ? null : Booking::query()
            ->where('trip_id', $trip->trip_id)
            ->where('passenger_id', $request->user()->id)
            ->where('booking_status', 'Accepted')
            ->first();

        abort_unless($isDriver || $booking, 403);

        $labels = $isDriver
            ? ['traffic_delay' => 'Traffic Delay', 'running_late' => 'Running Late', 'vehicle_problem' => 'Vehicle Problem', 'road_hazard' => 'Road Hazard', 'other' => 'Other Update']
            : ['waiting' => "I'm Waiting", 'running_late' => 'Running Late', 'pickup_clarification' => 'Need Pickup Clarification', 'other' => 'Other Update'];
        $validated = $request->validate([
            'update_type' => ['required', 'string', Rule::in(array_keys($labels))],
        ]);
        $label = $labels[$validated['update_type']];
        $route = $trip->departure_location.' → '.$trip->destination;

        if ($isDriver) {
            $trip->loadMissing(['bookings' => fn ($query) => $query->where('booking_status', 'Accepted')->with('passenger')]);
            foreach ($trip->bookings as $acceptedBooking) {
                $this->notificationDelivery->send(
                    $acceptedBooking->passenger,
                    new TripUpdateNotification($trip, "Driver update: {$label} for {$route}.", $acceptedBooking),
                );
            }
        } else {
            $this->notificationDelivery->send(
                $trip->user,
                new TripUpdateNotification($trip, "Passenger update: {$label} for {$route}."),
            );
        }

        return back()->with('success', 'Update sent.');
    }
}
