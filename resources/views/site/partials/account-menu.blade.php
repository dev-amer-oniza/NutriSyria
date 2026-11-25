{{--
    The signed-in person's menu (the owner's request): a round account button in the header
    that opens everything about them, "يومي", "معاييري", "أخصائيّي", the panel for staff, and
    signing out. It stays in the header on every screen width.
--}}
@php($user = auth()->user())
<div class="ns-acct" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button class="ns-acct__button" type="button" @click="open = ! open" aria-haspopup="true"
            :aria-expanded="open" aria-expanded="false" aria-controls="account-menu" aria-label="حسابي: {{ $user->name }}">
        @include('site.partials.icon', ['name' => 'user'])
    </button>

    <div class="ns-acct__menu" id="account-menu" x-show="open" x-cloak x-transition.opacity>
        <div class="ns-acct__who">
            <span class="ns-acct__avatar" aria-hidden="true">{{ mb_substr(trim($user->name), 0, 1) }}</span>
            <span class="ns-acct__name"><b>{{ $user->name }}</b><small dir="ltr">{{ $user->email }}</small></span>
        </div>
        <nav class="ns-acct__links" aria-label="حسابي">
            @foreach ([['site.daily', 'chart', 'يومي'], ['site.criteria', 'target', 'معاييري'], ['site.my-nutritionist', 'heart', 'أخصائيّي']] as [$route, $icon, $label])
                <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>
                    @include('site.partials.icon', ['name' => $icon]){{ $label }}
                </a>
            @endforeach
            @if ($user->canAccessPanel(filament()->getPanel('admin')))
                <a href="{{ filament()->getPanel('admin')->getUrl() }}">@include('site.partials.icon', ['name' => 'panel'])لوحة التحكم</a>
            @endif
        </nav>
        <form class="ns-acct__out" method="post" action="{{ route('site.logout') }}">
            @csrf
            <button type="submit">@include('site.partials.icon', ['name' => 'logout'])تسجيل الخروج</button>
        </form>
    </div>
</div>
