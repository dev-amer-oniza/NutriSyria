@php
    $values = $product->nutrientValues()->with('nutrient')->get()->sortBy('nutrient.display_order');
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @foreach ($values as $value)
        <div class="fi-section rounded-xl p-3" style="background-color: rgb(var(--gray-50)); border: 1px solid rgb(var(--gray-200));">
            <div class="text-xs" style="color: rgb(var(--gray-500));">{{ $value->nutrient->name_ar }}</div>
            <div class="font-bold" style="color: rgb(var(--gray-950));">
                {{ number_format((float) $value->amount_per_100g, 2) }} {{ $value->unit }}
            </div>
        </div>
    @endforeach
</div>
<p class="text-xs mt-3" style="color: rgb(var(--gray-400));">القيم لكل 100 غرام، كما يُقدَّم (بعد الطبخ)</p>
