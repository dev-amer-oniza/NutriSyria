@extends('site.layouts.app')

@section('title', 'أخصائيو التغذية')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">أخصائيو التغذية</p>
                    <h1 class="t-title">اختر أخصائياً يحدد معاييرك</h1>
                    <p class="t-lead ns-muted">يراجع سجلك الصحي بعد موافقتك، ويختار لك القالب والأرقام التي تُقيَّم عليها وجباتك. الاستشارة الأولى مجانية.</p>
                </div>
            </div>

            <form class="ns-filters" method="get" action="{{ route('site.nutritionists') }}">
                <label class="ns-sr" for="f-specialty">التخصص</label>
                <select class="ns-input" id="f-specialty" name="specialty">
                    <option value="">كل التخصصات</option>
                    @foreach ($specialties as $value => $label)
                        <option value="{{ $value }}" @selected($specialty?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="ns-sr" for="f-city">المحافظة</label>
                <select class="ns-input" id="f-city" name="city">
                    <option value="">كل المحافظات</option>
                    @foreach ($cities as $value => $label)
                        <option value="{{ $value }}" @selected($city?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="ns-btn ns-btn--primary" type="submit">اعرض</button>
                @if ($specialty || $city)
                    <a class="ns-link" href="{{ route('site.nutritionists') }}">إزالة الفلترة</a>
                @endif
            </form>

            @if ($profiles->isNotEmpty())
                <div class="ns-nutris">
                    @foreach ($profiles as $profile)
                        @include('site.components.nutritionist-card', ['profile' => $profile])
                    @endforeach
                </div>
            @else
                <div class="ns-empty">
                    @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 40])
                    <p class="t-body ns-muted">
                        @if ($specialty || $city)
                            لا يوجد أخصائي بهذه الفلترة الآن. جرّب تخصصاً أو محافظة أخرى.
                        @else
                            قريباً ينضم أخصائيو التغذية إلى المنصة.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
