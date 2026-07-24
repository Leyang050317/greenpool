<x-app-layout>

    <div class="max-w-7xl mx-auto px-6 pt-12 pb-8">

        <h1 class="text-3xl font-bold text-gray-800">
            Welcome, {{ Auth::user()->name }} 👋
        </h1>

        <p class="mt-2 text-gray-600">
            Welcome back to GreenPool.
        </p>

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

</x-app-layout>