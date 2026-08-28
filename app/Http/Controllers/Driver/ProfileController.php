<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverPreferenceRequest;
use App\Http\Requests\Driver\UpdateDriverProfileRequest;
use App\Http\Requests\Driver\UpdateDriverLicenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $driver = $request->user()
            ->loadAvg('ratingsReceived', 'score')
            ->loadCount('ratingsReceived')
            ->load('emergencyContacts');

        return view('driver.profile', [
            'user' => $driver,
            'canManagePassword' => ! $driver->usesGoogleAuthentication()
                && $request->session()->get('auth_provider') !== 'google',
            'preference' => $driver->driverPreference()->firstOrCreate(),
            'vehicles' => $driver->vehicles()->orderByDesc('vehicle_id')->get(),
            'recentLogins' => $driver->loginHistories()
                ->latest('login_at')
                ->latest('id')
                ->limit(5)
                ->get(),
            'profileCompleteness' => $driver->profileCompleteness(),
            'driverLicence' => $driver->driverLicence,
        ]);
    }

    public function update(UpdateDriverProfileRequest $request): RedirectResponse
    {
        $driver = $request->user();
        $driver->name = $request->validated('name');

        if ($request->hasFile('photo')) {
            if ($driver->photo) {
                Storage::disk('public')->delete($driver->photo);
            }

            $driver->photo = $request->file('photo')->store('profile-photos', 'public');
        }

        $driver->save();

        return Redirect::route('driver.profile.edit')->with('status', 'profile-updated');
    }

    public function updatePreferences(UpdateDriverPreferenceRequest $request): RedirectResponse
    {
        $request->user()->driverPreference()->updateOrCreate(
            [],
            $request->validated()
        );

        return Redirect::route('driver.profile.edit')->with('status', 'driver-preferences-updated');
    }

    public function updateLicence(UpdateDriverLicenceRequest $request): RedirectResponse
    {
        $driver = $request->user();
        $oldPath = $driver->driverLicence?->image_path;
        $newPath = $request->file('driving_licence')->store('driver-licences', 'local');

        try {
            $driver->driverLicence()->updateOrCreate([], [
                ...$request->safe()->except('driving_licence'),
                'image_path' => $newPath,
                'verification_status' => 'Verified',
                'verified_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $request->session()->forget('driver_licence_ocr');

        return Redirect::route('driver.profile.edit', ['section' => 'licence'])
            ->with('status', 'driver-licence-updated');
    }

    public function licenceImage(Request $request)
    {
        $path = $request->user()->driverLicence()->value('image_path');

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
