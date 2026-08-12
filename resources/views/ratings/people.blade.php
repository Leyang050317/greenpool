<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('ratings.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Ratings</a>
            <div class="mt-6"><h1 class="text-2xl font-bold text-slate-900">{{ ucfirst($targetRole) }} Profiles</h1><p class="mt-1 text-sm text-slate-400">View profiles and previous reviews from your accepted bookings.</p></div>
            <form method="GET" class="mt-6"><input name="search" value="{{ request('search') }}" placeholder="Search by user name..." class="w-full rounded-xl border-slate-200 text-sm focus:border-green-500 focus:ring-green-500"></form>
            <div class="mt-5 space-y-3">
                @forelse($people as $person)
                    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white">{{ strtoupper(substr($person->name,0,2)) }}</div><div class="min-w-0 flex-1"><h2 class="font-semibold text-slate-900">{{ $person->name }}</h2><p class="mt-1 text-sm text-slate-500"><span class="text-amber-400">★</span> {{ number_format((float) $person->ratings_received_avg_score, 1) }} · {{ $person->ratings_received_count }} reviews</p></div><a href="{{ route('ratings.received', $person) }}" class="w-full rounded-xl border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:w-auto">View Reviews</a></article>
                @empty<div class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">No {{ $targetRole }} profiles are available yet.</div>@endforelse
            </div><div class="mt-6">{{ $people->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
