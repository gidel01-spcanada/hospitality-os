@props(['name'])

<svg {{ $attributes->merge(['class' => 'icon', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.5 2.5 3.8 5.5 3.8 9S14.5 18.5 12 21c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3Z" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18M8 14h2M14 14h2M8 18h2" />
            @break
        @case('user-circle')
            <circle cx="12" cy="12" r="9" /><circle cx="12" cy="9" r="3" /><path d="M6.8 18.2c1.2-2.2 3-3.2 5.2-3.2s4 1 5.2 3.2" />
            @break
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
            @break
        @case('messages')
            <path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /><path d="M7 8h10M7 12h7" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3" /><path d="M3.5 19v-1.5A4.5 4.5 0 0 1 8 13h2a4.5 4.5 0 0 1 4.5 4.5V19M15 5.5a3 3 0 0 1 0 5.5M17 13a4 4 0 0 1 3.5 4v2" />
            @break
        @case('reservations')
            <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H11v18H6.5A2.5 2.5 0 0 0 4 22V4.5ZM20 4.5A2.5 2.5 0 0 0 17.5 2H13v18h4.5A2.5 2.5 0 0 1 20 22V4.5Z" />
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break
        @case('cleaning')
            <path d="m14 4 6 6M17 7 8 16M8 12l4 4M6 14l4 4M4 16l4 4M3 21h8l3-3-8-8-3 3v8Z" />
            @break
        @case('properties')
            <circle cx="8" cy="15" r="4" /><path d="m11 12 9-9M16 7l3 3M13.5 9.5l2 2" />
            @break
        @case('establishments')
            <path d="M4 21V5l8-3v19M12 8h8v13M2 21h20M7 7h2M7 11h2M7 15h2M15 12h2M15 16h2" />
            @break
        @case('amenities')
            <path d="M3 20v-8M21 20v-6a3 3 0 0 0-3-3H8a3 3 0 0 0-3 3v2h16M5 11V8a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3M3 20h18" />
            @break
        @case('settings')
            <path d="M4 7h10M18 7h2M4 17h2M10 17h10M4 12h4M12 12h8" /><circle cx="16" cy="7" r="2" /><circle cx="8" cy="17" r="2" /><circle cx="10" cy="12" r="2" />
            @break
        @case('tenants')
            <path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
            @break
    @endswitch
</svg>
