{{-- What is left to share between the meals (ClientPlan, phase 2). Param: $result (?NutritionPlanResult). --}}
@if ($result?->remaining)
    @php
        $items = [
            'share' => ['نسب الوجبات', '%'],
            'carbs' => ['الكربوهيدرات', ' غ'],
            'protein' => ['البروتين', ' غ'],
            'fat' => ['الدهون', ' غ'],
        ];
    @endphp
    <div class="flex flex-wrap items-center gap-2 text-sm" role="status" aria-live="polite">
        <span class="font-semibold">باقي للتوزيع:</span>
        @foreach ($items as $key => [$label, $unit])
            @php
                $left = $result->remaining[$key];
            @endphp
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-3 py-1',
                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $left == 0,
                'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $left > 0,
                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $left < 0,
            ])>
                {{ $label }}
                <b dir="ltr">{{ $left == 0 ? '✓' : ($left < 0 ? '−'.abs($left) : $left) }}{{ $left == 0 ? '' : $unit }}</b>
                @if ($left < 0)<span class="text-xs">زائد</span>@endif
            </span>
        @endforeach
    </div>
@endif
