<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8"><div class="mx-auto max-w-3xl"><a href="{{ Auth::id() === $user->id ? route('ratings.index') : route('ratings.people') }}" class="text-sm text-slate-500">← {{ Auth::id() === $user->id ? 'Back to Ratings' : 'Back to '.ucfirst($user->role).' Profiles' }}</a><div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center gap-4"><div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white">{{ strtoupper(substr($user->name,0,2)) }}</div><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ ucfirst($user->role) }} Profile & Reviews</p><h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $user->name }}</h1><div class="mt-2 flex items-center gap-2"><span class="text-xl text-amber-400">★★★★★</span><strong class="text-xl">{{ number_format($average,1) }}</strong><span class="text-sm text-slate-400">· {{ $reviewCount }} {{ Str::plural('review', $reviewCount) }}</span></div></div></div></div>
        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Rating breakdown">
            <h2 class="text-sm font-bold text-slate-900">Rating breakdown</h2>
            <div class="mt-4 space-y-2">
                @for($score = 5; $score >= 1; $score--)
                    @php($count = (int) ($breakdown[$score] ?? 0))
                    <div class="grid grid-cols-[52px_1fr_36px] items-center gap-3 text-xs">
                        <span class="font-medium text-slate-500">{{ $score }} star</span>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-amber-400" style="width: {{ $reviewCount > 0 ? ($count / $reviewCount) * 100 : 0 }}%"></div></div>
                        <span class="text-right text-slate-500">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </section>
        <form class="mt-6 flex flex-col gap-3 sm:flex-row"><input name="search" value="{{ request('search') }}" placeholder="Search reviews..." class="min-w-0 flex-1 rounded-xl border-slate-200 text-sm"><select name="sort" class="rounded-xl border-slate-200 text-sm"><option value="newest">Newest</option><option value="highest" @selected(request('sort')==='highest')>Highest</option><option value="lowest" @selected(request('sort')==='lowest')>Lowest</option></select><button class="rounded-xl bg-[#22C55E] px-5 py-2 text-sm font-semibold text-white">Apply</button></form>
        <div class="mt-5 space-y-3">@forelse($ratings as $rating)<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex justify-between gap-4"><div class="flex gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-500 text-xs font-bold text-white">{{ strtoupper(substr($rating->reviewer->name,0,2)) }}</div><div><div class="font-semibold">{{ $rating->reviewer->name }}</div><div class="text-amber-400">{{ str_repeat('★',$rating->score) }}<span class="text-slate-200">{{ str_repeat('★',5-$rating->score) }}</span></div>@if($rating->comment)<p class="mt-2 text-sm text-slate-600">{{ $rating->comment }}</p>@endif</div></div><time class="shrink-0 text-xs text-slate-400">{{ $rating->created_at->format('j M Y') }}</time></div></article>@empty<div class="rounded-2xl bg-white py-14 text-center text-sm text-slate-500">No reviews yet.</div>@endforelse</div><div class="mt-6">{{ $ratings->links() }}</div></div></div>
</x-ratings.shell>
