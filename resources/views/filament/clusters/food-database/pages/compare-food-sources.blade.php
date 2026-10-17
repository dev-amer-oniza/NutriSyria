<x-filament-panels::page>
    {{ $this->form }}

    @php $rows = $this->getComparisonRows(); @endphp

    @if ($rows)
        <x-filament::section heading="المقارنة" class="mt-6">
            <div class="space-y-6">
                @foreach ($rows as $row)
                    @php
                        $max = max($row['a'] ?? 0, $row['b'] ?? 0, 0.0001);
                        $pctA = $row['a'] !== null ? min(100, ($row['a'] / $max) * 100) : 0;
                        $pctB = $row['b'] !== null ? min(100, ($row['b'] / $max) * 100) : 0;
                        $bigDiff = $row['diff_pct'] !== null && abs($row['diff_pct']) >= 20;
                    @endphp

                    <div class="rounded-2xl border border-gray-200 dark:border-white/10 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ $row['name'] }}
                                <span class="font-normal text-gray-400 dark:text-gray-500 text-xs">({{ $row['unit'] }})</span>
                            </span>

                            @if ($row['diff_pct'] !== null)
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300' => $bigDiff,
                                    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $bigDiff,
                                ])>
                                    @if ($row['diff_pct'] > 0)
                                        <span aria-hidden="true">▲</span>
                                    @elseif ($row['diff_pct'] < 0)
                                        <span aria-hidden="true">▼</span>
                                    @endif
                                    فرق {{ $row['diff_pct'] > 0 ? '+' : '' }}{{ $row['diff_pct'] }}%
                                </span>
                            @endif
                        </div>

                        {{-- المصدر الأول --}}
                        <div class="flex items-center gap-3 mb-2">
                            <span class="w-24 shrink-0 text-xs text-gray-500 dark:text-gray-400">المصدر الأول</span>
                            <div class="flex-1 h-6 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden">
                                <div class="h-full rounded-full bg-emerald-500 dark:bg-emerald-400" style="width: {{ $pctA }}%"></div>
                            </div>
                            <span class="w-20 shrink-0 text-sm font-bold text-end text-gray-900 dark:text-white">
                                {{ $row['a'] !== null ? number_format($row['a'], 2) : '—' }}
                            </span>
                        </div>

                        {{-- المصدر الثاني --}}
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs text-gray-500 dark:text-gray-400">المصدر الثاني</span>
                            <div class="flex-1 h-6 rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden">
                                <div class="h-full rounded-full bg-sky-500 dark:bg-sky-400" style="width: {{ $pctB }}%"></div>
                            </div>
                            <span class="w-20 shrink-0 text-sm font-bold text-end text-gray-900 dark:text-white">
                                {{ $row['b'] !== null ? number_format($row['b'], 2) : '—' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
