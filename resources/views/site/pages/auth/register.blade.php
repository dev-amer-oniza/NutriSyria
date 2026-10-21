@extends('site.layouts.auth')

@section('title', 'أنشئ حسابك')
@section('heading', 'أنشئ حسابك')
@section('lead', 'في دقيقة واحدة، لتعرف مدى مناسبة كل وجبة لك.')

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.register') }}" novalidate
          x-data="{ type: @js(old('account_type', 'member')) }">
        @csrf
        @include('site.components.form.honeypot')
        @include('site.components.form.account-type', ['types' => $accountTypes])

        @include('site.components.form.field', ['name' => 'name', 'label' => 'الاسم', 'autocomplete' => 'name', 'autofocus' => true])
        @include('site.components.form.field', ['name' => 'email', 'label' => 'البريد الإلكتروني', 'type' => 'email', 'autocomplete' => 'email', 'inputmode' => 'email', 'dir' => 'ltr', 'placeholder' => 'name@example.com'])
        @include('site.components.form.password', ['label' => 'كلمة المرور', 'autocomplete' => 'new-password', 'hint' => '8 أحرف أو أكثر.'])

        <div x-show="type !== 'member'" x-cloak>
            @include('site.components.form.field', [
                'name' => 'whatsapp', 'label' => 'رقم واتساب', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel',
                'dir' => 'ltr', 'placeholder' => '0912 345 678', 'required' => false,
                'hint' => 'نتواصل معك عليه قبل تفعيل لوحة التحكم لحسابك.',
            ])
        </div>

        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">أنشئ الحساب</button>

        <p class="ns-form__aside">لديك حساب؟ <a class="ns-link" href="{{ route('site.login') }}">سجّل الدخول</a></p>
        <p class="ns-form__fine">بياناتك الصحية لك وحدك، ويمكنك حذفها متى شئت.</p>
    </form>
@endsection
