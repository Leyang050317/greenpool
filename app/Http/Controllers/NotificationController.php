<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $baseQuery = $request->user()->notifications();
        $types = (clone $baseQuery)
            ->latest()
            ->get()
            ->map(fn ($notification) => $notification->data['type'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $notifications = $baseQuery
            ->when(($validated['filter'] ?? 'all') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('data->type', $type))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('notifications.index', compact('notifications', 'types'));
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return redirect()->to($item->data['url'] ?? route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return back()->with('success', 'Notification removed.');
    }
}
