{{-- Params: $id, $placeholder, $action (url), $model (Alpine x-model, optional), $value (optional). --}}
<form class="ns-search" role="search" action="{{ $action }}" method="get">
    @include('site.partials.icon', ['name' => 'search'])
    <label class="ns-sr" for="{{ $id }}">ابحث في NutriSyria</label>
    <input class="ns-search__input" id="{{ $id }}" name="q" type="search" placeholder="{{ $placeholder }}"
           autocomplete="off" value="{{ $value ?? '' }}" @isset($model) x-model="{{ $model }}" x-ref="{{ $model }}" @endisset>
    <button class="ns-btn ns-btn--primary" type="submit">ابحث</button>
</form>
