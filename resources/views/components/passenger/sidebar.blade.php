@php
    $navigation = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => route('passenger.home'), 'active' => request()->routeIs('passenger.home')],
        ['label' => 'Find a Ride', 'icon' => 'search', 'href' => route('passenger.booking'), 'active' => request()->routeIs('passenger.booking', 'passenger.bookings.create')],
        ['label' => 'My Bookings', 'icon' => 'clipboard-list', 'href' => route('passenger.bookings.history'), 'active' => request()->routeIs('passenger.bookings.history')],
        ['label' => 'Tourist Attractions', 'icon' => 'map-pinned', 'href' => route('attractions.index'), 'active' => request()->routeIs('attractions.*')],
        ['label' => 'Notifications', 'icon' => 'bell', 'href' => '#', 'active' => false, 'unread' => true],
        ['label' => 'Profile', 'icon' => 'user-circle', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.edit')],
        ['label' => 'Ratings', 'icon' => 'star', 'href' => route('ratings.index'), 'active' => request()->routeIs('ratings.*')],
    ];

    $supportNavigation = [
        ['label' => 'Settings', 'icon' => 'settings'],
        ['label' => 'Help', 'icon' => 'circle-help'],
    ];

    $initials = collect(preg_split('/\s+/', trim(Auth::user()->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<aside {{ $attributes->merge(['class' => 'flex h-full w-full flex-col border-r border-slate-200 bg-white text-slate-800']) }} aria-label="Passenger navigation">
    <div class="flex h-[84px] shrink-0 items-center border-b border-slate-100 px-4">
        <a href="{{ route('passenger.home') }}" class="flex min-w-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2" aria-label="GreenPool dashboard">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#2E7D32] text-white">
                <x-icons.lucide name="car" class="h-7 w-7" />
            </span>
            <span class="ml-4 whitespace-nowrap text-xl font-semibold text-[#1F2D3D]">GreenPool</span>
        </a>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" aria-label="Primary">
        <ul class="space-y-1.5">
            @foreach ($navigation as $item)
                <li>
                    <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif class="group relative flex min-h-[52px] items-center rounded-xl px-3 text-[15px] font-medium transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2 {{ $item['active'] ? 'bg-green-50 text-[#2E7D32]' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">
                        <span class="relative flex h-7 w-7 shrink-0 items-center justify-center {{ $item['active'] ? 'text-[#2E7D32]' : 'text-slate-400 group-hover:text-slate-600' }}">
                            <x-icons.lucide :name="$item['icon']" />
                            @if ($item['active'])<span class="absolute -left-3 h-6 w-1 rounded-r-full bg-[#2E7D32]" aria-hidden="true"></span>@endif
                            @if ($item['unread'] ?? false)<span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-[#2E7D32] ring-2 ring-white" aria-hidden="true"></span>@endif
                        </span>
                        <span class="ml-3 whitespace-nowrap">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="shrink-0 border-t border-slate-100 px-3 py-3">
        <nav aria-label="Support">
            <ul class="space-y-1">
                @foreach ($supportNavigation as $item)
                    <li><a href="#" class="group flex min-h-[48px] items-center rounded-xl px-3 text-[15px] font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"><span class="flex h-7 w-7 shrink-0 items-center justify-center text-slate-400 group-hover:text-slate-600"><x-icons.lucide :name="$item['icon']" /></span><span class="ml-3 whitespace-nowrap">{{ $item['label'] }}</span></a></li>
                @endforeach
            </ul>
        </nav>
        <a href="{{ route('profile.edit') }}" class="flex min-h-[64px] items-center rounded-xl border border-slate-200 bg-slate-50 p-2.5 transition hover:border-green-200 hover:bg-green-50" aria-label="View passenger profile for {{ Auth::user()->name }}">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-semibold text-white">{{ $initials ?: 'P' }}</span>
            <span class="ml-3 min-w-0"><span class="block truncate text-sm font-semibold text-[#1F2D3D]">{{ Auth::user()->name }}</span><span class="mt-0.5 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-[#2E7D32]">Passenger</span></span>
        </a>
    </div>
</aside>
