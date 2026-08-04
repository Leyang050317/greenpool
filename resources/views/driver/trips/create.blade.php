<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex items-center gap-3"><a href="{{ route('driver.trips.index') }}" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icons.lucide name="arrow-left" class="h-[18px] w-[18px]" /></a><h1 class="text-2xl font-bold text-gray-900">Create Trip</h1></div>
            @if($vehicles->isEmpty())
                <div class="max-w-2xl rounded-2xl border border-gray-100 bg-white px-6 py-16 text-center"><div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#DCFCE7]"><x-icons.lucide name="car-front" class="h-9 w-9 text-[#16A34A]" /></div><h2 class="mt-4 text-base font-semibold text-gray-700">You haven't registered an active vehicle yet.</h2><p class="mt-1 text-sm text-gray-400">Activate a vehicle before creating a trip.</p><a href="{{ route('driver.vehicles.index') }}" class="mt-6 inline-flex rounded-xl bg-[#16A34A] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#15803D]">Go to My Vehicles</a></div>
            @else
                @include('driver.trips._form')
            @endif
        </div>
    </div>
</x-app-layout>
