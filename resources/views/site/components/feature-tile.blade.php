{{-- Params: $icon (partials/icon name), $title, $text, $href. --}}
<a class="ns-feature" href="{{ $href }}">
    <span class="ns-feature__icon">@include('site.partials.icon', ['name' => $icon])</span>
    <h3 class="ns-feature__title">{{ $title }}@include('site.partials.icon', ['name' => 'arrow'])</h3>
    <p class="ns-feature__text">{{ $text }}</p>
</a>
