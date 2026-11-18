{{--
    "ماذا تستطيع أن تفعل": changes tried on the meal and judged again, only those that
    help. Params: $suggestions (list<MealSuggestion>), $meal (MealFacts), $editor (true inside
    "اطبخها على طريقتك": the button applies the change to the recipe being edited).
--}}
@php
    $restaurant = ! $meal->platform;
    $editor ??= false;
@endphp
@if ($suggestions)
    <div class="ns-suggest">
        <p class="ns-suggest__title">ماذا تستطيع أن تفعل</p>
        <ul class="ns-suggest__list">
            @foreach ($suggestions as $s)
                @php
                    [$name, $unit] = $s->nutrient ? \App\Services\Site\MealCatalog::nutrientName($s->nutrient) : ['', ''];
                @endphp
                <li class="ns-suggest__item">
                    <div class="ns-suggest__text">
                        <strong>{{ $s->text($restaurant) }}</strong>@if ($s->amount !== null)<span>، و{{ $name }} فيها <span class="ns-num">{{ \App\Services\Nutrition\Fit\MealFitCalculator::number($s->amount) }}</span> {{ $unit }}</span>@endif
                    </div>
                    <div class="ns-suggest__then">
                        @include('site.components.fit-verdict', ['level' => $s->level, 'compact' => true])
                        @if (! $restaurant)
                            @if ($editor)
                                <button type="button" class="ns-btn ns-btn--ghost ns-btn--sm" @click="apply(@js($s->change()))">جرّبها</button>
                            @else
                                <a class="ns-btn ns-btn--ghost ns-btn--sm" href="{{ route('site.kitchen.recipe', $meal->id) }}?{{ http_build_query($s->change()) }}">اطبخها هكذا</a>
                            @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="ns-suggest__note">جرّبناها وأعدنا التقييم كاملاً@if ($restaurant)، ونذكر ما يمكنك طلبه من المطعم فقط@endif.</p>
    </div>
@endif
