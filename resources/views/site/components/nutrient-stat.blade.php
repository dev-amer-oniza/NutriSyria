{{-- Params: $label, $value, $unit (default غ), $key = protein | carbs | fat | fiber (colour key, optional). --}}
<div class="ns-stat">
    <span class="ns-stat__label">
        @isset($key)<i class="ns-stat__key ns-key-{{ $key }}"></i>@endisset{{ $label }}
    </span>
    <span class="ns-stat__value">
        <span class="ns-num">{{ $value }}</span>
        <span class="ns-stat__unit">{{ $unit ?? 'غ' }}</span>
    </span>
</div>
