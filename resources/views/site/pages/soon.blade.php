@extends('site.layouts.app')

@section('title', $title)

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-soon">
            <div>
                <p class="ns-eyebrow">قريباً</p>
                <h1 class="t-title">{{ $title }}</h1>
                <p class="t-lead ns-muted">نعمل على هذه الصفحة الآن. إلى ذلك الحين، يمكنك البحث عن وجبة من الصفحة الرئيسية.</p>
                <a class="ns-btn ns-btn--primary" href="{{ route('home') }}">العودة إلى الرئيسية</a>
            </div>
            @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 72])
        </div>
    </section>
@endsection
