{{-- The "back to the list" button at the top of a detail page. Params: $href, $label. --}}
<a class="ns-back" href="{{ $href }}">@include('site.partials.icon', ['name' => 'back', 'size' => 'sm'])<span>{{ $label }}</span></a>
