<x-app-layout>
    @php
        $statCards = [
            ['label' => 'Total Trips', 'value' => $driverStats['total_trips'], 'icon' => 'route', 'tone' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Completed Trips', 'value' => $driverStats['completed_trips'], 'icon' => 'circle-check', 'tone' => 'bg-green-100 text-green-700'],
            ['label' => 'Pending Requests', 'value' => $driverStats['pending_requests'], 'icon' => 'clipboard-list', 'tone' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Total Earnings', 'value' => 'RM '.number_format((float) $driverStats['total_earnings'], 2), 'amount' => (float) $driverStats['total_earnings'], 'icon' => 'dollar-sign', 'tone' => 'bg-emerald-100 text-emerald-700', 'hint' => 'Paid passenger payments', 'href' => route('payments.index')],
        ];
        $activePassengerCount = (int) ($activeTrip?->accepted_passengers_count ?? 0);
        $pickedPassengerCount = $activeTrip?->bookings?->whereNotNull('picked_up_at')->sum('number_of_seats') ?? 0;
    @endphp

    <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Welcome, {{ Auth::user()->name }}</h1>
                    <p class="mt-2 text-sm text-gray-500">Manage your journey, booking requests, and daily schedule.</p>
                </div>
                <a href="{{ route('driver.trips.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                    <x-icons.lucide name="plus" class="h-4 w-4" />
                    Create Trip
                </a>
            </div>

            <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Driver statistics">
                @foreach($statCards as $stat)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900" @isset($stat['amount']) data-driver-total-earnings data-amount="{{ $stat['amount'] }}" @endisset>{{ $stat['value'] }}</p>
                                @if(isset($stat['hint']))
                                    <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                                @endif
                            </div>
                            <span class="flex h-11 w-11 items-center justify-center rounded-lg {{ $stat['tone'] }}">
                                <x-icons.lucide :name="$stat['icon']" class="h-5 w-5" />
                            </span>
                        </div>
                        @if(isset($stat['href']))
                            <a href="{{ $stat['href'] }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-green-700 hover:text-green-800">
                                View payments
                                <x-icons.lucide name="arrow-right" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>
                @endforeach
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="active-trip-title">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-green-700">Active Trip</p>
                            <h2 id="active-trip-title" class="mt-1 text-xl font-bold text-slate-900">Current journey</h2>
                        </div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-100 text-green-700">
                            <x-icons.lucide name="play" class="h-5 w-5" />
                        </span>
                    </div>

                    @if($activeTrip)
                        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_240px]">
                            <div class="min-w-0">
                                <p class="truncate text-2xl font-bold text-slate-950">{{ $activeTrip->destination }}</p>
                                <p class="mt-2 text-sm text-slate-500">{{ $activeTrip->departure_location }} to {{ $activeTrip->destination }}</p>

                                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="calendar" class="h-3.5 w-3.5" />
                                            Departure
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeTrip->departure_at->format('d M Y, g:i A') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="clock" class="h-3.5 w-3.5" />
                                            Estimated Arrival
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeTrip->estimated_arrival_at?->format('g:i A') ?? 'Not calculated' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="car-front" class="h-3.5 w-3.5" />
                                            Vehicle
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeTrip->vehicle->brand }} {{ $activeTrip->vehicle->model }} ({{ $activeTrip->vehicle->plate_number }})</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="users" class="h-3.5 w-3.5" />
                                            Pickup Progress
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $pickedPassengerCount }}/{{ $activePassengerCount }} passengers</dd>
                                    </div>
                                </dl>

                                <div class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200">
                                    @forelse($activeTrip->bookings->take(3) as $booking)
                                        <div class="flex items-center justify-between gap-3 p-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900">{{ $booking->passenger->name }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $booking->pickup_point }}</p>
                                            </div>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $booking->picked_up_at ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $booking->picked_up_at ? 'Picked up' : 'Waiting' }}
                                            </span>
                                        </div>
                                    @empty
                                        <div class="p-4 text-sm text-slate-500">No accepted passengers yet.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Accepted Passengers</dt>
                                        <dd class="mt-1 text-lg font-bold text-slate-900">{{ $activePassengerCount }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Distance</dt>
                                        <dd class="mt-1 text-lg font-bold text-slate-900">{{ $activeTrip->estimated_distance_km ? $activeTrip->estimated_distance_km.' km' : '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Status</dt>
                                        <dd class="mt-2 inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">{{ $activeTrip->status }}</dd>
                                    </div>
                                </dl>
                                <a href="{{ route('driver.trips.journey') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                                    Open Journey
                                    <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-700">No trip in progress.</p>
                            <p class="mt-1 text-sm text-slate-500">Start a scheduled trip from your journey page when you are ready.</p>
                            <a href="{{ route('driver.trips.journey') }}" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Go to Journey
                                <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    @endif
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="recent-notifications-title">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Notifications</p>
                            <h2 id="recent-notifications-title" class="mt-1 text-xl font-bold text-slate-900">Recent updates</h2>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">View all</a>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($recentNotifications as $notification)
                            @php
                                $icon = $notification->data['icon'] ?? 'bell';
                                $title = $notification->data['title'] ?? 'Notification';
                                $message = $notification->data['message'] ?? 'You have a new update.';
                            @endphp
                            <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-3 rounded-lg border p-3 transition hover:border-green-300 {{ $notification->read_at ? 'border-slate-200 bg-white' : 'border-green-200 bg-green-50/70' }}">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-700">
                                    <x-icons.lucide :name="$icon" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $title }}</span>
                                    <span class="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500">{{ $message }}</span>
                                </span>
                                @unless($notification->read_at)
                                    <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-green-500"><span class="sr-only">Unread</span></span>
                                @endunless
                            </a>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                No notifications yet.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="pending-requests-title">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-amber-700">Booking Requests</p>
                            <h2 id="pending-requests-title" class="mt-1 text-xl font-bold text-slate-900">Pending passenger requests</h2>
                        </div>
                        <a href="{{ route('driver.booking-requests.index') }}" class="text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">View all</a>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse($pendingRequests as $booking)
                            <article class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-base font-bold text-slate-900">{{ $booking->passenger->name }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ $booking->trip->destination }}</p>
                                    </div>
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                </div>
                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Pickup</dt>
                                        <dd class="mt-1 truncate font-semibold text-slate-900">{{ $booking->pickup_point }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Seats</dt>
                                        <dd class="mt-1 font-semibold text-slate-900">{{ $booking->number_of_seats }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-1.5 text-xs font-semibold uppercase text-slate-400"><x-icons.lucide name="briefcase" class="h-3.5 w-3.5" />Luggage</dt>
                                        <dd class="mt-1 font-semibold text-slate-900">{{ $booking->number_of_luggage }}</dd>
                                    </div>
                                </dl>
                                <a href="{{ route('driver.booking-requests.show', $booking) }}" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                    View Request
                                    <x-icons.lucide name="arrow-right" class="h-3.5 w-3.5" />
                                </a>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                No pending booking requests.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="today-schedule-title">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Today</p>
                            <h2 id="today-schedule-title" class="mt-1 text-xl font-bold text-slate-900">Today's schedule</h2>
                        </div>
                        <a href="{{ route('driver.trips.index') }}" class="text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">My Trips</a>
                    </div>

                    <div class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                        @forelse($todaySchedule as $trip)
                            <article class="p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-base font-bold text-slate-900">{{ $trip->destination }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ $trip->departure_location }} to {{ $trip->destination }}</p>
                                        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1"><x-icons.lucide name="clock" class="h-3.5 w-3.5" />{{ $trip->departure_at->format('g:i A') }}</span>
                                            <span class="inline-flex items-center gap-1"><x-icons.lucide name="users" class="h-3.5 w-3.5" />{{ (int) ($trip->accepted_passengers_count ?? 0) }} passengers</span>
                                            <span class="inline-flex items-center gap-1"><x-icons.lucide name="car-front" class="h-3.5 w-3.5" />{{ $trip->vehicle->plate_number }}</span>
                                        </p>
                                    </div>
                                    <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $trip->status === 'In Progress' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $trip->status }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-slate-500">
                                No trips scheduled for today.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
