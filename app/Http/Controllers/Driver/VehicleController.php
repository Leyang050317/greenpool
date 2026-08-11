<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreVehicleRequest;
use App\Http\Requests\Driver\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

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
        $imagePath = $this->storeVehicleImage($request->file('vehicle_image'));

        try {
            $vehicle = $request->user()->vehicles()->create([
                ...$request->safe()->except('vehicle_image'),
                'vehicle_image_path' => $imagePath,
                'verification_status' => 'Pending',
                'verified_at' => null,
            ]);
        } catch (Throwable $exception) {
            $this->deleteVehicleImage($imagePath);

            throw $exception;
        }

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
        $data = $request->safe()->except('vehicle_image');
        $identityChanged = collect(['plate_number', 'brand', 'model', 'colour'])
            ->contains(fn (string $field) => $data[$field] !== $vehicle->{$field});

        if ($request->hasFile('vehicle_image')) {
            $vehicle = $this->replaceVehicleImage($vehicle, $request->file('vehicle_image'), $data);
        } else {
            if ($identityChanged) {
                $data['verification_status'] = 'Pending';
                $data['verified_at'] = null;
            }

            $vehicle->update($data);
            $vehicle->refresh();
        }

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
        abort_if(
            $vehicle->trips()->whereIn('status', ['Scheduled', 'In Progress'])->exists(),
            422,
            'This vehicle cannot be deleted while it is assigned to an active or upcoming trip.'
        );

        $imagePath = $vehicle->vehicle_image_path;
        DB::transaction(fn () => $vehicle->delete());
        $this->deleteVehicleImage($imagePath);

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

    private function storeVehicleImage(UploadedFile $image): string
    {
        return $image->store('vehicles', 'public');
    }

    private function replaceVehicleImage(Vehicle $vehicle, UploadedFile $image, array $data): Vehicle
    {
        $newImagePath = $this->storeVehicleImage($image);
        $oldImagePath = $vehicle->vehicle_image_path;

        try {
            DB::transaction(function () use ($vehicle, $data, $newImagePath): void {
                $vehicle->update([
                    ...$data,
                    'vehicle_image_path' => $newImagePath,
                    'verification_status' => 'Pending',
                    'verified_at' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteVehicleImage($newImagePath);

            throw $exception;
        }

        $this->deleteVehicleImage($oldImagePath);

        return $vehicle->refresh();
    }

    private function deleteVehicleImage(?string $imagePath): void
    {
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
