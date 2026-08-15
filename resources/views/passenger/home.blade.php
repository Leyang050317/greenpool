@extends('passenger.booking.layout')

@section('pageTitle', 'Dashboard')

@section('content')
    <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] px-4 py-8 sm:px-8">
        <div class="mx-auto max-w-7xl">
            <h1 class="text-3xl font-bold text-gray-800">
                Welcome, {{ Auth::user()->name }}
            </h1>

            <p class="mt-2 text-gray-600">
                Welcome back to GreenPool.
            </p>

            @if($pendingRatingBooking)
                <section class="mt-8 overflow-hidden rounded-2xl border border-green-200 bg-white shadow-sm" aria-labelledby="pending-rating-title">
                    <div class="bg-green-50 px-5 py-4 sm:px-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Trip completed</p>
                        <h2 id="pending-rating-title" class="mt-1 text-xl font-bold text-slate-900">How was your ride?</h2>
                    </div>
                    <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-bold text-white">
                            {{ strtoupper(substr($pendingRatingBooking->trip->user->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-slate-900">{{ $pendingRatingBooking->trip->user->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $pendingRatingBooking->trip->departure_location }} to {{ $pendingRatingBooking->trip->destination }}</p>
                            <p class="mt-2 text-2xl tracking-wide text-amber-400" aria-hidden="true">★★★★★</p>
                        </div>
                        <a href="{{ route('ratings.create', $pendingRatingBooking) }}" class="w-full rounded-xl bg-[#22C55E] px-5 py-3 text-center text-sm font-bold text-white hover:bg-green-600 sm:w-auto">Rate Now</a>
                    </div>
                </section>
            @endif

            <div class="mt-10 grid grid-cols-1 gap-6 md:grid-cols-2">
                <a href="{{ route('passenger.bookings.history') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-green-200 hover:bg-green-50/30">
                    <h2 class="text-lg font-semibold text-gray-900">My Booking</h2>
                    <p class="mt-2 text-gray-500">View your booking requests.</p>
                </a>

                <a href="{{ route('passenger.booking') }}" class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-green-200 hover:bg-green-50/30">
                    <h2 class="text-lg font-semibold text-gray-900">Upcoming Trip</h2>
                    <p class="mt-2 text-gray-500">Find an available ride for your next trip.</p>
                </a>
            </div>
        </div>
    </div>
@endsection
