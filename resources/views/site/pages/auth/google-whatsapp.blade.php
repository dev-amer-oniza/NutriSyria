@extends('site.layouts.auth')

@section('title', 'رقم واتساب')
@section('heading', 'خطوة أخيرة')
@section('lead')
    أهلاً {{ $name }}. بصفتك {{ $type->label() }}، نحتاج رقم واتساب لنتواصل معك قبل تفعيل لوحة التحكم لحسابك.
@endsection

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.google.whatsapp') }}" novalidate>
        @csrf
        @include('site.components.form.honeypot')
        @include('site.components.form.field', [
            'name' => 'whatsapp', 'label' => 'رقم واتساب', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel',
            'dir' => 'ltr', 'placeholder' => '0912 345 678', 'autofocus' => true,
        ])
        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">أنشئ الحساب</button>
    </form>
@endsection
