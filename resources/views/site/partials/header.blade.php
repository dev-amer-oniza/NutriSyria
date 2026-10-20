@php($nav = config('site.nav'))

<header class="ns-header" x-data="{ open: false }" @keydown.escape.window="open = false">
    <div class="ns-header__in">
        @include('site.partials.logo')

        <nav class="ns-nav" aria-label="القائمة الرئيسية">
            @include('site.partials.nav-links', ['items' => $nav])
        </nav>

        <div class="ns-header__actions">
            <a class="ns-btn ns-btn--ghost ns-btn--icon" href="{{ route('home') }}#search" aria-label="بحث">
                @include('site.partials.icon', ['name' => 'search'])
            </a>
            <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ route('site.login') }}">تسجيل الدخول</a>
            <a class="ns-btn ns-btn--primary ns-btn--sm" href="{{ route('site.register') }}">أنشئ حسابك</a>
            <button class="ns-btn ns-btn--ghost ns-btn--icon ns-header__menu" type="button"
                    aria-controls="site-drawer" :aria-expanded="open" aria-expanded="false" aria-label="القائمة"
                    @click="open = ! open">
                @include('site.partials.icon', ['name' => 'menu'])
            </button>
        </div>
    </div>

    <div class="ns-drawer" id="site-drawer" x-show="open" x-cloak x-transition.opacity>
        <nav aria-label="القائمة الرئيسية">
            @include('site.partials.nav-links', ['items' => $nav])
        </nav>
    </div>
</header>
