<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreVehicleRequest;
use App\Http\Requests\Driver\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\OcrException;
use App\Services\Vehicle\PlateNumberExtractionService;
use App\Services\Vehicle\VehicleImageValidationService;
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
    public function __construct(
        private readonly DocumentOcrService $documentOcr,
        private readonly VehicleImageValidationService $vehicleImageValidator,
        private readonly PlateNumberExtractionService $plateNumberExtractor,
    ) {}

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

    public function create(Request $request): View|RedirectResponse
    {
        if (! ($request->user()->driverLicence?->isValidOn(now()) ?? false)) {
            return redirect()
                ->route('driver.profile.edit', ['section' => 'licence'])
                ->with('error', 'Upload and verify a valid driving licence before adding a vehicle.');
        }

        return view('driver.vehicles.create');
    }

    public function archived(Request $request): View|JsonResponse
    {
        $vehicles = Vehicle::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('deleted_at')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $vehicles]);
        }

        return view('driver.vehicles.archived', compact('vehicles'));
    }

    public function store(StoreVehicleRequest $request): RedirectResponse|JsonResponse
    {
        if ($request->hasFile('vehicle_image')) {
            return $this->storeLegacyVehicle($request);
        }

        $publicFiles = collect([
            'front_image_path' => 'front_image',
            'rear_image_path' => 'rear_image',
            'side_image_path' => 'side_image',
        ])->mapWithKeys(fn (string $input, string $column) => [
            $column => $request->file($input)->store('vehicles', 'public'),
        ])->all();
        $privateFiles = collect([
            'vehicle_geran_path' => 'vehicle_geran',
        ])->mapWithKeys(fn (string $input, string $column) => [
            $column => $request->file($input)->store('vehicle-documents', 'local'),
        ])->all();
        $files = [...$publicFiles, ...$privateFiles];
        try {
            $vehicle = $request->user()->vehicles()->create([
                ...$request->safe()->except(['front_image', 'rear_image', 'side_image', 'vehicle_geran', 'front_image_validation_token', 'rear_image_validation_token', 'side_image_validation_token']),
                ...$files,
                'vehicle_image_path' => $files['front_image_path'],
                'verification_status' => 'Verified',
                'verified_at' => now(),
            ]);
        } catch (Throwable $exception) {
            foreach ($publicFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach ($privateFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $successMessage = 'Vehicle added successfully. Geran details match your driver profile.';

        if ($request->expectsJson()) {
            $request->session()->flash('success', $successMessage);

            return response()->json([
                'message' => $successMessage,
                'data' => $vehicle,
                // Keep the browser on the same host/port that submitted the form.
                // This matters in local development where APP_URL may be localhost
                // while the site is opened through 127.0.0.1:8000.
                'redirect_url' => route('driver.vehicles.index', ['created' => 1], false),
            ], 201);
        }

        return redirect()
            ->route('driver.vehicles.index')
            ->with('success', $successMessage);
    }

    private function storeLegacyVehicle(StoreVehicleRequest $request): RedirectResponse|JsonResponse
    {
        $path = $this->storeVehicleImage($request->file('vehicle_image'));
        try {
            $vehicle = $request->user()->vehicles()->create([
                ...$request->safe()->only(['plate_number', 'brand', 'model', 'colour', 'seat_capacity']),
                'vehicle_image_path' => $path,
                'status' => 'Inactive',
                'verification_status' => 'Pending',
                'verified_at' => null,
            ]);
        } catch (Throwable $exception) {
            $this->deleteVehicleImage($path);
            throw $exception;
        }

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Vehicle added successfully.');

            return response()->json([
                'message' => 'Vehicle added successfully.',
                'data' => $vehicle,
                'redirect_url' => route('driver.vehicles.index', ['created' => 1], false),
            ], 201);
        }

        return redirect()->route('driver.vehicles.index')->with('success', 'Vehicle added successfully.');
    }

    public function ocr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=500,min_height=300'],
            'expected_document_type' => ['required', 'in:DRIVING_LICENCE,VEHICLE_GERAN'],
        ], [
            'document.image' => 'Unable to read the uploaded document. Please upload a clear JPG or PNG image.',
            'document.mimes' => 'Unable to read the uploaded document. Please upload a clear JPG or PNG image.',
        ]);

        try {
            $result = $this->documentOcr->process($validated['document'], $validated['expected_document_type']);

            if ($validated['expected_document_type'] === 'DRIVING_LICENCE') {
                $request->session()->put('driver_licence_ocr', [
                    'hash' => hash_file('sha256', $validated['document']->getRealPath()),
                    'fields' => collect($result['fields'] ?? [])->mapWithKeys(fn (array $field, string $name) => [$name => $field['value'] ?? null])->all(),
                ]);
            } elseif ($validated['expected_document_type'] === 'VEHICLE_GERAN') {
                $request->session()->put('vehicle_geran_ocr', [
                    'hash' => hash_file('sha256', $validated['document']->getRealPath()),
                    'fields' => collect($result['fields'] ?? [])->mapWithKeys(fn (array $field, string $name) => [$name => $field['value'] ?? null])->all(),
                ]);
            }

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (OcrException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function validateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=800,min_height=450'],
            'expected_view' => ['required', 'in:FRONT,REAR,SIDE'],
        ], [
            'image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels. Choose a higher-resolution image.',
            'image.mimes' => 'Vehicle photos must be JPG or PNG images.',
            'image.max' => 'Vehicle photos must not exceed 8 MB.',
        ]);

        try {
            $imageResult = $this->vehicleImageValidator->validate($validated['image'], $validated['expected_view']);
            $plateNumber = null;
            if ($imageResult['accepted'] ?? false) {
                try {
                    $plateNumber = $this->plateNumberExtractor->extract($validated['image']);
                } catch (OcrException) {
                    // Photo validation still succeeds when the plate is not readable.
                }
            }

            if (filled($imageResult['token'] ?? null)) {
                $imageResult['token'] = $this->vehicleImageValidator->attachPlateNumber($imageResult['token'], $plateNumber);
            }

            return response()->json([
                'success' => true,
                ...$imageResult,
                'plate_number' => $plateNumber,
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function plateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plate_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/'],
        ]);
        $plateNumber = mb_strtoupper(trim($validated['plate_number']));
        $canonicalPlate = preg_replace('/[^A-Z0-9]/', '', $plateNumber);
        $exists = Vehicle::query()
            ->withTrashed()
            ->whereRaw("REPLACE(REPLACE(UPPER(plate_number), ' ', ''), '-', '') = ?", [$canonicalPlate])
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'plate_number' => $plateNumber,
            'message' => $exists
                ? "Plate number {$plateNumber} is already registered. Please use a different vehicle or check My Vehicles."
                : 'Plate number is available.',
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): View|JsonResponse
    {
        $this->ensureOwnership($request, $vehicle);

        $hasBlockingTrips = $vehicle->trips()
            ->whereIn('status', ['Scheduled', 'In Progress'])
            ->exists();

        if ($request->expectsJson()) {
            return response()->json(['data' => $vehicle]);
        }

        return view('driver.vehicles.show', compact('vehicle', 'hasBlockingTrips'));
    }

    public function edit(Request $request, Vehicle $vehicle): View
    {
        $this->ensureOwnership($request, $vehicle);

        return view('driver.vehicles.edit', compact('vehicle'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse|JsonResponse
    {
        $data = $request->safe()->except([
            'front_image', 'rear_image', 'side_image', 'vehicle_geran',
            'front_image_validation_token', 'rear_image_validation_token', 'side_image_validation_token',
        ]);
        $data['brand'] = $vehicle->brand;
        $identityChanged = collect(['plate_number', 'brand', 'model', 'colour'])
            ->contains(fn (string $field) => $data[$field] !== $vehicle->{$field});

        if ($identityChanged) {
            $photosChanged = $request->hasFile('front_image');
            $geranChanged = $request->hasFile('vehicle_geran');
            $newPublicFiles = $photosChanged
                ? collect([
                    'front_image_path' => 'front_image',
                    'rear_image_path' => 'rear_image',
                    'side_image_path' => 'side_image',
                ])->mapWithKeys(fn (string $input, string $column) => [
                    $column => $request->file($input)->store('vehicles', 'public'),
                ])->all()
                : [];
            $newPrivateFiles = $geranChanged
                ? ['vehicle_geran_path' => $request->file('vehicle_geran')->store('vehicle-documents', 'local')]
                : [];
            $oldPublicFiles = $photosChanged
                ? array_filter([$vehicle->front_image_path, $vehicle->rear_image_path, $vehicle->side_image_path, $vehicle->vehicle_image_path])
                : [];
            $oldPrivateFiles = $geranChanged ? array_filter([$vehicle->vehicle_geran_path]) : [];

            try {
                DB::transaction(function () use ($vehicle, $data, $newPublicFiles, $newPrivateFiles, $photosChanged): void {
                    $vehicle->update([
                        ...$data,
                        ...$newPublicFiles,
                        ...$newPrivateFiles,
                        ...($photosChanged ? ['vehicle_image_path' => $newPublicFiles['front_image_path']] : []),
                        'verification_status' => 'Verified',
                        'verified_at' => now(),
                    ]);
                });
            } catch (Throwable $exception) {
                foreach ($newPublicFiles as $path) {
                    Storage::disk('public')->delete($path);
                }
                foreach ($newPrivateFiles as $path) {
                    Storage::disk('local')->delete($path);
                }
                throw $exception;
            }

            foreach (array_unique($oldPublicFiles) as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach (array_unique($oldPrivateFiles) as $path) {
                Storage::disk('local')->delete($path);
            }

            $vehicle->refresh();
        } else {
            $vehicle->update(['seat_capacity' => $data['seat_capacity']]);
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
        if ($vehicle->trips()->whereIn('status', ['Scheduled', 'In Progress'])->exists()) {
            $message = 'This vehicle cannot be deleted while it is assigned to an active or upcoming trip. Cancel or complete the trip first.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        DB::transaction(function () use ($vehicle): void {
            $vehicle->update(['status' => 'Inactive']);
            $vehicle->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Vehicle archived successfully.']);
        }

        return redirect()
            ->route('driver.vehicles.index')
            ->with('success', 'Vehicle archived successfully.');
    }

    public function restore(Request $request, int $vehicle): RedirectResponse|JsonResponse
    {
        $archivedVehicle = Vehicle::onlyTrashed()->findOrFail($vehicle);
        $this->ensureOwnership($request, $archivedVehicle);

        DB::transaction(function () use ($archivedVehicle): void {
            $updates = ['status' => 'Inactive'];

            $archivedVehicle->restore();
            $archivedVehicle->update($updates);
        });
        $archivedVehicle->refresh();

        $message = $archivedVehicle->verification_status === 'Pending'
            ? 'Vehicle restored as Inactive. Its Geran details must be verified before it can be used for trips.'
            : 'Vehicle restored successfully as Inactive.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $archivedVehicle,
            ]);
        }

        return redirect()
            ->route('driver.vehicles.index')
            ->with('success', $message);
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
