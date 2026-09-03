<x-ratings.shell>
    @php
        $target = ucfirst($targetRole);
        $cards = [
            ['title' => 'Rate a '.$target, 'description' => 'Submit a '.$targetRole.' rating', 'icon' => 'star', 'iconClass' => 'bg-[#22C55E]', 'href' => route('ratings.pending')],
            ['title' => 'Rating History', 'description' => 'All ratings you submitted', 'icon' => 'clock', 'iconClass' => 'bg-slate-500', 'href' => route('ratings.history')],
            ['title' => $target.' Profiles & Reviews', 'description' => 'View profiles, ratings, and reviews', 'icon' => $targetRole === 'driver' ? 'car-front' : 'user-circle', 'iconClass' => 'bg-green-600', 'href' => route('ratings.people')],
        ];
    @endphp
    <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] px-5 py-8 sm:px-8 lg:py-10">
        <div class="mx-auto max-w-5xl">
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#176B32] via-[#16803C] to-[#22A447] px-6 py-8 text-white shadow-lg shadow-green-900/10 sm:px-8 sm:py-10">
                <div class="absolute -right-14 -top-16 h-52 w-52 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-24 right-24 h-48 w-48 rounded-full bg-lime-300/10"></div>
                <div class="relative max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-green-50 ring-1 ring-white/20">
                        <x-icons.lucide name="shield-check" class="h-3.5 w-3.5" />
                        Community trust
                    </span>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Ratings & Reviews</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-green-50/90 sm:text-base">Share your trip experience, keep track of your feedback, and make confident choices with trusted community reviews.</p>
                </div>
                <div class="absolute right-8 top-1/2 hidden -translate-y-1/2 items-center justify-center lg:flex">
                    <div class="flex h-28 w-28 rotate-6 items-center justify-center rounded-3xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                        <x-icons.lucide name="star" class="h-14 w-14 fill-current text-yellow-300" />
                    </div>
                </div>
            </section>

            <div class="mb-4 mt-8 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-green-700">Rating centre</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">What would you like to do?</h2>
                </div>
                <p class="hidden text-sm text-slate-400 sm:block">Choose an option to continue</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($cards as $card)
                    <a href="{{ $card['href'] }}" class="group relative overflow-hidden rounded-2xl border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2 {{ $loop->first ? 'border-green-600 bg-gradient-to-br from-green-600 to-green-700 text-white md:col-span-2' : 'border-slate-200 bg-white text-slate-900 hover:border-green-300' }}">
                        @if($loop->first)
                            <div class="absolute -right-10 -top-14 h-40 w-40 rounded-full bg-white/10"></div>
                        @endif
                        <div class="relative flex items-center gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $loop->first ? 'bg-white/15 text-white ring-1 ring-white/20' : $card['iconClass'].' text-white' }}"><x-icons.lucide :name="$card['icon']" class="h-6 w-6" /></span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold {{ $loop->first ? 'text-white' : 'text-slate-900' }}">{{ $card['title'] }}</h3>
                                <p class="mt-1 text-sm {{ $loop->first ? 'text-green-50/90' : 'text-slate-500' }}">{{ $card['description'] }}</p>
                            </div>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition group-hover:translate-x-1 {{ $loop->first ? 'bg-white/15 text-white' : 'bg-slate-50 text-slate-400 group-hover:bg-green-50 group-hover:text-green-700' }}">
                                <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-ratings.shell>
