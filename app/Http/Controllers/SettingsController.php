<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $preferences = $request->user()->notificationPreference()->firstOrCreate([]);

        return view('settings.index', compact('preferences'));
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $fields = ['trip_updates', 'booking_updates', 'payment_updates', 'message_alerts', 'rating_reminders'];
        $preferences = $request->user()->notificationPreference()->firstOrCreate([]);
        $preferences->update(collect($fields)->mapWithKeys(fn (string $field) => [$field => $request->boolean($field)])->all());

        return back()->with('success', 'Notification preferences saved.');
    }
}
