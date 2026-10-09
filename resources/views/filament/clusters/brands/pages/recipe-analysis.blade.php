<x-filament-panels::page>
    @if ($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400 font-bold">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif ($result)
        <x-filament::section>
            <div class="flex items-center justify-between mb-2">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $product->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product->brand?->name }}</p>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 text-left">
                    <div>وزن المكونات: {{ number_format($result['grams_total'], 1) }} غ</div>
                    <div>الحصص: {{ $result['servings'] }}</div>
                </div>
            </div>

            @if ($result['weight_warning'])
                <div class="mt-3 rounded-xl bg-warning-50 dark:bg-warning-500/10 text-warning-800 dark:text-warning-300 px-4 py-3 text-sm">
                    ⚠ {{ $result['weight_warning'] }}
                </div>
            @endif

            @unless ($result['can_save_official_value'])
                <div class="mt-3 rounded-xl bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300 px-4 py-3 text-sm">
                    أدخل "الوزن بعد الطبخ" في الوصفة لتتمكن من اعتماد هذا التحليل كقيمة رسمية.
                </div>
            @endunless
        </x-filament::section>

        <x-filament::section heading="القيم الغذائية" class="mt-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pe-4">المغذي</th>
                            <th class="py-2 px-3">الإجمالي</th>
                            <th class="py-2 px-3">لكل 100غ (قبل الطبخ)</th>
                            <th class="py-2 px-3 font-bold text-success-700 dark:text-success-400">لكل 100غ (بعد الطبخ) — الرسمي</th>
                            <th class="py-2 px-3">لكل حصة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['totals'] as $nutrientId => $row)
                            @php
                                $before = $result['per_100g_before_cooking'][$nutrientId]['amount'] ?? null;
                                $after = $result['per_100g_after_cooking'][$nutrientId]['amount'] ?? null;
                                $perServing = $result['per_serving'][$nutrientId]['amount'] ?? null;
                                $unit = $row['nutrient']['unit'];
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-4">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $row['nutrient']['name_ar'] }}</span>
                                    @if ($row['missing_from'])
                                        <div class="text-xs text-warning-600 dark:text-warning-400">
                                            ⚠ ناقص من: {{ implode('، ', array_unique($row['missing_from'])) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-200">
                                    {{ $row['amount'] !== null ? number_format($row['amount'], 2).' '.$unit : '—' }}
                                </td>
                                <td class="py-2 px-3 text-gray-500 dark:text-gray-400">
                                    {{ $before !== null ? number_format($before, 2) : '—' }}
                                </td>
                                <td class="py-2 px-3 font-bold text-gray-900 dark:text-white">
                                    {{ $after !== null ? number_format($after, 2) : '—' }}
                                </td>
                                <td class="py-2 px-3 text-gray-500 dark:text-gray-400">
                                    {{ $perServing !== null ? number_format($perServing, 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($result['can_save_official_value'])
                <div class="mt-6">
                    <x-filament::button wire:click="approveAnalysis" color="success">
                        اعتماد هذا التحليل كقيمة رسمية
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
