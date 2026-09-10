@extends('passenger.booking.layout')

@section('pageTitle', 'Dashboard')

@section('content')
    @php
        $statCards = [
            ['label' => 'Total Bookings', 'value' => $rideStats['total'], 'icon' => 'history', 'tone' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Completed Rides', 'value' => $rideStats['completed'], 'icon' => 'circle-check', 'tone' => 'bg-green-100 text-green-700'],
            ['label' => 'Pending Requests', 'value' => $rideStats['pending'], 'icon' => 'clock', 'tone' => 'bg-amber-100 text-amber-700'],
            ['label' => 'Cancelled / Rejected', 'value' => $rideStats['cancelled_rejected'], 'icon' => 'circle-x', 'tone' => 'bg-red-100 text-red-700'],
        ];
    @endphp

    <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Welcome, {{ Auth::user()->name }}</h1>
                    <p class="mt-2 text-sm text-gray-500">Track your rides, requests, and booking updates.</p>
                </div>
                <a href="{{ route('passenger.booking') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                    <x-icons.lucide name="search" class="h-4 w-4" />
                    Find a Ride
                </a>
            </div>

            @if($pendingPaymentBooking)
                <section class="mt-8 overflow-hidden rounded-xl border border-green-200 bg-white shadow-sm" aria-labelledby="pending-payment-title">
                    <div class="bg-green-50 px-5 py-4 sm:px-6">
                        <p class="text-xs font-semibold uppercase text-green-700">Trip completed</p>
                        <h2 id="pending-payment-title" class="mt-1 text-xl font-bold text-slate-900">Complete your payment</h2>
                    </div>
                    <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-green-100 text-sm font-bold text-green-700">RM</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $pendingPaymentBooking->trip->user->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $pendingPaymentBooking->trip->departure_location }} to {{ $pendingPaymentBooking->trip->destination }}</p>
                            <p class="mt-2 text-xl font-bold text-[#2E7D32]">RM {{ number_format((float) $pendingPaymentBooking->payment->amount, 2) }}</p>
                        </div>
                        <a href="{{ $pendingPaymentBooking->payment->payment_method === 'cash' ? route('payments.show', $pendingPaymentBooking->payment) : route('payments.checkout', $pendingPaymentBooking) }}" class="w-full rounded-lg bg-[#22C55E] px-5 py-3 text-center text-sm font-bold text-white hover:bg-green-600 sm:w-auto">{{ $pendingPaymentBooking->payment->payment_method === 'cash' ? 'View Cash Status' : 'Pay Now' }}</a>
                    </div>
                </section>
            @elseif($pendingRatingBooking)
                <section class="mt-8 overflow-hidden rounded-xl border border-green-200 bg-white shadow-sm" aria-labelledby="pending-rating-title">
                    <div class="bg-green-50 px-5 py-4 sm:px-6">
                        <p class="text-xs font-semibold uppercase text-green-700">Trip completed</p>
                        <h2 id="pending-rating-title" class="mt-1 text-xl font-bold text-slate-900">How was your ride?</h2>
                    </div>
                    <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-bold text-white">
                            {{ strtoupper(substr($pendingRatingBooking->trip->user->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $pendingRatingBooking->trip->user->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $pendingRatingBooking->trip->departure_location }} to {{ $pendingRatingBooking->trip->destination }}</p>
                            <p class="mt-2 text-2xl tracking-wide text-amber-400" aria-hidden="true">*****</p>
                        </div>
                        <a href="{{ route('ratings.create', $pendingRatingBooking) }}" class="w-full rounded-lg bg-[#22C55E] px-5 py-3 text-center text-sm font-bold text-white hover:bg-green-600 sm:w-auto">Rate Now</a>
                    </div>
                </section>
            @endif

            <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ride statistics">
                @foreach($statCards as $stat)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $stat['value'] }}</p>
                            </div>
                            <span class="flex h-11 w-11 items-center justify-center rounded-lg {{ $stat['tone'] }}">
                                <x-icons.lucide :name="$stat['icon']" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                @endforeach
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="upcoming-ride-title">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-green-700">Upcoming Ride</p>
                            <h2 id="upcoming-ride-title" class="mt-1 text-xl font-bold text-slate-900">Your next accepted ride</h2>
                        </div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-100 text-green-700">
                            <x-icons.lucide name="car" class="h-5 w-5" />
                        </span>
                    </div>

                    @if($upcomingRide)
                        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px]">
                            <div class="min-w-0">
                                <p class="truncate text-2xl font-bold text-slate-950">{{ $upcomingRide->trip->destination }}</p>
                                <p class="mt-2 text-sm text-slate-500">{{ $upcomingRide->trip->departure_location }} to {{ $upcomingRide->trip->destination }}</p>

                                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="user" class="h-3.5 w-3.5" />
                                            Driver
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $upcomingRide->trip->user->name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="calendar" class="h-3.5 w-3.5" />
                                            Departure
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $upcomingRide->trip->departure_at->format('d M Y, g:i A') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="map-pin" class="h-3.5 w-3.5" />
                                            Pickup Point
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $upcomingRide->pickup_point }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-400">
                                            <x-icons.lucide name="car-front" class="h-3.5 w-3.5" />
                                            Vehicle
                                        </dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                                            {{ $upcomingRide->trip->vehicle?->model ?? 'Vehicle not set' }}
                                            @if($upcomingRide->trip->vehicle?->plate_number)
                                                ({{ $upcomingRide->trip->vehicle->plate_number }})
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Seats</dt>
                                        <dd class="mt-1 text-lg font-bold text-slate-900">{{ $upcomingRide->number_of_seats }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-1.5 text-xs font-semibold uppercase text-slate-400"><x-icons.lucide name="briefcase" class="h-3.5 w-3.5" />Luggage</dt>
                                        <dd class="mt-1 text-lg font-bold text-slate-900">{{ $upcomingRide->number_of_luggage }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Status</dt>
                                        <dd class="mt-2 inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">{{ $upcomingRide->trip->status }}</dd>
                                    </div>
                                </dl>
                                <a href="{{ route('passenger.bookings.show', $upcomingRide) }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    View Booking
                                    <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-700">No accepted ride coming up.</p>
                            <p class="mt-1 text-sm text-slate-500">Find a ride and wait for the driver to accept your request.</p>
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

            <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="pending-requests-title">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-amber-700">Pending Requests</p>
                        <h2 id="pending-requests-title" class="mt-1 text-xl font-bold text-slate-900">Waiting for driver response</h2>
                    </div>
                    <a href="{{ route('passenger.bookings.history') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">
                        My Bookings
                        <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    @forelse($pendingRequests as $requestItem)
                        <article class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-bold text-slate-900">{{ $requestItem->trip->destination }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ $requestItem->trip->departure_at->format('d M Y, g:i A') }}</p>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Pending</span>
                            </div>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-slate-400">Driver</dt>
                                    <dd class="mt-1 font-semibold text-slate-900">{{ $requestItem->trip->user->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-slate-400">Pickup</dt>
                                    <dd class="mt-1 font-semibold text-slate-900">{{ $requestItem->pickup_point }}</dd>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-slate-400">Seats</dt>
                                        <dd class="mt-1 font-semibold text-slate-900">{{ $requestItem->number_of_seats }}</dd>
                                    </div>
                                    <div>
                                        <dt class="flex items-center gap-1.5 text-xs font-semibold uppercase text-slate-400"><x-icons.lucide name="briefcase" class="h-3.5 w-3.5" />Luggage</dt>
                                        <dd class="mt-1 font-semibold text-slate-900">{{ $requestItem->number_of_luggage }}</dd>
                                    </div>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500 lg:col-span-3">
                            No pending requests right now.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
