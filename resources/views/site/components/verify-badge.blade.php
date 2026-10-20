{{-- Verification badge. Params: $level = verified | calculated | estimated (config/site.php). --}}
@php($v = config("site.verification.$level"))
<span class="ns-verify ns-verify--{{ $level }}">
    <svg viewBox="0 0 12 12" aria-hidden="true"><path d="{{ $v['icon'] }}"/></svg>{{ $v['label'] }}
</span>
