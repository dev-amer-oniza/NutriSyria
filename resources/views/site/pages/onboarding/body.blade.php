@extends('site.layouts.onboarding')

@section('title', 'عنّي')
@section('heading', 'عرّفنا عليك')
@section('lead', 'نحتاج هذه الأرقام لنحسب احتياجك اليومي. لا يراها أحد غيرك.')

@section('form')
    <form class="ns-form" method="post" action="{{ route('site.onboarding.body') }}" novalidate>
        @csrf
        @include('site.components.form.choice-cards', [
            'name' => 'sex', 'legend' => 'الجنس', 'cols' => 2, 'selected' => $profile->sex?->value,
            'options' => collect($options['sexes'])->mapWithKeys(fn ($s) => [$s->value => ['label' => $s->label()]])->all(),
        ])
        <div class="ns-form__cols">
            @include('site.components.form.field', ['name' => 'birth_year', 'label' => 'سنة الميلاد', 'inputmode' => 'numeric', 'dir' => 'ltr', 'placeholder' => '1994', 'value' => $profile->birth_year])
            @include('site.components.form.field', ['name' => 'height_cm', 'label' => 'الطول (سم)', 'inputmode' => 'decimal', 'dir' => 'ltr', 'placeholder' => '172', 'value' => $profile->height_cm])
            @include('site.components.form.field', ['name' => 'weight_kg', 'label' => 'الوزن (كغ)', 'inputmode' => 'decimal', 'dir' => 'ltr', 'placeholder' => '75.5', 'value' => $profile->weight_kg])
        </div>
        <button class="ns-btn ns-btn--primary ns-form__submit" type="submit">التالي</button>
    </form>
@endsection
