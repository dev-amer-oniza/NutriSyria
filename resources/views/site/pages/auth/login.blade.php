@extends('site.layouts.auth')

@section('title', 'تسجيل الدخول')
@section('heading', 'أهلاً بعودتك')
@section('lead', 'سجّل الدخول لترى مدى مناسبة الوجبات لك.')

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.login') }}" novalidate>
        @csrf
        @include('site.components.form.honeypot')
        @include('site.components.form.google-button')

        @include('site.components.form.field', ['name' => 'email', 'label' => 'البريد الإلكتروني', 'type' => 'email', 'autocomplete' => 'username', 'inputmode' => 'email', 'dir' => 'ltr', 'autofocus' => true])
        @include('site.components.form.password', ['label' => 'كلمة المرور', 'autocomplete' => 'current-password'])

        <div class="ns-form__row">
            <label class="ns-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> تذكّرني</label>
            <a class="ns-link" href="{{ route('site.password.request') }}">نسيت كلمة المرور؟</a>
        </div>

        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">تسجيل الدخول</button>

        <p class="ns-form__aside">ليس لديك حساب؟ <a class="ns-link" href="{{ route('site.register') }}">أنشئ حسابك</a></p>
    </form>
@endsection
