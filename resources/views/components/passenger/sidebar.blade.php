<div class="w-64 bg-white border-r border-gray-200 flex flex-col h-full">

    <nav class="flex-1 px-4 py-4 space-y-2">

        <a href="{{ route('passenger.home') }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg bg-emerald-100 text-emerald-700 font-medium">

            🏠
            <span>Home</span>

        </a>

        <a href="#"
            class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100">

            📋
            <span>My Bookings</span>

        </a>

        <a href="{{ route('myprofile') }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-100">

            👤
            <span>Profile</span>

        </a>

    </nav>

    <div class="border-t p-4">

        <a href="#"
            class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-600 hover:bg-red-50">

            🚪
            <span>Logout</span>

        </a>

    </div>

</div>