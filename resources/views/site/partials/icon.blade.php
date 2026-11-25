{{-- Line icons, 24px grid, stroke 1.6. Params: $name, $size ('sm' optional). --}}
<svg @class(['ns-icon', 'ns-icon--'.$name, 'ns-icon--sm' => ($size ?? null) === 'sm']) viewBox="0 0 24 24" aria-hidden="true">
    @switch($name)
        @case('bread')
            <path d="M5 11.5a3.5 3.5 0 0 1 1-6.9h12a3.5 3.5 0 0 1 1 6.9V19a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1Z"/><path d="M9 11v5M12 10v6M15 11v5"/>
            @break
        @case('pot')
            <path d="M4 10h16v7a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3Z"/><path d="M2.5 10h19"/><path d="M9 6.5c0-1 .8-1 .8-2M12 6.5c0-1 .8-1 .8-2M15 6.5c0-1 .8-1 .8-2"/>
            @break
        @case('user')
            <circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c.9-3.6 4-5.6 7.5-5.6s6.6 2 7.5 5.6"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('close')
            <path d="M6 6l12 12M18 6 6 18"/>
            @break
        @case('logout')
            <path d="M10 4.5H6.5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2H10"/><path d="M14 8l-4 4 4 4M10 12h10"/>
            @break
        @case('panel')
            <rect x="4" y="4.5" width="16" height="15" rx="2"/><path d="M4 9.5h16M9.5 9.5v10"/>
            @break
        @case('walk')
            <circle cx="13" cy="4.5" r="1.8"/><path d="M11 21l2-6-2.5-2.5L12 8l3 3h3"/><path d="M12 8 8.5 9.5 7 13"/><path d="M13 15l2.5 6"/>
            @break
        @case('run')
            <circle cx="15" cy="4.5" r="1.8"/><path d="M5 20l4-1 2-4"/><path d="M8 11l3-3 4 2 1 3h3"/><path d="M11 8l-1 6 4 2-1 5"/>
            @break
        @case('dumbbell')
            <path d="M3 9v6M6 7v10M18 7v10M21 9v6M6 12h12"/>
            @break
        @case('gym')
            <path d="M4 20h16"/><path d="M7 20V9l5-4 5 4v11"/><path d="M10 20v-5h4v5"/>
            @break
        @case('cardio')
            <path d="M3 12h4l2-5 3 10 2-5h7"/>
            @break
        @case('bike')
            <circle cx="6" cy="16" r="3.5"/><circle cx="18" cy="16" r="3.5"/><path d="M6 16l4-7h5l3 7M10 9l2 7h-6M14 6h2"/>
            @break
        @case('swim')
            <path d="M3 17c1.5 1.2 3 1.2 4.5 0s3-1.2 4.5 0 3 1.2 4.5 0 3-1.2 4.5 0"/><path d="M6 13l4-4 3 2 3-3"/><circle cx="17" cy="6" r="1.8"/>
            @break
        @case('ball')
            <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5l3.5 2.5-1.3 4h-4.4l-1.3-4Z"/><path d="M12 3.5v4M15.5 10l4.2-1.5M14.2 14l2.6 3.6M9.8 14l-2.6 3.6M8.5 10 4.3 8.5"/>
            @break
        @case('boxing')
            <path d="M7 11V7a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v5a4 4 0 0 1-4 4H9"/><path d="M7 11h4v3"/><path d="M8 16v4h8v-4"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.4 15.4 20 20"/>
            @break
        @case('arrow')
            <path d="M14.5 6 8.5 12l6 6"/>
            @break
        @case('back')
            <path d="M9.5 6l6 6-6 6"/>
            @break
        @case('bell')
            <path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 1.5h-15Z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>
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
