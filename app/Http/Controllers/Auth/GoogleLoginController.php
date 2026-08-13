<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                if (!$user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                Auth::login($user);

                return $user->role === 'driver' ? redirect()->route('driver.home') : redirect()->route('passenger.home');
            }

            session()->put('google_new_user', [
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
            ]);

            return redirect()->route('auth.google.role');

        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google login failed. Please try again.');
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

        $user = User::create([
            'name' => $googleData['name'],
            'email' => $googleData['email'],
            'password' => Hash::make(Str::random(24)),
            'role' => $request->role,
        ]);

        $user->markEmailAsVerified();

        session()->forget('google_new_user');

        Auth::login($user);

        return $user->role === 'driver' ? redirect()->route('driver.home') : redirect()->route('passenger.home');
    }
}