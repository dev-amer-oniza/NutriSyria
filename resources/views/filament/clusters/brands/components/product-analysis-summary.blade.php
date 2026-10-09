@php
    $values = $product->nutrientValues()->with('nutrient')->get()->sortBy('nutrient.display_order');
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @foreach ($values as $value)
        <div class="rounded-xl bg-gray-50 dark:bg-white/5 px-3 py-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $value->nutrient->name_ar }}</div>
            <div class="font-bold text-gray-900 dark:text-white">
                {{ number_format((float) $value->amount_per_100g, 2) }} {{ $value->unit }}
            </div>
        </div>
    @endforeach
</div>
<p class="text-xs text-gray-400 dark:text-gray-500 mt-3">القيم لكل 100 غرام، كما يُقدَّم (بعد الطبخ)</p>
