<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route(
                $request->user()->role === 'driver' ? 'driver.home' : 'passenger.home'
            );
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'We could not send the verification email right now. Please try again later.');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
