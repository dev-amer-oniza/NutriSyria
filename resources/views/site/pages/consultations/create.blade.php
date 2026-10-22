@extends('site.layouts.app')

@section('title', 'طلب استشارة')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-book">
            <a class="ns-link" href="{{ route('site.nutritionists.show', $profile) }}">العودة إلى صفحة الأخصائي</a>
            <h1 class="t-title">طلب استشارة</h1>
            @include('site.components.nutritionist-person', ['profile' => $profile])

            <form class="ns-form ns-book__form" method="post" action="{{ route('site.consultations.create', $profile) }}" novalidate
                  x-data="{ count: @js(mb_strlen((string) old('reason'))) }">
                @csrf
                @include('site.components.form.honeypot')

                <div class="ns-field">
                    <label class="ns-field__label" for="f-reason">لماذا تريد الاستشارة؟</label>
                    <textarea class="ns-input ns-textarea" id="f-reason" name="reason" rows="4" required autofocus
                              maxlength="{{ \App\Http\Requests\Consultations\ConsultationRequestForm::REASON_MAX }}"
                              placeholder="مثلاً: عندي سكري من النوع الثاني، وأريد خطة تناسب أكلنا اليومي."
                              @input="count = $el.value.length"
                              @error('reason') aria-invalid="true" aria-describedby="f-reason-error" @enderror>{{ old('reason') }}</textarea>
                    <p class="ns-field__hint">سطران يكفيان، وتكملان على واتساب. <span class="ns-num" x-text="count"></span> من <span class="ns-num">{{ \App\Http\Requests\Consultations\ConsultationRequestForm::REASON_MAX }}</span> حرف.</p>
                    @error('reason')<p class="ns-field__error" id="f-reason-error">{{ $message }}</p>@enderror
                </div>

                @include('site.components.form.field', [
                    'name' => 'whatsapp', 'label' => 'رقم واتساب', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel',
                    'dir' => 'ltr', 'placeholder' => '0912 345 678', 'value' => $whatsapp,
                    'hint' => 'يظهر للأخصائي بعد قبول طلبك فقط، ونحفظه في حسابك للمرة القادمة.',
                ])

                <div>
                    <label class="ns-check ns-consent">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent'))>
                        <span>أوافق على أن يرى هذا الأخصائي سجلي الصحي (حالاتي وحساسياتي) بعد قبوله طلبي، ليحدد معاييري. يتوقف وصوله حين أنهي الارتباط.</span>
                    </label>
                    @error('consent')<p class="ns-field__error">{{ $message }}</p>@enderror
                </div>

                <p class="ns-book__note">قبل القبول يرى الأخصائي اسمك وسببك وعمرك وجنسك وهدفك فقط. <b>الاستشارة الأولى مجانية.</b> إن لم يرد خلال {{ \App\Services\Consultations\ConsultationService::EXPIRES_AFTER_DAYS }} أيام ينتهي الطلب، وتختار غيره.</p>

                <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">أرسل الطلب</button>
            </form>
        </div>
    </section>
@endsection
