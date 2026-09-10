<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('ratings.index') }}" class="mb-6 inline-flex text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Ratings</a>
            <div class="mb-6"><h1 class="text-2xl font-bold text-slate-900">Rating History</h1><p class="mt-1 text-sm text-slate-400">All ratings you have submitted.</p></div>
            @if(session('success'))<div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
            <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row">
                <input name="search" value="{{ request('search') }}" placeholder="Search by user name..." class="min-w-0 flex-1 rounded-xl border-slate-200 text-sm focus:border-green-500 focus:ring-green-500">
                <select name="sort" class="rounded-xl border-slate-200 text-sm"><option value="newest">Newest first</option><option value="oldest" @selected(request('sort')==='oldest')>Oldest first</option></select>
                <button class="rounded-xl bg-[#22C55E] px-5 py-2 text-sm font-semibold text-white">Apply</button>
            </form>
            <div class="mb-5 grid gap-4 sm:grid-cols-2">
                @foreach([['Total Ratings',$stats['total']],['Editable Ratings',$stats['editable']]] as [$label,$value])
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm"><div class="text-2xl font-bold text-slate-900">{{ $value }}</div><div class="mt-1 text-xs text-slate-400">{{ $label }}</div></div>
                @endforeach
            </div>
            <div class="space-y-3">
                @forelse($ratings as $rating)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-500 text-sm font-bold text-white">{{ strtoupper(substr($rating->reviewee->name,0,2)) }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2"><div><span class="font-semibold text-slate-900">{{ $rating->reviewee->name }}</span><span class="ml-2 rounded-full bg-green-50 px-2 py-1 text-[11px] font-semibold text-green-700">{{ ucfirst($rating->reviewee->role) }}</span></div><span class="text-xs text-slate-400">Trip: {{ $rating->booking->trip->departure_at->format('j M Y') }}</span></div>
                                <div class="mt-1 text-lg leading-none text-amber-400" aria-label="{{ $rating->score }} out of 5">{{ str_repeat('★',$rating->score) }}<span class="text-slate-200">{{ str_repeat('★',5-$rating->score) }}</span></div>
                                @if($rating->comment)<p class="mt-2 text-sm text-slate-600">{{ $rating->comment }}</p>@endif
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <p class="text-xs text-slate-400">Submitted {{ $rating->created_at->format('j M Y') }}</p>
                                    @if($rating->isEditable())
                                        <div class="flex items-center gap-3">
                                            <span class="hidden text-right text-[11px] leading-4 text-slate-400 sm:block">Editable until<br>{{ $rating->editableUntil()?->format('j M Y') }}</span>
                                            <a href="{{ route('ratings.edit', $rating) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-green-200 bg-green-50 px-3.5 py-2 text-xs font-semibold text-green-700 transition hover:border-green-300 hover:bg-green-100"><x-icons.lucide name="pencil" class="h-3.5 w-3.5" /> Edit Rating</a>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-2.5 rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100/80 px-3.5 py-2 shadow-sm" title="The 7-day editing period ended on {{ $rating->editableUntil()?->format('j M Y, g:i A') }}">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-200"><x-icons.lucide name="lock-keyhole" class="h-3.5 w-3.5" /></span>
                                            <span class="text-left leading-tight"><span class="block text-xs font-bold text-slate-600">Locked</span><span class="mt-0.5 block text-[10px] font-medium text-slate-400">7-day edit period ended</span></span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">You have not submitted any ratings yet.</div>
                @endforelse
            </div>
            <div class="mt-6">{{ $ratings->links() }}</div>
        </div>
    </div>
</x-ratings.shell>
