<?php

namespace App\Http\Controllers\Driver;

use App\Events\TripLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TripLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripLocationController extends Controller
{
    public function __construct(private readonly TripLocationService $tripLocationService) {}

    public function store(Request $request, Trip $trip): JsonResponse
    {
        abort_unless($trip->user_id === $request->user()->id, 403);
        abort_unless($trip->status === 'In Progress', 422, 'Live location is available only during an active trip.');

        $location = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'between:0,150'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed_mps' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $record = $this->tripLocationService->record($trip->loadMissing('latestLocation'), $request->user()->id, $location);

        if ($record) {
            TripLocationUpdated::dispatch($record);
        }

        return response()->json(['stored' => $record !== null], $record ? 201 : 200);
    }
}
