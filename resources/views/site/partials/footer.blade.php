<footer class="ns-foot">
    <div class="ns-wrap">
        <div class="ns-foot__top">
            <div class="ns-foot__brand">
                @include('site.partials.logo', ['onPine' => true])
                <p>منصة معرفية للتغذية، تعرض القيم الغذائية لوجبات المطاعم السورية بمصادر واضحة، ومدى مناسبة كل وجبة لك.</p>
            </div>

            @foreach (config('site.footer') as $heading => $links)
                <div>
                    <h3 class="ns-foot__h">{{ $heading }}</h3>
                    <ul>
                        @foreach ($links as $link)
                            <li><a href="{{ \App\Support\Site\SiteLink::url($link['route']) }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="ns-foot__base">
            <p>المعلومات في NutriSyria للتثقيف الغذائي، ولا تغني عن استشارة أخصائي تغذية أو طبيب.</p>
            <p class="ns-latin">© {{ now()->year }} NutriSyria</p>
        </div>
    </div>
</footer>
