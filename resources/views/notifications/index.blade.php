<x-ratings.shell page-title="Notifications">
    @php
        $activeFilter = request('filter', 'all');
        $activeType = request('type');
        $typeLabels = [
            'message_received' => 'Messages', 'trip_updated' => 'Trip updates',
            'payment_due' => 'Payments due', 'payment_completed' => 'Payment updates',
            'rating_reminder' => 'Rating reminders', 'rating_received' => 'Ratings received',
            'payment_received' => 'Payments received', 'booking_request' => 'Booking requests',
            'booking_status' => 'Booking updates', 'trip_reminder' => 'Trip reminders',
            'emergency_alert' => 'Emergency alerts',
        ];
    @endphp

    <div data-notification-center class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>
                    <p class="mt-1 text-sm text-slate-500">Stay updated on your bookings, trips, messages, payments, and ratings.</p>
                </div>
                @if(Auth::user()->unreadNotifications()->exists())
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')
                        <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Mark all as read</button>
                    </form>
                @endif
            </div>

            @if(session('success'))<div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif

            <form method="GET" class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center">
                <div class="flex rounded-xl bg-slate-100 p-1 text-sm font-semibold">
                    <a href="{{ route('notifications.index', array_filter(['type' => $activeType])) }}" class="rounded-lg px-3 py-2 {{ $activeFilter === 'all' ? 'bg-white text-green-700 shadow-sm' : 'text-slate-500' }}">All</a>
                    <a href="{{ route('notifications.index', array_filter(['filter' => 'unread', 'type' => $activeType])) }}" class="rounded-lg px-3 py-2 {{ $activeFilter === 'unread' ? 'bg-white text-green-700 shadow-sm' : 'text-slate-500' }}">Unread</a>
                </div>
                <label class="flex min-w-0 flex-1 items-center gap-2 text-sm text-slate-500">
                    <span class="sr-only">Filter notifications by type</span>
                    <select name="type" onchange="this.form.submit()" class="w-full rounded-xl border-slate-200 py-2 text-sm text-slate-700 focus:border-green-500 focus:ring-green-500">
                        <option value="">All notification types</option>
                        @foreach($types as $type)<option value="{{ $type }}" @selected($activeType === $type)>{{ $typeLabels[$type] ?? str($type)->replace('_', ' ')->title() }}</option>@endforeach
                    </select>
                    @if($activeFilter === 'unread')<input type="hidden" name="filter" value="unread">@endif
                </label>
            </form>

            <div class="mt-5 space-y-3">
                @forelse($notifications as $notification)
                    @php
                        $icon = $notification->data['icon'] ?? 'bell'; $title = $notification->data['title'] ?? 'Notification';
                        $message = $notification->data['message'] ?? 'You have a new update.'; $type = $notification->data['type'] ?? '';
                        $iconStyle = match (true) {
                            str_contains($type, 'emergency') => 'bg-red-100 text-red-600', str_contains($type, 'payment') => 'bg-emerald-100 text-emerald-600',
                            str_contains($type, 'rating') => 'bg-amber-100 text-amber-500', str_contains($type, 'message') => 'bg-blue-100 text-blue-600',
                            default => 'bg-green-100 text-green-600',
                        };
                    @endphp
                    <div class="flex gap-3 rounded-2xl border p-4 shadow-sm {{ $notification->read_at ? 'border-slate-200 bg-white' : 'border-green-200 bg-green-50/60' }}">
                        <a href="{{ route('notifications.open', $notification->id) }}" class="flex min-w-0 flex-1 gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $iconStyle }}"><x-icons.lucide :name="$icon" class="h-5 w-5" /></span>
                            <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">{{ $title }}</span><span class="mt-1 block text-sm text-slate-600">{{ $message }}</span><span class="mt-2 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span></span>
                        </a>
                        <div class="flex shrink-0 items-start gap-1">
                            @unless($notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-green-600" title="Mark as read"><x-icons.lucide name="check" class="h-4 w-4" /><span class="sr-only">Mark as read</span></button></form>@endunless
                            <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">@csrf @method('DELETE')<button class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-red-600" title="Delete notification"><x-icons.lucide name="trash-2" class="h-4 w-4" /><span class="sr-only">Delete notification</span></button></form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">No notifications match this filter.</div>
                @endforelse
            </div>
            <div class="mt-6">{{ $notifications->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
