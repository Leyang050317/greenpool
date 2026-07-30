@php
    $mainNavigation = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => route('driver.home'), 'active' => request()->routeIs('driver.home')],
        ['label' => 'My Trips', 'icon' => 'route', 'href' => '#', 'active' => request()->routeIs('driver.trips.*')],
        ['label' => 'Booking Requests', 'icon' => 'clipboard-list', 'href' => '#', 'active' => request()->routeIs('driver.booking-requests.*')],
        ['label' => 'My Vehicles', 'icon' => 'car-front', 'href' => route('driver.vehicles.index'), 'active' => request()->routeIs('driver.vehicles.*')],
        ['label' => 'Tourist Attractions', 'icon' => 'map-pinned', 'href' => '#', 'active' => request()->routeIs('driver.attractions.*')],
        ['label' => 'Notifications', 'icon' => 'bell', 'href' => '#', 'active' => request()->routeIs('driver.notifications.*'), 'unread' => true],
        ['label' => 'Profile', 'icon' => 'user-circle', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.edit')],
    ];

    $bottomNavigation = [
        ['label' => 'Settings', 'icon' => 'settings', 'href' => '#', 'active' => request()->routeIs('driver.settings.*')],
        ['label' => 'Help', 'icon' => 'circle-help', 'href' => '#', 'active' => request()->routeIs('driver.help.*')],
    ];

    $initials = collect(preg_split('/\s+/', trim(Auth::user()->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<aside
    {{ $attributes->merge(['class' => 'flex h-full flex-col border-r border-slate-200 bg-white text-slate-800']) }}
    aria-label="Driver navigation"
>
    <div class="flex h-[84px] shrink-0 items-center border-b border-slate-100 px-4">
        <a
            href="{{ route('driver.home') }}"
            class="flex min-w-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
            aria-label="GreenPool dashboard"
        >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#2E7D32] text-white">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m5 11 1.5-3.7A2 2 0 0 1 8.35 6h7.3a2 2 0 0 1 1.85 1.3L19 11" />
                    <path d="M5 11h14a2 2 0 0 1 2 2v4H3v-4a2 2 0 0 1 2-2Z" />
                    <path d="M5 17v2M19 17v2M7 14h.01M17 14h.01" />
                </svg>
            </span>
            <span x-show="sidebarExpanded || mobileDrawerOpen" x-transition.opacity.duration.150ms class="ml-4 whitespace-nowrap text-xl font-semibold text-[#1F2D3D]">
                GreenPool
            </span>
        </a>

        <button
            type="button"
            class="ml-auto hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition duration-150 hover:bg-slate-100 hover:text-[#2E7D32] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2 lg:flex"
            @click="setSidebarExpanded(!sidebarExpanded)"
            :aria-label="sidebarExpanded ? 'Collapse sidebar' : 'Expand sidebar'"
            :title="sidebarExpanded ? 'Collapse sidebar' : 'Expand sidebar'"
        >
            <x-icons.lucide name="panel-left-close" x-show="sidebarExpanded" />
            <x-icons.lucide name="panel-left-open" x-show="!sidebarExpanded" />
        </button>

        <button
            type="button"
            class="ml-auto flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2 md:hidden"
            @click="closeMobileDrawer()"
            aria-label="Close navigation menu"
        >
            <x-icons.lucide name="x" />
        </button>
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" aria-label="Primary">
        <ul class="space-y-1.5">
            @foreach ($mainNavigation as $item)
                <li>
                    <a
                        href="{{ $item['href'] }}"
                        @click="if (mobileDrawerOpen) closeMobileDrawer()"
                        @if ($item['active']) aria-current="page" @endif
                        title="{{ $item['label'] }}"
                        class="group relative flex h-13 min-h-[52px] items-center rounded-xl px-3 text-[15px] font-medium transition duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2 {{ $item['active'] ? 'bg-green-50 text-[#2E7D32]' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}"
                    >
                        <span class="relative flex h-7 w-7 shrink-0 items-center justify-center {{ $item['active'] ? 'text-[#2E7D32]' : 'text-slate-400 group-hover:text-slate-600' }}">
                            <x-icons.lucide :name="$item['icon']" />
                            @if ($item['active'])
                                <span class="absolute -left-3 h-6 w-1 rounded-r-full bg-[#2E7D32]" aria-hidden="true"></span>
                            @endif
                            @if ($item['unread'] ?? false)
                                <span class="absolute right-0 top-0 h-2 w-2 rounded-full bg-[#2E7D32] ring-2 ring-white" aria-hidden="true"></span>
                                <span class="sr-only">Unread notifications</span>
                            @endif
                        </span>
                        <span x-show="sidebarExpanded || mobileDrawerOpen" x-transition.opacity.duration.150ms class="ml-3 whitespace-nowrap">
                            {{ $item['label'] }}
                        </span>
                        <span
                            x-show="!sidebarExpanded && !mobileDrawerOpen"
                            class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm group-hover:block group-focus-visible:block"
                            role="tooltip"
                        >
                            {{ $item['label'] }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="shrink-0 border-t border-slate-100 px-3 py-3">
        <nav aria-label="Support">
            <ul class="space-y-1">
                @foreach ($bottomNavigation as $item)
                    <li>
                        <a
                            href="{{ $item['href'] }}"
                            @click="if (mobileDrawerOpen) closeMobileDrawer()"
                            title="{{ $item['label'] }}"
                            class="group relative flex min-h-[48px] items-center rounded-xl px-3 text-[15px] font-medium text-slate-700 transition duration-150 hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
                        >
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center text-slate-400 group-hover:text-slate-600">
                                <x-icons.lucide :name="$item['icon']" />
                            </span>
                            <span x-show="sidebarExpanded || mobileDrawerOpen" x-transition.opacity.duration.150ms class="ml-3 whitespace-nowrap">
                                {{ $item['label'] }}
                            </span>
                            <span
                                x-show="!sidebarExpanded && !mobileDrawerOpen"
                                class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm group-hover:block group-focus-visible:block"
                                role="tooltip"
                            >
                                {{ $item['label'] }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <a
            href="{{ route('profile.edit') }}"
            @click="if (mobileDrawerOpen) closeMobileDrawer()"
            class="mt-3 flex min-h-[64px] items-center rounded-xl border border-slate-200 bg-slate-50 p-2.5 transition duration-150 hover:border-green-200 hover:bg-green-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
            aria-label="View driver profile for {{ Auth::user()->name }}"
            title="{{ Auth::user()->name }} — Driver"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-semibold text-white">
                {{ $initials ?: 'D' }}
            </span>
            <span x-show="sidebarExpanded || mobileDrawerOpen" x-transition.opacity.duration.150ms class="ml-3 min-w-0">
                <span class="block truncate text-sm font-semibold text-[#1F2D3D]">{{ Auth::user()->name }}</span>
                <span class="mt-0.5 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-[#2E7D32]">Driver</span>
            </span>
        </a>
    </div>
</aside>
