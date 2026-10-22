{{-- One meal's values, then how it fits each person (the example people, or just you). Param $meal (FitPanel). --}}
<section class="ns-panel" aria-labelledby="panel-title">
    <div class="ns-panel__head">
        <div>
            <h2 class="ns-panel__title" id="panel-title">{{ $meal['name'] }}</h2>
            <p class="ns-panel__sub">{{ $meal['brand'] }} · حصة <span class="ns-num">{{ $meal['portion'] }}</span> غ</p>
        </div>
        @include('site.components.verify-badge', ['level' => $meal['verification']])
    </div>

    <div class="ns-energy__fig">
        <span class="ns-num t-figure-xl">{{ $meal['kcal'] }}</span>
        <span class="ns-energy__unit">سعرة حرارية</span>
    </div>

    @include('site.components.macro-bar', $meal['macros'])

    <div class="ns-panel__stats">
        @include('site.components.nutrient-stat', ['label' => 'بروتين', 'value' => $meal['macros']['protein'], 'key' => 'protein'])
        @include('site.components.nutrient-stat', ['label' => 'كربوهيدرات', 'value' => $meal['macros']['carbs'], 'key' => 'carbs'])
        @include('site.components.nutrient-stat', ['label' => 'دهون', 'value' => $meal['macros']['fat'], 'key' => 'fat'])
        @include('site.components.nutrient-stat', ['label' => 'ألياف', 'value' => $meal['fiber']])
    </div>

    <div class="ns-fit">
        <p class="ns-fit__ask" id="fit-ask">{{ $meal['ask'] ?? 'الوجبة نفسها لا تناسب الجميع بالقدر نفسه. لمن تريد أن تراها؟' }}</p>

        @if (count($meal['people']) > 1)
            <div class="ns-fit__people" role="radiogroup" aria-labelledby="fit-ask">
                @foreach ($meal['people'] as $i => $person)
                    <button class="ns-chip" type="button" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}"
                            :aria-checked="active === {{ $i }}" @click="choose({{ $i }})">{{ $person['label'] }}</button>
                @endforeach
            </div>
        @endif

        @foreach ($meal['people'] as $i => $person)
            <div class="ns-fit__person" x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif aria-live="polite">
                @include('site.components.fit-result', $person)
            </div>
        @endforeach
    </div>

    <p class="ns-panel__source">
        @include('site.partials.icon', ['name' => 'document', 'size' => 'sm'])
        <span>{{ $meal['source'] }}</span>
        <a href="{{ $meal['link'] ?? route('site.method') }}">{{ $meal['link_label'] ?? 'المنهجية' }}</a>
    </p>
</section>
