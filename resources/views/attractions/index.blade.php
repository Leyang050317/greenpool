<x-app-layout>
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-[1200px] gap-1 px-5 sm:px-8">
            <a href="{{ route('attractions.index') }}" class="-mb-px inline-flex items-center border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'discover' ? 'border-green-600 text-green-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                Discover
            </a>
            <a href="{{ route('attractions.index', ['tab' => 'favourites']) }}" class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'favourites' ? 'border-green-600 text-green-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
                Favourites
                @if ($favouritesCount > 0)
                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] {{ $tab === 'favourites' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $favouritesCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <main class="mx-auto max-w-[1200px] px-5 py-8 sm:px-8">
        <div class="mb-6">
            <h1 class="text-[22px] font-bold tracking-tight text-slate-900">{{ $tab === 'favourites' ? 'Favourite Attractions' : 'Explore Malaysia' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $tab === 'favourites' ? "Places you've saved for later." : 'Discover places worth visiting across Malaysia.' }}</p>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('attractions.index') }}" class="mb-5 max-w-[480px]" x-data="attractionSearch({{ Js::from(route('attractions.autocomplete')) }}, {{ Js::from(route('attractions.google-place')) }})">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="state" value="{{ $state }}">
            <label for="search" class="sr-only">Search attractions</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><x-icons.lucide name="search" /></span>
                <input id="search" name="search" value="{{ $search }}" placeholder="Search Malaysian attractions..." @input="searchGoogle()" @focus="open = true" @keydown.escape="open = false" autocomplete="off" class="block w-full rounded-[10px] border-slate-200 py-2.5 pl-10 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-green-600 focus:ring-2 focus:ring-green-100">
                @if ($search !== '')
                    <a href="{{ route('attractions.index', ['tab' => $tab, 'state' => $state ?: null]) }}" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700" aria-label="Clear search"><x-icons.lucide name="x" /></a>
                @endif
                <div x-cloak x-show="open && suggestions.length" class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    <template x-for="suggestion in suggestions" :key="suggestion.place_id">
                        <button type="button" @click="openPlace(suggestion)" class="block w-full px-3.5 py-2.5 text-left text-sm text-slate-700 hover:bg-green-50 hover:text-green-800" x-text="suggestion.text"></button>
                    </template>
                </div>
            </div>
            <p class="mt-1.5 text-xs text-slate-400">Search saved places or choose a Google suggestion to explore any Malaysian attraction.</p>
        </form>

        <section class="mb-8">
            <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-400">Explore by State</p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('attractions.index', ['tab' => $tab, 'search' => $search ?: null]) }}" class="rounded-full border px-3.5 py-1.5 text-[13px] font-medium transition {{ $state === '' ? 'border-green-600 bg-green-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-green-300 hover:text-green-700' }}">All States</a>
                @foreach ($quickStates as $availableState)
                    <a href="{{ route('attractions.index', ['tab' => $tab, 'search' => $search ?: null, 'state' => $availableState]) }}" class="rounded-full border px-3.5 py-1.5 text-[13px] font-medium transition {{ $state === $availableState ? 'border-green-600 bg-green-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-green-300 hover:text-green-700' }}">{{ $availableState }}</a>
                @endforeach
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-[13px] font-medium text-slate-600 hover:border-green-300 hover:text-green-700">More States</summary>
                    <div class="absolute left-0 z-20 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        @foreach ($moreStates as $availableState)
                            <a href="{{ route('attractions.index', ['tab' => $tab, 'search' => $search ?: null, 'state' => $availableState]) }}" class="block px-4 py-2 text-sm {{ $state === $availableState ? 'bg-green-50 font-semibold text-green-700' : 'text-slate-700 hover:bg-slate-50' }}">{{ $availableState }}</a>
                        @endforeach
                    </div>
                </details>
            </div>
            @if ($state !== '')
                <div class="mt-2.5 flex items-center gap-2 text-[13px] text-slate-500">
                    <span>Filtering: <span class="font-semibold text-green-700">{{ $state }}</span></span>
                    <a href="{{ route('attractions.index', ['tab' => $tab, 'search' => $search ?: null]) }}" class="text-slate-400 underline hover:text-slate-700">Clear filter</a>
                </div>
            @endif
        </section>

        @if ($tab === 'discover' && $search === '' && $state === '' && $featured->isNotEmpty())
            <section class="mb-9">
                <h2 class="mb-4 text-[15px] font-semibold text-slate-900">Featured Attractions</h2>
                <div class="grid gap-3 lg:grid-cols-[2fr_1fr]">
                    @php($primaryFeatured = $featured->first())
                    @php($primaryFeaturedFallback = $primaryFeatured->displayImageUrl())
                    <article class="group relative min-h-[390px] overflow-hidden rounded-[14px] bg-slate-200 lg:row-span-2">
                        <a href="{{ route('attractions.show', $primaryFeatured) }}" class="block h-full">
                            <img src="{{ $googleAutoCards && ! $primaryFeatured->image_url ? route('attractions.google-card-photo', $primaryFeatured) : $primaryFeaturedFallback }}" data-fallback="{{ $primaryFeaturedFallback }}" onerror="this.src = this.dataset.fallback" alt="{{ $primaryFeatured->attraction_name }}" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/75 via-slate-900/25 to-transparent p-5 pt-20 text-white">
                                <span class="rounded-md bg-white/20 px-2 py-0.5 text-[11px] font-medium backdrop-blur">{{ $primaryFeatured->category }}</span>
                                <h3 class="mt-2 text-lg font-semibold">{{ $primaryFeatured->attraction_name }}</h3>
                                <p class="mt-1 inline-flex items-center gap-1 text-[13px] text-white/80"><x-icons.lucide name="map-pin" />{{ $primaryFeatured->state }}</p>
                            </div>
                        </a>
                        <form method="POST" action="{{ $primaryFeatured->is_favourited ? route('attractions.favourites.destroy', $primaryFeatured) : route('attractions.favourites.store', $primaryFeatured) }}" class="absolute right-3 top-3">
                            @csrf
                            @if ($primaryFeatured->is_favourited) @method('DELETE') @endif
                            <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-full {{ $primaryFeatured->is_favourited ? 'bg-red-100 text-red-500' : 'bg-white/90 text-slate-400' }}"><span class="text-xl">{{ $primaryFeatured->is_favourited ? '♥' : '♡' }}</span></button>
                        </form>
                    </article>
                    @foreach ($featured->skip(1) as $attraction)
                        @php($featuredFallback = $attraction->displayImageUrl())
                        <article class="group relative min-h-[189px] overflow-hidden rounded-[14px] bg-slate-200">
                            <a href="{{ route('attractions.show', $attraction) }}" class="block h-full">
                                <img src="{{ $googleAutoCards && ! $attraction->image_url ? route('attractions.google-card-photo', $attraction) : $featuredFallback }}" data-fallback="{{ $featuredFallback }}" onerror="this.src = this.dataset.fallback" alt="{{ $attraction->attraction_name }}" class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/75 via-slate-900/25 to-transparent p-4 pt-16 text-white">
                                    <span class="rounded-md bg-white/20 px-2 py-0.5 text-[11px] font-medium backdrop-blur">{{ $attraction->category }}</span>
                                    <h3 class="mt-2 text-[15px] font-semibold">{{ $attraction->attraction_name }}</h3>
                                    <p class="mt-1 inline-flex items-center gap-1 text-xs text-white/80"><x-icons.lucide name="map-pin" />{{ $attraction->state }}</p>
                                </div>
                            </a>
                            <form method="POST" action="{{ $attraction->is_favourited ? route('attractions.favourites.destroy', $attraction) : route('attractions.favourites.store', $attraction) }}" class="absolute right-3 top-3">
                                @csrf
                                @if ($attraction->is_favourited) @method('DELETE') @endif
                                <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-full {{ $attraction->is_favourited ? 'bg-red-100 text-red-500' : 'bg-white/90 text-slate-400' }}"><span class="text-lg">{{ $attraction->is_favourited ? '♥' : '♡' }}</span></button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-[15px] font-semibold text-slate-900">
                    @if ($tab === 'favourites')
                        Your saved places
                    @elseif ($search !== '')
                        Search Results
                    @elseif ($state !== '')
                        Attractions in {{ $state }}
                    @else
                        Discover Attractions
                    @endif
                </h2>
                @if ($attractions->total() > 0)
                    <span class="text-[13px] text-slate-400">{{ $attractions->total() }} {{ $attractions->total() === 1 ? 'place' : 'places' }}</span>
                @endif
            </div>

            @if ($attractions->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <p class="font-semibold text-slate-800">{{ $tab === 'favourites' ? 'No favourite attractions yet.' : 'No attractions found.' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $tab === 'favourites' ? 'Save places you would like to visit and they will appear here.' : 'Try another search term or state.' }}</p>
                    @if ($tab === 'favourites')
                        <a href="{{ route('attractions.index') }}" class="mt-5 inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Explore attractions</a>
                    @endif
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($attractions as $attraction)
                        @php($cardFallback = $attraction->displayImageUrl())
                        <article class="group overflow-hidden rounded-xl border border-slate-100 bg-white transition duration-200 hover:border-slate-200 hover:shadow-[0_4px_16px_rgba(0,0,0,0.08)]">
                            <div class="relative aspect-[4/3] overflow-hidden bg-slate-200">
                                <a href="{{ route('attractions.show', $attraction) }}" class="block h-full w-full">
                                    <img src="{{ $googleAutoCards && ! $attraction->image_url ? route('attractions.google-card-photo', $attraction) : $cardFallback }}" data-fallback="{{ $cardFallback }}" onerror="this.src = this.dataset.fallback" alt="{{ $attraction->attraction_name }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                                </a>
                                <form method="POST" action="{{ $attraction->is_favourited ? route('attractions.favourites.destroy', $attraction) : route('attractions.favourites.store', $attraction) }}" class="absolute right-2.5 top-2.5">
                                    @csrf
                                    @if ($attraction->is_favourited) @method('DELETE') @endif
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-full {{ $attraction->is_favourited ? 'bg-red-100 text-red-500' : 'bg-white/90 text-slate-400 hover:bg-red-50 hover:text-red-500' }}" aria-label="{{ $attraction->is_favourited ? 'Remove from favourites' : 'Save to favourites' }}">
                                        <span class="text-base leading-none">{{ $attraction->is_favourited ? '♥' : '♡' }}</span>
                                    </button>
                                </form>
                            </div>
                            <div class="p-4">
                                <h3 class="text-[15px] font-semibold leading-snug text-slate-900"><a href="{{ route('attractions.show', $attraction) }}" class="hover:text-green-700">{{ $attraction->attraction_name }}</a></h3>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1"><x-icons.lucide name="map-pin" />{{ $attraction->state }}</span>
                                    <span class="text-slate-300">·</span>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">{{ $attraction->category }}</span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-[13px] leading-relaxed text-slate-500">{{ $attraction->description ?: 'Discover this destination on your next GreenPool journey.' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-7">{{ $attractions->links() }}</div>
            @endif
        </section>
        <p class="mt-8 text-xs text-slate-400">Explore Malaysian attractions and load live Google details when needed.</p>
    </main>

    <script>
        window.attractionSearch = (endpoint, placeEndpoint) => ({
            timer: null,
            open: false,
            suggestions: [],
            searchGoogle() {
                const input = this.$root.querySelector('#search').value.trim();
                this.suggestions = [];
                this.open = true;
                clearTimeout(this.timer);
                if (input.length < 2) return;
                this.timer = setTimeout(async () => {
                    try {
                        const response = await window.axios.get(endpoint, { params: { input } });
                        this.suggestions = response.data.data || [];
                    } catch (_) {
                        this.suggestions = [];
                    }
                }, 350);
            },
            openPlace(suggestion) {
                window.location.assign(`${placeEndpoint}?${new URLSearchParams({ place_id: suggestion.place_id })}`);
            },
        });
    </script>
</x-app-layout>
