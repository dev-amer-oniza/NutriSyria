{{--
    Body mass index for the person themself (gentle labels, decision 3a). Params: $bmi
    (App\Data\Nutrition\BmiAssessment), $goalUrl (where to change the goal, or null when locked).
    The scale runs from 15 to 45; each band is one class of config nutrition_criteria.bmi.
--}}
@php
    $scaleFrom = 15;
    $scaleTo = 45;
    $edges = [$scaleFrom, ...array_map(fn ($c) => $c['below'] ?? $scaleTo, config('nutrition_criteria.bmi.classes'))];
    $marker = max(0, min(100, ($bmi->value - $scaleFrom) / ($scaleTo - $scaleFrom) * 100));
@endphp
<section class="ns-bmi" aria-labelledby="bmi-title">
    <div class="ns-bmi__head">
        <h2 class="ns-bmi__title" id="bmi-title">مؤشر كتلة الجسم</h2>
        <p class="ns-bmi__value"><span class="ns-num">{{ number_format($bmi->value, 1) }}</span>@if ($bmi->classified()) <span class="ns-bmi__label" data-class="{{ $bmi->class }}">{{ $bmi->label }}</span>@endif</p>
    </div>

    @if ($bmi->classified())
        <div class="ns-bmi__scale" aria-hidden="true">
            @foreach (config('nutrition_criteria.bmi.classes') as $i => $class)
                <span @class(['ns-bmi__band', 'is-current' => $class['key'] === $bmi->class]) style="flex-grow: {{ $edges[$i + 1] - $edges[$i] }}"></span>
            @endforeach
            <span class="ns-bmi__marker" style="inset-inline-start: {{ round($marker, 1) }}%"></span>
        </div>
        <p class="ns-bmi__range">الوزن الصحي لطولك: بين <span class="ns-num">{{ $bmi->healthyMinKg }}</span> و<span class="ns-num">{{ $bmi->healthyMaxKg }}</span> كغ.</p>
        @if ($bmi->warning)
            <p class="ns-bmi__warning" role="note">{{ $bmi->warning }}@if ($goalUrl) <a class="ns-link" href="{{ $goalUrl }}">غيّر هدفك</a>@endif</p>
        @endif
        <p class="ns-field__hint">المؤشر تقدير عام: لا يفرّق بين العضل والدهون، ولا يغني عن رأي أخصائي.</p>
    @else
        <p class="ns-field__hint">في الحمل والرضاعة لا يعبّر المؤشر عن حالتك، ويتابع طبيبك وزنك.</p>
    @endif
</section>
