@extends('passenger.booking.layout')

@section('pageTitle', 'Trip Details')

@section('content')
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-8 flex items-center gap-3">
                <a href="{{ route('passenger.booking') }}" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <x-icons.lucide name="arrow-left" class="h-5 w-5" />
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Trip Details</h1>
                    <p class="mt-1 text-sm text-gray-500">Review the trip and submit a booking request.</p>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please check your booking details.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(! $trip)
                <div class="rounded-xl border border-gray-100 bg-white px-6 py-16 text-center">
                    <p class="text-base font-semibold text-gray-700">Select an available trip first.</p>
                    <a href="{{ route('passenger.booking') }}" class="mt-4 inline-flex rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">Find a Ride</a>
                </div>
            @else
                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="rounded-xl border border-gray-100 bg-white p-5 lg:col-span-2">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Ride Information</h2>
                        <dl class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Driver</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->user->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Vehicle</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->vehicle?->brand }} {{ $trip->vehicle?->model }} {{ $trip->vehicle?->plate_number ? '- '.$trip->vehicle->plate_number : '' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Departure Location</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->departure_location }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Destination</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->destination }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Date</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->departure_at->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Time</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->departure_at->format('g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Available Seats</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $trip->available_seats }} seats</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Fare Per Passenger</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">RM {{ number_format((float) $trip->price_per_passenger, 2) }}</dd>
                            </div>
                        </dl>

                        @if($trip->description)
                            <div class="mt-5 border-t border-gray-50 pt-4">
                                <p class="text-xs font-medium text-gray-500">Driver Notes</p>
                                <p class="mt-1 text-sm leading-6 text-gray-700">{{ $trip->description }}</p>
                            </div>
                        @endif
                    </section>

                    <form
                        method="POST"
                        action="{{ route('passenger.bookings.store') }}"
                        class="rounded-xl border border-gray-100 bg-white p-5"
                        x-data="pickupPointForm({{ Js::from(route('passenger.bookings.locations.autocomplete')) }}, {{ Js::from(old('pickup_point', '')) }}, {{ Js::from(old('pickup_place_id', '')) }})"
                        @submit.prevent="submitForm($event)"
                    >
                        @csrf
                        <input type="hidden" name="trip_id" value="{{ $trip->trip_id }}" />

                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Booking Request</h2>

                        <div class="space-y-4">
                            <div x-cloak x-show="pickup.error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-800" x-text="pickup.error"></div>

                            <div class="relative">
                                <label for="pickup_point" class="mb-1.5 block text-xs font-semibold text-gray-500">Pickup Point</label>
                                <input id="pickup_point" name="pickup_point" x-model="pickup.text" @input="search()" @focus="pickup.open = true" autocomplete="off" required maxlength="255" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Search a Malaysian address or place" />
                                <input type="hidden" name="pickup_place_id" :value="pickup.placeId" />
                                <div x-cloak x-show="pickup.open && pickup.suggestions.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                                    <template x-for="suggestion in pickup.suggestions" :key="suggestion.place_id">
                                        <button type="button" @click="select(suggestion)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" x-text="suggestion.text"></button>
                                    </template>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Select a suggestion in Malaysia to use it.</p>
                            </div>

                            <div>
                                <label for="number_of_seats" class="mb-1.5 block text-xs font-semibold text-gray-500">Requested Seats</label>
                                <input id="number_of_seats" type="number" name="number_of_seats" value="{{ old('number_of_seats', 1) }}" required min="1" max="{{ $trip->available_seats }}" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                            </div>
                        </div>

                        <button type="submit" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                            <x-icons.lucide name="send" class="h-4 w-4" />
                            Submit Request
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <script>
        window.pickupPointForm = (endpoint, pickupText, pickupPlaceId) => ({
            timer: null,
            pickup: { text: pickupText, placeId: pickupPlaceId, suggestions: [], open: false, error: '' },
            submitForm(event) {
                if (!this.pickup.placeId) {
                    this.pickup.error = 'Please select your pickup point from the location suggestions.';
                    return;
                }

                this.pickup.error = '';
                event.target.submit();
            },
            async search() {
                this.pickup.placeId = '';
                this.pickup.suggestions = [];
                this.pickup.error = '';
                this.pickup.open = true;

                clearTimeout(this.timer);
                if (this.pickup.text.trim().length < 2) {
                    return;
                }

                this.timer = setTimeout(async () => {
                    try {
                        const response = await window.axios.get(endpoint, { params: { input: this.pickup.text.trim() } });
                        this.pickup.suggestions = response.data.data || [];
                    } catch (_) {
                        this.pickup.suggestions = [];
                    }
                }, 250);
            },
            select(suggestion) {
                this.pickup.text = suggestion.text;
                this.pickup.placeId = suggestion.place_id;
                this.pickup.suggestions = [];
                this.pickup.open = false;
            },
        });
    </script>
@endsection
