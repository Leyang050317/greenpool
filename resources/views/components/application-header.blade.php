@php
    $pageTitle = match (true) {
        request()->routeIs('driver.home', 'passenger.home') => 'Dashboard',
        request()->routeIs('driver.trips.*') => 'My Trips',
        request()->routeIs('driver.booking-requests.*') => 'Booking Requests',
        request()->routeIs('driver.vehicles.*') => 'My Vehicles',
        request()->routeIs('passenger.booking') => 'Find a Ride',
        request()->routeIs('passenger.bookings.create') => 'Trip Details',
        request()->routeIs('passenger.bookings.history') => 'My Bookings',
        request()->routeIs('attractions.show') => request()->route('attraction')->attraction_name,
        request()->routeIs('attractions.index') && request()->query('tab') === 'favourites' => 'Favourite Attractions',
        request()->routeIs('attractions.index', 'driver.attractions.*', 'passenger.attractions.*') => 'Tourist Attractions',
        request()->routeIs('driver.notifications.*', 'passenger.notifications.*', 'notifications.*') => 'Notifications',
        request()->routeIs('ratings.*') => 'Ratings',
        request()->routeIs('profile.*', 'driver.profile.*', 'passenger.profile.*') => 'Profile',
        request()->routeIs('driver.settings.*', 'passenger.settings.*') => 'Settings',
        request()->routeIs('driver.help.*', 'passenger.help.*') => 'Help',
        default => 'Dashboard',
    };

    $notificationRoute = match (Auth::user()->role) {
        'driver' => Route::has('driver.notifications.index') ? route('driver.notifications.index') : '#',
        'passenger' => Route::has('passenger.notifications.index') ? route('passenger.notifications.index') : '#',
        default => '#',
    };

    $initials = collect(preg_split('/\s+/', trim(Auth::user()->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="flex h-16 w-full items-center border-b border-[#E5E7EB] bg-white px-8 [font-family:Inter,sans-serif]">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-600 transition-colors duration-150 hover:bg-[#F3F4F6] active:bg-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2 md:hidden"
            @click="$dispatch('open-navigation-drawer')"
            aria-label="Open navigation menu"
            aria-controls="{{ Auth::user()->role }}-mobile-navigation"
        >
            <x-icons.lucide name="menu" />
        </button>

        <h1 class="truncate text-lg font-semibold leading-none text-[#111827]">
            {{ $pageTitle }}
        </h1>
    </div>

    <div class="ml-6 flex shrink-0 items-center gap-6">
        <a
            href="{{ $notificationRoute }}"
            class="relative flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 transition-colors duration-150 hover:bg-[#F3F4F6] hover:text-gray-700 active:bg-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
            aria-label="Notifications, 3 unread"
            title="Notifications"
        >
            <x-icons.lucide name="bell" />
            <span class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white" aria-hidden="true">
                3
            </span>
        </a>

        <a
            href="{{ route('profile.edit') }}"
            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-bold text-white transition-colors duration-150 hover:bg-[#256b29] active:bg-[#1f5b23] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
            aria-label="Open profile for {{ Auth::user()->name }}"
            title="{{ Auth::user()->name }}"
        >
            {{ $initials ?: 'U' }}
        </a>
    </div>
</header>
