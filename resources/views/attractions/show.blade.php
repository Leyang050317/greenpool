<x-app-layout>
    <main class="mx-auto max-w-[820px] px-5 py-8 sm:px-8">
        <a href="{{ route('attractions.index') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-slate-500 transition hover:text-slate-900">
            <x-icons.lucide name="arrow-left" /> Back to Explore Malaysia
        </a>

        @if (session('success'))
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
        @endif

        <article class="mt-6">
            <div class="aspect-[16/7] overflow-hidden rounded-2xl bg-slate-200">
                @if ($attraction->image_url)
                    <img src="{{ $attraction->image_url }}" alt="{{ $attraction->attraction_name }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-green-100 to-emerald-200 text-green-700"><x-icons.lucide name="map-pinned" /></span>
                @endif
            </div>

            <div class="mt-7 flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">{{ $attraction->category }}</span>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $attraction->attraction_name }}</h1>
                    <p class="mt-2 inline-flex items-center gap-1.5 text-[13px] text-slate-500"><x-icons.lucide name="map-pin" />{{ $attraction->location ?: $attraction->state }}</p>
                </div>
                <form method="POST" action="{{ $isFavourited ? route('attractions.favourites.destroy', $attraction) : route('attractions.favourites.store', $attraction) }}">
                    @csrf
                    @if ($isFavourited) @method('DELETE') @endif
                    <button type="submit" class="inline-flex items-center gap-2 rounded-[10px] border px-4 py-2.5 text-[13px] font-semibold transition {{ $isFavourited ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100' : 'border-slate-200 bg-white text-slate-700 hover:border-green-200 hover:text-green-700' }}">
                        <span class="text-base leading-none">{{ $isFavourited ? '♥' : '♡' }}</span>{{ $isFavourited ? 'Saved' : 'Save to Favourites' }}
                    </button>
                </form>
            </div>

            <section class="mt-7">
                <h2 class="text-sm font-semibold text-slate-900">About</h2>
                <p class="mt-2.5 whitespace-pre-line text-sm leading-[1.65] text-slate-600">{{ $attraction->description ?: 'Details for this attraction will be added as curated information becomes available.' }}</p>
            </section>

            <section class="mt-7">
                <h2 class="text-sm font-semibold text-slate-900">Essential Information</h2>
                <div class="mt-3 grid gap-2.5 sm:grid-cols-2">
                    <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">Opening Hours</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->opening_hours }}</p></div>
                    <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">Entrance Fee</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->entrance_fee }}</p></div>
                    <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">State</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->state }}</p></div>
                    <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">Category</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->category }}</p></div>
                    @if ($attraction->contact)
                        <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">Contact</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->contact }}</p></div>
                    @endif
                    @if ($attraction->website)
                        <div class="rounded-[10px] border border-slate-100 bg-slate-50 px-4 py-3"><p class="text-[11px] font-medium text-slate-400">Website</p><p class="mt-1 text-sm font-medium text-slate-900">{{ $attraction->website }}</p></div>
                    @endif
                </div>
            </section>

            <section class="mt-7 flex flex-col justify-between gap-5 rounded-[14px] border border-green-200 bg-green-50 px-5 py-[18px] sm:flex-row sm:items-center">
                <div>
                    <h2 class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900"><x-icons.lucide name="car" /> Find a Ride</h2>
                    <p class="mt-1 text-[13px] text-slate-500">See available GreenPool trips heading to this destination.</p>
                </div>
                <a href="{{ route('passenger.booking') }}" class="inline-flex items-center justify-center gap-1.5 rounded-[9px] bg-green-600 px-[18px] py-2.5 text-[13px] font-semibold text-white transition hover:bg-green-700">Find a Ride <x-icons.lucide name="arrow-up-right" /></a>
            </section>
        </article>
    </main>
</x-app-layout>
