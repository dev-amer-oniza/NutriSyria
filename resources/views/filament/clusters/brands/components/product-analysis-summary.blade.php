@php
    $values = $product->nutrientValues()->with('nutrient')->get()->sortBy('nutrient.display_order');
    $recipe = $product->recipe;
    $approver = $recipe?->nutrition_approved_by ? $recipe->nutritionApprovedBy?->name : null;
@endphp

<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ($values as $value)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $value->nutrient->name_ar }}</div>
            <div class="font-bold tabular-nums text-gray-950 dark:text-white" dir="ltr">
                {{ number_format((float) $value->amount_per_100g, 2) }} {{ $value->unit }}
            </div>
        </div>
    @endforeach
</div>

<p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
    القيم لكل 100 غرام، كما يُقدَّم (بعد الطبخ).
    @if ($recipe?->nutrition_approved_at)
        اعتمدها {{ $approver ?? 'مستخدم محذوف' }} بتاريخ <span dir="ltr">{{ $recipe->nutrition_approved_at->format('Y-m-d') }}</span>.
    @endif
</p>
