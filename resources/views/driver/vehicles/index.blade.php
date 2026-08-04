<x-app-layout>
    <div class="px-4 py-8 sm:px-6 lg:px-8" x-data="{ managementMode: 'details' }">
        <div class="mx-auto max-w-7xl">
            @if (session('success'))
                <div class="mb-6 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="rounded p-1 hover:bg-green-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]" aria-label="Dismiss message">×</button>
                </div>
            @endif

            <div class="mb-8 flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-xl">
                    <h2 class="text-[30px] font-bold leading-tight text-gray-950">My Vehicles</h2>
                    <p class="mt-1.5 text-base leading-6 text-gray-600">Manage your registered vehicles and choose which vehicle to use for your trips.</p>
                </div>

                <div class="flex flex-wrap gap-2.5">
                    <button
                        type="button"
                        @click="managementMode = managementMode === 'availability' ? 'details' : 'availability'"
                        :aria-pressed="managementMode === 'availability'"
                        :class="managementMode === 'availability' ? 'border-green-300 bg-green-50 text-[#2E7D32]' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
                    >
                        <x-icons.lucide name="circle-check" class="h-5 w-5" />
                        Status
                    </button>
                    <a href="{{ route('driver.vehicles.create') }}" class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#256b29] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2">
                        <x-icons.lucide name="plus" class="h-5 w-5" />
                        Add Vehicle
                    </a>
                </div>
            </div>

            <p x-cloak x-show="managementMode === 'availability'" class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">
                Choose Activate or Deactivate on the vehicle you want to update.
            </p>
            @if ($vehicles->isEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                        <x-icons.lucide name="car-front" class="h-7 w-7" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold text-gray-900">No vehicles registered</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">Add your first vehicle to start creating trips and accepting passengers.</p>
                    <a href="{{ route('driver.vehicles.create') }}" class="mt-6 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#256b29] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2">
                        Add your first vehicle
                    </a>
                </div>
            @else
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" id="vehicle-list">
                    @foreach ($vehicles as $vehicle)
                        @php
                            $vehicleColour = mb_strtolower(trim($vehicle->colour));
                            $colourTheme = match ($vehicleColour) {
                                'black' => ['panel' => 'bg-slate-900', 'icon' => 'text-slate-400'],
                                'navy', 'dark blue' => ['panel' => 'bg-blue-950', 'icon' => 'text-blue-300'],
                                'dark grey', 'dark gray' => ['panel' => 'bg-slate-700', 'icon' => 'text-slate-300'],
                                'grey', 'gray' => ['panel' => 'bg-gray-300', 'icon' => 'text-gray-500'],
                                'silver' => ['panel' => 'bg-slate-200', 'icon' => 'text-slate-500'],
                                'white' => ['panel' => 'border border-gray-200 bg-white', 'icon' => 'text-gray-400'],
                                'red' => ['panel' => 'bg-red-100', 'icon' => 'text-red-500'],
                                'blue' => ['panel' => 'bg-blue-100', 'icon' => 'text-blue-500'],
                                'green' => ['panel' => 'bg-green-100', 'icon' => 'text-green-600'],
                                'yellow' => ['panel' => 'bg-yellow-100', 'icon' => 'text-yellow-600'],
                                'orange' => ['panel' => 'bg-orange-100', 'icon' => 'text-orange-500'],
                                'purple' => ['panel' => 'bg-purple-100', 'icon' => 'text-purple-500'],
                                'brown' => ['panel' => 'bg-amber-200', 'icon' => 'text-amber-800'],
                                'gold' => ['panel' => 'bg-amber-100', 'icon' => 'text-amber-600'],
                                'beige', 'cream' => ['panel' => 'bg-stone-200', 'icon' => 'text-stone-500'],
                                default => ['panel' => 'bg-gray-100', 'icon' => 'text-gray-400'],
                            };
                        @endphp
                        <article class="rounded-2xl border border-gray-200 bg-white p-5" x-data="{ submitting: false }">
                            <div class="flex h-24 items-center justify-center rounded-xl {{ $colourTheme['panel'] }} {{ $colourTheme['icon'] }}">
                                <x-icons.lucide name="car-front" class="h-9 w-9" />
                            </div>

                            <div class="mt-5 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-bold text-gray-950">{{ $vehicle->brand }} {{ $vehicle->model }}</h3>
                                    <p class="mt-0.5 font-mono text-sm text-gray-400">{{ $vehicle->plate_number }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold {{ $vehicle->status === 'Active' ? 'border-green-200 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                                        <span class="h-2 w-2 rounded-full {{ $vehicle->status === 'Active' ? 'bg-green-600' : 'bg-gray-400' }}" aria-hidden="true"></span>
                                        {{ $vehicle->status }}
                                    </span>
                                </div>
                            </div>

                            <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-y border-gray-100 py-4 text-sm text-gray-600">
                                <div>
                                    <dt class="sr-only">Colour</dt>
                                    <dd>{{ $vehicle->colour }}</dd>
                                </div>
                                <div>
                                    <dt class="sr-only">Passenger seats</dt>
                                    <dd>{{ $vehicle->seat_capacity }} {{ Str::plural('seat', $vehicle->seat_capacity) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-3">
                                <a
                                    x-show="managementMode === 'details'"
                                    href="{{ route('driver.vehicles.show', $vehicle) }}"
                                    class="inline-flex min-h-[40px] w-full items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#256b29] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
                                >
                                    Manage
                                </a>

                                <form
                                    x-cloak
                                    x-show="managementMode === 'availability'"
                                    method="POST"
                                    action="{{ $vehicle->status === 'Active' ? route('driver.vehicles.deactivate', $vehicle) : route('driver.vehicles.activate', $vehicle) }}"
                                    @submit="submitting = true"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" :disabled="submitting" class="inline-flex min-h-[40px] w-full items-center justify-center rounded-lg border border-[#2E7D32] px-4 py-2 text-sm font-semibold text-[#2E7D32] hover:bg-green-50 disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">
                                        {{ $vehicle->status === 'Active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
