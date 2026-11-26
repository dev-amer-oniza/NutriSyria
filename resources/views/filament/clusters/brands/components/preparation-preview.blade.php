{{--
    A preparation's 100 g, live as its foods are typed (RecipePreview::per100g). Param $preview.
    For the owner only: each product's own analysis is what the nutritionist approves.
--}}
@php
    $kcal = $preview['per100g']['energy_kcal'];
    $macros = ['protein_g' => 'بروتين', 'carbohydrate_g' => 'كربوهيدرات', 'fat_g' => 'دهون'];
@endphp

<div class="flex flex-col gap-3">
    <div class="flex items-end justify-between gap-4">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">كل 100 غ{{ $preview['grams'] ? ' · الطبخة كلها '.number_format($preview['grams']).' غ' : '' }}</div>
            <div class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $kcal !== null ? number_format($kcal) : '—' }} <span class="text-base font-medium text-gray-500">سعرة</span>
            </div>
        </div>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">تقريبي</span>
    </div>
    <dl class="grid grid-cols-3 gap-2">
        @foreach ($macros as $code => $label)
            <div class="rounded-lg bg-gray-50 p-2 text-center dark:bg-white/5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="font-bold tabular-nums text-gray-950 dark:text-white">
                    {{ $preview['per100g'][$code] !== null ? number_format($preview['per100g'][$code], 1) : '—' }} <span class="text-xs font-normal">غ</span>
                </dd>
            </div>
        @endforeach
    </dl>
    @if ($preview['unconverted'] !== [])
        <p class="text-sm text-warning-700 dark:text-warning-300">○ كمّل سطر: {{ implode('، ', $preview['unconverted']) }} (اختار الحجم، أو اكتبه بالغرام)</p>
    @endif
    <p class="text-sm text-gray-600 dark:text-gray-300">بوصفة المنتج بتنكتب بالغرام، والنظام بيحسب منها حصّتها.</p>
</div>
