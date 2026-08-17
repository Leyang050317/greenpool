<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('ratings.index') }}" class="mb-6 inline-flex text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Ratings</a>
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Rate a Trip</h1>
                <p class="mt-1 text-sm text-slate-400">Completed trips waiting for your rating.</p>
            </div>

            <div class="space-y-3">
                @forelse($bookings as $booking)
                    @php
                        $reviewee = Auth::user()->role === 'passenger' ? $booking->trip->user : $booking->passenger;
                        $ratingExpired = $booking->trip->completed_at?->addDays(7)->isPast() ?? false;
                    @endphp
                    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white">
                            {{ strtoupper(substr($reviewee->name, 0, 2)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-slate-900">{{ $reviewee->name }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $booking->trip->departure_location }} to {{ $booking->trip->destination }}</div>
                            <div class="mt-1 text-xs text-slate-400">Completed {{ $booking->trip->completed_at->format('j M Y') }}</div>
                        </div>
                        @if($ratingExpired)
                            <span class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-500"><x-icons.lucide name="lock" class="h-4 w-4" /> Expired</span>
                        @else
                            <a href="{{ route('ratings.create', $booking) }}" class="rounded-xl bg-[#22C55E] px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-green-600">Rate {{ ucfirst($reviewee->role) }}</a>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center">
                        <p class="font-semibold text-slate-700">No trips waiting for a rating.</p>
                        <p class="mt-1 text-sm text-slate-400">Accepted bookings appear here after the trip is completed.</p>
                    </div>
                @endforelse
            </div>
            <div class="mt-6">{{ $bookings->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
