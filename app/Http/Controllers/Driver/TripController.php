<?php

namespace App\Http\Controllers\Driver;

use App\Events\BookingStatusUpdated;
use App\Events\TripCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreTripRequest;
use App\Http\Requests\Driver\UpdateTripRequest;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\BookingStatusNotification;
use App\Notifications\PaymentDueNotification;
use App\Notifications\RatingReminderNotification;
use App\Notifications\TripUpdatedNotification;
use App\Services\FareRecommendationService;
use App\Services\NotificationDeliveryService;
use App\Services\PaymentService;
use App\Services\Routing\TripDistanceService;
use App\Services\Routing\TripLocationService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class TripController extends Controller
{
    public function __construct(
        private readonly TripDistanceService $tripDistanceService,
        private readonly TripLocationService $tripLocationService,
        private readonly PaymentService $paymentService,
        private readonly FareRecommendationService $fareRecommendationService,
        private readonly NotificationDeliveryService $notificationDelivery,
    ) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate(['input' => ['required', 'string', 'min:2', 'max:255']]);

        try {
            return response()->json(['data' => $this->tripLocationService->autocomplete($request->string('input')->trim()->toString())]);
        } catch (TripRoutingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => [$exception->errorField => [$exception->getMessage()]]], 422);
        }
    }

    public function fareEstimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'departure_place_id' => ['required', 'string', 'max:255'],
            'destination_place_id' => ['required', 'string', 'max:255'],
            'vehicle_id' => ['required', 'integer'],
        ]);

        $vehicle = $request->user()->vehicles()->whereKey($validated['vehicle_id'])->first();
        if (! $vehicle) {
            return response()->json(['message' => 'Please select one of your vehicles.'], 422);
        }

        try {
            $departure = $this->tripLocationService->resolve($validated['departure_place_id'], 'departure_place_id');
            $destination = $this->tripLocationService->resolve($validated['destination_place_id'], 'destination_place_id');
            $route = $this->tripDistanceService->calculate($departure, $destination);
        } catch (TripRoutingException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => [
            ...$route,
            ...$this->fareRecommendationService->recommend($route['estimated_distance_km'], $vehicle),
        ]]);
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = $this->driverTrips($request)->whereNotIn('status', ['Completed', 'Cancelled']);
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($q) => $q->where('departure_location', 'like', "%{$search}%")->orWhere('destination', 'like', "%{$search}%"));
        }
        if ($request->filled('status') && in_array($request->input('status'), ['Scheduled', 'In Progress'], true)) {
            $query->where('status', $request->input('status'));
        }
        $query->orderBy($request->input('sort') === 'distance' ? 'estimated_distance_km' : 'departure_at');
        $trips = $query
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->paginate(4)
            ->withQueryString();
        $stats = $this->stats($request);
        if ($request->expectsJson()) {
            return response()->json(['data' => $trips, 'stats' => $stats]);
        }

        return view('driver.trips.index', compact('trips', 'stats'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $licence = $request->user()->driverLicence;

        if (! $licence) {
            return redirect()
                ->route('driver.profile.edit', ['section' => 'licence'])
                ->with('error', 'Upload your driving licence before creating a trip.');
        }

        if (! $licence->isValidOn(now())) {
            return redirect()
                ->route('driver.profile.edit', ['section' => 'licence'])
                ->with('error', 'Renew and verify your driving licence before creating a trip.');
        }

        $vehicles = $request->user()->vehicles()->orderByDesc('vehicle_id')->get();

        return view('driver.trips.create', compact('vehicles', 'licence'));
    }

    public function store(StoreTripRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $locations = $this->selectedLocations($request);
        } catch (TripRoutingException $exception) {
            return $this->routingError($request, $exception);
        }

        $routingData = $this->routingDataOrEmpty($locations);
        $tripData = $request->tripData();
        if (blank($request->input('price_per_passenger')) && isset($routingData['estimated_distance_km'])) {
            $vehicle = $request->user()->vehicles()->whereKey($request->integer('vehicle_id'))->firstOrFail();
            $tripData['price_per_passenger'] = $this->fareRecommendationService
                ->recommend((float) $routingData['estimated_distance_km'], $vehicle)['recommended_price'];
        }
        $trip = $request->user()->trips()->create([...$tripData, ...$locations['attributes'], ...$routingData]);
        TripCreated::dispatch($trip);

        return $this->response($request, $trip, 'Trip published successfully.', 201, 'driver.trips.index');
    }

    public function show(Request $request, Trip $trip): View|JsonResponse
    {
        $this->ensureOwnership($request, $trip);
        $trip->load([
            'vehicle',
            'bookings' => fn ($booking) => $booking
                ->where('booking_status', 'Accepted')
                ->with('passenger')
                ->oldest(),
        ]);
        $trip->loadSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats');

        $returnTo = $this->returnRoute($request);
        $hasInProgressTrip = $request->user()->trips()->where('status', 'In Progress')->whereKeyNot($trip->getKey())->exists();

        return $request->expectsJson() ? response()->json(['data' => $trip]) : view('driver.trips.show', compact('trip', 'returnTo', 'hasInProgressTrip'));
    }

    public function edit(Request $request, Trip $trip): View
    {
        $this->ensureEditable($request, $trip);
        $vehicles = $request->user()->vehicles()->orderByDesc('vehicle_id')->get();
        $returnTo = $this->returnRoute($request);
        $isExpiredRecovery = $trip->status === 'Scheduled' && $trip->hasExpiredDeparture();

        $licence = $request->user()->driverLicence;

        return view('driver.trips.edit', compact('trip', 'vehicles', 'returnTo', 'licence', 'isExpiredRecovery'));
    }

    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $data = $request->tripData();
        $locationsChanged = $request->filled('departure_place_id') || $request->filled('destination_place_id');
        if ($locationsChanged) {
            try {
                $locations = $this->selectedLocations($request, $trip);
            } catch (TripRoutingException $exception) {
                return $this->routingError($request, $exception);
            }

            $data = [...$data, ...$locations['attributes'], ...$this->routingDataOrEmpty($locations)];
        }

        $trip->update($data);

        $changedAttributes = array_keys($trip->getChanges());
        $tripChanged = (bool) array_intersect($changedAttributes, ['departure_location', 'destination', 'departure_at', 'available_seats', 'price_per_passenger', 'vehicle_id']);
        $departureTimeChanged = $trip->wasChanged('departure_at');
        if ($tripChanged) {
            $this->acceptedBookingsFor($trip->refresh())->each(function (Booking $booking) use ($departureTimeChanged, $changedAttributes): void {
                $this->notificationDelivery->send($booking->passenger, new TripUpdatedNotification($booking, $departureTimeChanged, $changedAttributes));
            });
        }

        return $this->response($request, $trip->refresh(), 'Trip updated successfully.', 200, $this->returnRoute($request));
    }

    public function start(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['Scheduled']);
        abort_if($trip->hasExpiredDeparture(), 422, 'This trip has expired and can no longer be started.');
        abort_unless(
            $request->user()->driverLicence?->isValidOn(now()) ?? false,
            422,
            'Your driving licence is missing, unverified, or expired. Update it in My Profile before starting the trip.'
        );
        try {
            $bookingsToNotify = DB::transaction(function () use ($request, $trip) {
                abort_if($request->user()->trips()->where('status', 'In Progress')->exists(), 422, 'Complete your current trip before starting another.');
                $lockedTrip = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();
                $bookings = $this->acceptedBookingsFor($lockedTrip, true);
                $this->assertRouteCoordinates($lockedTrip, $bookings);

                $route = $this->tripDistanceService->calculate(
                    $this->tripDeparture($lockedTrip),
                    $this->tripDestination($lockedTrip),
                    $bookings->map(fn (Booking $booking) => $this->bookingPickup($booking))->all(),
                    true,
                );
                $this->persistPickupSequence($bookings, $route['optimized_intermediate_waypoint_index'] ?? []);
                $startedAt = now();
                $lockedTrip->update([
                    'status' => 'In Progress',
                    'started_at' => $startedAt,
                    'estimated_distance_km' => $route['estimated_distance_km'],
                    'estimated_duration_seconds' => $route['estimated_duration_seconds'],
                    'estimated_arrival_at' => $startedAt->copy()->addSeconds($route['estimated_duration_seconds']),
                ]);

                return $bookings;
            });
        } catch (TripRoutingException $exception) {
            return $this->routingError($request, $exception);
        }
        $this->notifyPassengers($bookingsToNotify, 'Started');
        $this->broadcastBookingUpdates($bookingsToNotify, null, 'trip_started');

        return $this->response($request, $trip->refresh(), 'Journey started successfully.', 200, 'driver.trips.journey');
    }

    public function complete(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['In Progress']);
        $bookingsToNotify = DB::transaction(function () use ($trip) {
            $trip->update(['status' => 'Completed', 'completed_at' => now()]);

            $bookings = $this->acceptedBookingsFor($trip);
            $bookings->each(fn (Booking $booking) => $this->paymentService->createPendingForBooking($booking));

            return $bookings;
        });
        $this->notifyPassengers($bookingsToNotify, 'Completed');
        $this->broadcastBookingUpdates($bookingsToNotify, null, 'trip_completed');
        foreach ($bookingsToNotify as $booking) {
            $booking->loadMissing(['payment', 'passenger', 'trip.user']);
            if ($booking->payment) {
                $this->notificationDelivery->send($booking->passenger, new PaymentDueNotification($booking->payment));
            }

            $driver = $booking->trip->user;
            if (! $booking->ratings()->where('reviewer_id', $driver->id)->exists()) {
                $this->notificationDelivery->send($driver, new RatingReminderNotification($booking));
            }
        }

        if (! $request->expectsJson()) {
            $bookingToRate = $trip->bookings()
                ->where('booking_status', 'Accepted')
                ->whereDoesntHave('ratings', fn ($rating) => $rating->where('reviewer_id', $request->user()->id))
                ->oldest()
                ->first();

            if ($bookingToRate) {
                return redirect()->route('ratings.create', $bookingToRate)
                    ->with('success', 'Journey completed. Please rate your passenger.');
            }
        }

        return $this->response($request, $trip->refresh(), 'Journey completed successfully.', 200, 'driver.trips.journey');
    }

    public function pickup(Request $request, Trip $trip, Booking $booking): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($request, $trip);

        try {
            $booking = DB::transaction(function () use ($trip, $booking) {
                $lockedTrip = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();
                $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

                abort_unless($lockedBooking->trip_id === $lockedTrip->getKey(), 404);
                abort_unless($lockedTrip->status === 'In Progress', 422, 'Passengers can only be picked up during an active journey.');
                abort_unless($lockedBooking->booking_status === 'Accepted', 422, 'Only accepted bookings can be marked as picked up.');
                abort_if($lockedBooking->picked_up_at !== null, 422, 'This passenger has already been picked up.');

                $lockedBooking->update(['picked_up_at' => now()]);
                $remainingBookings = $this->acceptedBookingsFor($lockedTrip, true)
                    ->whereNull('picked_up_at')
                    ->values();
                $this->assertRouteCoordinates($lockedTrip, $remainingBookings);
                $route = $this->tripDistanceService->calculate(
                    $this->bookingPickup($lockedBooking),
                    $this->tripDestination($lockedTrip),
                    $remainingBookings->map(fn (Booking $remaining) => $this->bookingPickup($remaining))->all(),
                );
                $calculatedAt = now();
                $lockedTrip->update([
                    'estimated_distance_km' => $route['estimated_distance_km'],
                    'estimated_duration_seconds' => $route['estimated_duration_seconds'],
                    'estimated_arrival_at' => $calculatedAt->copy()->addSeconds($route['estimated_duration_seconds']),
                ]);

                return $lockedBooking;
            });
        } catch (TripRoutingException $exception) {
            return $this->routingError($request, $exception);
        }

        BookingStatusUpdated::dispatch($booking, pickedUpBookingId: $booking->id);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Passenger marked as picked up.', 'data' => $booking]);
        }

        return redirect()->route('driver.trips.journey')->with('success', 'Passenger marked as picked up.');
    }

    public function cancel(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['Scheduled']);
        $cancellation = DB::transaction(function () use ($trip) {
            $trip->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

            return $this->cancelOpenBookingsFor($trip);
        });
        $this->notifyPassengers($cancellation['accepted'], 'Cancelled');
        $acceptedIds = $cancellation['accepted']->modelKeys();
        $this->broadcastBookingUpdates($cancellation['all'], null, 'trip_cancelled', $acceptedIds);

        return $this->response($request, $trip->refresh(), 'Trip cancelled successfully.', 200, 'driver.trips.index');
    }

    public function destroy(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($request, $trip);
        abort_unless(in_array($trip->status, ['Completed', 'Cancelled'], true), 422);
        $trip->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Trip removed successfully.']);
        }

        return redirect()->route('driver.trips.history')->with('success', 'Trip removed successfully.');
    }

    public function journey(Request $request): View|JsonResponse
    {
        $trips = $this->driverTrips($request)
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->with([
                'bookings' => fn ($booking) => $booking
                    ->where('booking_status', 'Accepted')
                    ->with('passenger')
                    ->orderByRaw('case when pickup_sequence is null then 1 else 0 end')
                    ->orderBy('pickup_sequence')
                    ->oldest(),
                'emergencies' => fn ($emergencies) => $emergencies->with('user')->latest('triggered_at'),
                'latestLocation',
            ])
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->orderBy('departure_at')
            ->get();

        $mapRoutes = $trips
            ->where('status', 'In Progress')
            ->mapWithKeys(fn (Trip $trip) => [$trip->trip_id => $this->mapRoute($trip)])
            ->all();

        return $request->expectsJson() ? response()->json(['data' => $trips]) : view('driver.trips.journey', compact('trips', 'mapRoutes'));
    }

    public function history(Request $request): View|JsonResponse
    {
        $query = $this->driverTrips($request)->whereIn('status', ['Completed', 'Cancelled']);
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($q) => $q->where('departure_location', 'like', "%{$search}%")
                ->orWhere('destination', 'like', "%{$search}%")
                ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")));
        }
        if (in_array($request->input('status'), ['Completed', 'Cancelled'], true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date')) {
            $query->whereDate('departure_at', $request->input('date'));
        }
        $trips = $query
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->latest('departure_at')
            ->paginate(8)
            ->withQueryString();

        return $request->expectsJson() ? response()->json(['data' => $trips]) : view('driver.trips.history', compact('trips'));
    }

    private function driverTrips(Request $request)
    {
        return $request->user()->trips()->with('vehicle');
    }

    private function stats(Request $request): array
    {
        $counts = $request->user()->trips()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(['Scheduled', 'In Progress', 'Completed', 'Cancelled'])->mapWithKeys(fn ($status) => [$status => $counts[$status] ?? 0])->all();
    }

    private function ensureOwnership(Request $request, Trip $trip): void
    {
        abort_unless($trip->user_id === $request->user()->id, 403);
    }

    private function ensureEditable(Request $request, Trip $trip): void
    {
        $this->ensureStatus($request, $trip, ['Scheduled']);
    }

    private function ensureStatus(Request $request, Trip $trip, array $statuses): void
    {
        $this->ensureOwnership($request, $trip);
        abort_unless(in_array($trip->status, $statuses, true), 422);
    }

    private function response(Request $request, Trip $trip, string $message, int $status, string $route): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'data' => $trip], $status)
            : redirect()->route($route, $route === 'driver.trips.show' ? $trip : [])->with('success', $message);
    }

    private function returnRoute(Request $request): string
    {
        return in_array($request->query('return_to'), ['driver.trips.index', 'driver.trips.journey', 'driver.trips.history'], true)
            ? $request->query('return_to')
            : 'driver.trips.index';
    }

    private function routingError(Request $request, TripRoutingException $exception): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => [$exception->errorField => [$exception->getMessage()]]], 422);
        }

        return back()->withInput()->withErrors([$exception->errorField => $exception->getMessage()]);
    }

    private function routingDataOrEmpty(array $locations): array
    {
        try {
            return $this->tripDistanceService->calculate($locations['departure'], $locations['destination']);
        } catch (TripRoutingException $exception) {
            Log::warning('Trip distance calculation failed.', [
                'message' => $exception->getMessage(),
                'departure' => $locations['departure']['display'] ?? null,
                'destination' => $locations['destination']['display'] ?? null,
            ]);

            return [
                'estimated_distance_km' => null,
                'estimated_duration_seconds' => null,
            ];
        }
    }

    private function acceptedBookingsFor(Trip $trip, bool $lock = false)
    {
        return Booking::query()
            ->with('trip')
            ->where('trip_id', $trip->getKey())
            ->where('booking_status', 'Accepted')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderByRaw('case when pickup_sequence is null then 1 else 0 end')
            ->orderBy('pickup_sequence')
            ->oldest()
            ->get();
    }

    private function cancelOpenBookingsFor(Trip $trip)
    {
        $bookings = Booking::query()
            ->with(['passenger', 'trip.user'])
            ->where('trip_id', $trip->getKey())
            ->whereIn('booking_status', ['Pending', 'Accepted'])
            ->lockForUpdate()
            ->oldest()
            ->get();
        $accepted = $bookings->where('booking_status', 'Accepted')->values();
        $bookings->each->update(['booking_status' => 'Cancelled']);

        return ['all' => $bookings, 'accepted' => $accepted];
    }

    private function assertRouteCoordinates(Trip $trip, $bookings): void
    {
        foreach ([$this->tripDeparture($trip), $this->tripDestination($trip)] as $location) {
            if (! $this->hasCoordinates($location)) {
                throw new TripRoutingException('destination', 'Trip locations must have valid coordinates before routing.');
            }
        }
        foreach ($bookings as $booking) {
            if (! $this->hasCoordinates($this->bookingPickup($booking))) {
                throw new TripRoutingException('booking', 'Every accepted booking must have valid pickup coordinates before starting the journey.');
            }
        }
    }

    private function tripDeparture(Trip $trip): array
    {
        return ['latitude' => $trip->departure_latitude, 'longitude' => $trip->departure_longitude];
    }

    private function tripDestination(Trip $trip): array
    {
        return ['latitude' => $trip->destination_latitude, 'longitude' => $trip->destination_longitude];
    }

    private function bookingPickup(Booking $booking): array
    {
        return ['latitude' => $booking->pickup_latitude, 'longitude' => $booking->pickup_longitude];
    }

    private function hasCoordinates(array $location): bool
    {
        return is_numeric($location['latitude'] ?? null) && is_numeric($location['longitude'] ?? null)
            && (float) $location['latitude'] >= -90 && (float) $location['latitude'] <= 90
            && (float) $location['longitude'] >= -180 && (float) $location['longitude'] <= 180;
    }

    private function mapRoute(Trip $trip): ?array
    {
        if (blank(config('services.google_maps.browser_key'))) {
            return null;
        }

        $bookings = $trip->bookings;
        $locations = [
            $this->tripDeparture($trip),
            $this->tripDestination($trip),
            ...$bookings->map(fn (Booking $booking) => $this->bookingPickup($booking))->all(),
        ];

        if (collect($locations)->contains(fn (array $location) => ! $this->hasCoordinates($location))) {
            return null;
        }

        try {
            return $this->tripDistanceService->calculate(
                $this->tripDeparture($trip),
                $this->tripDestination($trip),
                $bookings->map(fn (Booking $booking) => $this->bookingPickup($booking))->all(),
            );
        } catch (TripRoutingException) {
            return null;
        }
    }

    private function persistPickupSequence($bookings, array $optimizedIndexes): void
    {
        foreach ($optimizedIndexes as $sequence => $index) {
            $bookings->get($index)?->update(['pickup_sequence' => $sequence + 1]);
        }
    }

    private function notifyPassengers($bookings, string $status): void
    {
        foreach ($bookings as $booking) {
            try {
                $booking->passenger->notify(new BookingStatusNotification($booking, $status));
            } catch (Throwable $exception) {
                Log::warning('Trip lifecycle notification could not be created.', [
                    'booking_id' => $booking->id,
                    'trip_id' => $booking->trip_id,
                    'status' => $status,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function broadcastBookingUpdates($bookings, ?string $driverNotificationType = null, ?string $passengerNotificationType = null, array $passengerNotificationBookingIds = []): void
    {
        foreach ($bookings as $booking) {
            $booking->unsetRelation('trip')->load('trip');
            $shouldNotifyPassenger = $passengerNotificationBookingIds === [] || in_array($booking->getKey(), $passengerNotificationBookingIds, true);
            try {
                BookingStatusUpdated::dispatch($booking, $driverNotificationType, $shouldNotifyPassenger ? $passengerNotificationType : null);
            } catch (Throwable $exception) {
                Log::warning('Trip lifecycle update could not be broadcast.', [
                    'booking_id' => $booking->id,
                    'trip_id' => $booking->trip_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function selectedLocations(Request $request, ?Trip $trip = null): array
    {
        $departure = $request->filled('departure_place_id')
            ? $this->tripLocationService->resolve($request->string('departure_place_id')->toString(), 'departure_place_id')
            : $this->storedLocation($trip, 'departure');
        $destination = $request->filled('destination_place_id')
            ? $this->tripLocationService->resolve($request->string('destination_place_id')->toString(), 'destination_place_id')
            : $this->storedLocation($trip, 'destination');

        if ($departure['latitude'] === $destination['latitude'] && $departure['longitude'] === $destination['longitude']) {
            throw new TripRoutingException('destination_place_id', 'Departure location and destination cannot be the same.');
        }

        return [
            'departure' => $departure,
            'destination' => $destination,
            'attributes' => [
                'departure_location' => $departure['display'],
                'departure_latitude' => $departure['latitude'],
                'departure_longitude' => $departure['longitude'],
                'destination' => $destination['display'],
                'destination_latitude' => $destination['latitude'],
                'destination_longitude' => $destination['longitude'],
            ],
        ];
    }

    private function storedLocation(?Trip $trip, string $prefix): array
    {
        if (! $trip || $trip->{"{$prefix}_latitude"} === null || $trip->{"{$prefix}_longitude"} === null) {
            throw new TripRoutingException("{$prefix}_place_id", 'Please select a location from the suggestions.');
        }

        return [
            'display' => $prefix === 'departure' ? $trip->departure_location : $trip->destination,
            'latitude' => (float) $trip->{"{$prefix}_latitude"},
            'longitude' => (float) $trip->{"{$prefix}_longitude"},
        ];
    }
}
