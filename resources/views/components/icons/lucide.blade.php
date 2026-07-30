@props(['name'])

<svg
    {{ $attributes->merge([
        'class' => 'h-[21px] w-[21px] shrink-0',
        'fill' => 'none',
        'viewBox' => '0 0 24 24',
        'stroke' => 'currentColor',
        'stroke-width' => '2',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
    ]) }}
>
    @switch($name)
        @case('layout-dashboard')
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
            @break
        @case('route')
            <circle cx="6" cy="19" r="3" />
            <path d="M9 19h6.5a3.5 3.5 0 0 0 0-7h-8a3.5 3.5 0 0 1 0-7H18" />
            <circle cx="18" cy="5" r="3" />
            @break
        @case('clipboard-list')
            <rect width="16" height="18" x="4" y="3" rx="2" />
            <path d="M9 5a3 3 0 0 1 6 0" />
            <path d="M8 11h.01M12 11h4M8 15h.01M12 15h4" />
            @break
        @case('car-front')
            <path d="m21 8-2 2-1.5-3.7A2 2 0 0 0 15.65 5h-7.3A2 2 0 0 0 6.5 6.3L5 10 3 8" />
            <path d="M7 14h.01M17 14h.01" />
            <rect width="18" height="8" x="3" y="10" rx="2" />
            <path d="M5 18v2M19 18v2" />
            @break
        @case('map-pinned')
            <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15" />
            <circle cx="15" cy="10" r="2" />
            @break
        @case('bell')
            <path d="M10.3 21h3.4M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9" />
            @break
        @case('user-circle')
            <circle cx="12" cy="12" r="10" />
            <circle cx="12" cy="10" r="3" />
            <path d="M6.2 18.4c.9-2.1 3.1-3.4 5.8-3.4s4.9 1.3 5.8 3.4" />
            @break
        @case('settings')
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
            <circle cx="12" cy="12" r="3" />
            @break
        @case('circle-help')
            <circle cx="12" cy="12" r="10" />
            <path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4M12 18h.01" />
            @break
        @case('panel-left-open')
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <path d="M9 3v18M14 9l3 3-3 3" />
            @break
        @case('panel-left-close')
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <path d="M9 3v18M16 15l-3-3 3-3" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('x')
            <path d="M18 6 6 18M6 6l12 12" />
            @break
    @endswitch
</svg>
