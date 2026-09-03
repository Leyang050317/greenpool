<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        return Socialite::driver('google')
            ->redirectUrl($this->callbackUrl($request))
            ->redirect();
    }

    public function redirectToGoogleForDeactivation(Request $request)
    {
        $request->session()->put('google_deactivation_user_id', $request->user()->id);

        return Socialite::driver('google')
            ->redirectUrl($this->callbackUrl($request))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->callbackUrl($request))
                ->user();
            $googleId = (string) $googleUser->getId();

            if ($deactivationUserId = $request->session()->pull('google_deactivation_user_id')) {
                $user = User::find($deactivationUserId);

                if (! $user || $user->google_id !== $googleId) {
                    return redirect()->route('profile.edit')->with('error', 'Use the Google account connected to GreenPool to continue.');
                }

                $request->session()->put('google_deactivation_verified_user_id', $user->id);

                return redirect()->route($user->role === 'driver' ? 'driver.profile.edit' : 'profile.edit')
                    ->with('status', 'google-account-confirmed');
            }

            if ($request->session()->pull('google_linking', false) && Auth::check()) {
                $user = $request->user();

                if (User::query()->where('google_id', $googleId)->whereKeyNot($user->id)->exists()) {
                    return redirect()->route($user->role === 'driver' ? 'driver.profile.edit' : 'profile.edit')
                        ->with('error', 'This Google account is already connected to another GreenPool account.');
                }

                $user->update(['google_id' => $googleId]);

                return redirect()->route($user->role === 'driver' ? 'driver.profile.edit' : 'profile.edit')
                    ->with('status', 'google-account-linked');
            }

            $googleEmail = (string) $googleUser->getEmail();
            $user = User::query()->where('google_id', $googleId)->first();

            if ($user) {
                if (!$user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                Auth::login($user);
                $request->session()->put('auth_provider', 'google');

                if ($user->isDeactivated()) {
                    $request->session()->put('reactivation_google_verified_user_id', $user->id);

                    return redirect()->route('account.reactivate.show');
                }

                if (! $user->isActive()) {
                    Auth::logout();

                    return redirect()->route('login')->with('error', 'This account has been permanently closed. Please register a new account to use GreenPool again.');
                }

                return $user->role === 'driver' ? redirect()->route('driver.home') : redirect()->route('passenger.home');
            }

            $legacyGoogleUser = User::query()
                ->where('email', $googleEmail)
                ->where('auth_provider', 'google')
                ->first();

            if ($legacyGoogleUser) {
                $legacyGoogleUser->update(['google_id' => $googleId]);
                Auth::login($legacyGoogleUser);
                $request->session()->put('auth_provider', 'google');

                if ($legacyGoogleUser->isDeactivated()) {
                    $request->session()->put('reactivation_google_verified_user_id', $legacyGoogleUser->id);

                    return redirect()->route('account.reactivate.show');
                }

                return $legacyGoogleUser->role === 'driver' ? redirect()->route('driver.home') : redirect()->route('passenger.home');
            }

            if (User::query()->where('email', $googleEmail)->exists()) {
                return redirect()->route('login')->with(
                    'error',
                    'An account with this email already exists. Sign in with your password and connect Google from Profile settings.'
                );
            }

            session()->put('google_new_user', [
                'name' => $googleUser->getName(),
                'email' => $googleEmail,
                'google_id' => $googleId,
            ]);

            return redirect()->route('auth.google.role');

        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login')->with('error', 'Google sign-in could not be completed. Please try again.');
        }
    }

    public function showRoleSelection()
    {
        if (!session()->has('google_new_user')) {
            return redirect()->route('login');
        }

        return view('auth.google-role');
    }

    public function storeRole(Request $request)
    {
        if (!session()->has('google_new_user')) {
            return redirect()->route('login');
        }

        $request->validate([
            'role' => ['required', 'in:passenger,driver'],
        ]);

        $googleData = session()->get('google_new_user');

        if (User::query()->where('email', $googleData['email'])->exists()) {
            session()->forget('google_new_user');

            return redirect()->route('login')->with(
                'error',
                'An account with this email already exists. Sign in with your password and connect Google from Profile settings.'
            );
        }

        $user = User::create([
            'name' => $googleData['name'],
            'email' => $googleData['email'],
            'password' => Hash::make(Str::random(24)),
            'auth_provider' => 'google',
            'google_id' => $googleData['google_id'],
            'role' => $request->role,
        ]);

        $user->markEmailAsVerified();

        session()->forget('google_new_user');

        Auth::login($user);
        $request->session()->put('auth_provider', 'google');

        return $user->role === 'driver' ? redirect()->route('driver.home') : redirect()->route('passenger.home');
    }

    private function callbackUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/') . '/auth/google/callback';
    }
}
