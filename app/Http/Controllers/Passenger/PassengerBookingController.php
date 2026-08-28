<?php

namespace App\Http\Controllers\Passenger;

use App\Events\BookingCreated;
use App\Events\BookingStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\BookingRequestNotification;
use App\Services\Routing\TripLocationService;
use App\Services\Routing\TripDistanceService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PassengerBookingController extends Controller
{
    private const DESTINATION_FILTER_RADIUS_KM = 20;
    private const DESTINATION_CLOSE_RADIUS_KM = 5;
    private const DESTINATION_NEAR_RADIUS_KM = 10;

    public function __construct(
        private readonly TripLocationService $tripLocationService,
        private readonly TripDistanceService $tripDistanceService,
    ) {}

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

    public function currentLocation(Request $request): JsonResponse
    {
        $this->ensurePassenger($request);
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $location = $this->tripLocationService->reverseGeocode(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                'destination_place_id'
            );
        } catch (TripRoutingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'text' => $location['display'],
                'place_id' => $location['place_id'],
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $this->ensurePassenger($request);

        $query = Trip::query()
            ->with([
                'user' => fn ($driver) => $driver
                    ->with('driverPreference')
                    ->withAvg('ratingsReceived', 'score')
                    ->withCount('ratingsReceived'),
                'vehicle',
            ])
            ->where('status', 'Scheduled')
            ->where('available_seats', '>', 0)
            ->where('departure_at', '>=', now())
            ->whereHas('user.driverLicence', fn ($licence) => $licence
                ->where('verification_status', 'Verified')
                ->whereDate('valid_until', '>=', today())
                ->whereRaw('DATE(valid_until) >= DATE(trips.departure_at)'))
            ->where('user_id', '!=', $request->user()->id)
            ->whereDoesntHave('bookings', fn ($booking) => $booking
                ->where('passenger_id', $request->user()->id)
                ->where('booking_status', '!=', 'Rejected'));

        $rankBySearchScore = false;

        if ($request->filled('destination')) {
            $this->applyDestinationSearch($query, $request);
            $rankBySearchScore = true;
        }

        if ($request->filled('travel_date')) {
            $query->whereDate('departure_at', $request->input('travel_date'));
        }

        if ($request->filled('passengers')) {
            $query->where('available_seats', '>=', $request->integer('passengers'));
        }

        if ($rankBySearchScore) {
            $query->orderByDesc('search_score');
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
                        ->with('driverPreference')
                        ->withAvg('ratingsReceived', 'score')
                        ->withCount('ratingsReceived'),
                    'vehicle',
                ])
                ->where('status', 'Scheduled')
                ->where('available_seats', '>', 0)
                ->whereHas('user.driverLicence', fn ($licence) => $licence
                    ->where('verification_status', 'Verified')
                    ->whereDate('valid_until', '>=', today())
                    ->whereRaw('DATE(valid_until) >= DATE(trips.departure_at)'))
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

            $licence = $trip->user->driverLicence;
            if (! $licence || ! $licence->isValidOn(now()) || ! $licence->isValidOn($trip->departure_at)) {
                throw ValidationException::withMessages([
                    'trip_id' => 'This trip is unavailable because the driver does not have a valid licence for the departure date.',
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

            $existingBooking = Booking::query()
                ->where('trip_id', $trip->trip_id)
                ->where('passenger_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($existingBooking && $existingBooking->booking_status !== 'Rejected') {
                throw ValidationException::withMessages([
                    'trip_id' => 'You already submitted a booking request for this trip.',
                ]);
            }

            $bookingData = [
                ...$request->bookingData(),
                'pickup_point' => $pickup['display'],
                'pickup_place_id' => $request->string('pickup_place_id')->toString(),
                'pickup_latitude' => $pickup['latitude'],
                'pickup_longitude' => $pickup['longitude'],
            ];

            if ($existingBooking) {
                $existingBooking->update($bookingData);

                return $existingBooking->refresh();
            }

            return Booking::create($bookingData);
        });

        $booking->trip->user->notify(new BookingRequestNotification($booking, 'submitted'));
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
                'payment',
            ])
            ->latest()
            ->paginate(8);

        return view('passenger.booking.history', compact('bookings'));
    }

    public function show(Request $request, Booking $booking): View
    {
        $this->ensurePassenger($request);
        abort_unless($booking->passenger_id === $request->user()->id, 403);

        $booking->load([
            'trip.user' => fn ($driver) => $driver
                ->withAvg('ratingsReceived', 'score')
                ->withCount('ratingsReceived'),
            'trip.vehicle',
            'trip.bookings' => fn ($tripBookings) => $tripBookings
                ->where('booking_status', 'Accepted')
                ->orderByRaw('case when pickup_sequence is null then 1 else 0 end')
                ->orderBy('pickup_sequence')
                ->oldest(),
            'trip.emergencies' => fn ($emergencies) => $emergencies->with('user')->latest('triggered_at'),
            'trip.latestLocation',
            'ratings',
            'payment',
        ]);

        $mapRoute = $this->mapRoute($booking);

        return view('passenger.booking.show', compact('booking', 'mapRoute'));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->ensurePassenger($request);

        abort_unless($booking->passenger_id === $request->user()->id, 403);

        if ($booking->booking_status !== 'Pending') {
            return back()->with('error', 'Only pending booking requests can be cancelled.');
        }

        $booking->update(['booking_status' => 'Cancelled']);
        $booking->trip->user->notify(new BookingRequestNotification($booking, 'cancelled'));
        BookingStatusUpdated::dispatch($booking, 'booking_request_cancelled');

        return redirect()->route('passenger.bookings.history')->with('success', 'Booking request cancelled successfully.');
    }

    private function ensurePassenger(Request $request): void
    {
        abort_unless($request->user()?->role === 'passenger', 403);
    }

    private function mapRoute(Booking $booking): ?array
    {
        $trip = $booking->trip;

        if ($booking->booking_status !== 'Accepted'
            || $trip->status !== 'In Progress'
            || blank(config('services.google_maps.browser_key'))
        ) {
            return null;
        }

        $locations = [
            [$trip->departure_latitude, $trip->departure_longitude],
            [$trip->destination_latitude, $trip->destination_longitude],
            ...$trip->bookings->map(fn (Booking $item) => [$item->pickup_latitude, $item->pickup_longitude])->all(),
        ];

        if (collect($locations)->contains(fn (array $location) => ! is_numeric($location[0]) || ! is_numeric($location[1]))) {
            return null;
        }

        try {
            return $this->tripDistanceService->calculate(
                ['latitude' => $trip->departure_latitude, 'longitude' => $trip->departure_longitude],
                ['latitude' => $trip->destination_latitude, 'longitude' => $trip->destination_longitude],
                $trip->bookings->map(fn (Booking $item) => ['latitude' => $item->pickup_latitude, 'longitude' => $item->pickup_longitude])->all(),
            );
        } catch (TripRoutingException) {
            return null;
        }
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
                            $this->destinationDistanceSql().' <= ?',
                            [
                                $selectedLocation['latitude'],
                                $selectedLocation['longitude'],
                                $selectedLocation['latitude'],
                                self::DESTINATION_FILTER_RADIUS_KM,
                            ]
                        );
                });
            }
        });

        $this->addDestinationSearchScore($query, $request, $destination, $keywords, $selectedLocation);
    }

    /**
     * @param list<string> $keywords
     */
    private function addDestinationSearchScore($query, Request $request, string $destination, array $keywords, ?array $selectedLocation): void
    {
        $scoreParts = ['CASE WHEN LOWER(destination) LIKE ? THEN 50 ELSE 0 END'];
        $bindings = ['%'.strtolower($destination).'%'];

        foreach ($keywords as $keyword) {
            $scoreParts[] = 'CASE WHEN LOWER(destination) LIKE ? THEN 20 ELSE 0 END';
            $bindings[] = '%'.strtolower($keyword).'%';
        }

        if ($selectedLocation) {
            $distanceSql = $this->destinationDistanceSql();
            $scoreParts[] = "CASE
                WHEN destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL AND {$distanceSql} <= ? THEN 40
                WHEN destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL AND {$distanceSql} <= ? THEN 30
                WHEN destination_latitude IS NOT NULL AND destination_longitude IS NOT NULL AND {$distanceSql} <= ? THEN 15
                ELSE 0
            END";
            $bindings = [
                ...$bindings,
                ...$this->destinationDistanceBindings($selectedLocation),
                self::DESTINATION_CLOSE_RADIUS_KM,
                ...$this->destinationDistanceBindings($selectedLocation),
                self::DESTINATION_NEAR_RADIUS_KM,
                ...$this->destinationDistanceBindings($selectedLocation),
                self::DESTINATION_FILTER_RADIUS_KM,
            ];
        }

        if ($request->filled('travel_date')) {
            $scoreParts[] = 'CASE WHEN DATE(departure_at) = ? THEN 20 ELSE 0 END';
            $bindings[] = $request->input('travel_date');
        }

        if ($request->filled('passengers')) {
            $scoreParts[] = 'CASE WHEN available_seats >= ? THEN 10 ELSE 0 END';
            $bindings[] = $request->integer('passengers');
        }

        $ratingAverageSql = '(SELECT AVG(score) FROM ratings WHERE ratings.reviewee_id = trips.user_id)';
        $scoreParts[] = "CASE
            WHEN COALESCE({$ratingAverageSql}, 0) >= 4.5 THEN 10
            WHEN COALESCE({$ratingAverageSql}, 0) >= 4 THEN 5
            ELSE 0
        END";

        $query->addSelect('trips.*')->selectRaw('('.implode(' + ', $scoreParts).') AS search_score', $bindings);
    }

    private function destinationDistanceSql(): string
    {
        return '(6371 * acos(
            cos(radians(?)) * cos(radians(destination_latitude)) *
            cos(radians(destination_longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(destination_latitude))
        ))';
    }

    private function destinationDistanceBindings(array $location): array
    {
        return [
            $location['latitude'],
            $location['longitude'],
            $location['latitude'],
        ];
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
