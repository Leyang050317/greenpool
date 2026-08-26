<x-app-layout>
    <div class="px-4 py-8 sm:px-6 lg:px-8" x-data="{ confirmDelete: false }">
        <div class="mx-auto max-w-4xl">
            @if (session('success'))
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800" role="status">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mb-5">
                <a href="{{ route('driver.vehicles.index') }}" class="text-sm font-semibold text-[#2E7D32] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">← Back to vehicles</a>
            </div>

            <article class="rounded-2xl border border-gray-200 bg-white">
                <div class="p-6 pb-0">
                    @if ($vehicle->vehicle_image_path)
                        <img src="{{ asset('storage/'.$vehicle->vehicle_image_path) }}" alt="{{ $vehicle->brand }} {{ $vehicle->model }} vehicle" class="aspect-[16/7] w-full rounded-xl object-cover">
                    @else
                        <div class="flex aspect-[16/7] w-full items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                            <x-icons.lucide name="car-front" class="h-10 w-10" />
                        </div>
                    @endif
                </div>
                <div class="flex flex-col gap-5 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-green-50 text-[#2E7D32]">
                            <x-icons.lucide name="car-front" class="h-7 w-7" />
                        </span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-2xl font-bold text-gray-900">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                            </div>
                            <p class="mt-1 font-mono text-sm font-semibold tracking-wide text-gray-600">{{ $vehicle->plate_number }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 self-start sm:self-auto">
                        <span class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $vehicle->status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $vehicle->status }}</span>
                        <span class="rounded-full border px-3 py-1.5 text-sm font-semibold {{ match($vehicle->verification_status) { 'Verified' => 'border-green-200 bg-green-50 text-green-700', 'Rejected' => 'border-red-200 bg-red-50 text-red-700', default => 'border-amber-200 bg-amber-50 text-amber-700' } }}">{{ $vehicle->verification_status }}</span>
                    </div>
                </div>

                <dl class="grid gap-px bg-gray-100 sm:grid-cols-2">
                    @foreach ([
                        'Plate number' => $vehicle->plate_number,
                        'Brand' => $vehicle->brand,
                        'Model' => $vehicle->model,
                        'Colour' => $vehicle->colour,
                        'Passenger seats' => $vehicle->seat_capacity,
                    ] as $label => $value)
                        <div class="bg-white px-6 py-5 {{ $label === 'Passenger seats' ? 'sm:col-span-2' : '' }}">
                            <dt class="text-sm text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-base font-semibold text-gray-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="flex flex-wrap gap-3 border-t border-gray-100 p-6">
                    <a href="{{ route('driver.vehicles.edit', $vehicle) }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">
                        Edit vehicle
                    </a>

                    @if ($hasBlockingTrips)
                        <div class="ml-auto text-right">
                            <button type="button" disabled class="inline-flex min-h-[44px] cursor-not-allowed items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-400">
                                Archive vehicle
                            </button>
                            <p class="max-w-xs text-xs text-gray-500">Cancel or complete the assigned trip before deleting this vehicle.</p>
                        </div>
                    @else
                        <button type="button" @click="confirmDelete = true" class="ml-auto inline-flex min-h-[44px] items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600">
                            Archive vehicle
                        </button>
                    @endif
                </div>
            </article>
        </div>

        <div x-cloak x-show="confirmDelete" @keydown.escape.window="confirmDelete = false" class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-vehicle-title">
            <button type="button" class="absolute inset-0 bg-gray-950/45" @click="confirmDelete = false" aria-label="Close confirmation"></button>
            <div x-show="confirmDelete" x-transition class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6">
                <h3 id="delete-vehicle-title" class="text-lg font-bold text-gray-900">Archive this vehicle?</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->plate_number }}) will be hidden from vehicle management and new trips. You can restore it later from Archived Vehicles.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="confirmDelete = false" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">Cancel</button>
                    <form method="POST" action="{{ route('driver.vehicles.destroy', $vehicle) }}" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @method('DELETE')
                        <button type="submit" :disabled="submitting" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                            <span x-show="!submitting">Archive vehicle</span>
                            <span x-cloak x-show="submitting">Archiving…</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
