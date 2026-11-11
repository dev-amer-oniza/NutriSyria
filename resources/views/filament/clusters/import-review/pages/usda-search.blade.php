<x-filament-panels::page>
    @unless ($this->configured())
        <x-filament::section>
            <p class="font-bold text-warning-600">مفتاح USDA غير موجود بعد.</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                سجّل ببريدك في <span dir="ltr">api.data.gov/signup</span> (مجاني ويصلك فوراً)، ثم أضف السطر التالي إلى ملف <span dir="ltr">.env</span> وأعد تشغيل الخادم:
            </p>
            <p class="mt-2 font-mono text-sm" dir="ltr">FDC_API_KEY=your-key</p>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <form wire:submit.prevent="search" class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <label for="usda-query" class="mb-1 block text-sm font-medium">اسم الغذاء بالإنكليزية</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="usda-query" type="search" wire:model="query" dir="ltr" placeholder="chickpeas cooked" autocomplete="off" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled" wire:target="search">ابحث</x-filament::button>
        </form>
        <p class="mt-3 text-sm text-gray-500">يبحث في Foundation و SR Legacy فقط. القيم لكل 100 غ.</p>
    </x-filament::section>

    @if ($errorMessage)
        <x-filament::section>
            <p class="font-bold text-danger-600">{{ $errorMessage }}</p>
        </x-filament::section>
    @endif

    @if ($results)
        @php($existing = $this->existing())
        <x-filament::section>
            <x-slot name="heading">
                @if ($results['hits'])
                    {{ number_format($results['hits']) }} نتيجة
                @else
                    لا نتائج
                @endif
            </x-slot>

            @if ($results['foods'])
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-start text-gray-500 dark:border-white/10">
                                <th class="py-2 text-start font-medium">الغذاء في USDA</th>
                                <th class="px-2 py-2 text-start font-medium">المصدر</th>
                                <th class="px-2 py-2 text-start font-medium">طاقة</th>
                                <th class="px-2 py-2 text-start font-medium">بروتين</th>
                                <th class="px-2 py-2 text-start font-medium">دهون</th>
                                <th class="px-2 py-2 text-start font-medium">كربوهيدرات</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results['foods'] as $food)
                                @php($record = $existing[$food['fdcId']] ?? null)
                                <tr class="border-b align-top dark:border-white/10" wire:key="fdc-{{ $food['fdcId'] }}">
                                    <td class="py-3">
                                        <div class="font-medium" dir="ltr" style="text-align: start">{{ $food['description'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            <span dir="ltr">FDC {{ $food['fdcId'] }}</span>
                                            @if ($food['category']) · <span dir="ltr">{{ $food['category'] }}</span> @endif
                                        </div>
                                        @if ($record)
                                            <div class="mt-2">
                                                <x-filament::badge color="warning">موجود: {{ $record->food?->name_ar }}@if ($record->foodForm) ({{ $record->foodForm->name_ar }}) @endif · {{ $record->status->getLabel() }}</x-filament::badge>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 whitespace-nowrap">
                                        <span dir="ltr" @class(['font-medium', 'text-success-600 dark:text-success-400' => $food['dataType'] === 'Foundation', 'text-gray-600 dark:text-gray-300' => $food['dataType'] !== 'Foundation'])>{{ $food['dataType'] }}</span>
                                    </td>
                                    @foreach (['kcal', 'protein', 'fat', 'carbs'] as $field)
                                        <td class="px-2 py-3 whitespace-nowrap" dir="ltr" style="text-align: start">{{ $food[$field] === null ? '—' : rtrim(rtrim(number_format($food[$field], 1), '0'), '.') }}</td>
                                    @endforeach
                                    <td class="py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-filament::button size="sm" color="gray" wire:click="mountAction('details', { fdcId: {{ $food['fdcId'] }} })">التفاصيل</x-filament::button>
                                            <x-filament::button size="sm" :color="$record ? 'gray' : 'primary'" wire:click="add({{ $food['fdcId'] }})">أضف للقاعدة</x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($results['pages'] > 1)
                    <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                        <x-filament::button size="sm" color="gray" wire:click="goTo({{ $page - 1 }})" :disabled="$page <= 1">السابق</x-filament::button>
                        <span>صفحة {{ $page }} من {{ number_format($results['pages']) }}</span>
                        <x-filament::button size="sm" color="gray" wire:click="goTo({{ $page + 1 }})" :disabled="$page >= $results['pages']">التالي</x-filament::button>
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-500">لا يوجد في USDA غذاء بهذا الاسم. جرّب كلمة أبسط، أو صيغة أخرى (مثل bulgur بدل burghul).</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
