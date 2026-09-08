<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    private const GENERIC_RESET_RESPONSE = 'If an account exists for this email address, we have sent a password reset link.';

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower(trim($validatedEmail = $request->string('email')->toString()));
        $rateLimitKey = 'password-reset:'.hash('sha256', $request->ip().'|'.$email);

        // The browser response is identical for existing and unknown addresses,
        // preventing this endpoint from revealing who has an account.
        if (! RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            RateLimiter::hit($rateLimitKey, 60);

            try {
                $status = Password::sendResetLink(['email' => $validatedEmail]);

                Log::info('Password reset request processed.', [
                    'outcome' => $status === Password::RESET_LINK_SENT ? 'sent' : 'not_sent',
                ]);
            } catch (Throwable $exception) {
                // Do not log the email address or expose a delivery failure:
                // either could reveal whether an account exists.
                Log::warning('Password reset request could not be completed.', [
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return back()
            ->withInput($request->only('email'))
            ->with('status', self::GENERIC_RESET_RESPONSE);
    }
}
