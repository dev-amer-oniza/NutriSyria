{{--
    "أكلتها" on a meal's page: how much of it, and which meal of the day (this hour's meal is
    chosen first), then "سجّل · 337 سعرة". Param $meal (MealFacts). Logs to the person's day.
    $custom (false): under "نتيجة طبختك", where the form sends the recipe itself (customRecipe
    ::logEaten), the plate is the person's own, and the server works the numbers out again.
--}}
@php
    $per100g = $meal->servingGrams === null;
    $kcal = (float) ($meal->nutrients['energy_kcal'] ?? 0);
    $amounts = \App\Services\Daily\DailyLog::amountsFor($meal);
    $type = \App\Enums\MealType::forHour(\App\Services\Daily\DailyLog::now()->hour);
    $first = old('amount', 1.0);
    $custom ??= false;
    $field = $custom ? 'eaten' : 'amount';
@endphp
<div class="ns-ate" x-data="{ sheet: {{ $errors->has('amount') ? "'ate'" : 'null' }}, amount: {{ (float) $first }}, kcal: {{ $kcal }} }">
    <button class="ns-btn ns-btn--primary ns-ate__go" type="button" @click="sheet = 'ate'">
        @include('site.partials.icon', ['name' => 'plus'])أكلتها
    </button>
    <p class="ns-ate__hint">{{ $custom ? 'بقيم طبختك إنت،' : 'بتنحسب' }} من احتياجك اليوم، بـ<a class="ns-link" href="{{ route('site.daily') }}">يومي</a>.</p>

    <div class="ns-sheet-wrap" x-show="sheet === 'ate'" x-cloak @keydown.escape.window="sheet = null">
        <div class="ns-sheet-dim" @click="sheet = null"></div>
        <div class="ns-sheet" role="dialog" aria-modal="true" aria-labelledby="ate-title">
            <span class="ns-sheet__grab" aria-hidden="true"></span>
            <div class="ns-sheet__head">
                <span class="ns-sheet__mark">@include('site.partials.icon', ['name' => 'bowl'])</span>
                <div>
                    <h2 class="ns-sheet__title" id="ate-title">{{ $custom ? 'قديش أكلت من طبختك؟' : 'قديش أكلت من '.$meal->name.'؟' }}</h2>
                    <p class="ns-sheet__lead">{{ $custom ? 'الصحن هو حصة من الحصص اللي كتبتها بوصفتك.' : ($per100g ? 'قيمها لكل 100 غ، فاختار الوزن تقريباً.' : 'الصحن هو حصة المطعم الكاملة.') }}</p>
                </div>
                <button class="ns-sheet__close" type="button" @click="sheet = null" aria-label="إغلاق">@include('site.partials.icon', ['name' => 'close'])</button>
            </div>

            <form class="ns-form ns-sheet__form" method="post"
                  action="{{ $custom ? route('site.daily.custom', $meal->id) : route('site.daily.store', $meal->id) }}"
                  @if ($custom) @submit.prevent="logEaten($el)" @endif>
                @csrf
                <fieldset class="ns-field">
                    <legend class="ns-field__label">{{ $per100g ? 'الوزن' : 'من الصحن' }}</legend>
                    <div class="ns-amounts">
                        @foreach ($amounts as $a)
                            <label class="ns-amount">
                                <input type="radio" name="{{ $field }}" value="{{ $a }}" x-model.number="amount" @checked((float) $first === (float) $a)>
                                <span class="ns-amount__box">
                                    @unless ($per100g)
                                        <span @class(['ns-amount__pie', 'is-more' => $a > 1]) style="--fill: {{ min(100, $a * 100) }}%"></span>
                                    @endunless
                                    <span class="ns-amount__name">{{ \App\Services\Daily\DailyLog::amountLabel($a, $per100g) }}</span>
                                    <span class="ns-amount__kcal"><span class="ns-num">{{ round($kcal * $a) }}</span> سعرة</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('amount')<p class="ns-field__error">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="ns-field">
                    <legend class="ns-field__label">أي وجبة؟</legend>
                    <div class="ns-choice-chips">
                        @foreach (\App\Enums\MealType::cases() as $t)
                            <label class="ns-choice-chip">
                                <input type="radio" name="meal_type" value="{{ $t->value }}" @checked(old('meal_type', $type->value) === $t->value)>
                                <span>{{ $t->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($custom)
                    <p class="ns-field__error" x-show="eatError" x-text="eatError" x-cloak></p>
                @endif
                <button class="ns-btn ns-btn--primary ns-sheet__submit" type="submit" @if ($custom) :disabled="eating" @endif>
                    سجّل · <span class="ns-num" x-text="Math.round(kcal * amount)">{{ round($kcal * $first) }}</span> سعرة
                </button>
            </form>
        </div>
    </div>
</div>
