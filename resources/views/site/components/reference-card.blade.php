{{--
    One card of the sources page (App\Data\Nutrition\References\ReferenceCard): its rules with
    their kind, value and references; an unverified rule says so plainly.
    Params: $card, $references (MedicalReferences).
--}}
<article class="ns-ref-card" id="{{ $card->anchor }}" aria-labelledby="{{ $card->anchor }}-title">
    <header class="ns-ref-card__head">
        <h4 class="ns-ref-card__title" id="{{ $card->anchor }}-title">{{ $card->title }}</h4>
        <span class="ns-ref-status ns-ref-status--{{ $card->status }}">{{ $card->statusLabel() }}</span>
    </header>
    @if ($card->description)<p class="ns-ref-card__text">{{ $card->description }}</p>@endif
    @if ($card->template)
        <p class="ns-ref-card__text">تجلب قالب <a class="ns-link" href="#{{ $card->template['anchor'] }}">{{ $card->template['label'] }}</a> بحدوده.</p>
    @endif
    @if ($card->refs)
        <p class="ns-ref-card__text">لا تعطي المراجع لهذه الحالة أرقاماً خاصة، فنطبّق الاحتياج العام الموثّق.</p>
        @include('site.components.reference-quotes', ['keys' => $card->refs])
    @elseif ($card->status === 'general' && ! $card->template)
        <p class="ns-ref-card__text">بلا حدود خاصة: نطبّق الاحتياج العام.</p>
    @endif

    @if ($card->rules)
        <ul class="ns-ref-rules">
            @foreach ($card->rules as $rule)
                <li class="ns-ref-rule">
                    <div class="ns-ref-rule__line">
                        <strong>{{ $rule->label }}</strong>
                        <span>{{ $rule->value }}</span>
                        <span class="ns-ref-kind ns-ref-kind--{{ $rule->infoOnly ? 'info' : $rule->kind }}">{{ $rule->kindLabel() }}</span>
                        @if (! $rule->verified && ! $rule->infoOnly)<span class="ns-ref-status ns-ref-status--pending">قيد المراجعة</span>@endif
                    </div>
                    @if ($rule->note)<p class="ns-ref-rule__note">{{ $rule->note }}</p>@endif
                    @if ($rule->refs)
                        @include('site.components.reference-quotes', ['keys' => $rule->refs])
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($card->hasPending())
        <p class="ns-ref-rule__pending">ما هو «قيد المراجعة» نراجعه مع المراجع العالمية، وحتى ذلك الحين لا يدخل في تقييم الوجبات.</p>
    @endif
    @if ($card->advice)<p class="ns-ref-card__advice">{{ $card->advice }}</p>@endif
</article>
