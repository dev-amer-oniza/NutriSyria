{{-- Params: $featured (one approved meal with example people), $suggestions (search chips). --}}
<section class="ns-hero" id="search">
    <div class="ns-wrap ns-hero__in">
        <div class="ns-hero__copy" x-data="{ q: '' }">
            <p class="ns-eyebrow">القيم الغذائية لوجبات المطاعم السورية</p>
            <h1 class="t-display ns-hero__title">اعرف ما في وجبتك<br><span>وكم تناسبك أنت</span></h1>
            <p class="t-lead ns-muted ns-hero__lead">قيم غذائية موثّقة المصدر لوجبات المطاعم، يراجعها أخصائي تغذية. ابحث عن وجبة أو امسح رمزها على الطاولة، واعرف مدى مناسبتها لجسمك وهدفك.</p>

            @include('site.components.search-field', [
                'id' => 'q',
                'placeholder' => 'ابحث عن وجبة أو مطعم…',
                'action' => \App\Support\Site\SiteLink::url('site.meals'),
                'model' => 'q',
            ])

            <div class="ns-try">
                <span>جرّب:</span>
                @foreach ($suggestions as $term)
                    <button class="ns-chip" type="button" @click="q = @js($term); $refs.q.focus()">{{ $term }}</button>
                @endforeach
            </div>
        </div>

        <div class="ns-hero__visual" x-data="fitDemo">
            @include('site.components.chain-tiles', ['pattern' => 'hero', 'tile' => 64, 'class' => 'ns-hero__field', 'ref' => 'field'])
            @include('site.sections.home.fit-panel', ['meal' => $featured])
        </div>
    </div>
</section>
