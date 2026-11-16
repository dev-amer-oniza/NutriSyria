@extends('site.layouts.app')

@section('title', 'اطبخها على طريقتك: '.$meal->name)

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-mine-page"
             x-data="customRecipe(@js([
                 'recipe' => $recipe,
                 'ingredientsUrl' => route('site.kitchen.ingredients'),
                 'analyzeUrl' => route('site.kitchen.recipe.analyze', $meal->id),
                 'storageKey' => 'ns.myRecipe.'.$meal->id,
                 'maxLines' => \App\Http\Requests\Kitchen\CustomRecipeRequest::MAX_LINES,
             ]))">
            <a class="ns-link" href="{{ route('site.meals.show', $meal->id) }}">{{ $meal->name }}</a>
            <header class="ns-mine-page__head">
                <p class="ns-eyebrow">المطبخ السوري</p>
                <h1 class="t-title">اطبخها على طريقتك: {{ $meal->name }}</h1>
                <p class="t-body ns-muted">هذه وصفتنا. غيّر المقادير والمكوّنات وعدد الحصص كما تطبخها في بيتك، ثم اضغط «حلّل». لا نحفظ شيئاً منها: تبقى في هذا التبويب فقط.</p>
            </header>

            <form class="ns-mine-form" @submit.prevent="analyze" novalidate>
                @csrf
                <p class="ns-field__hint" x-show="loading">نحضّر قائمة المكوّنات…</p>

                <ol class="ns-mine-lines">
                    <template x-for="(line, i) in lines" :key="line.key">
                        <li class="ns-mine-line">
                            <div class="ns-mine-line__food">
                                <label class="ns-field__label" :for="'food-' + line.key">المكوّن</label>
                                <input class="ns-input" type="text" autocomplete="off" :id="'food-' + line.key"
                                       :value="line.open ? line.query : label(line.record_id)"
                                       @focus="openSearch(line)" @input="line.query = $event.target.value; line.open = true"
                                       @keydown.escape="line.open = false" @blur="closeLater(line)"
                                       placeholder="ابحث: عدس، زيت زيتون…">
                                {{-- Only the open line renders its results. --}}
                                <template x-if="line.open">
                                    <ul class="ns-mine-results" role="listbox">
                                        <template x-for="option in matches(line.query)" :key="option.id">
                                            <li role="option" @mousedown.prevent="choose(line, option)">
                                                <strong x-text="option.name"></strong>
                                                <span x-text="(option.form ? option.form + ' · ' : '') + option.source"></span>
                                            </li>
                                        </template>
                                        <li class="ns-mine-results__none" x-show="matches(line.query).length === 0">لا نتائج: جرّب كلمة أخرى.</li>
                                    </ul>
                                </template>
                            </div>

                            <div class="ns-mine-line__amount">
                                <label class="ns-field__label" :for="'amount-' + line.key">الكمية</label>
                                <input class="ns-input" type="number" inputmode="decimal" min="0" step="any" dir="ltr" :id="'amount-' + line.key" x-model.number="line.amount">
                            </div>

                            <div class="ns-mine-line__unit">
                                <label class="ns-field__label" :for="'unit-' + line.key">الوحدة</label>
                                <select class="ns-input" :id="'unit-' + line.key" x-model.number="line.unit_id" @change="presetSize(line)">
                                    <template x-for="unit in unitsOf(line)" :key="unit.id">
                                        <option :value="unit.id" x-text="unit.name" :selected="unit.id === line.unit_id"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="ns-mine-line__size" x-show="choicesOf(line).length">
                                <label class="ns-field__label" :for="'size-' + line.key">حجم الحصة</label>
                                <select class="ns-input" :id="'size-' + line.key" x-model.number="line.portion_id">
                                    <option :value="null">اختر الحجم</option>
                                    <template x-for="choice in choicesOf(line)" :key="choice.id">
                                        <option :value="choice.id" x-text="choice.label" :selected="choice.id === line.portion_id"></option>
                                    </template>
                                </select>
                            </div>

                            <label class="ns-mine-line__after">
                                <input type="checkbox" x-model="line.after">
                                <span>يُضاف بعد الطبخ</span>
                            </label>

                            <p class="ns-mine-line__grams" x-text="gramsText(line)"></p>
                            <button type="button" class="ns-mine-line__remove" @click="remove(i)" :aria-label="'احذف ' + label(line.record_id)">حذف</button>
                        </li>
                    </template>
                </ol>

                <button type="button" class="ns-btn ns-btn--ghost" @click="add()" :disabled="lines.length >= maxLines">أضف مكوّناً</button>

                <div class="ns-form__cols ns-mine-form__totals">
                    <div class="ns-field">
                        <label class="ns-field__label" for="servings">عدد الحصص</label>
                        <input class="ns-input" id="servings" type="number" min="1" max="50" inputmode="numeric" dir="ltr" x-model.number="servings">
                    </div>
                    <div class="ns-field">
                        <label class="ns-field__label" for="weight-after">الوزن بعد الطبخ (غ)، اختياري</label>
                        <input class="ns-input" id="weight-after" type="number" min="1" inputmode="decimal" dir="ltr" x-model.number="weightAfter" placeholder="إن وزنت الطبخة">
                        <p class="ns-field__hint">إن تركته فارغاً قدّرناه بنسبة ما تخسره وصفتنا في الطبخ.</p>
                    </div>
                </div>

                <p class="ns-mine-form__error" role="alert" x-show="error" x-text="error"></p>

                <div class="ns-book__actions">
                    <button type="submit" class="ns-btn ns-btn--primary" :disabled="busy || loading">
                        <span x-show="!busy">حلّل</span><span x-show="busy">نحلّل…</span>
                    </button>
                    <button type="button" class="ns-btn ns-btn--ghost" @click="reset()">ارجع لوصفتنا</button>
                </div>
            </form>

            <div class="ns-mine-page__result" x-ref="result" x-html="resultHtml" aria-live="polite"></div>
        </div>
    </section>
@endsection
