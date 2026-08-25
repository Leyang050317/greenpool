@if(Auth::user()->role === 'driver')
    <x-app-layout>
        <x-trip-toast />
        @if(session('error'))
            <div class="fixed bottom-6 right-6 z-50 max-w-sm rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">{{ session('error') }}</div>
        @endif
        {{ $slot }}
    </x-app-layout>
@else
    @include('passenger.booking.layout', [
        'slotContent' => new Illuminate\Support\HtmlString(view('components.trip-toast')->render().(session('error') ? '<div class="fixed bottom-6 right-6 z-50 max-w-sm rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-lg">'.e(session('error')).'</div>' : '').$slot),
        'pageTitle' => 'Payments',
    ])
@endif
