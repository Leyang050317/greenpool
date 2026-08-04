@if(session('success'))
    <div x-data="{ open: true }" x-init="setTimeout(() => open = false, 3500)" x-cloak x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-sm font-medium text-white shadow-lg">
        <span class="shrink-0 text-lg leading-none text-[#4ADE80]">●</span>
        <span>{{ session('success') }}</span>
        <button type="button" @click="open = false" class="ml-2 text-gray-400 transition-colors hover:text-white" aria-label="Close notification">×</button>
    </div>
@endif
