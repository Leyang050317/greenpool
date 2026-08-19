<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class LinkedAccountController extends Controller
{
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $request->session()->put('google_linking', true);

        return Socialite::driver('google')
            ->redirectUrl($this->callbackUrl($request))
            ->redirect();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->google_id) {
            return back()->with('error', 'No Google account is connected to this account.');
        }

        if ($user->password === null || $user->usesGoogleAuthentication()) {
            return back()->with('error', 'Please set a password in your account settings before unlinking your Google account.');
        }

        $user->update(['google_id' => null]);

        return back()->with('status', 'google-account-unlinked');
    }

    private function callbackUrl(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/') . '/auth/google/callback';
    }
}
