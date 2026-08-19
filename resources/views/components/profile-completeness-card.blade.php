@props(['profileCompleteness'])

<section
    class="rounded-2xl border border-green-100 bg-gradient-to-br from-green-50 via-white to-white p-5 shadow-sm"
    x-data="{ progress: 0, target: {{ $profileCompleteness['percentage'] }} }"
    x-init="$nextTick(() => { progress = target })"
>
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-100 text-[#2E7D32]"><x-icons.lucide name="chart-no-axes-combined" class="h-5 w-5" /></span>
            <div><h3 class="text-base font-bold text-gray-950">Profile Completeness</h3><p class="mt-0.5 text-xs text-gray-500">Complete your details for a safer ride-sharing experience.</p></div>
        </div>
        <span class="text-xl font-bold text-[#2E7D32]">{{ $profileCompleteness['percentage'] }}%</span>
    </div>

    <div class="mt-5 h-3 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Profile completeness" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress">
        <div class="h-full rounded-full bg-gradient-to-r from-[#2E7D32] to-green-400 transition-all duration-1000 ease-out" :style="{ width: progress + '%' }"></div>
    </div>

    @if ($profileCompleteness['next_action_hint'])
        <p class="mt-4 flex items-center gap-2 text-sm font-medium text-gray-700"><x-icons.lucide name="arrow-down-right" class="h-4 w-4 shrink-0 text-[#2E7D32]" />{{ $profileCompleteness['next_action_hint'] }}</p>
    @else
        <p class="mt-4 flex items-center gap-2 text-sm font-semibold text-green-700"><x-icons.lucide name="circle-check" class="h-4 w-4 shrink-0" />Your profile is fully optimized for rides!</p>
    @endif
</section>
