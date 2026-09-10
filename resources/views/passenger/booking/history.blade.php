@extends('passenger.booking.layout')

@section('pageTitle', 'Booking History')

@section('content')
    @php
        $badge = [
            'Pending' => 'bg-amber-50 text-amber-700',
            'Scheduled' => 'bg-blue-50 text-blue-700',
            'Accepted' => 'bg-green-50 text-green-700',
            'In Progress' => 'bg-orange-50 text-orange-700',
            'Completed' => 'bg-gray-100 text-gray-700',
            'Rejected' => 'bg-red-50 text-red-700',
            'Cancelled' => 'bg-gray-100 text-gray-600',
            'Expired' => 'bg-red-50 text-red-700',
        ];
    @endphp

    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Booking History</h1>
                    <p class="mt-1 text-sm text-gray-500">Track your GreenPool booking requests.</p>
                </div>
                <a href="{{ route('passenger.booking') }}" class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">
                    <x-icons.lucide name="search" class="h-4 w-4" />
                    Find a Ride
                </a>
            </div>

            @if(session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                @if($bookings->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <p class="text-base font-semibold text-gray-700">No booking history yet.</p>
                        <p class="mt-1 text-sm text-gray-500">Your booking requests will appear here.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    @foreach(['Trip', 'Date', 'Seats', 'Pickup Point', 'Status', 'Action'] as $heading)
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($bookings as $booking)
                                    @php
                                        // A driver accepting a request changes the booking to Accepted.
                                        // Scheduled describes the trip timetable, not the passenger's request.
                                        $displayStatus = $booking->booking_status === 'Accepted' && $booking->trip->status === 'Scheduled'
                                            ? ($booking->trip->hasExpiredDeparture() ? 'Expired' : 'Accepted')
                                            : ($booking->booking_status === 'Accepted' ? $booking->trip->status : $booking->booking_status);
                                    @endphp
                                    <tr x-data data-booking-url="{{ route('passenger.bookings.show', $booking) }}" tabindex="0" role="link" @click="window.location.href = $el.dataset.bookingUrl" @keydown.enter="window.location.href = $el.dataset.bookingUrl" class="cursor-pointer transition hover:bg-green-50/50 focus:outline-none focus-visible:bg-green-50">
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-gray-900">{{ $booking->trip->departure_location }} to {{ $booking->trip->destination }}</div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                                <span>{{ $booking->trip->user->name }}</span>
                                                @if($booking->trip->user->ratings_received_count > 0)
                                                    <span class="inline-flex items-center gap-1 text-amber-500"><x-icons.lucide name="star" class="h-3 w-3 fill-current" />{{ number_format((float) $booking->trip->user->ratings_received_avg_score, 1) }}</span>
                                                @else
                                                    <span class="text-gray-400">No ratings yet</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $booking->trip->departure_at->format('d M Y, g:i A') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $booking->number_of_seats }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $booking->pickup_point }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span data-trip-expiry-badge data-departure-at="{{ $booking->trip->departure_at->toIso8601String() }}" data-scheduled-class="{{ $badge['Scheduled'] }}" data-expired-class="{{ $badge['Expired'] }}" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$displayStatus] }}">{{ $displayStatus }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3" @click.stop>
                                            <div class="flex items-center gap-2">
                                            @if($booking->booking_status === 'Pending')
                                                <x-trip-confirmation
                                                    name="cancel-booking-{{ $booking->id }}"
                                                    title="Cancel Booking Request?"
                                                    message="Are you sure you want to cancel this booking request?"
                                                    confirm-label="Confirm Cancel"
                                                    :action="route('passenger.bookings.cancel', $booking)"
                                                    variant="danger"
                                                    class="h-9 rounded-lg px-3 text-xs font-medium"
                                                >
                                                    Cancel
                                                </x-trip-confirmation>
                                            @elseif($booking->booking_status === 'Accepted' && $booking->trip->status === 'Scheduled')
                                                <x-trip-confirmation name="cancel-confirmed-booking-{{ $booking->id }}" title="Cancel confirmed booking?" message="Your driver will be notified and your seats will become available again." confirm-label="Cancel booking" :action="route('passenger.bookings.cancel', $booking)" variant="danger" class="h-9 rounded-lg px-3 text-xs font-medium">Cancel booking</x-trip-confirmation>
                                            @elseif($booking->booking_status === 'Accepted' && $booking->trip->status === 'In Progress' && $booking->picked_up_at === null)
                                                <x-trip-confirmation name="not-boarding-{{ $booking->id }}" title="Tell your driver you will not board?" message="Your driver will be told to skip your pickup point. Use Emergency instead if you need urgent help." confirm-label="I will not board" :action="route('passenger.bookings.cancel', $booking)" variant="danger" class="h-9 rounded-lg px-3 text-xs font-medium">I will not board</x-trip-confirmation>
                                            @elseif($booking->booking_status === 'Accepted' && $booking->trip->status === 'Completed' && ! $booking->payment?->isPaid())
                                                <a href="{{ route('payments.checkout', $booking) }}" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Pay RM {{ number_format((float) $booking->trip->price_per_passenger * $booking->number_of_seats, 2) }}</a>
                                            @elseif($booking->booking_status === 'Accepted' && $booking->trip->status === 'Completed' && ! $booking->ratings->contains('reviewer_id', Auth::id()))
                                                @if($booking->trip->completed_at?->addDays(7)->isPast())
                                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400"><x-icons.lucide name="lock" class="h-3.5 w-3.5" /> Rating expired</span>
                                                @else
                                                    <a href="{{ route('ratings.create', $booking) }}" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Rate Driver</a>
                                                @endif
                                            @elseif($booking->ratings->contains('reviewer_id', Auth::id()))
                                                <span class="text-xs font-medium text-green-600">Rated</span>
                                            @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-50 px-4 py-3">{{ $bookings->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
