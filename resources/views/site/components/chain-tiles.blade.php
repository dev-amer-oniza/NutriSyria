{{--
    The identity's tiles: a box of the official pattern (the owner's images in
    public/images/site/tiles). Params:
    $pattern  name in App\Support\Site\ChainPatterns (gives the box's columns and rows)
    $tile     side of one tile in px (default 48)
    $class    extra classes, $ref an Alpine x-ref (both optional)
    A dish cover or a small mark (up to two columns) shows one square crop; a larger field
    shows the full pattern.
--}}
@php
    $chain = \App\Support\Site\ChainPatterns::get($pattern);
    $rows = (int) ceil(count($chain['tiles']) / $chain['cols']);
    $crop = str_starts_with($pattern, 'dish-') ? (int) substr($pattern, 5) : ($chain['cols'] <= 2 ? crc32($pattern) % 4 + 1 : null);
@endphp
<div class="ns-chain {{ $crop ? 'ns-chain--crop ns-chain--'.$crop : 'ns-chain--field' }} {{ $class ?? '' }}"
     style="--cols: {{ $chain['cols'] }}; --rows: {{ $rows }}; --tile: {{ $tile ?? 48 }}px;"
     aria-hidden="true" @isset($ref) x-ref="{{ $ref }}" @endisset></div>
