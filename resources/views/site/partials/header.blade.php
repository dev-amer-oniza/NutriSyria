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
            @auth
                @if (auth()->user()->canAccessPanel(filament()->getPanel('admin')))
                    <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ filament()->getPanel('admin')->getUrl() }}">لوحة التحكم</a>
                @endif
                @include('site.partials.bell', ['bell' => $bell])
                <a class="ns-header__user" href="{{ route('site.criteria') }}" title="معاييري">{{ auth()->user()->name }}</a>
                <form method="post" action="{{ route('site.logout') }}">
                    @csrf
                    <button class="ns-btn ns-btn--secondary ns-btn--sm" type="submit">خروج</button>
                </form>
            @else
                <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ route('site.login') }}">تسجيل الدخول</a>
                <a class="ns-btn ns-btn--primary ns-btn--sm" href="{{ route('site.register') }}">أنشئ حسابك</a>
            @endauth
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
