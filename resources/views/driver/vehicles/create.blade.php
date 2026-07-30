<x-app-layout>
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('driver.vehicles.index') }}" class="text-sm font-semibold text-[#2E7D32] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">← Back to vehicles</a>

            <div class="mt-5 rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-gray-900">Add a vehicle</h2>
                    <p class="mt-1 text-sm text-gray-600">Enter the details exactly as shown on the vehicle registration.</p>
                </div>

                <form method="POST" action="{{ route('driver.vehicles.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @include('driver.vehicles._form', ['vehicle' => null])

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                        <a href="{{ route('driver.vehicles.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">
                            Cancel
                        </a>
                        <button type="submit" :disabled="submitting" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29] disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2">
                            <span x-show="!submitting">Add vehicle</span>
                            <span x-cloak x-show="submitting">Adding vehicle…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
