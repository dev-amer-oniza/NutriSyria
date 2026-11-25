{{--
    "وزنك اليوم": a stepper (−/＋ 0.1 kg) and the number itself, saved for today. Opened by the
    page's Alpine `sheet` = 'weight'. Params $weight (WeightCard), $managed (a nutritionist
    manages the profile: the weight goes to them).
--}}
@php($start = old('weight_kg', $weight->current ?? 70))
<div class="ns-sheet-wrap" x-show="sheet === 'weight'" x-cloak @keydown.escape.window="sheet = null">
    <div class="ns-sheet-dim" @click="sheet = null"></div>
    <div class="ns-sheet" role="dialog" aria-modal="true" aria-labelledby="weigh-title">
        <span class="ns-sheet__grab" aria-hidden="true"></span>
        <div class="ns-sheet__head">
            <span class="ns-sheet__mark">@include('site.partials.icon', ['name' => 'scale'])</span>
            <div>
                <h2 class="ns-sheet__title" id="weigh-title">وزنك اليوم</h2>
                <p class="ns-sheet__lead">أفضل وقت: الصبح قبل الأكل، وبنفس الميزان كل مرة.</p>
            </div>
            <button class="ns-sheet__close" type="button" @click="sheet = null" aria-label="إغلاق">@include('site.partials.icon', ['name' => 'close'])</button>
        </div>

        <form class="ns-form ns-sheet__form" method="post" action="{{ route('site.daily.weight') }}"
              x-data="{ kg: {{ (float) $start }}, step(d) { this.kg = Math.min(300, Math.max(30, Math.round((Number(this.kg) + d) * 10) / 10)); } }">
            @csrf
            <div class="ns-stepper">
                <button class="ns-stepper__btn" type="button" @click="step(-0.1)" aria-label="ناقص 100 غرام">−</button>
                <label class="ns-stepper__value">
                    <input class="ns-stepper__input ns-num" type="number" name="weight_kg" inputmode="decimal" step="0.1" min="30" max="300"
                           x-model="kg" value="{{ $start }}" aria-label="وزنك بالكيلوغرام">
                    <span>كغ</span>
                </label>
                <button class="ns-stepper__btn" type="button" @click="step(0.1)" aria-label="زائد 100 غرام">＋</button>
            </div>
            @error('weight_kg')<p class="ns-field__error">{{ $message }}</p>@enderror

            <p class="ns-weigh-note">
                @if ($managed)
                    بيوصل وزنك لأخصائيّك، وهو بيحدّث ملفك ومعاييرك.
                @else
                    بيتحدّث ملفك واحتياجك اليومي عليه لحالهم.
                @endif
            </p>

            <button class="ns-btn ns-btn--primary ns-sheet__submit" type="submit">سجّل</button>
        </form>
    </div>
</div>
