
<x-filament-panels::page>
    @include('filament.clusters.import-review.components.import-steps', ['current' => 3, 'matching' => $fromMatching])

    @unless ($report)
        <form wire:submit.prevent="validateBundle">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    افحص الملفات
                </x-filament::button>
            </div>
        </form>
    @endunless

    @if ($errorMessage)
        <x-filament::section class="mt-6">
            <p class="text-danger-600 font-bold">{{ $errorMessage }}</p>
        </x-filament::section>
    @endif

    @if ($report)
        <x-filament::section heading="نتيجة الفحص" description="لا شيء حُفظ بعد. السجلات السليمة تُحفظ «قيد المراجعة» ولا تُستعمل قبل اعتمادها.">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <div class="text-sm text-gray-500">السجلات</div>
                    <div class="text-lg font-bold">
                        {{ $report['records']['ready'] }} جاهز / {{ $report['records']['blocked'] }} محجوب
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">أغذية جديدة</div>
                    <div class="text-lg font-bold">{{ $report['records']['new_foods'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">القيم الغذائية</div>
                    <div class="text-lg font-bold">{{ $report['values']['ok'] }} / {{ $report['values']['total'] }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">الحصص</div>
                    <div class="text-lg font-bold">{{ $report['portions']['ok'] }} / {{ $report['portions']['total'] }}</div>
                </div>
            </div>

            @if (! empty($report['unmapped_unit_labels']))
                <p class="mb-4">
                    <span class="font-bold">وحدات قياس غير معروفة (تُعرَّف مرة واحدة، ولا تمنع حفظ الغذاء نفسه):</span>
                    {{ implode('، ', array_map(fn ($label, $count) => "{$label} ×{$count}", array_keys($report['unmapped_unit_labels']), $report['unmapped_unit_labels'])) }}
                </p>
            @endif

            @if (! empty($report['issues']))
                @php
                    $sheets = ['records' => 'السجلات', 'nutrient_values' => 'القيم الغذائية', 'portions' => 'الحصص'];
                @endphp
                <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-danger-600 dark:text-danger-400">الخطأ</span> يمنع حفظ سجله،
                    و<span class="font-semibold text-warning-600 dark:text-warning-400">التحذير</span> لا يمنع، لكنه يظهر تنبيهاً في المراجعة.
                    رقم السطر كما في الملف (السطر 1 أسماء الأعمدة).
                </p>
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            <tr>
                                <th class="px-3 py-2 text-start">الملف</th>
                                <th class="px-3 py-2 text-start">السطر</th>
                                <th class="px-3 py-2 text-start">النوع</th>
                                <th class="px-3 py-2 text-start">السبب</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['issues'] as $issue)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="whitespace-nowrap px-3 py-1.5">{{ $sheets[$issue['sheet']] ?? $issue['sheet'] }}</td>
                                    <td class="px-3 py-1.5 tabular-nums">{{ $issue['row'] }}</td>
                                    <td class="px-3 py-1.5">
                                        <span class="{{ $issue['level'] === 'error' ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400' }} font-bold">
                                            {{ $issue['level'] === 'error' ? 'خطأ' : 'تحذير' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5">{{ $issue['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($report['records']['ready'] > 0)
                    <x-filament::button wire:click="commitBundle" color="success" icon="heroicon-o-check">
                        احفظ وافتح المراجعة ({{ $report['records']['ready'] }} سجل)
                    </x-filament::button>
                @endif
                <x-filament::button wire:click="startOver" color="gray" icon="heroicon-o-arrow-path">
                    ابدأ من جديد
                </x-filament::button>
            </div>
            @if ($report['records']['blocked'] > 0)
                <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">
                    {{ $report['records']['blocked'] }} سجل فيه خطأ لن يُحفظ (انظر الجدول أعلاه). صحّح الملف وارفعه من جديد لإضافتها.
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
