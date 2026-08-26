@props(['name', 'title', 'message', 'confirmLabel', 'action', 'method' => 'PATCH', 'variant' => 'primary', 'disabled' => false, 'disabledMessage' => ''])

<div x-data="{ open: false }" class="inline-flex">
    <button type="button" @click="open = true" @disabled($disabled) title="{{ $disabled ? $disabledMessage : '' }}" {{ $attributes->class([$disabled ? 'cursor-not-allowed bg-gray-100 text-gray-400' : ($variant === 'danger' ? 'border border-red-100 bg-red-50 text-red-600 hover:bg-red-100' : 'bg-[#16A34A] text-white hover:bg-[#15803D]')]) }}>{{ $slot }}</button>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/40" @click="open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"><h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2><p class="mt-2 w-full min-w-0 whitespace-normal break-words text-sm leading-6 text-gray-500 [overflow-wrap:anywhere]">{{ $message }}</p><div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" @click="open = false" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button><form method="POST" action="{{ $action }}">@csrf @method($method)<button class="rounded-xl px-4 py-2 text-sm font-medium {{ $variant === 'danger' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-[#16A34A] text-white hover:bg-[#15803D]' }}">{{ $confirmLabel }}</button></form></div></div>
    </div>
</div>
