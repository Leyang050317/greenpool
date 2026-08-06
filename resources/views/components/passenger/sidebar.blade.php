<div class="flex h-full w-64 flex-col border-r border-gray-200 bg-white">
    <nav class="flex-1 space-y-2 px-4 py-4">
        <a href="{{ route('passenger.home') }}" class="flex items-center gap-3 rounded-lg bg-emerald-100 px-4 py-3 font-medium text-emerald-700">
            <x-icons.lucide name="layout-dashboard" class="h-5 w-5" />
            <span>Home</span>
        </a>
        <a href="{{ route('passenger.bookings.history') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 hover:bg-gray-100">
            <x-icons.lucide name="calendar" class="h-5 w-5" />
            <span>My Bookings</span>
        </a>
        <a href="{{ route('ratings.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 {{ request()->routeIs('ratings.*') ? 'bg-emerald-100 font-medium text-emerald-700' : 'hover:bg-gray-100' }}">
            <x-icons.lucide name="star" class="h-5 w-5" />
            <span>Ratings</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-4 py-3 hover:bg-gray-100">
            <x-icons.lucide name="user-circle" class="h-5 w-5" />
            <span>Profile</span>
        </a>
    </nav>
    <div class="border-t p-4">
        <a href="#" class="flex items-center gap-3 rounded-lg px-4 py-3 text-red-600 hover:bg-red-50">
            <x-icons.lucide name="arrow-left" class="h-5 w-5" />
            <span>Logout</span>
        </a>
    </div>
</div>
