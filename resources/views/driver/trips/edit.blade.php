<x-app-layout>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex items-center gap-3">
                <a href="{{ route($returnTo) }}" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icons.lucide name="arrow-left" class="h-[18px] w-[18px]" /></a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $isExpiredRecovery ? 'Edit Departure Time' : 'Edit Trip' }}</h1>
            </div>

            @if($isExpiredRecovery)
                <div class="max-w-xl rounded-2xl border border-red-200 bg-white p-6">
                    <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">This trip has expired. Choose a future departure time to make it scheduled and startable again.</p>
                    <form method="POST" action="{{ route('driver.trips.update', ['trip' => $trip, 'return_to' => $returnTo]) }}" class="mt-6 space-y-5">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="version" value="{{ old('version', $trip->version) }}" />
                        <div>
                            <p class="text-sm font-medium text-gray-700">Current departure</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $trip->departure_at->format('d M Y, g:i A') }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700">New departure date<input type="date" name="departure_date" min="{{ now()->toDateString() }}" value="{{ old('departure_date', $trip->departure_at->toDateString()) }}" required class="mt-1 block w-full rounded-xl border-gray-200" /></label>
                            <label class="text-sm font-medium text-gray-700">New departure time<input type="time" name="departure_time" value="{{ old('departure_time', $trip->departure_at->format('H:i')) }}" required class="mt-1 block w-full rounded-xl border-gray-200" /></label>
                        </div>
                        <x-input-error :messages="$errors->get('departure_time')" />
                        <x-input-error :messages="$errors->get('version')" />
                        <div class="flex gap-3">
                            <a href="{{ route($returnTo) }}" class="rounded-xl border border-gray-200 px-5 py-2 text-sm font-semibold text-gray-700">Cancel</a>
                            <button class="rounded-xl bg-[#16A34A] px-5 py-2 text-sm font-semibold text-white">Save Changes</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="mb-6 max-w-2xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Trip information can only be changed before the journey starts.</div>
                @include('driver.trips._form')
            @endif
        </div>
    </div>
</x-app-layout>
