@extends('site.layouts.app')

@section('title', 'حالة الكبد')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-criteria-page">
            <div class="ns-section__head">
                <div>
                    <p class="ns-eyebrow">حسابي</p>
                    <h1 class="t-title">{{ \App\Services\Nutrition\LiverUpdate::TITLE }}</h1>
                    <p class="t-lead ns-muted">اخترت سابقاً «الكبد الوبائي أو التليّف»، وصارت الآن ثلاث حالات. اختر ما يناسبك حتى نقيّم وجباتك على الحالة الصحيحة.</p>
                </div>
            </div>

            <form class="ns-liver" method="post" action="{{ route('site.liver.update') }}" novalidate>
                @csrf @method('put')
                <fieldset class="ns-liver__choices">
                    <legend class="ns-sr">حالتك</legend>
                    @foreach ($choices as $choice)
                        <label class="ns-liver__choice">
                            <input type="radio" name="choice" value="{{ $choice->value }}" @checked(old('choice') === $choice->value)>
                            <span><b>{{ $choice->label() }}</b><small>{{ $choice->hint() }}</small></span>
                        </label>
                    @endforeach
                    <label class="ns-liver__choice">
                        <input type="radio" name="choice" value="{{ \App\Services\Nutrition\LiverUpdate::UNKNOWN }}" @checked(old('choice') === \App\Services\Nutrition\LiverUpdate::UNKNOWN)>
                        <span><b>لا أعرف بعد</b><small>نقيّم وجباتك على احتياجك العام حتى تعرف. اسأل طبيبك عن نوع التهاب الكبد.</small></span>
                    </label>
                </fieldset>
                @error('choice')<p class="ns-field__error">{{ $message }}</p>@enderror
                <div class="ns-form__row">
                    <button class="ns-btn ns-btn--primary" type="submit">احفظ</button>
                    <a class="ns-btn ns-btn--ghost" href="{{ route('site.criteria') }}">ليس الآن</a>
                </div>
            </form>
        </div>
    </section>
@endsection
