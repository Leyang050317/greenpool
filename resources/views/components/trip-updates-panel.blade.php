@props(['trip', 'role'])

@php
    $updates = $role === 'driver'
        ? ['traffic_delay' => 'Traffic Delay', 'running_late' => 'Running Late', 'vehicle_problem' => 'Vehicle Problem', 'road_hazard' => 'Road Hazard', 'other' => 'Other Update']
        : ['waiting' => "I'm Waiting", 'running_late' => 'Running Late', 'pickup_clarification' => 'Need Pickup Clarification', 'other' => 'Other Update'];
@endphp

<section x-data="{ open: false }" class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm sm:p-5" aria-label="Trip updates">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 text-[#16A34A]"><x-icons.lucide name="message-square" class="h-4 w-4" /></div>
            <div><h3 class="text-sm font-semibold text-gray-900">Trip Updates</h3><p class="mt-1 text-xs leading-5 text-gray-500">Send a quick non-emergency update to {{ $role === 'driver' ? 'accepted passengers' : 'your driver' }}.</p></div>
        </div>
        <button type="button" @click="open = true" class="min-h-10 shrink-0 rounded-xl border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-100">Send Update</button>
    </div>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/40" @click="open = false"></div>
        <form method="POST" action="{{ route('trips.updates.store', $trip) }}" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            @csrf
            <h2 class="text-lg font-semibold text-gray-900">Send Trip Update</h2>
            <p class="mt-2 text-sm text-gray-500">Use Report Emergency instead if anyone is in immediate danger.</p>
            <fieldset class="mt-5 space-y-3"><legend class="text-sm font-medium text-gray-700">Select an update</legend>@foreach($updates as $value => $label)<label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 hover:border-green-200 hover:bg-green-50"><input type="radio" name="update_type" value="{{ $value }}" required class="text-green-600 focus:ring-green-500" />{{ $label }}</label>@endforeach</fieldset>
            <div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" @click="open = false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><button class="rounded-xl bg-[#16A34A] px-4 py-2 text-sm font-semibold text-white hover:bg-[#15803D]">Send Update</button></div>
        </form>
    </div>
</section>
