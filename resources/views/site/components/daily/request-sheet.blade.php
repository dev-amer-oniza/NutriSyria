{{--
    "ما لقيتها؟": the dish's name and what is in it, sent to the team (FoodRequests). Opened by
    the page's Alpine `sheet` = 'request'. Param $type: the meal of this hour, chosen first.
--}}
@php($types = \App\Enums\MealType::cases())
<div class="ns-sheet-wrap" x-show="sheet === 'request'" x-cloak @keydown.escape.window="sheet = null">
    <div class="ns-sheet-dim" @click="sheet = null"></div>
    <div class="ns-sheet" role="dialog" aria-modal="true" aria-labelledby="request-title">
        <span class="ns-sheet__grab" aria-hidden="true"></span>
        <div class="ns-sheet__head">
            <span class="ns-sheet__mark">@include('site.partials.icon', ['name' => 'pot'])</span>
            <div>
                <h2 class="ns-sheet__title" id="request-title">أكلت شي مو موجود؟</h2>
                <p class="ns-sheet__lead">ابعتلنا اسمها وشو فيها، ورح نشتغل على توافرها. لحد ما نضيفها بتبين بيومك بلا ما تنحسب.</p>
            </div>
            <button class="ns-sheet__close" type="button" @click="sheet = null" aria-label="إغلاق">@include('site.partials.icon', ['name' => 'close'])</button>
        </div>

        <form class="ns-form ns-sheet__form" method="post" action="{{ route('site.daily.request') }}">
            @csrf
            @include('site.components.form.honeypot')

            <div class="ns-field">
                <label class="ns-field__label" for="food_name">اسم الأكلة</label>
                <input class="ns-input" id="food_name" name="food_name" maxlength="{{ \App\Http\Requests\Daily\FoodRequestForm::NAME_MAX }}"
                       value="{{ old('food_name', request('name')) }}" placeholder="مثلاً: شاورما دجاج" required>
                @error('food_name')<p class="ns-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="ns-field">
                <label class="ns-field__label" for="ingredients">شو فيها؟</label>
                <textarea class="ns-input ns-textarea" id="ingredients" name="ingredients" rows="3"
                          maxlength="{{ \App\Http\Requests\Daily\FoodRequestForm::INGREDIENTS_MAX }}"
                          placeholder="مثلاً: خبز صاج، دجاج متبّل، توم، مخلل، بطاطا مقلية" required>{{ old('ingredients') }}</textarea>
                <p class="ns-field__hint">اكتبلنا لمحة عن مكوّناتها، لنضيف أكلة بنفس المكوّنات اللي بتاكلها.</p>
                @error('ingredients')<p class="ns-field__error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="ns-field ns-chips-field">
                <legend class="ns-field__label">أي وجبة كانت؟</legend>
                <div class="ns-choice-chips">
                    @foreach ($types as $t)
                        <label class="ns-choice-chip">
                            <input type="radio" name="meal_type" value="{{ $t->value }}" @checked(old('meal_type', $type->value) === $t->value)>
                            <span>{{ $t->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button class="ns-btn ns-btn--primary ns-sheet__submit" type="submit">ابعتها لفريق NutriSyria</button>
        </form>
    </div>
</div>
