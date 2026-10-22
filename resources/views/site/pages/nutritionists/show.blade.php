@extends('site.layouts.app')

@section('title', $profile->display_name)

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-nutri-page">
            <div class="ns-nutri-page__main">
                <a class="ns-link" href="{{ route('site.nutritionists') }}">كل الأخصائيين</a>

                <div class="ns-nutri-page__head">
                    <div class="ns-nutri-page__photo">
                        @if ($profile->photoUrl())
                            <img src="{{ $profile->photoUrl() }}" alt="{{ $profile->display_name }}">
                        @else
                            @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 60])
                        @endif
                    </div>
                    <div>
                        <p class="ns-eyebrow">أخصائي تغذية</p>
                        <h1 class="t-title">{{ $profile->display_name }}</h1>
                        <ul class="ns-nutri__tags">
                            @foreach ($profile->specialtyLabels() as $label)
                                <li>{{ $label }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <dl class="ns-nutri-page__facts">
                    <div><dt>المحافظة</dt><dd>{{ $profile->city->label() }}</dd></div>
                    <div><dt>الاستشارة</dt><dd>{{ $profile->consultation_mode->label() }}</dd></div>
                    @if ($profile->years_experience)
                        <div><dt>الخبرة</dt><dd><span class="ns-num">{{ $profile->years_experience }}</span> سنة</dd></div>
                    @endif
                    @if ($profile->clinic_address)
                        <div><dt>العيادة</dt><dd>{{ $profile->clinic_address }}</dd></div>
                    @endif
                </dl>

                <h2 class="t-heading">نبذة</h2>
                <p class="t-body ns-nutri-page__bio">{{ $profile->bio }}</p>
            </div>

            <aside class="ns-nutri-page__book" aria-labelledby="book-title">
                <h2 class="ns-advice__title" id="book-title">الاستشارة الأولى مجانية</h2>
                <ol class="ns-nutri-page__steps">
                    <li>ترسل طلباً قصيراً وتوافق على مشاركة سجلك الصحي.</li>
                    <li>يوافق الأخصائي، فتتواصلان على واتساب.</li>
                    <li>يختار لك القالب والمعايير، فتُقيَّم كل وجبة على أساسها.</li>
                </ol>
                <span class="ns-btn ns-btn--primary ns-nutri-page__cta" aria-disabled="true">طلب الاستشارة متاح قريباً</span>
                <p class="ns-field__hint">يمكنك إنهاء الارتباط متى شئت، فيتوقف وصول الأخصائي إلى سجلك.</p>
            </aside>
        </div>
    </section>
@endsection
