<?php

namespace App\Http\Controllers;

use App\Events\EmergencyTriggered;
use App\Events\EmergencyAcknowledged;
use App\Events\EmergencyResolved;
use App\Models\Booking;
use App\Models\Emergency;
use App\Models\Trip;
use App\Notifications\EmergencyAlertNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class EmergencyController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $participant = $this->participantBooking($request, $trip);
        abort_unless($trip->status === 'In Progress', 422, 'Emergency reports are available only during an active trip.');
        $role = $request->user()->role;
        $issueTypes = ['medical_emergency', 'safety_risk', 'accident_road_danger', 'other_emergency'];
        $validated = $request->validate([
            'issue_type' => ['required', 'string', Rule::in($issueTypes)],
            'description' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_source' => ['nullable', 'string', Rule::in(['device', 'unavailable'])],
        ]);

        // Prevent duplicate emergencies from double-click / AJAX-then-fallback.
        $recent = Emergency::query()
            ->where('trip_id', $trip->trip_id)
            ->where('user_id', $request->user()->id)
            ->where('issue_type', $validated['issue_type'])
            ->where('status', 'Active')
            ->where('triggered_at', '>=', now()->subMinute())
            ->first();

        if ($recent) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Emergency reported successfully.', 'emergency_id' => $recent->id]);
            }

            return back()->with('success', 'Emergency reported successfully.');
        }

        $emergency = DB::transaction(function () use ($request, $trip, $participant, $validated) {
            [$latitude, $longitude, $locationSource] = $this->emergencyLocation($validated, $trip, $participant);

            return Emergency::create([
                'trip_id' => $trip->trip_id,
                'user_id' => $request->user()->id,
                'role' => $request->user()->role,
                'issue_type' => $validated['issue_type'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_source' => $locationSource,
                'description' => $validated['description'] ?? null,
                'status' => 'Active',
                'triggered_at' => now(),
            ]);
        });

        $this->notifyParticipants($emergency, $trip);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Emergency reported successfully.',
                'emergency_id' => $emergency->id,
            ]);
        }

        return back()->with('success', 'Emergency reported successfully.');
    }

    public function acknowledge(Request $request, Emergency $emergency): RedirectResponse
    {
        $this->participantBooking($request, $emergency->trip);
        abort_unless($emergency->user_id !== $request->user()->id, 403);
        abort_unless($emergency->status === 'Active', 422);
        $emergency->update(['status' => 'Acknowledged', 'acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id]);
        EmergencyAcknowledged::dispatch($emergency->fresh());

        return back()->with('success', 'Issue report acknowledged.');
    }

    public function resolve(Request $request, Emergency $emergency): RedirectResponse
    {
        $this->participantBooking($request, $emergency->trip);
        abort_unless($emergency->status === 'Acknowledged', 422, 'An emergency must be acknowledged before it can be resolved.');

        $emergency->update([
            'status' => 'Resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        EmergencyResolved::dispatch($emergency->fresh());

        return back()->with('success', 'Emergency marked as resolved.');
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

    private function emergencyLocation(array $validated, Trip $trip, ?Booking $participant): array
    {
        if (($validated['location_source'] ?? null) === 'device'
            && array_key_exists('latitude', $validated) && array_key_exists('longitude', $validated)
            && $validated['latitude'] !== null && $validated['longitude'] !== null) {
            return [(float) $validated['latitude'], (float) $validated['longitude'], 'device'];
        }

        if ($participant && is_numeric($participant->pickup_latitude) && is_numeric($participant->pickup_longitude)) {
            return [(float) $participant->pickup_latitude, (float) $participant->pickup_longitude, 'pickup'];
        }

        if (is_numeric($trip->departure_latitude) && is_numeric($trip->departure_longitude)) {
            return [(float) $trip->departure_latitude, (float) $trip->departure_longitude, 'departure'];
        }

        return [null, null, 'unavailable'];
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
