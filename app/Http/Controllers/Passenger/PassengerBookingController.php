<?php

namespace App\Http\Controllers\Passenger;

use App\Events\BookingCreated;
use App\Events\BookingStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\Routing\TripLocationService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PassengerBookingController extends Controller
{
    private const DESTINATION_RADIUS_KM = 10;

    public function __construct(private readonly TripLocationService $tripLocationService) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $this->ensurePassenger($request);
        $request->validate(['input' => ['required', 'string', 'min:2', 'max:255']]);

        try {
            return response()->json(['data' => $this->tripLocationService->autocomplete($request->string('input')->trim()->toString())]);
        } catch (TripRoutingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['pickup_place_id' => [$exception->getMessage()]]], 422);
        }
    }

    public function index(Request $request): View
    {
        $this->ensurePassenger($request);

        $query = Trip::query()
            ->with([
                'user' => fn ($driver) => $driver
                    ->withAvg('ratingsReceived', 'score')
                    ->withCount('ratingsReceived'),
                'vehicle',
            ])
            ->where('status', 'Scheduled')
            ->where('available_seats', '>', 0)
            ->where('departure_at', '>=', now())
            ->where('user_id', '!=', $request->user()->id);

        if ($request->filled('destination')) {
            $this->applyDestinationSearch($query, $request);
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
                ->with([
                    'user' => fn ($driver) => $driver
                        ->withAvg('ratingsReceived', 'score')
                        ->withCount('ratingsReceived'),
                    'vehicle',
                ])
                ->where('status', 'Scheduled')
                ->where('available_seats', '>', 0)
                ->findOrFail($request->integer('trip_id'));
        }

        return view('passenger.booking.create', compact('trip'));
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        try {
            $pickup = $this->tripLocationService->resolve($request->string('pickup_place_id')->toString(), 'pickup_place_id');
        } catch (TripRoutingException $exception) {
            return back()->withInput()->withErrors(['pickup_place_id' => $exception->getMessage()]);
        }

        $booking = DB::transaction(function () use ($request, $pickup) {
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

            return Booking::create([
                ...$request->bookingData(),
                'pickup_point' => $pickup['display'],
                'pickup_place_id' => $request->string('pickup_place_id')->toString(),
                'pickup_latitude' => $pickup['latitude'],
                'pickup_longitude' => $pickup['longitude'],
            ]);
        });

        BookingCreated::dispatch($booking);

        return redirect()->route('passenger.bookings.history')->with('success', 'Booking request submitted successfully.');
    }

    public function history(Request $request): View
    {
        $this->ensurePassenger($request);

        $bookings = $request->user()
            ->bookings()
            ->with([
                'trip.user' => fn ($driver) => $driver
                    ->withAvg('ratingsReceived', 'score')
                    ->withCount('ratingsReceived'),
                'trip.vehicle',
                'ratings',
            ])
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
        BookingStatusUpdated::dispatch($booking);

        return redirect()->route('passenger.bookings.history')->with('success', 'Booking request cancelled successfully.');
    }

    private function ensurePassenger(Request $request): void
    {
        abort_unless($request->user()?->role === 'passenger', 403);
    }

    private function applyDestinationSearch($query, Request $request): void
    {
        $destination = $request->string('destination')->trim()->toString();
        $keywords = $this->destinationKeywords($destination);
        $selectedLocation = null;

        if ($request->filled('destination_place_id')) {
            try {
                $selectedLocation = $this->tripLocationService->resolve(
                    $request->string('destination_place_id')->toString(),
                    'destination_place_id'
                );
            } catch (TripRoutingException) {
                $selectedLocation = null;
            }
        }

        $query->where(function ($query) use ($destination, $keywords, $selectedLocation) {
            $query->where('destination', 'like', "%{$destination}%");

            foreach ($keywords as $keyword) {
                $query->orWhere('destination', 'like', "%{$keyword}%");
            }

            if ($selectedLocation) {
                $query->orWhere(function ($query) use ($selectedLocation) {
                    $query->whereNotNull('destination_latitude')
                        ->whereNotNull('destination_longitude')
                        ->whereRaw(
                            '(6371 * acos(
                                cos(radians(?)) * cos(radians(destination_latitude)) *
                                cos(radians(destination_longitude) - radians(?)) +
                                sin(radians(?)) * sin(radians(destination_latitude))
                            )) <= ?',
                            [
                                $selectedLocation['latitude'],
                                $selectedLocation['longitude'],
                                $selectedLocation['latitude'],
                                self::DESTINATION_RADIUS_KM,
                            ]
                        );
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function destinationKeywords(string $destination): array
    {
        $stopWords = [
            'malaysia', 'jalan', 'jln', 'lorong', 'persiaran', 'taman', 'kampung',
            'kg', 'near', 'and', 'the', 'of', 'to', 'in',
        ];

        return collect(preg_split('/[\s,;()\-]+/', $destination) ?: [])
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => strlen($word) >= 3)
            ->reject(fn (string $word) => is_numeric($word))
            ->reject(fn (string $word) => in_array(strtolower($word), $stopWords, true))
            ->unique(fn (string $word) => strtolower($word))
            ->take(8)
            ->values()
            ->all();
    }
}
