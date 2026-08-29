@php
    $isEditing = isset($trip);
    $returnTo = $returnTo ?? 'driver.trips.index';
    $value = fn (string $key, mixed $default = null) => old($key, $default);
    $prefilledDestination = $isEditing ? ($trip->destination ?? '') : request('destination', '');
    $prefilledDestinationPlaceId = $isEditing ? '' : request('destination_place_id', '');
@endphp

<form method="POST" action="{{ $isEditing ? route('driver.trips.update', ['trip' => $trip, 'return_to' => $returnTo]) : route('driver.trips.store') }}" class="max-w-2xl space-y-6" x-data="tripLocationForm({{ Js::from(route('driver.trips.locations.autocomplete')) }}, {{ Js::from(route('driver.trips.fare-estimate')) }}, {{ Js::from($isEditing) }}, {{ Js::from($value('departure_location', $trip->departure_location ?? '')) }}, {{ Js::from($value('destination', $prefilledDestination)) }}, {{ Js::from(old('departure_place_id', '')) }}, {{ Js::from(old('destination_place_id', $prefilledDestinationPlaceId)) }}, {{ Js::from((string) $value('price_per_passenger', $trip->price_per_passenger ?? '')) }})" x-ref="tripForm" @submit.prevent="submitForm()">
    @csrf
    @if ($isEditing) @method('PATCH') @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please fix the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-cloak x-show="locationError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" x-text="locationError"></div>

    <div class="rounded-2xl border border-gray-100 bg-white p-5 sm:p-6">
        <div class="space-y-5">
            <div>
                <label for="vehicle_id" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="car-front" class="h-4 w-4 text-gray-400" />Vehicle</label>
                <select id="vehicle_id" name="vehicle_id" @change="updateFareEstimate()" class="w-full rounded-xl border-gray-200 text-sm text-gray-700 focus:border-[#16A34A] focus:ring-[#16A34A]" required>
                    <option value="">Select an active vehicle</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->vehicle_id }}" @selected((string) $value('vehicle_id', $trip->vehicle_id ?? '') === (string) $vehicle->vehicle_id)>
                            {{ $vehicle->brand }} {{ $vehicle->model }} — {{ $vehicle->plate_number }} ({{ $vehicle->seat_capacity }} seats)
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">The suggestion uses the vehicle's engine size and RON95 as a consistent fuel-price reference.</p>
                <x-input-error :messages="$errors->get('vehicle_id')" class="mt-1.5" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="relative">
                    <label for="departure_location" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="map-pin" class="h-4 w-4 text-gray-400" />Departure location</label>
                    <input id="departure_location" name="departure_location" x-model="departure.text" @input="search('departure')" @focus="departure.open = true" autocomplete="off" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Search a Malaysian address or place" />
                    <input type="hidden" name="departure_place_id" :value="departure.placeId" />
                    <div x-cloak x-show="departure.open && departure.suggestions.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg"><template x-for="suggestion in departure.suggestions" :key="suggestion.place_id"><button type="button" @click="select('departure', suggestion)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" x-text="suggestion.text"></button></template></div>
                    <p class="mt-1 text-xs text-gray-400">Select a suggestion in Malaysia to use it.</p><x-input-error :messages="$errors->get('departure_place_id')" class="mt-1.5" />
                </div>
                <div class="relative">
                    <label for="destination" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="map-pin" class="h-4 w-4 text-gray-400" />Destination</label>
                    <input id="destination" name="destination" x-model="destination.text" @input="search('destination')" @focus="destination.open = true" autocomplete="off" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Search a Malaysian address or place" />
                    <input type="hidden" name="destination_place_id" :value="destination.placeId" />
                    <div x-cloak x-show="destination.open && destination.suggestions.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg"><template x-for="suggestion in destination.suggestions" :key="suggestion.place_id"><button type="button" @click="select('destination', suggestion)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" x-text="suggestion.text"></button></template></div>
                    <p class="mt-1 text-xs text-gray-400">Select a suggestion in Malaysia to use it.</p>@if (! $isEditing && filled($prefilledDestinationPlaceId))<p class="mt-1 text-xs font-medium text-green-700">Destination was added from Tourist Attractions.</p>@endif<x-input-error :messages="$errors->get('destination_place_id')" class="mt-1.5" />
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="departure_date" class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-gray-700"><x-icons.lucide name="calendar" class="h-4 w-4 text-gray-400" />Departure date</label>
                    <input id="departure_date" type="date" name="departure_date" min="{{ now()->toDateString() }}" max="{{ $licence?->valid_until?->toDateString() }}" value="{{ $value('departure_date', isset($trip) ? $trip->departure_at->toDateString() : '') }}" required class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    @if($licence)<p class="mt-1 text-xs text-gray-400">Your licence permits trips through {{ $licence->valid_until->format('d M Y') }}.</p>@endif
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
                    <input id="price_per_passenger" type="number" name="price_per_passenger" min="0" step="0.01" x-model="price" @input="priceManuallyEdited = true" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Select locations for a suggestion" />
                    <div x-cloak x-show="estimating" class="mt-2 text-xs text-slate-400">Calculating route and suggested price...</div>
                    <div x-cloak x-show="fareEstimate && !estimating" class="mt-2 rounded-lg bg-green-50 px-3 py-2 text-xs leading-5 text-green-800">
                        <strong>Suggested: RM <span x-text="Number(fareEstimate?.recommended_price || 0).toFixed(2)"></span></strong>
                        <span> · <span x-text="fareEstimate?.estimated_distance_km"></span> km · about <span x-text="Math.max(1, Math.round((fareEstimate?.estimated_duration_seconds || 0) / 60))"></span> min</span>
                        <span class="block text-green-700">Based on the route and estimated driving cost. You may adjust it.</span>
                    </div>
                    <div x-cloak x-show="fareError" class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <span x-text="fareError"></span>
                        <button type="button" @click="updateFareEstimate()" class="shrink-0 font-semibold text-amber-900 underline">Retry suggestion</button>
                    </div>
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
<script>
    window.tripLocationForm = (endpoint, fareEndpoint, isEditing, departureText, destinationText, departurePlaceId, destinationPlaceId, initialPrice) => ({
        confirmOpen: false, discardOpen: false, timer: null, locationError: '', fareError: '', estimating: false, fareEstimate: null,
        price: initialPrice, priceManuallyEdited: initialPrice !== '',
        departure: { text: departureText, initialText: departureText, placeId: departurePlaceId, suggestions: [], open: false },
        destination: { text: destinationText, initialText: destinationText, placeId: destinationPlaceId, suggestions: [], open: false },
        locationsValid() {
            return [this.departure, this.destination].every((location) => location.placeId || (isEditing && location.text === location.initialText));
        },
        submitForm() {
            if (!this.locationsValid()) {
                this.locationError = 'Please select both departure and destination from the location suggestions.';
                return;
            }

            this.locationError = '';
            this.confirmOpen = true;
        },
        async search(field) {
            this.locationError = '';
            const location = this[field]; location.placeId = ''; location.suggestions = []; location.open = true;
            clearTimeout(this.timer); if (location.text.trim().length < 2) return;
            this.timer = setTimeout(async () => { try {
                const response = await window.axios.get(endpoint, { params: { input: location.text.trim() } });
                location.suggestions = response.data.data || [];
            } catch (_) { location.suggestions = []; } }, 250);
        },
        select(field, suggestion) { this[field].text = suggestion.text; this[field].placeId = suggestion.place_id; this[field].suggestions = []; this[field].open = false; this.updateFareEstimate(); },
        async updateFareEstimate() {
            if (!this.departure.placeId || !this.destination.placeId) return;
            const vehicleId = this.$refs.tripForm.querySelector('[name="vehicle_id"]').value;
            if (!vehicleId) return;
            this.estimating = true; this.fareError = '';
            try {
                const response = await window.axios.get(fareEndpoint, { params: { departure_place_id: this.departure.placeId, destination_place_id: this.destination.placeId, vehicle_id: vehicleId } });
                this.fareEstimate = response.data.data;
                if (!this.priceManuallyEdited) this.price = Number(this.fareEstimate.recommended_price).toFixed(2);
            } catch (error) {
                this.fareEstimate = null;
                this.fareError = error.response?.data?.message || 'Unable to calculate a suggested price right now.';
            } finally { this.estimating = false; }
        },
    });
</script>
