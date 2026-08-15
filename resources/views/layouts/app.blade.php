<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

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
            </div>
        @endif
    </body>
</html>
