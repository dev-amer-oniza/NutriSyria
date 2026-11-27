{{--
    The live summary under the recipe: what one plate holds so far (RecipePreview), and what is
    still missing, as a short checklist. Param $preview. Not official: the nutritionist approves.
--}}
@php
    $kcal = $preview['perServing']['energy_kcal'];
    $macros = ['protein_g' => 'بروتين', 'carbohydrate_g' => 'كربوهيدرات', 'fat_g' => 'دهون'];
    $todo = array_values(array_filter([
        $preview['complete'] === 0 ? 'أضف المكوّنات بكمياتها' : null,
        $preview['complete'] > 0 && $preview['complete'] < $preview['rows'] ? 'كمّل الأسطر الناقصة' : null,
        $preview['unconverted'] !== [] ? 'كمّل سطر: '.implode('، ', $preview['unconverted']).' (اختار الحجم، أو اكتبه بالغرام)' : null,
        ! $preview['hasWeight'] ? 'اكتب وزن الطبخة بعد الطبخ' : null,
    ]));
@endphp

<div class="flex flex-col gap-4">
    <div class="flex items-end justify-between gap-4">
        <div>
            <div class="text-sm text-gray-500 dark:text-gray-400">الصحن الواحد (من {{ $preview['servings'] }})</div>
            <div class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">
                {{ $kcal !== null ? number_format($kcal) : '—' }} <span class="text-base font-medium text-gray-500">سعرة</span>
            </div>
        </div>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">تقريبي، قبل الاعتماد</span>
    </div>

    <dl class="grid grid-cols-3 gap-2">
        @foreach ($macros as $code => $label)
            <div class="rounded-lg bg-gray-50 p-2 text-center dark:bg-white/5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="font-bold tabular-nums text-gray-950 dark:text-white">
                    {{ $preview['perServing'][$code] !== null ? number_format($preview['perServing'][$code], 1) : '—' }} <span class="text-xs font-normal">غ</span>
                </dd>
            </div>
        @endforeach
    </dl>

    @if ($todo)
        <ul class="flex flex-col gap-1.5 text-sm">
            @foreach ($todo as $item)
                <li class="flex items-center gap-2 text-warning-700 dark:text-warning-300">
                    <span aria-hidden="true">○</span>{{ $item }}
                </li>
            @endforeach
        </ul>
    @else
        <p class="flex items-center gap-2 text-sm font-medium text-success-700 dark:text-success-300">
            <span aria-hidden="true">✓</span>الوصفة كاملة. احفظ وبتوصل للمراجع.
        </p>
    @endif
</div>
