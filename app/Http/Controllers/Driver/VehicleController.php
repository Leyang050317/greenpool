<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreVehicleRequest;
use App\Http\Requests\Driver\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $vehicles = $request->user()
            ->vehicles()
            ->orderByDesc('vehicle_id')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $vehicles]);
        }

        return view('driver.vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        return view('driver.vehicles.create');
    }

    public function store(StoreVehicleRequest $request): RedirectResponse|JsonResponse
    {
        $vehicle = $request->user()->vehicles()->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Vehicle added successfully.',
                'data' => $vehicle,
            ], 201);
        }

        return redirect()
            ->route('driver.vehicles.show', $vehicle)
            ->with('success', 'Vehicle added successfully.');
    }

    public function show(Request $request, Vehicle $vehicle): View|JsonResponse
    {
        $this->ensureOwnership($request, $vehicle);

        if ($request->expectsJson()) {
            return response()->json(['data' => $vehicle]);
        }

        return view('driver.vehicles.show', compact('vehicle'));
    }

    public function edit(Request $request, Vehicle $vehicle): View
    {
        $this->ensureOwnership($request, $vehicle);

        return view('driver.vehicles.edit', compact('vehicle'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $vehicle->update($request->validated());
        $vehicle->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Vehicle updated successfully.',
                'data' => $vehicle,
            ]);
        }

        return redirect()
            ->route('driver.vehicles.show', $vehicle)
            ->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($request, $vehicle);
        $vehicle->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Vehicle deleted successfully.']);
        }

        return redirect()
            ->route('driver.vehicles.index')
            ->with('success', 'Vehicle deleted successfully.');
    }

    public function activate(Request $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($request, $vehicle);
        $vehicle = DB::transaction(function () use ($vehicle) {
            $this->deactivateOtherVehicles($vehicle);
            $vehicle->update(['status' => 'Active']);

            return $vehicle->refresh();
        });

        return $this->actionResponse($request, $vehicle, 'Vehicle activated successfully.');
    }

    public function deactivate(Request $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($request, $vehicle);
        $vehicle->update([
            'status' => 'Inactive',
        ]);
        $vehicle->refresh();

        return $this->actionResponse($request, $vehicle, 'Vehicle deactivated successfully.');
    }

    private function ensureOwnership(Request $request, Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === $request->user()->id, 403);
    }

    private function deactivateOtherVehicles(Vehicle $vehicle): void
    {
        Vehicle::query()
            ->where('user_id', $vehicle->user_id)
            ->whereKeyNot($vehicle->getKey())
            ->update([
                'status' => 'Inactive',
            ]);
    }

    private function actionResponse(Request $request, Vehicle $vehicle, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $vehicle,
            ]);
        }

        return back()->with('success', $message);
    }
}
