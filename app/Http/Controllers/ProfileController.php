<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()
            ->loadAvg('ratingsReceived', 'score')
            ->loadCount('ratingsReceived');

        $canManagePassword = ! $user->usesGoogleAuthentication()
            && $request->session()->get('auth_provider') !== 'google';

        $recentLogins = $user->loginHistories()
            ->latest('login_at')
            ->latest('id')
            ->limit(5)
            ->get();
        $profileCompleteness = $user->profileCompleteness();

        if ($request->user()->role === 'passenger') {
            return view('passenger.profile', [
                'user' => $user,
                'canManagePassword' => $canManagePassword,
                'recentLogins' => $recentLogins,
                'profileCompleteness' => $profileCompleteness,
            ]);
        }

        return view('profile.edit', [
            'user' => $user,
            'canManagePassword' => $canManagePassword,
            'recentLogins' => $recentLogins,
            'profileCompleteness' => $profileCompleteness,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        if ($request->hasFile('photo')) {
            if ($request->user()->photo) {
                Storage::disk('public')->delete($request->user()->photo);
            }

            $path = $request->file('photo')->store('profile-photos', 'public');
            $request->user()->photo = $path;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
