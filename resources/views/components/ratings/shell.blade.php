@props(['pageTitle' => 'Ratings'])

@if(Auth::user()->role === 'driver')
    <x-app-layout>
        {{ $slot }}
    </x-app-layout>
@else
    @include('passenger.booking.layout', [
        'slotContent' => $slot,
        'pageTitle' => $pageTitle,
    ])
@endif
