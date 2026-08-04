<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreTripRequest;
use App\Http\Requests\Driver\UpdateTripRequest;
use App\Models\Trip;
use App\Services\Routing\TripDistanceService;
use App\Services\Routing\TripRoutingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TripController extends Controller
{
    public function __construct(private readonly TripDistanceService $tripDistanceService) {}

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
        $trips = $query->paginate(4)->withQueryString();
        $stats = $this->stats($request);
        if ($request->expectsJson()) {
            return response()->json(['data' => $trips, 'stats' => $stats]);
        }

        return view('driver.trips.index', compact('trips', 'stats'));
    }

    public function create(Request $request): View
    {
        $vehicles = $request->user()->vehicles()->where('status', 'Active')->orderByDesc('vehicle_id')->get();

        return view('driver.trips.create', compact('vehicles'));
    }

    public function store(StoreTripRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $routingData = $this->tripDistanceService->calculate($request->string('departure_location')->trim()->toString(), $request->string('destination')->trim()->toString());
        } catch (TripRoutingException $exception) {
            return $this->routingError($request, $exception);
        }

        $trip = $request->user()->trips()->create([...$request->tripData(), ...$routingData]);

        return $this->response($request, $trip, 'Trip published successfully.', 201, 'driver.trips.index');
    }

    public function show(Request $request, Trip $trip): View|JsonResponse
    {
        $this->ensureOwnership($request, $trip);
        $trip->load('vehicle');

        $returnTo = $this->returnRoute($request);
        $hasInProgressTrip = $request->user()->trips()->where('status', 'In Progress')->whereKeyNot($trip->getKey())->exists();

        return $request->expectsJson() ? response()->json(['data' => $trip]) : view('driver.trips.show', compact('trip', 'returnTo', 'hasInProgressTrip'));
    }

    public function edit(Request $request, Trip $trip): View
    {
        $this->ensureEditable($request, $trip);
        $vehicles = $request->user()->vehicles()->where('status', 'Active')->orderByDesc('vehicle_id')->get();
        $returnTo = $this->returnRoute($request);

        return view('driver.trips.edit', compact('trip', 'vehicles', 'returnTo'));
    }

    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse|JsonResponse
    {
        $data = $request->tripData();
        $locationsChanged = $data['departure_location'] !== $trip->departure_location || $data['destination'] !== $trip->destination;
        if ($locationsChanged) {
            try {
                $data = [...$data, ...$this->tripDistanceService->calculate($data['departure_location'], $data['destination'])];
            } catch (TripRoutingException $exception) {
                return $this->routingError($request, $exception);
            }
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
        $trips = $this->driverTrips($request)->whereIn('status', ['Scheduled', 'In Progress'])->orderBy('departure_at')->get();

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
}
