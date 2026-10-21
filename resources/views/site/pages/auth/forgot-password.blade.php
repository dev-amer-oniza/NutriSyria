@extends('site.layouts.auth')

@section('title', 'نسيت كلمة المرور')
@section('heading', 'نسيت كلمة المرور؟')
@section('lead', 'اكتب بريدك، ونرسل إليه رمزاً لتعيين كلمة مرور جديدة.')

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.password.request') }}" novalidate>
        @csrf
        @include('site.components.form.honeypot')
        @include('site.components.form.field', ['name' => 'email', 'label' => 'البريد الإلكتروني', 'type' => 'email', 'autocomplete' => 'email', 'inputmode' => 'email', 'dir' => 'ltr', 'autofocus' => true])
        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">أرسل الرمز</button>
        <p class="ns-form__aside"><a class="ns-link" href="{{ route('site.login') }}">العودة إلى تسجيل الدخول</a></p>
    </form>
@endsection
