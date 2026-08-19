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
        @case('circle-check')
            <circle cx="12" cy="12" r="10" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('chart-no-axes-combined')
            <path d="M4 19V5" />
            <path d="M4 19h16" />
            <path d="m7 15 4-4 3 2 4-5" />
            <circle cx="7" cy="15" r="1" />
            <circle cx="11" cy="11" r="1" />
            <circle cx="14" cy="13" r="1" />
            <circle cx="18" cy="8" r="1" />
            @break
        @case('smartphone')
            <rect width="12" height="20" x="6" y="2" rx="2" />
            <path d="M10 18h4" />
            @break
        @case('monitor')
            <rect width="18" height="12" x="3" y="3" rx="2" />
            <path d="M8 21h8M12 15v6" />
            @break
        @case('shield-check')
            <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('badge-check')
            <path d="M12 3a3 3 0 0 0 4.24 1.76A3 3 0 0 0 19.24 8 3 3 0 0 0 21 12a3 3 0 0 0-1.76 4.24A3 3 0 0 0 16 19.24 3 3 0 0 0 12 21a3 3 0 0 0-4.24-1.76A3 3 0 0 0 4.76 16 3 3 0 0 0 3 12a3 3 0 0 0 1.76-4.24A3 3 0 0 0 8 4.76 3 3 0 0 0 12 3Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('star')
            <path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z" />
            @break
        @case('plus')
            <path d="M5 12h14M12 5v14" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('arrow-up-right')
            <path d="M7 17 17 7M7 7h10v10" />
            @break
        @case('arrow-down-right')
            <path d="m7 7 10 10M17 17V7M17 17H7" />
            @break
        @case('arrow-left')
            <path d="M19 12H5M11 18l-6-6 6-6" />
            @break
        @case('calendar')
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('message-square')
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('eye')
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12" />
            <circle cx="12" cy="12" r="3" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('circle-alert')
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v4M12 16h.01" />
            @break
        @case('circle-x')
            <circle cx="12" cy="12" r="10" />
            <path d="m15 9-6 6M9 9l6 6" />
            @break
        @case('clock-3')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5h4" />
            @break
        @case('filter')
            <path d="M4 5h16M7 12h10M10 19h4" />
            @break
        @case('history')
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5M12 7v5l3 2" />
            @break
        @case('image-up')
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="m21 15-5-5L5 21M15 5v4M13 7h4" />
            @break
        @case('lock')
            <rect width="14" height="11" x="5" y="11" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
            @break
        @case('lock-keyhole')
            <rect width="14" height="11" x="5" y="11" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v3" />
            <circle cx="12" cy="15" r="1" />
            @break
        @case('mail')
            <rect width="18" height="14" x="3" y="5" rx="2" />
            <path d="m3 7 9 6 9-6" />
            @break
        @case('send')
            <path d="m22 2-7 20-4-9-9-4Z" />
            <path d="M22 2 11 13" />
            @break
        @case('pencil')
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
            @break
        @case('map-pin')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
            <circle cx="12" cy="10" r="2.5" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
            @break
        @case('car')
            <path d="m5 11 1.5-3.7A2 2 0 0 1 8.35 6h7.3a2 2 0 0 1 1.85 1.3L19 11" />
            <path d="M5 11h14a2 2 0 0 1 2 2v4H3v-4a2 2 0 0 1 2-2Z" />
            <path d="M5 17v2M19 17v2M7 14h.01M17 14h.01" />
            @break
        @case('armchair')
            <path d="M4 11V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5M12 11V7a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4M4 11h16v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2ZM6 18v2M18 18v2" />
            @break
        @case('dollar-sign')
            <path d="M12 2v20M17 5.5c-1.2-1-2.8-1.5-5-1.5-3 0-5 1.5-5 4s2 3.5 5 4 5 1.5 5 4-2 4-5 4c-2.2 0-3.8-.5-5-1.5" />
            @break
        @case('align-left')
            <path d="M3 6h18M3 12h12M3 18h16" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
            @break
        @case('play')
            <path d="m8 5 11 7-11 7Z" />
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
