@extends('passenger.booking.layout')

@section('pageTitle', 'Find a Ride')

@section('content')
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Find a Ride</h1>
                    <p class="mt-1 text-sm text-gray-500">Search available GreenPool trips.</p>
                </div>
                <a href="{{ route('passenger.bookings.history') }}" class="inline-flex w-fit items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-icons.lucide name="history" class="h-4 w-4" />
                    Booking History
                </a>
            </div>

            @if(session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
            @endif

            <form
                method="GET"
                action="{{ route('passenger.booking') }}"
                class="mb-6 rounded-xl border border-gray-100 bg-white p-5"
                x-data="rideSearchForm({{ Js::from(route('passenger.bookings.locations.autocomplete')) }}, {{ Js::from(request('destination', '')) }}, {{ Js::from(request('destination_place_id', '')) }})"
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="relative">
                        <label for="destination" class="mb-1.5 block text-xs font-semibold text-gray-500">Destination</label>
                        <input id="destination" name="destination" x-model="destination.text" @input="search()" @focus="destination.open = true" autocomplete="off" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Search a Malaysian address or place" />
                        <input type="hidden" name="destination_place_id" :value="destination.placeId" />
                        <div x-cloak x-show="destination.open && destination.suggestions.length" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                            <template x-for="suggestion in destination.suggestions" :key="suggestion.place_id">
                                <button type="button" @click="select(suggestion)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" x-text="suggestion.text"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label for="travel_date" class="mb-1.5 block text-xs font-semibold text-gray-500">Travel Date</label>
                        <input id="travel_date" type="date" name="travel_date" value="{{ request('travel_date') }}" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" />
                    </div>
                    <div>
                        <label for="passengers" class="mb-1.5 block text-xs font-semibold text-gray-500">Number of Passengers</label>
                        <input id="passengers" type="number" min="1" name="passengers" value="{{ request('passengers') }}" class="block w-full rounded-xl border-gray-200 text-sm focus:border-[#16A34A] focus:ring-[#16A34A]" placeholder="Select" />
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">
                        <x-icons.lucide name="search" class="h-4 w-4" />
                        Search
                    </button>
                    <a href="{{ route('passenger.booking') }}" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>

            @if($trips->isEmpty())
                <div class="rounded-xl border border-gray-100 bg-white px-6 py-16 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-50">
                        <x-icons.lucide name="car-front" class="h-8 w-8 text-[#2E7D32]" />
                    </div>
                    <p class="mt-4 text-base font-semibold text-gray-700">No available trips found.</p>
                    <p class="mt-1 text-sm text-gray-500">Try changing your search filters.</p>
                </div>
            @else
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($trips as $trip)
                        <article class="rounded-xl border border-gray-100 bg-white p-5 transition-shadow hover:shadow-md">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-50 text-sm font-semibold text-[#2E7D32]">
                                        {{ collect(preg_split('/\s+/', trim($trip->user->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'D' }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $trip->user->name }}</p>
                                        @if($trip->user->ratings_received_count > 0)
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-amber-500"><x-icons.lucide name="star" class="h-3 w-3 fill-current" />{{ number_format((float) $trip->user->ratings_received_avg_score, 1) }} <span class="text-gray-400">({{ $trip->user->ratings_received_count }})</span></p>
                                        @else
                                            <p class="mt-0.5 text-xs text-gray-400">No ratings yet</p>
                                        @endif
                                        <p class="truncate text-xs text-gray-500">{{ $trip->vehicle?->brand }} {{ $trip->vehicle?->model }} {{ $trip->vehicle?->plate_number ? '('.$trip->vehicle->plate_number.')' : '' }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-[#2E7D32]">{{ $trip->available_seats }} seats</span>
                            </div>

                            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">Destination</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">{{ $trip->destination }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">Date</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">{{ $trip->departure_at->format('d M Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">Departure</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">{{ $trip->departure_at->format('g:i A') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">Estimated Fare</dt>
                                    <dd class="mt-1 font-semibold text-gray-900">RM {{ number_format((float) $trip->price_per_passenger, 2) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5 flex justify-end border-t border-gray-50 pt-4">
                                <a href="{{ route('passenger.bookings.create', ['trip_id' => $trip->trip_id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">
                                    View Details
                                    <x-icons.lucide name="arrow-right" class="h-4 w-4" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-6">{{ $trips->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        window.rideSearchForm = (endpoint, destinationText, destinationPlaceId) => ({
            timer: null,
            destination: { text: destinationText, placeId: destinationPlaceId, suggestions: [], open: false },
            async search() {
                this.destination.placeId = '';
                this.destination.suggestions = [];
                this.destination.open = true;

                clearTimeout(this.timer);
                if (this.destination.text.trim().length < 2) {
                    return;
                }

                this.timer = setTimeout(async () => {
                    try {
                        const response = await window.axios.get(endpoint, { params: { input: this.destination.text.trim() } });
                        this.destination.suggestions = response.data.data || [];
                    } catch (_) {
                        this.destination.suggestions = [];
                    }
                }, 250);
            },
            select(suggestion) {
                this.destination.text = suggestion.text;
                this.destination.placeId = suggestion.place_id;
                this.destination.suggestions = [];
                this.destination.open = false;
            },
        });
    </script>
@endsection
