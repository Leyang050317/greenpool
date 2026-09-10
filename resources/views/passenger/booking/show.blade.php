@extends('passenger.booking.layout')

@section('pageTitle', 'Booking Details')

@section('content')
    @php
        $trip = $booking->trip;
        // Booking status is the passenger's request outcome; trip status is shown separately below.
        $displayStatus = $booking->booking_status === 'Accepted' && $trip->status === 'Scheduled' ? ($trip->hasExpiredDeparture() ? 'Expired' : 'Accepted') : ($booking->booking_status === 'Accepted' ? $trip->status : $booking->booking_status);
        $statusStyle = match ($displayStatus) { 'Accepted' => 'bg-green-50 text-green-700', 'Scheduled' => 'bg-blue-50 text-blue-700', 'In Progress' => 'bg-orange-50 text-orange-700', 'Completed' => 'bg-slate-100 text-slate-700', 'Pending' => 'bg-amber-50 text-amber-700', default => 'bg-red-50 text-red-700' };
        $acceptedPickups = $trip->bookings;
        $nextPickup = $acceptedPickups->firstWhere('picked_up_at', null);
        $canShowMap = $booking->booking_status === 'Accepted' && $trip->status === 'In Progress';
    @endphp

    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-5xl">
        <div class="mb-7 flex items-start justify-between gap-4"><div class="flex items-start gap-3"><a href="{{ route('passenger.bookings.history') }}" class="rounded-xl p-2 text-slate-400 hover:bg-white hover:text-slate-700"><x-icons.lucide name="arrow-left" class="h-5 w-5" /></a><div><h1 class="text-2xl font-bold text-slate-900">Booking Details</h1><p data-trip-route-summary class="mt-1 text-sm text-slate-500">{{ $trip->departure_location }} to {{ $trip->destination }}</p></div></div><span data-trip-expiry-badge data-departure-at="{{ $trip->departure_at->toIso8601String() }}" data-scheduled-class="bg-blue-50 text-blue-700" data-expired-class="bg-red-50 text-red-700" class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ $statusStyle }}">{{ $displayStatus }}</span></div>

        @if(session('success'))<div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

        @if($trip->status === 'In Progress' && $booking->booking_status === 'Accepted')
            <section class="mb-6 rounded-2xl border border-orange-200 bg-orange-50 p-5"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 animate-pulse rounded-full bg-orange-500"></span><h2 class="font-semibold text-orange-900">Trip in progress</h2></div><p class="mt-2 text-sm text-orange-800">Live driver location is active.</p></section>
        @elseif($booking->booking_status === 'Accepted' && $trip->status === 'Scheduled')
            <section class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5"><h2 class="font-semibold text-green-900">Upcoming trip</h2><p data-trip-upcoming-departure class="mt-1 text-sm text-green-800">Your booking is confirmed for {{ $trip->departure_at->format('d M Y, g:i A') }}.</p></section>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]"><div class="space-y-6">
            <section class="rounded-2xl border border-gray-100 bg-white p-5"><h2 class="mb-4 text-sm font-semibold text-gray-900">Route and trip information</h2><dl class="grid gap-4 sm:grid-cols-2">@foreach([['map-pin','Departure',$trip->departure_location, 'data-trip-departure-location'],['map-pinned','Destination',$trip->destination, 'data-trip-destination'],['calendar','Departure',$trip->departure_at->format('d M Y, g:i A'), 'data-trip-departure-at'],['map-pin','Your pickup point',$booking->pickup_point, null],['route','Estimated distance',$trip->estimated_distance_km ? $trip->estimated_distance_km.' km' : '—', null],['clock','Estimated duration',$trip->estimated_duration_seconds ? ceil($trip->estimated_duration_seconds / 60).' min' : '—', null]] as [$icon,$label,$value,$attribute])<div><dt class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><x-icons.lucide :name="$icon" class="h-3.5 w-3.5" />{{ $label }}</dt><dd @if($attribute) {{ $attribute }} @endif class="mt-1 text-sm font-semibold text-gray-900">{{ $value }}</dd></div>@endforeach</dl></section>

            @if($canShowMap)<x-trip-static-map :trip="$trip" :bookings="$acceptedPickups" :route="$mapRoute" :live-location="$trip->latestLocation" :live-tracking="true" />@endif

            @if($trip->status === 'In Progress' && $booking->booking_status === 'Accepted')
                <section class="rounded-2xl border border-gray-100 bg-white p-5"><h2 class="mb-4 text-sm font-semibold text-gray-900">Journey progress</h2><ol class="space-y-4" data-pickup-progress>@forelse($acceptedPickups as $pickup)<li data-pickup-progress-item="{{ $pickup->id }}" class="flex items-center gap-3"><span data-pickup-progress-icon class="flex h-7 w-7 items-center justify-center rounded-full {{ $pickup->picked_up_at ? 'bg-green-600 text-white' : ($nextPickup?->is($pickup) ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500') }} text-xs font-bold">{{ $pickup->picked_up_at ? '✓' : ($nextPickup?->is($pickup) ? '→' : '○') }}</span><div><p class="text-sm font-medium text-slate-900">Pickup {{ $loop->iteration }}{{ $pickup->is($booking) ? ' (your pickup)' : '' }}</p><p data-pickup-progress-text class="text-xs text-slate-500">{{ $pickup->picked_up_at ? 'Picked up '.$pickup->picked_up_at->format('g:i A') : ($nextPickup?->is($pickup) ? 'Current / waiting' : 'Upcoming') }}</p></div></li>@empty<li class="text-sm text-slate-500">Pickup progress is not available yet.</li>@endforelse<li class="flex items-center gap-3"><span class="flex h-7 w-7 items-center justify-center rounded-full {{ $nextPickup ? 'bg-slate-100 text-slate-500' : 'bg-orange-100 text-orange-700' }} text-xs font-bold">○</span><div><p class="text-sm font-medium text-slate-900">Destination</p><p class="text-xs text-slate-500">{{ $nextPickup ? 'Upcoming' : 'Next stop' }}</p></div></li></ol></section>
                <x-trip-updates-panel :trip="$trip" role="passenger" />
                <x-active-trip-chat :booking="$booking" :other-user="$trip->user" />
                <x-emergency-panel :trip="$trip" />
            @endif

            @if($trip->status === 'Completed')
                <section class="rounded-2xl border border-gray-100 bg-white p-5"><h2 class="text-sm font-semibold text-gray-900">Completed trip</h2><p class="mt-2 text-sm text-slate-600">Completed {{ $trip->completed_at?->format('d M Y, g:i A') ?? '—' }}.</p>@if($booking->payment)<p class="mt-2 text-sm text-slate-600">Payment: {{ $booking->payment->payment_status }}</p>@endif</section>
            @endif
        </div>

        <aside class="space-y-6"><section class="rounded-2xl border border-gray-100 bg-white p-5"><h2 class="mb-4 text-sm font-semibold text-gray-900">Driver and vehicle</h2><p class="text-sm font-semibold text-gray-900">{{ $trip->user->name }}</p><p class="mt-1 text-xs text-gray-500">Driver</p><div class="mt-4 border-t border-gray-100 pt-4"><p data-trip-driver-vehicle class="text-sm font-semibold text-gray-900">{{ $trip->vehicle?->brand }} {{ $trip->vehicle?->model }}</p><p class="mt-1 font-mono text-xs text-gray-500">{{ $trip->vehicle?->plate_number ?? 'Vehicle details unavailable' }}</p></div></section>
            <section class="rounded-2xl border border-gray-100 bg-white p-5"><h2 class="mb-4 text-sm font-semibold text-gray-900">Booking information</h2><dl class="space-y-3 text-sm"><div><dt class="text-xs text-gray-500">Seats</dt><dd class="font-semibold text-gray-900">{{ $booking->number_of_seats }}</dd></div><div><dt class="text-xs text-gray-500">Luggage</dt><dd class="font-semibold text-gray-900">{{ $booking->number_of_luggage }}</dd></div><div><dt class="text-xs text-gray-500">Booking status</dt><dd class="font-semibold text-gray-900">{{ $booking->booking_status }}</dd></div><div><dt class="text-xs text-gray-500">Trip status</dt><dd class="font-semibold text-gray-900">{{ $trip->status }}</dd></div></dl></section>
            @if($booking->booking_status === 'Pending')<x-trip-confirmation name="cancel-booking-detail-{{ $booking->id }}" title="Cancel Booking Request?" message="Are you sure you want to cancel this booking request?" confirm-label="Confirm Cancel" :action="route('passenger.bookings.cancel', $booking)" variant="danger" class="w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold">Cancel booking request</x-trip-confirmation>@elseif($booking->booking_status === 'Accepted' && $trip->status === 'Completed' && ! $booking->payment?->isPaid())<a href="{{ route('payments.checkout', $booking) }}" class="block rounded-xl bg-green-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-green-700">Pay RM {{ number_format((float) $trip->price_per_passenger * $booking->number_of_seats, 2) }}</a>@elseif($booking->booking_status === 'Accepted' && $trip->status === 'Completed' && ! $booking->ratings->contains('reviewer_id', Auth::id()) && ! $trip->completed_at?->addDays(7)->isPast())<a href="{{ route('ratings.create', $booking) }}" class="block rounded-xl bg-green-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-green-700">Rate Driver</a>@endif
        </aside></div>
    </div></div>
@endsection
