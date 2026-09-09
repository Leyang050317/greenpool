@php
    $pageTitle = request()->routeIs('profile.edit') ? 'Profile' : (Auth::user()?->role === 'driver' ? match (true) {
        request()->routeIs('driver.home') => 'Dashboard',
        request()->routeIs('driver.trips.index') => 'My Trips',
        request()->routeIs('driver.trips.create') => 'Create Trip',
        request()->routeIs('driver.trips.edit') => 'Edit Trip',
        request()->routeIs('driver.trips.show') => 'Trip Details',
        request()->routeIs('driver.trips.journey') => 'My Journey',
        request()->routeIs('driver.trips.history') => 'Trip History',
        request()->routeIs('driver.booking-requests.index') => 'My Booking Requests',
        request()->routeIs('driver.booking-requests.show') => 'Booking Request Details',
        request()->routeIs('driver.vehicles.index') => 'My Vehicles',
        request()->routeIs('driver.vehicles.create') => 'Add Vehicle',
        request()->routeIs('driver.vehicles.edit') => 'Edit Vehicle',
        request()->routeIs('driver.vehicles.show') => 'Vehicle Details',
        request()->routeIs('driver.vehicles.archived') => 'Archived Vehicles',
        request()->routeIs('driver.profile.*') => 'Profile',
        request()->routeIs('settings.*') => 'Settings',
        request()->routeIs('help.*') => 'Help & Support',
        request()->routeIs('notifications.*') => 'Notifications',
        request()->routeIs('messages.*', 'bookings.chat.*') => 'Messages',
        request()->routeIs('payments.*') => 'Payments',
        request()->routeIs('ratings.*') => 'Ratings',
        request()->routeIs('attractions.*') => 'Tourist Attractions',
        default => config('app.name', 'GreenPool'),
    } : config('app.name', 'Laravel'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - GreenPool</title>

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&family=inter:700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-sans antialiased"
        data-auth-id="{{ Auth::id() }}"
        data-auth-role="{{ Auth::user()?->role }}"
        data-trip-start-early-minutes="{{ config('trips.start_early_minutes', 30) }}"
    >
        @if(Auth::user()?->role === 'driver')
            <div
                x-data="driverNavigation"
                @keydown.escape.window="closeMobileDrawer()"
                @open-navigation-drawer.window="openMobileDrawer()"
                class="min-h-screen overflow-x-hidden bg-gray-100"
            >
                <div class="fixed inset-y-0 left-0 z-40 hidden w-72 md:block">
                    <x-driver.sidebar />
                </div>

                <div
                    x-cloak
                    x-show="mobileDrawerOpen"
                    x-transition.opacity.duration.150ms
                    class="fixed inset-0 z-40 bg-slate-950/45 md:hidden"
                    @click="closeMobileDrawer()"
                    aria-hidden="true"
                ></div>

                <div
                    id="driver-mobile-navigation"
                    x-cloak
                    x-show="mobileDrawerOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="fixed inset-y-0 left-0 z-50 w-72 md:hidden"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Driver navigation menu"
                >
                    <x-driver.sidebar />
                </div>

                <div class="min-h-screen md:pl-72">
                    <x-application-header />

                    <main>
                        {{ $slot }}
                    </main>
                </div>
                <x-faq-bot />
            </div>
        @else
            <div
                x-data="passengerNavigation"
                @keydown.escape.window="closeMobileDrawer()"
                @open-navigation-drawer.window="openMobileDrawer()"
                class="min-h-screen overflow-x-hidden bg-gray-100"
            >
                <div class="fixed inset-y-0 left-0 z-40 hidden w-72 md:block">
                    <x-passenger.sidebar />
                </div>

                <div x-cloak x-show="mobileDrawerOpen" x-transition.opacity.duration.150ms class="fixed inset-0 z-40 bg-slate-950/45 md:hidden" @click="closeMobileDrawer()" aria-hidden="true"></div>

                <div id="passenger-mobile-navigation" x-cloak x-show="mobileDrawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="fixed inset-y-0 left-0 z-50 w-72 md:hidden" role="dialog" aria-modal="true" aria-label="Passenger navigation menu">
                    <x-passenger.sidebar />
                </div>

                <div class="min-h-screen md:pl-72">
                    <x-application-header />

                    <main>
                        {{ $slot }}
                    </main>
                </div>
                <x-faq-bot />
            </div>
        @endif
    </body>
</html>
