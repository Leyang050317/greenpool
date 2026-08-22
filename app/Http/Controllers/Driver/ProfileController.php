<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\UpdateDriverPreferenceRequest;
use App\Http\Requests\Driver\UpdateDriverProfileRequest;
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
}
