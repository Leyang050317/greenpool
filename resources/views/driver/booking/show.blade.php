<x-app-layout>
    @php
        $badge = [
            'Pending' => 'bg-amber-50 text-amber-700',
            'Accepted' => 'bg-green-50 text-green-700',
            'Rejected' => 'bg-red-50 text-red-700',
            'Cancelled' => 'bg-gray-100 text-gray-600',
        ];

        $initials = collect(preg_split('/\s+/', trim($booking->passenger->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $bookingActionMessage = match (request('result')) {
            'accepted' => $booking->booking_status === 'Accepted' ? 'Booking request accepted successfully.' : null,
            'rejected' => $booking->booking_status === 'Rejected' ? 'Booking request rejected successfully.' : null,
            default => null,
        };
    @endphp

    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8" x-data="{ acceptOpen: false, rejectOpen: false }">
        <div class="mx-auto max-w-5xl">
            <div class="mb-6 flex items-center justify-between gap-4">
                <a href="{{ route('driver.booking-requests.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-800">
                    <x-icons.lucide name="arrow-left" class="h-4 w-4" />
                    Back
                </a>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$booking->booking_status] }}">
                    @if($booking->booking_status === 'Pending')
                        <x-icons.lucide name="clock-3" class="h-3 w-3" />
                    @elseif($booking->booking_status === 'Accepted')
                        <x-icons.lucide name="circle-check" class="h-3 w-3" />
                    @elseif($booking->booking_status === 'Rejected')
                        <x-icons.lucide name="circle-x" class="h-3 w-3" />
                    @else
                        <x-icons.lucide name="x" class="h-3 w-3" />
                    @endif
                    {{ $booking->booking_status }}
                </span>
            </div>

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Booking Request Details</h1>
            </div>

            @if(session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
            @endif

            @if($bookingActionMessage)
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ $bookingActionMessage }}</div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-xl border border-gray-100 bg-white p-5">
                    <h2 class="mb-6 text-sm font-semibold text-gray-900">Passenger Information</h2>
                    <div class="flex flex-col items-center text-center">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-lg font-bold text-[#2E7D32]">
                            {{ $initials ?: 'P' }}
                        </span>
                        <p class="mt-4 text-sm font-semibold text-gray-900">{{ $booking->passenger->name }}</p>
                        @if($booking->passenger->ratings_received_count > 0)
                            <p class="mt-1 flex items-center justify-center gap-1 text-xs text-amber-500">
                                <x-icons.lucide name="star" class="h-3 w-3 fill-current" />
                                {{ number_format((float) $booking->passenger->ratings_received_avg_score, 1) }}
                                <span class="text-gray-400">({{ $booking->passenger->ratings_received_count }} {{ Str::plural('review', $booking->passenger->ratings_received_count) }})</span>
                            </p>
                        @else
                            <p class="mt-1 text-xs text-gray-400">No ratings yet</p>
                        @endif
                    </div>

                    <div class="mt-8 space-y-4 text-sm">
                        <div>
                            <p class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                <x-icons.lucide name="mail" class="h-3.5 w-3.5" />
                                Email
                            </p>
                            <p class="mt-1 text-gray-900">{{ $booking->passenger->email }}</p>
                        </div>
                    </div>
                </section>

                <div class="space-y-5 lg:col-span-2">
                    <section class="rounded-xl border border-gray-100 bg-white p-5">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Booking Information</h2>

                        <div class="rounded-lg border border-green-100 bg-green-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase text-[#2E7D32]">Pickup Point</p>
                            <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-[#166534]">
                                <x-icons.lucide name="map-pin" class="h-3.5 w-3.5" />
                                {{ $booking->pickup_point }}
                            </p>
                        </div>

                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="users" class="h-3.5 w-3.5" />
                                    Passenger Count
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->number_of_seats }} {{ Str::plural('passenger', $booking->number_of_seats) }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="briefcase" class="h-3.5 w-3.5" />
                                    Luggage Count
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->number_of_luggage }} {{ Str::plural('luggage', $booking->number_of_luggage) }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="clock" class="h-3.5 w-3.5" />
                                    Booking Time
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->created_at->format('d M Y, g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="badge-check" class="h-3.5 w-3.5" />
                                    Booking Status
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->booking_status }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-gray-100 bg-white p-5">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Trip Information</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="map-pin" class="h-3.5 w-3.5" />
                                    Destination
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->trip->destination }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="calendar" class="h-3.5 w-3.5" />
                                    Departure Date
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->trip->departure_at->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="clock" class="h-3.5 w-3.5" />
                                    Departure Time
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->trip->departure_at->format('g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="car-front" class="h-3.5 w-3.5" />
                                    Vehicle
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->trip->vehicle?->brand }} {{ $booking->trip->vehicle?->model }} {{ $booking->trip->vehicle?->plate_number ? '('.$booking->trip->vehicle->plate_number.')' : '' }}</dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-2 text-xs font-semibold uppercase text-gray-400">
                                    <x-icons.lucide name="users" class="h-3.5 w-3.5" />
                                    Available Seats
                                </dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $booking->trip->available_seats }} seats</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                @if($booking->booking_status === 'Pending')
                    @if($canAcceptBooking)
                        @if($booking->number_of_seats <= $booking->trip->available_seats)
                            <button type="button" @click="acceptOpen = true" class="inline-flex items-center gap-2 rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                                <x-icons.lucide name="check" class="h-4 w-4" />
                                Accept Booking
                            </button>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800" role="status">
                                <x-icons.lucide name="circle-alert" class="h-4 w-4" />
                                Not enough available seats
                            </span>
                        @endif
                    @else
                        <a href="{{ route('driver.profile.edit', ['section' => 'licence']) }}" class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                            Renew licence to accept
                        </a>
                    @endif
                    <button type="button" @click="rejectOpen = true" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        <x-icons.lucide name="x" class="h-4 w-4" />
                        Reject Booking
                    </button>
                @endif
                <a href="{{ route('driver.booking-requests.index') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
            </div>
        </div>

        @if($booking->number_of_seats <= $booking->trip->available_seats)
        <div x-cloak x-show="acceptOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.outside="acceptOpen = false">
                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-[#2E7D32]">
                    <x-icons.lucide name="circle-alert" class="h-5 w-5" />
                </div>
                <h2 class="text-base font-semibold text-gray-900">Accept Booking Request?</h2>
                <p class="mt-2 text-sm leading-6 text-gray-500">The passenger will be notified and the available seats will be updated automatically.</p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="acceptOpen = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <form method="POST" action="{{ route('driver.booking-requests.accept', $booking) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Accept</button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <div x-cloak x-show="rejectOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @click.outside="rejectOpen = false">
                <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <x-icons.lucide name="circle-alert" class="h-5 w-5" />
                </div>
                <h2 class="text-base font-semibold text-gray-900">Reject Booking Request?</h2>
                <p class="mt-2 text-sm leading-6 text-gray-500">The passenger will be notified that their request has been rejected.</p>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" @click="rejectOpen = false" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Keep Booking</button>
                    <form method="POST" action="{{ route('driver.booking-requests.reject', $booking) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
