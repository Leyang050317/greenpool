<x-app-layout>
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-[30px] font-bold leading-tight text-gray-950">Archived Vehicles</h2>
                    <p class="mt-1.5 text-base text-gray-600">Restore a vehicle if you want to use it again.</p>
                </div>
                <a href="{{ route('driver.vehicles.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">
                    Back to My Vehicles
                </a>
            </div>

            @if ($vehicles->isEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                        <x-icons.lucide name="archive" class="h-7 w-7" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold text-gray-900">No archived vehicles</h3>
                    <p class="mt-2 text-sm text-gray-600">Vehicles you archive will appear here.</p>
                </div>
            @else
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ($vehicles as $vehicle)
                        <article class="rounded-2xl border border-gray-200 bg-white p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-bold text-gray-950">{{ $vehicle->brand }} {{ $vehicle->model }}</h3>
                                    <p class="mt-1 font-mono text-sm text-gray-500">{{ $vehicle->plate_number }}</p>
                                    <p class="mt-3 text-sm text-gray-600">{{ $vehicle->colour }} · {{ $vehicle->seat_capacity }} {{ Str::plural('seat', $vehicle->seat_capacity) }}</p>
                                    <p class="mt-1 text-xs text-gray-400">Archived {{ $vehicle->deleted_at?->format('d M Y, g:i A') }}</p>
                                </div>
                                <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-600">Archived</span>
                            </div>

                            <form method="POST" action="{{ route('driver.vehicles.restore', $vehicle->vehicle_id) }}" class="mt-5" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                @method('PATCH')
                                <button type="submit" :disabled="submitting" class="inline-flex min-h-[42px] w-full items-center justify-center rounded-xl bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29] disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2">
                                    <span x-show="!submitting">Restore vehicle</span>
                                    <span x-cloak x-show="submitting">Restoring…</span>
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
