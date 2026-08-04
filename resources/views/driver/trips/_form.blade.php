@php
    $isEditing = isset($trip);
    $returnTo = $returnTo ?? 'driver.trips.index';
    $value = fn (string $key, mixed $default = null) => old($key, $default);
@endphp

<form method="POST" action="{{ $isEditing ? route('driver.trips.update', ['trip' => $trip, 'return_to' => $returnTo]) : route('driver.trips.store') }}" class="max-w-2xl space-y-6" x-data="{ confirmOpen: false, discardOpen: false }" x-ref="tripForm" @submit.prevent="confirmOpen = true">
    @csrf
    @if ($isEditing) @method('PATCH') @endif

    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6">
        <div class="space-y-5">
            <div>
                <label for="vehicle_id" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="car-front" class="h-4 w-4 text-gray-400" />Vehicle</label>
                <select id="vehicle_id" name="vehicle_id" class="w-full rounded-xl border-gray-200 text-sm text-gray-700 focus:border-[#16A34A] focus:ring-[#16A34A]" required>
                    <option value="">Select an active vehicle</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->vehicle_id }}" @selected((string) $value('vehicle_id', $trip->vehicle_id ?? '') === (string) $vehicle->vehicle_id)>
                            {{ $vehicle->brand }} {{ $vehicle->model }} — {{ $vehicle->plate_number }} ({{ $vehicle->seat_capacity }} seats)
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('vehicle_id')" class="mt-1.5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="departure_location" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="map-pin" class="h-4 w-4 text-gray-400" />Departure location</label>
                    <input id="departure_location" name="departure_location" value="{{ $value('departure_location', $trip->departure_location ?? '') }}" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="e.g. Petaling Jaya" />
                    <p class="mt-1 text-xs text-gray-400">Malaysia locations only.</p><x-input-error :messages="$errors->get('departure_location')" class="mt-1.5" />
                </div>
                <div>
                    <label for="destination" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="map-pin" class="h-4 w-4 text-gray-400" />Destination</label>
                    <input id="destination" name="destination" value="{{ $value('destination', $trip->destination ?? '') }}" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="e.g. KLCC" />
                    <p class="mt-1 text-xs text-gray-400">Malaysia locations only.</p><x-input-error :messages="$errors->get('destination')" class="mt-1.5" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="departure_date" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="calendar" class="h-4 w-4 text-gray-400" />Departure date</label>
                    <input id="departure_date" type="date" name="departure_date" min="{{ now()->toDateString() }}" value="{{ $value('departure_date', isset($trip) ? $trip->departure_at->toDateString() : '') }}" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    <x-input-error :messages="$errors->get('departure_date')" class="mt-1.5" />
                </div>
                <div>
                    <label for="departure_time" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="clock" class="h-4 w-4 text-gray-400" />Departure time</label>
                    <input id="departure_time" type="time" name="departure_time" value="{{ $value('departure_time', isset($trip) ? $trip->departure_at->format('H:i') : '') }}" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    <x-input-error :messages="$errors->get('departure_time')" class="mt-1.5" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="available_seats" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="armchair" class="h-4 w-4 text-gray-400" />Available seats</label>
                    <input id="available_seats" type="number" name="available_seats" min="1" max="4" value="{{ $value('available_seats', $trip->available_seats ?? '') }}" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    <x-input-error :messages="$errors->get('available_seats')" class="mt-1.5" />
                </div>
                <div>
                    <label for="price_per_passenger" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="dollar-sign" class="h-4 w-4 text-gray-400" />Price per passenger (RM)</label>
                    <input id="price_per_passenger" type="number" name="price_per_passenger" min="0" step="0.01" value="{{ $value('price_per_passenger', $trip->price_per_passenger ?? '0.00') }}" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    <x-input-error :messages="$errors->get('price_per_passenger')" class="mt-1.5" />
                </div>
            </div>
            <div>
                <label for="description" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="align-left" class="h-4 w-4 text-gray-400" />Description <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Add pickup information or instructions...">{{ $value('description', $trip->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
            </div>
        </div>
    </div>
    <div class="flex flex-wrap gap-3">
        <button type="button" @click="discardOpen = true" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ $isEditing ? 'Discard Changes' : 'Cancel' }}</button>
        <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#16A34A] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">{{ $isEditing ? 'Save changes' : 'Publish Trip' }}</button>
    </div>
    <div x-cloak x-show="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-slate-900/40" @click="confirmOpen = false"></div><div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"><h2 class="text-lg font-semibold text-gray-900">{{ $isEditing ? 'Save Changes?' : 'Publish Trip?' }}</h2><p class="mt-2 text-sm leading-6 text-gray-500">{{ $isEditing ? 'Passengers with confirmed bookings will receive updated trip information.' : 'Your trip will become available for passengers after publishing.' }}</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="confirmOpen = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">Cancel</button><button type="button" @click="$refs.tripForm.submit()" class="rounded-xl bg-[#16A34A] px-4 py-2 text-sm font-medium text-white">{{ $isEditing ? 'Save' : 'Publish' }}</button></div></div></div>
    <div x-cloak x-show="discardOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-slate-900/40" @click="discardOpen = false"></div><div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"><h2 class="text-lg font-semibold text-gray-900">{{ $isEditing ? 'Discard Changes?' : 'Discard Trip?' }}</h2><p class="mt-2 text-sm leading-6 text-gray-500">{{ $isEditing ? 'Unsaved changes will be lost.' : 'Your entered information will not be saved.' }}</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="discardOpen = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">{{ $isEditing ? 'Continue Editing' : 'Keep Editing' }}</button><a href="{{ route($returnTo) }}" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Discard</a></div></div></div>
</form>
