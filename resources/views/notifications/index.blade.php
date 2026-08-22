<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h1 class="text-2xl font-bold text-slate-900">Notifications</h1><p class="mt-1 text-sm text-slate-400">Booking, rating, and account updates.</p></div>
                @if(Auth::user()->unreadNotifications()->exists())
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Mark all as read</button></form>
                @endif
            </div>
            @if(session('success'))<div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
            <div class="mt-6 space-y-3">
                @forelse($notifications as $notification)
                    @php
                        $icon = $notification->data['icon'] ?? 'bell';
                        $title = $notification->data['title'] ?? 'Notification';
                        $message = $notification->data['message'] ?? 'You have a new update.';
                        $iconStyle = str_starts_with((string) ($notification->data['type'] ?? ''), 'booking_request')
                            ? 'bg-green-100 text-green-600'
                            : 'bg-amber-100 text-amber-500';
                    @endphp
                    <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-4 rounded-2xl border p-5 shadow-sm transition hover:border-green-300 {{ $notification->read_at ? 'border-slate-200 bg-white' : 'border-green-200 bg-green-50/60' }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $iconStyle }}"><x-icons.lucide :name="$icon" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">{{ $title }}</span><span class="mt-1 block text-sm text-slate-600">{{ $message }}</span><span class="mt-2 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span></span>
                        @unless($notification->read_at)<span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-green-500"><span class="sr-only">Unread</span></span>@endunless
                    </a>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">You have no notifications yet.</div>
                @endforelse
            </div>
            <div class="mt-6">{{ $notifications->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
