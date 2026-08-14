<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreTripRequest;
use App\Http\Requests\Driver\UpdateTripRequest;
use App\Models\Trip;
use App\Services\Routing\TripDistanceService;
use App\Services\Routing\TripLocationService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TripController extends Controller
{
    public function __construct(private readonly TripDistanceService $tripDistanceService, private readonly TripLocationService $tripLocationService) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate(['input' => ['required', 'string', 'min:2', 'max:255']]);

        try {
            return response()->json(['data' => $this->tripLocationService->autocomplete($request->string('input')->trim()->toString())]);
        } catch (TripRoutingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => [$exception->errorField => [$exception->getMessage()]]], 422);
        }
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

    public function create(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->selectableForTrips()->orderByDesc('vehicle_id')->get();

        return view('driver.trips.create', compact('vehicles'));
    }

    public function store(StoreTripRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $locations = $this->selectedLocations($request);
        } catch (TripRoutingException $exception) {
            return $this->routingError($request, $exception);
        }

        $routingData = $this->routingDataOrEmpty($locations);
        $trip = $request->user()->trips()->create([...$request->tripData(), ...$locations['attributes'], ...$routingData]);

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
        $vehicles = $request->user()->vehicles()->selectableForTrips()->orderByDesc('vehicle_id')->get();
        $returnTo = $this->returnRoute($request);

        return view('driver.trips.edit', compact('trip', 'vehicles', 'returnTo'));
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

        return $this->response($request, $trip->refresh(), 'Trip updated successfully.', 200, $this->returnRoute($request));
    }

    public function start(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['Scheduled']);
        DB::transaction(function () use ($request, $trip) {
            abort_if($request->user()->trips()->where('status', 'In Progress')->exists(), 422, 'Complete your current trip before starting another.');
            $trip->update(['status' => 'In Progress', 'started_at' => now()]);
        });

        return $this->response($request, $trip->refresh(), 'Journey started successfully.', 200, 'driver.trips.journey');
    }

    public function complete(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['In Progress']);
        $trip->update(['status' => 'Completed', 'completed_at' => now()]);

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

    public function cancel(Request $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $this->ensureStatus($request, $trip, ['Scheduled']);
        $trip->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

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
            ->withSum(['bookings as accepted_passengers_count' => fn ($booking) => $booking->where('booking_status', 'Accepted')], 'number_of_seats')
            ->orderBy('departure_at')
            ->get();

        return $request->expectsJson() ? response()->json(['data' => $trips]) : view('driver.trips.journey', compact('trips'));
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
        $trips = $query->latest('departure_at')->paginate(8)->withQueryString();

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
