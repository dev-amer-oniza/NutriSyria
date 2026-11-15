
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
                <table class="w-full text-sm border-t">
                    <thead>
                        <tr class="text-start">
                            <th class="py-2">الصفحة</th>
                            <th class="py-2">السطر</th>
                            <th class="py-2">النوع</th>
                            <th class="py-2">الرسالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['issues'] as $issue)
                            <tr class="border-t">
                                <td class="py-1">{{ $issue['sheet'] }}</td>
                                <td class="py-1">{{ $issue['row'] }}</td>
                                <td class="py-1">
                                    <span class="{{ $issue['level'] === 'error' ? 'text-danger-600' : 'text-warning-600' }} font-bold">
                                        {{ $issue['level'] === 'error' ? 'خطأ' : 'تحذير' }}
                                    </span>
                                </td>
                                <td class="py-1">{{ $issue['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
