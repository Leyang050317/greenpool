<?php

namespace App\Http\Controllers;

use App\Events\EmergencyTriggered;
use App\Models\Booking;
use App\Models\Emergency;
use App\Models\Trip;
use App\Notifications\EmergencyAlertNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class EmergencyController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $participant = $this->participantBooking($request, $trip);
        abort_unless($trip->status === 'In Progress', 422, 'Issue reports are available only during an active trip.');
        $role = $request->user()->role;
        $issueTypes = $role === 'driver'
            ? ['traffic_delay', 'vehicle_problem', 'road_hazard', 'other']
            : ['personal_emergency', 'medical_emergency', 'other'];
        $validated = $request->validate([
            'issue_type' => ['required', 'string', Rule::in($issueTypes)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $emergency = DB::transaction(function () use ($request, $trip, $participant, $validated) {
            [$latitude, $longitude] = $participant
                ? [$participant->pickup_latitude, $participant->pickup_longitude]
                : [$trip->departure_latitude, $trip->departure_longitude];

            return Emergency::create([
                'trip_id' => $trip->trip_id,
                'user_id' => $request->user()->id,
                'role' => $request->user()->role,
                'issue_type' => $validated['issue_type'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'description' => $validated['description'] ?? null,
                'status' => 'Active',
                'triggered_at' => now(),
            ]);
        });

        $this->notifyParticipants($emergency, $trip);

        return back()->with('success', 'Issue reported successfully.');
    }

    public function acknowledge(Request $request, Emergency $emergency): RedirectResponse
    {
        $this->participantBooking($request, $emergency->trip);
        abort_unless($emergency->user_id !== $request->user()->id, 403);
        abort_unless($emergency->status === 'Active', 422);
        $emergency->update(['status' => 'Acknowledged', 'acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id]);

        return back()->with('success', 'Issue report acknowledged.');
    }

    private function participantBooking(Request $request, Trip $trip): ?Booking
    {
        if ($trip->user_id === $request->user()->id) {
            return null;
        }

        $booking = Booking::query()
            ->where('trip_id', $trip->trip_id)
            ->where('passenger_id', $request->user()->id)
            ->where('booking_status', 'Accepted')
            ->first();

        abort_unless($booking, 403);

        return $booking;
    }

    private function notifyParticipants(Emergency $emergency, Trip $trip): void
    {
        $trip->loadMissing(['user', 'bookings' => fn ($bookings) => $bookings->where('booking_status', 'Accepted')->with('passenger')]);
        if ($emergency->role === 'driver') {
            foreach ($trip->bookings as $booking) {
                $this->notify($emergency, $booking->passenger, 'passenger', $booking);
            }

            return;
        }

        $this->notify($emergency, $trip->user, 'driver');
    }

    private function notify(Emergency $emergency, $recipient, string $role, ?Booking $booking = null): void
    {
        try {
            $recipient->notify(new EmergencyAlertNotification($emergency, $booking));
            EmergencyTriggered::dispatch($emergency, $recipient->id, $role, $booking);
        } catch (Throwable $exception) {
            Log::warning('Emergency participant notification could not be sent.', ['emergency_id' => $emergency->id, 'user_id' => $recipient->id, 'exception' => $exception->getMessage()]);
        }
    }
}
