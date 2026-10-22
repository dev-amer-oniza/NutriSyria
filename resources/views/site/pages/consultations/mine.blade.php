@extends('site.layouts.app')

@section('title', 'أخصائيّي')

@section('content')
    <section class="ns-section">
        <div class="ns-wrap ns-book">
            <p class="ns-eyebrow">حسابي</p>
            <h1 class="t-title">أخصائيّي</h1>

            @if (! $current)
                <div class="ns-book__card">
                    <p class="t-body">لم تختر أخصائياً بعد. الأخصائي يراجع سجلك الصحي بعد موافقتك، ويحدد معاييرك بدقة. <b>الاستشارة الأولى مجانية.</b></p>
                    <a class="ns-btn ns-btn--primary" href="{{ route('site.nutritionists') }}">اختر أخصائياً</a>
                </div>
            @else
                @php
                    $profile = $current->nutritionist->nutritionistProfile;
                    $whatsapp = \App\Models\ConsultationRequest::whatsappUrl($current->nutritionist->phone);
                @endphp
                <div @class(['ns-book__card', 'ns-book__card--accepted' => $current->isAccepted()])>
                    <div class="ns-book__head">
                        @if ($profile)
                            @include('site.components.nutritionist-person', ['profile' => $profile, 'link' => $profile->isListed()])
                        @else
                            <span class="ns-person__name">{{ $current->nutritionist->name }}</span>
                        @endif
                        <span @class(['ns-status', 'ns-status--'.$current->status->value])>{{ $current->status->getLabel() }}</span>
                    </div>

                    @if ($current->isPending())
                        <p class="t-body">أُرسل طلبك يوم <span class="ns-num">{{ $current->created_at->format('Y-m-d') }}</span>. يصلك إشعار على الجرس حين يرد، وينتهي الطلب إن لم يرد خلال <span class="ns-num">{{ $current->daysLeft() }}</span> أيام.</p>
                        <p class="ns-book__reason">{{ $current->reason }}</p>
                        <form method="post" action="{{ route('site.consultations.cancel', $current->id) }}">
                            @csrf
                            <button class="ns-btn ns-btn--ghost" type="submit">إلغاء الطلب</button>
                        </form>
                    @else
                        <p class="t-body">أخصائيّك الآن. تواصلا على واتساب؛ يرى سجلك الصحي ويستطيع تحديد قالبك ومعاييرك. <b>الاستشارة الأولى مجانية.</b></p>
                        <div class="ns-book__actions">
                            @if ($whatsapp)
                                <a class="ns-btn ns-btn--primary" href="{{ $whatsapp }}" target="_blank" rel="noopener">تواصل على واتساب</a>
                            @else
                                <span class="ns-field__hint">سيتواصل معك على رقمك.</span>
                            @endif
                            <a class="ns-btn ns-btn--ghost" href="{{ route('site.criteria') }}">معاييري</a>
                        </div>
                        <form class="ns-book__end" method="post" action="{{ route('site.consultations.end', $current->id) }}" x-data
                              @submit="if (! confirm('إنهاء الارتباط؟ يتوقف وصوله إلى سجلك، وتعود معاييرك إلى قيمك أنت.')) $event.preventDefault()">
                            @csrf
                            <button class="ns-btn ns-btn--ghost ns-btn--sm" type="submit">إنهاء الارتباط</button>
                            <p class="ns-field__hint">متى شئت. يتوقف وصوله إلى سجلك، وتعود معاييرك إلى قيمك أنت.</p>
                        </form>
                    @endif
                </div>
            @endif

            @if ($history->isNotEmpty())
                <h2 class="t-heading">طلبات سابقة</h2>
                <ul class="ns-book__history">
                    @foreach ($history as $past)
                        <li>
                            <span class="ns-book__who">{{ $past->nutritionist->nutritionistProfile?->display_name ?? $past->nutritionist->name }}</span>
                            <span @class(['ns-status', 'ns-status--'.$past->status->value])>{{ $past->status->getLabel() }}</span>
                            <span class="ns-book__when">{{ $past->created_at->format('Y-m-d') }}</span>
                            @if ($past->decline_reason ?? $past->end_reason)<p class="ns-field__hint">{{ $past->decline_reason ?? $past->end_reason }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
