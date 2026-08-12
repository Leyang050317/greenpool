<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('ratings.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Ratings</a>
            <div class="mt-6"><h1 class="text-2xl font-bold text-slate-900">{{ ucfirst($targetRole) }} Reviews</h1><p class="mt-1 text-sm text-slate-400">Browse feedback received by {{ $targetRole }}s from your completed rides.</p></div>
            <form method="GET" class="mt-6 flex flex-col gap-3 sm:flex-row"><input name="search" value="{{ request('search') }}" placeholder="Search reviews or user names..." class="min-w-0 flex-1 rounded-xl border-slate-200 text-sm focus:border-green-500 focus:ring-green-500"><select name="sort" class="rounded-xl border-slate-200 text-sm"><option value="newest">Newest</option><option value="highest" @selected(request('sort')==='highest')>Highest</option><option value="lowest" @selected(request('sort')==='lowest')>Lowest</option></select><button class="rounded-xl bg-[#22C55E] px-5 py-2.5 text-sm font-semibold text-white">Apply</button></form>
            <div class="mt-5 space-y-3">
                @forelse($ratings as $rating)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-col gap-4 sm:flex-row"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white">{{ strtoupper(substr($rating->reviewee->name,0,2)) }}</div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center justify-between gap-2"><div><span class="font-semibold text-slate-900">{{ $rating->reviewee->name }}</span><span class="ml-2 text-xs text-slate-400">reviewed by {{ $rating->reviewer->name }}</span></div><time class="text-xs text-slate-400">{{ $rating->created_at->format('j M Y') }}</time></div><div class="mt-1 text-amber-400">{{ str_repeat('★',$rating->score) }}<span class="text-slate-200">{{ str_repeat('★',5-$rating->score) }}</span></div>@if($rating->comment)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $rating->comment }}</p>@endif</div></div></article>
                @empty<div class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">No {{ $targetRole }} reviews are available yet.</div>@endforelse
            </div><div class="mt-6">{{ $ratings->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
