<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PhoneVerificationController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'phone_number' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9\s\-()]{7,30}$/',
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],
        ]);

        $phoneNumber = trim($validated['phone_number']);

        if ($user->phone_verified_at !== null && $phoneNumber === $user->phone_number) {
            return redirect()->route($this->profileRoute($user))->with('status', 'phone-already-verified');
        }

        if (
            $user->phone_verified_at !== null
            && ! $request->boolean('confirm_phone_change')
        ) {
            return redirect()->route($this->profileRoute($user))
                ->withErrors(['phone_number' => 'Confirm the phone number change before requesting a new verification code.']);
        }

        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'phone_number' => $phoneNumber,
            'phone_verified_at' => null,
        ])->save();

        Cache::put($this->cacheKey($user), [
            'code' => $otp,
            'phone_number' => $phoneNumber,
        ], now()->addMinutes(5));

        if (app()->environment('local')) {
            Log::info('Mock phone verification OTP generated.', [
                'user_id' => $user->id,
                'phone_number' => $phoneNumber,
                'otp' => $otp,
            ]);
        }

        return redirect()->route($this->profileRoute($user))->with('status', 'phone-otp-sent');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $verification = Cache::get($this->cacheKey($user));

        if (
            ! is_array($verification)
            || ! isset($verification['code'], $verification['phone_number'])
            || ! hash_equals((string) $verification['code'], $validated['otp'])
            || $verification['phone_number'] !== $user->phone_number
        ) {
            return redirect()->route($this->profileRoute($user))
                ->withErrors(['otp' => 'The verification code is invalid or has expired.']);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        Cache::forget($this->cacheKey($user));

        return redirect()->route($this->profileRoute($user))->with('status', 'phone-verified');
    }

    private function cacheKey(User $user): string
    {
        return 'phone-verification:'.$user->id;
    }

    private function profileRoute(User $user): string
    {
        return $user->role === 'driver' ? 'driver.profile.edit' : 'profile.edit';
    }
}
