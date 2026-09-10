@props(['trip', 'role'])

@php
    $updates = $role === 'driver'
        ? ['traffic_delay' => 'Traffic Delay', 'running_late' => 'Running Late', 'vehicle_problem' => 'Vehicle Problem', 'road_hazard' => 'Road Hazard', 'other' => 'Other Update']
        : ['waiting' => "I'm Waiting", 'running_late' => 'Running Late', 'pickup_clarification' => 'Need Pickup Clarification', 'other' => 'Other Update'];
@endphp

<section x-data="{ open: false, selected: '' }" class="rounded-2xl border border-green-100 bg-white p-4 shadow-sm sm:p-5" aria-label="Trip quick replies">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 text-[#16A34A]"><x-icons.lucide name="message-square" class="h-4 w-4" /></div>
            <div><h3 class="text-sm font-semibold text-gray-900">Quick replies</h3><p class="mt-1 text-xs leading-5 text-gray-500">Send a quick non-emergency update to {{ $role === 'driver' ? 'accepted passengers' : 'your driver' }}.</p></div>
        </div>
        <button type="button" @click="open = !open" :aria-expanded="open" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-100 sm:w-auto"><x-icons.lucide name="message-square" class="h-4 w-4" /><span x-text="open ? 'Hide replies' : 'Quick replies'"></span></button>
    </div>
    <form x-cloak x-show="open" x-transition method="POST" action="{{ route('trips.updates.store', $trip) }}" class="mt-4 border-t border-gray-100 pt-4">
        @csrf
        <p class="mb-3 text-xs text-gray-500">Use Report Emergency instead if anyone is in immediate danger.</p>
        <fieldset class="space-y-2">
            <legend class="sr-only">Choose a quick reply</legend>
            @foreach($updates as $value => $label)
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 transition hover:border-green-200 hover:bg-green-50 has-[:checked]:border-green-400 has-[:checked]:bg-green-50 has-[:checked]:font-semibold has-[:checked]:text-green-800">
                    <input x-model="selected" type="radio" name="update_type" value="{{ $value }}" required class="text-green-600 focus:ring-green-500" />
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>
        <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" @click="open = false; selected = ''" class="min-h-11 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
            <button :disabled="!selected" class="min-h-11 rounded-xl bg-[#16A34A] px-4 py-2 text-sm font-semibold text-white hover:bg-[#15803D] disabled:cursor-not-allowed disabled:bg-gray-300">Send update</button>
        </div>
    </form>
</section>
