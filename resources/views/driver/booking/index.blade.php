<x-app-layout>
    @php
        $badge = [
            'Pending' => 'bg-amber-50 text-amber-700',
            'Accepted' => 'bg-green-50 text-green-700',
            'Rejected' => 'bg-red-50 text-red-700',
            'Cancelled' => 'bg-gray-100 text-gray-600',
        ];
    @endphp

    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Booking Requests</h1>
                    <p class="mt-1 text-sm text-gray-500">Review passenger requests for your trips.</p>
                </div>
                <form method="GET" action="{{ route('driver.booking-requests.index') }}" class="flex w-full gap-3 sm:w-auto">
                    <select name="status" onchange="this.form.submit()" class="w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A] sm:w-44">
                        <option value="">All Statuses</option>
                        @foreach(['Pending', 'Accepted', 'Rejected', 'Cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">
                        <x-icons.lucide name="filter" class="h-4 w-4" />
                        Filter
                    </button>
                </form>
            </div>

            @if(session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @if($bookings->isEmpty())
                <div class="rounded-xl border border-gray-100 bg-white px-6 py-16 text-center">
                    <p class="text-base font-semibold text-gray-700">No booking requests found.</p>
                    <p class="mt-1 text-sm text-gray-500">Passenger requests for your trips will appear here.</p>
                </div>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($bookings as $booking)
                        @php
                            $initials = collect(preg_split('/\s+/', trim($booking->passenger->name)))
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                        @endphp
                        <article class="rounded-xl border border-gray-100 bg-white p-5 transition-shadow hover:shadow-md">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-50 text-sm font-bold text-[#2E7D32]">
                                        {{ $initials ?: 'P' }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $booking->passenger->name }}</p>
                                        @if($booking->passenger->ratings_received_count > 0)
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-amber-500">
                                                <x-icons.lucide name="star" class="h-3 w-3 fill-current" />
                                                {{ number_format((float) $booking->passenger->ratings_received_avg_score, 1) }}
                                                <span class="text-gray-400">({{ $booking->passenger->ratings_received_count }})</span>
                                            </p>
                                        @else
                                            <p class="mt-0.5 text-xs text-gray-400">No ratings yet</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$booking->booking_status] }}">
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

                            <div class="space-y-2 text-xs text-gray-600">
                                <p class="flex items-center gap-2">
                                    <x-icons.lucide name="map-pin" class="h-3.5 w-3.5 text-gray-400" />
                                    Pickup: {{ $booking->pickup_point }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <x-icons.lucide name="users" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $booking->number_of_seats }} {{ Str::plural('passenger', $booking->number_of_seats) }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <x-icons.lucide name="briefcase" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $booking->number_of_luggage }} {{ Str::plural('luggage', $booking->number_of_luggage) }}
                                </p>
                                <p class="flex items-center gap-2">
                                    <x-icons.lucide name="clock" class="h-3.5 w-3.5 text-gray-400" />
                                    {{ $booking->created_at->format('d M Y, g:i A') }}
                                </p>
                            </div>

                            <div class="mt-4 border-t border-gray-50 pt-4">
                                <a href="{{ route('driver.booking-requests.show', $booking) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-xs font-semibold text-white hover:bg-[#256b29]">
                                    View Details
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-6">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
