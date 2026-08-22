<?php

namespace App\Http\Controllers\Driver;

use App\Events\BookingStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\BookingStatusNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DriverBookingController extends Controller
{
    public function index(Request $request): View
    {
        $query = Booking::query()
            ->with([
                'passenger' => fn ($passenger) => $passenger
                    ->withAvg('ratingsReceived', 'score')
                    ->withCount('ratingsReceived'),
                'trip.vehicle',
            ])
            ->whereHas('trip', fn ($trip) => $trip->where('user_id', $request->user()->id));

        if (in_array($request->input('status'), ['Pending', 'Accepted', 'Rejected', 'Cancelled'], true)) {
            $query->where('booking_status', $request->input('status'));
        }

        $bookings = $query->latest()->paginate(8)->withQueryString();

        return view('driver.booking.index', compact('bookings'));
    }

    public function show(Request $request, Booking $booking): View
    {
        $booking->load([
            'passenger' => fn ($passenger) => $passenger
                ->withAvg('ratingsReceived', 'score')
                ->withCount('ratingsReceived'),
            'trip.vehicle',
        ]);
        $this->ensureDriverOwnsTrip($request, $booking->trip);

        return view('driver.booking.show', compact('booking'));
    }

    public function accept(Request $request, Booking $booking): RedirectResponse
    {
        $booking = DB::transaction(function () use ($request, $booking) {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $trip = Trip::query()
                ->whereKey($booking->trip_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDriverOwnsTrip($request, $trip);
            $this->ensurePending($booking);

            if ($trip->status !== 'Scheduled') {
                throw ValidationException::withMessages([
                    'booking' => 'Only scheduled trips can accept booking requests.',
                ]);
            }

            if ($booking->number_of_seats > $trip->available_seats) {
                throw ValidationException::withMessages([
                    'booking' => 'There are not enough available seats for this booking request.',
                ]);
            }

            $booking->update(['booking_status' => 'Accepted']);
            $trip->decrement('available_seats', $booking->number_of_seats);

            return $booking;
        });

        $booking->passenger->notify(new BookingStatusNotification($booking, 'Accepted'));
        BookingStatusUpdated::dispatch($booking, null, 'booking_request_accepted');

        return redirect()->route('driver.booking-requests.index')->with('success', 'Booking request accepted successfully.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $booking = DB::transaction(function () use ($request, $booking) {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $trip = Trip::query()
                ->whereKey($booking->trip_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDriverOwnsTrip($request, $trip);
            $this->ensurePending($booking);

            $booking->update(['booking_status' => 'Rejected']);

            return $booking;
        });

        $booking->passenger->notify(new BookingStatusNotification($booking, 'Rejected'));
        BookingStatusUpdated::dispatch($booking, null, 'booking_request_rejected');

        return redirect()->route('driver.booking-requests.index')->with('success', 'Booking request rejected successfully.');
    }

    private function ensureDriverOwnsTrip(Request $request, Trip $trip): void
    {
        abort_unless($trip->user_id === $request->user()->id, 403);
    }

    private function ensurePending(Booking $booking): void
    {
        if ($booking->booking_status !== 'Pending') {
            throw ValidationException::withMessages([
                'booking' => 'Only pending booking requests can be updated.',
            ]);
        }
    }
}
