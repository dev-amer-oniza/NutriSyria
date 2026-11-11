{{-- The pine band: join, and the platform's three promises. --}}
@php($points = [
    ['icon' => 'document', 'title' => 'معلومات موثوقة', 'text' => 'كل رقم بمصدره، ويراجعه أخصائي تغذية', 'route' => 'site.method'],
    ['icon' => 'chart', 'title' => 'تحليل دقيق', 'text' => 'القيم الغذائية لكل حصة من كل وجبة', 'route' => 'site.meals'],
    ['icon' => 'leaf', 'title' => 'مناسب لك', 'text' => 'حكم حسب جسمك وهدفك وحالتك الصحية', 'route' => 'site.goals'],
])
<section class="ns-join" aria-labelledby="join-title">
    <div class="ns-wrap ns-join__in">
        <div class="ns-join__lead">
            <div>
                <h2 class="ns-join__title" id="join-title">معاً نحو غذاء أفضل في سوريا</h2>
                <p class="ns-join__text">اعرف ما في وجبتك قبل أن تطلبها، واختر ما يناسبك.</p>
            </div>
            @auth
                <a class="ns-btn ns-btn--on-pine" href="{{ route('site.meals') }}">تصفّح الوجبات@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
            @else
                <a class="ns-btn ns-btn--on-pine" href="{{ route('site.register') }}">أنشئ حسابك@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
            @endauth
        </div>
        <ul class="ns-join__points">
            @foreach ($points as $point)
                <li>
                    <a href="{{ route($point['route']) }}">
                        @include('site.partials.icon', ['name' => $point['icon']])
                        <b>{{ $point['title'] }}</b>
                        <span>{{ $point['text'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
