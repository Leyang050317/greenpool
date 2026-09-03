<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountLifecycleService;
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
            ->loadCount('ratingsReceived')
            ->load('emergencyContacts');

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

    public function destroy(Request $request, AccountLifecycleService $accountLifecycle): RedirectResponse
    {
        $user = $request->user();

        if ($user->usesGoogleAuthentication()) {
            if ((int) $request->session()->pull('google_deactivation_verified_user_id') !== $user->id) {
                return back()->withErrors([
                    'account' => 'Confirm your Google account before deactivating your account.',
                ], 'userDeletion');
            }
        } else {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        }

        $accountLifecycle->deactivate($user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('login')->with('status', 'Your account has been deactivated. Sign in within 30 days if you want to reactivate it.');
    }

    public function showReactivation(Request $request): View|RedirectResponse
    {
        if ($request->user()->isActive()) {
            return Redirect::to('/');
        }

        return view('auth.reactivate-account', ['user' => $request->user()]);
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isDeactivated()) {
            return Redirect::route('login')->with('error', 'This account can no longer be reactivated.');
        }

        $proofKey = $user->usesGoogleAuthentication()
            ? 'reactivation_google_verified_user_id'
            : 'reactivation_password_verified_user_id';

        if ((int) $request->session()->pull($proofKey) !== $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Redirect::route('login')->with('error', 'Sign in again to confirm that you own this account.');
        }

        $user->update([
            'account_status' => 'active',
            'deactivated_at' => null,
        ]);

        return Redirect::to('/')->with('status', 'Your GreenPool account has been reactivated.');
    }
}
