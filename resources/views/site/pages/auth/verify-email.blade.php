@extends('site.layouts.auth')

@section('title', 'تأكيد البريد')
@section('heading', 'أكّد بريدك')
@section('lead')
    أرسلنا رمزاً من 6 أرقام إلى <bdi class="ns-latin">{{ $email }}</bdi>. إن لم تجده، تفقّد مجلد الرسائل غير المرغوب فيها.
@endsection

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.verify') }}" novalidate>
        @csrf
        @include('site.components.form.code')
        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">تأكيد</button>
    </form>

    @include('site.partials.resend-code', ['action' => route('site.verify.resend'), 'seconds' => $resendIn])
@endsection
