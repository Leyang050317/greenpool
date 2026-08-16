<x-ratings.shell>
    @php
        $target = ucfirst($targetRole);
        $cards = [
            ['title' => 'Rate a '.$target, 'description' => 'Submit a '.$targetRole.' rating', 'icon' => 'star', 'iconClass' => 'bg-[#22C55E]', 'href' => route('ratings.pending')],
            ['title' => 'Rating History', 'description' => 'All ratings you submitted', 'icon' => 'clock', 'iconClass' => 'bg-slate-500', 'href' => route('ratings.history')],
            ['title' => $target.' Reviews', 'description' => 'Browse reviews for '.$targetRole.'s', 'icon' => 'message-square', 'iconClass' => 'bg-violet-500', 'href' => route('ratings.reviews')],
            ['title' => $target.' Profile', 'description' => 'View '.$targetRole.' profiles and ratings', 'icon' => $targetRole === 'driver' ? 'car-front' : 'user-circle', 'iconClass' => 'bg-green-600', 'href' => route('ratings.people')],
        ];
    @endphp
    <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] px-5 py-8 sm:px-8">
        <div class="mx-auto max-w-[640px]">
            <h1 class="text-2xl font-bold text-slate-900">Ratings</h1>
            <p class="mt-1 text-sm text-slate-400">Rate {{ $targetRole }}s, view reviews, and track your rating history.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach($cards as $card)
                    <a href="{{ $card['href'] }}" class="group min-h-[136px] rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-green-300 hover:bg-green-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl text-white {{ $card['iconClass'] }}"><x-icons.lucide :name="$card['icon']" class="h-6 w-6" /></span>
                        <h2 class="mt-3 text-sm font-bold text-slate-900">{{ $card['title'] }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $card['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-ratings.shell>
