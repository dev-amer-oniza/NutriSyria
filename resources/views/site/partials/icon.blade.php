{{-- Line icons, 24px grid, stroke 1.6. Params: $name, $size ('sm' optional). --}}
<svg @class(['ns-icon', 'ns-icon--sm' => ($size ?? null) === 'sm']) viewBox="0 0 24 24" aria-hidden="true">
    @switch($name)
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.4 15.4 20 20"/>
            @break
        @case('arrow')
            <path d="M14.5 6 8.5 12l6 6"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('document')
            <path d="M6.5 3.5h7l4 4v13h-11Z"/><path d="M13.5 3.5v4h4"/><path d="M9 12h6M9 15.5h4"/>
            @break
        @case('chart')
            <rect x="4" y="12" width="3.6" height="8" rx="1"/><rect x="10.2" y="5" width="3.6" height="15" rx="1"/><rect x="16.4" y="9" width="3.6" height="11" rx="1"/>
            @break
        @case('bowl')
            <path d="M3 11.5h18a9 9 0 0 1-18 0Z"/><path d="M12.5 11.5c.2-3.3 2.3-5.5 5.6-6-.3 3.3-2.4 5.5-5.6 6Z"/><path d="M8.5 8.2c-.6-1.2-.6-2.4 0-3.6"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r=".9" fill="currentColor"/>
            @break
        @case('compare')
            <rect x="3.5" y="4.5" width="7" height="15" rx="2"/><rect x="13.5" y="4.5" width="7" height="15" rx="2"/><path d="M6 9.5h2M16 9.5h2M6 13h2M16 13h2"/>
            @break
        @case('qr')
            <rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2.5v2.5H14zM17.5 17.5H20V20h-2.5zM14 20h1.5M20 14v1.5"/>
            @break
        @case('drop')
            <path d="M12 3.5c3.6 4.3 6 7.5 6 10.6a6 6 0 0 1-12 0c0-3.1 2.4-6.3 6-10.6Z"/>
            @break
        @case('leaf')
            <path d="M12 20.5v-8.5"/><path d="M12 12c0-3.6 2.4-6.2 6.2-6.2 0 3.6-2.5 6.2-6.2 6.2Z"/><path d="M12 14.5c0-2.9-2-5-5-5 0 2.9 2 5 5 5Z"/>
            @break
        @case('heart')
            <path d="M12 19.5s-7.5-4.4-7.5-10a4.2 4.2 0 0 1 7.5-2.6 4.2 4.2 0 0 1 7.5 2.6c0 5.6-7.5 10-7.5 10Z"/>
            @break
        @case('scale')
            <path d="M5 20h14"/><path d="M12 4v16"/><path d="M5 8h14"/><path d="M5 8l-2.5 6a2.5 2.5 0 0 0 5 0Z"/><path d="M19 8l-2.5 6a2.5 2.5 0 0 0 5 0Z"/>
            @break
    @endswitch
</svg>
