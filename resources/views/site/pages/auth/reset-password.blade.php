@extends('site.layouts.auth')

@section('title', 'كلمة مرور جديدة')
@section('heading', 'كلمة مرور جديدة')
@section('lead')
    أدخل الرمز الذي وصل إلى <bdi class="ns-latin">{{ $email }}</bdi>، ثم اختر كلمة مرور جديدة.
@endsection

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.password.reset') }}" novalidate>
        @csrf
        @include('site.components.form.code')
        @include('site.components.form.password', ['label' => 'كلمة المرور الجديدة', 'autocomplete' => 'new-password', 'hint' => '8 أحرف أو أكثر.'])
        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">احفظ كلمة المرور</button>
    </form>

    {{-- Asking again posts the same email back to the "forgot password" form. --}}
    <form class="ns-resend" method="post" action="{{ route('site.password.request') }}">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <span>لم يصلك الرمز؟</span>
        <button class="ns-link ns-resend__btn" type="submit">أرسله مجدداً</button>
    </form>
@endsection
