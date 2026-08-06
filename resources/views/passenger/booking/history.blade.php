@extends('passenger.booking.layout')

@section('pageTitle', 'Booking History')

@section('content')
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
                                    <tr>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-gray-900">{{ $booking->trip->departure_location }} to {{ $booking->trip->destination }}</div>
                                            <div class="text-xs text-gray-500">{{ $booking->trip->user->name }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $booking->trip->departure_at->format('d M Y, g:i A') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $booking->number_of_seats }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $booking->pickup_point }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badge[$booking->booking_status] }}">{{ $booking->booking_status }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            @if($booking->booking_status === 'Pending')
                                                <form method="POST" action="{{ route('passenger.bookings.cancel', $booking) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">Cancel</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">No action</span>
                                            @endif
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
