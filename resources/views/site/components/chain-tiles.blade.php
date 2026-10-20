{{--
    Chain-reaction tiles. Params:
    $pattern  name in App\Support\Site\ChainPatterns
    $tile     side of one tile in px (default 48)
    $class    extra classes, $ref an Alpine x-ref (both optional)
--}}
@php($chain = \App\Support\Site\ChainPatterns::get($pattern))
<div class="ns-chain {{ $class ?? '' }}" style="--cols: {{ $chain['cols'] }}; --tile: {{ $tile ?? 48 }}px;"
     aria-hidden="true" @isset($ref) x-ref="{{ $ref }}" @endisset>
    @foreach ($chain['tiles'] as $t)
        <i class="{{ $t['class'] }}" @if ($t['d'] !== null) data-d="{{ $t['d'] }}" @endif></i>
    @endforeach
</div>
