<?php

namespace App\Http\Controllers\Passenger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PassengerBookingController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensurePassenger($request);

        $query = Trip::query()
            ->with(['user', 'vehicle'])
            ->where('status', 'Scheduled')
            ->where('available_seats', '>', 0)
            ->where('departure_at', '>=', now())
            ->where('user_id', '!=', $request->user()->id);

        if ($request->filled('destination')) {
            $destination = $request->string('destination')->trim();
            $query->where('destination', 'like', "%{$destination}%");
        }

        if ($request->filled('travel_date')) {
            $query->whereDate('departure_at', $request->input('travel_date'));
        }

        if ($request->filled('passengers')) {
            $query->where('available_seats', '>=', $request->integer('passengers'));
        }

        $trips = $query->orderBy('departure_at')->paginate(6)->withQueryString();

        return view('passenger.booking.index', compact('trips'));
    }

    public function create(Request $request): View
    {
        $this->ensurePassenger($request);

        $trip = null;

        if ($request->filled('trip_id')) {
            $trip = Trip::query()
                ->with(['user', 'vehicle'])
                ->where('status', 'Scheduled')
                ->where('available_seats', '>', 0)
                ->findOrFail($request->integer('trip_id'));
        }

        return view('passenger.booking.create', compact('trip'));
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $trip = Trip::query()
                ->whereKey($request->integer('trip_id'))
                ->lockForUpdate()
                ->firstOrFail();

            if ($trip->status !== 'Scheduled' || $trip->departure_at->isPast()) {
                throw ValidationException::withMessages([
                    'trip_id' => 'This trip is no longer available for booking.',
                ]);
            }

            if ($trip->user_id === $request->user()->id) {
                throw ValidationException::withMessages([
                    'trip_id' => 'You cannot book your own trip.',
                ]);
            }

            if ($request->integer('number_of_seats') > $trip->available_seats) {
                throw ValidationException::withMessages([
                    'number_of_seats' => 'Requested seats must not exceed the available seats.',
                ]);
            }

            $alreadyBooked = Booking::query()
                ->where('trip_id', $trip->trip_id)
                ->where('passenger_id', $request->user()->id)
                ->exists();

            if ($alreadyBooked) {
                throw ValidationException::withMessages([
                    'trip_id' => 'You already submitted a booking request for this trip.',
                ]);
            }

            Booking::create($request->bookingData());
        });

        return redirect()->route('passenger.bookings.history')->with('success', 'Booking request submitted successfully.');
    }

    public function history(Request $request): View
    {
        $this->ensurePassenger($request);

        $bookings = $request->user()
            ->bookings()
            ->with(['trip.user', 'trip.vehicle', 'ratings'])
            ->latest()
            ->paginate(8);

        return view('passenger.booking.history', compact('bookings'));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensurePassenger($request);

        abort_unless($booking->passenger_id === $request->user()->id, 403);

        if ($booking->booking_status !== 'Pending') {
            return back()->with('error', 'Only pending booking requests can be cancelled.');
        }

        $booking->update(['booking_status' => 'Cancelled']);

        return redirect()->route('passenger.bookings.history')->with('success', 'Booking request cancelled successfully.');
    }

    private function ensurePassenger(Request $request): void
    {
        abort_unless($request->user()?->role === 'passenger', 403);
    }
}
