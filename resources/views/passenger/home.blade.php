<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - GreenPool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="font-sans antialiased text-gray-900 bg-[#F9FAFB] flex h-screen overflow-hidden"
    data-auth-id="{{ Auth::id() }}"
    data-auth-role="{{ Auth::user()?->role }}"
>

    <div class="flex w-full h-full" x-data="{ showLogoutModal: false }">
        
        <aside class="w-64 bg-white border-r border-gray-200 flex flex-col justify-between hidden md:flex shrink-0">
            <div>
                <div class="h-16 flex items-center px-6 mb-4 mt-2">
                    <div class="bg-[#2E7D32] text-white p-2 rounded-xl mr-3 shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m5 11 1.5-3.7A2 2 0 0 1 8.35 6h7.3a2 2 0 0 1 1.85 1.3L19 11" />
                            <path d="M5 11h14a2 2 0 0 1 2 2v4H3v-4a2 2 0 0 1 2-2Z" />
                            <path d="M5 17v2M19 17v2M7 14h.01M17 14h.01" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-800">GreenPool</span>
                </div>

                <nav class="px-4 space-y-1">
                    <a href="{{ route('passenger.home') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-[#2E7D32] bg-green-50 rounded-lg">
                        <svg class="w-5 h-5 mr-3 text-[#2E7D32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        Dashboard
                    </a>
                    
                    <a href="{{ route('passenger.booking') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Find a Ride
                    </a>
                    
                    <a href="{{ route('passenger.bookings.history') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                        </svg>
                        My Bookings
                    </a>

                    <a href="{{ route('attractions.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Tourist Attractions
                    </a>
                    
                    <a href="#" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Notifications
                    </a>
                    
                    <a href="{{ route('ratings.index') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                        Ratings
                    </a>

                    <a href="{{ route('profile.edit') }}" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Profile
                    </a>
                </nav>

                <div class="px-4 mt-8 space-y-1">
                    <a href="#" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Settings
                    </a>
                    <a href="#" class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Help
                    </a>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center p-2 rounded-xl bg-gray-50 border border-gray-100 shadow-sm">
                    @if(Auth::user()->photo)
                        <img src="{{ asset('storage/' . Auth::user()->photo) }}" alt="Profile" class="flex-shrink-0 w-10 h-10 rounded-full object-cover border border-gray-200">
                    @else
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-sm">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                        </div>
                    @endif
                    <div class="ml-3 overflow-hidden">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ Auth::user()->name ?? 'User' }}</p>
                        <p class="text-xs font-semibold text-[#2E7D32] bg-green-100 px-1.5 py-0.5 rounded inline-block mt-0.5">{{ ucfirst(Auth::user()->role) }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
                <h2 class="text-sm font-semibold text-gray-700">Dashboard</h2>
                <div class="flex items-center space-x-4">
                    <button class="text-gray-400 hover:text-gray-600 relative">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="absolute top-0 right-0 block w-2 h-2 bg-red-500 rounded-full ring-2 ring-white"></span>
                    </button>
                    @if(Auth::user()->photo)
                        <img src="{{ asset('storage/' . Auth::user()->photo) }}" alt="Profile" class="w-8 h-8 rounded-full cursor-pointer object-cover border border-gray-200">
                    @else
                        <div class="w-8 h-8 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xs cursor-pointer">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                        </div>
                    @endif
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-8 relative">
                <div class="max-w-7xl mx-auto">
                    <h1 class="text-3xl font-bold text-gray-800">
                        Welcome, {{ Auth::user()->name }} 👋
                    </h1>

                    <p class="mt-2 text-gray-600">
                        Welcome back to GreenPool.
                    </p>

                    @if($pendingRatingBooking)
                        <section class="mt-8 overflow-hidden rounded-2xl border border-green-200 bg-white shadow-sm" aria-labelledby="pending-rating-title">
                            <div class="bg-green-50 px-5 py-4 sm:px-6">
                                <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Trip completed</p>
                                <h2 id="pending-rating-title" class="mt-1 text-xl font-bold text-slate-900">How was your ride?</h2>
                            </div>
                            <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-sm font-bold text-white">{{ strtoupper(substr($pendingRatingBooking->trip->user->name, 0, 2)) }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900">{{ $pendingRatingBooking->trip->user->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $pendingRatingBooking->trip->departure_location }} to {{ $pendingRatingBooking->trip->destination }}</p>
                                    <p class="mt-2 text-2xl tracking-wide text-amber-400" aria-hidden="true">★★★★★</p>
                                </div>
                                <a href="{{ route('ratings.create', $pendingRatingBooking) }}" class="w-full rounded-xl bg-[#22C55E] px-5 py-3 text-center text-sm font-bold text-white hover:bg-green-600 sm:w-auto">Rate Now</a>
                            </div>
                        </section>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
                        <div class="bg-white rounded-xl shadow p-6">
                            <h2 class="font-semibold text-lg">
                                My Booking
                            </h2>
                            <p class="text-gray-500 mt-2">
                                View your booking requests.
                            </p>
                        </div>

                        <div class="bg-white rounded-xl shadow p-6">
                            <h2 class="font-semibold text-lg">
                                Upcoming Trip
                            </h2>
                            <p class="text-gray-500 mt-2">
                                No upcoming trip.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</body>
</html>
