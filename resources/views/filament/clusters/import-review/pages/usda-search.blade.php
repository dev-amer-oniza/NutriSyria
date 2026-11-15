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
        <form wire:submit.prevent="search" class="grid gap-4">
            <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
                <label class="grid gap-1">
                    <span class="text-sm font-medium">اسم الغذاء بالإنكليزية</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" wire:model="query" dir="ltr" placeholder="chickpeas" autocomplete="off" />
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">كل كلمة تكتبها لازم تكون بالنتيجة. اكتب الاسم بس، والشكل من القائمة جنبه.</span>
                </label>
                <label class="grid gap-1">
                    <span class="text-sm font-medium">الشكل</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="form">
                            <option value="">أي شكل</option>
                            @foreach ($this->formOptions() as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">المعلّب والمجمّد والمجفف ما بيطلعوا إلا إذا طلبتهم.</span>
                </label>
            </div>
            <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
                <label class="grid gap-1">
                    <span class="text-sm font-medium">استبعد (اختياري)</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="exclude" dir="ltr" placeholder="soup; flour; sprouted" autocomplete="off" />
                    </x-filament::input.wrapper>
                    <span class="text-xs text-gray-500">كلمات بالإنكليزي بينهن «;». أي نتيجة فيها وحدة منهن بتنشال.</span>
                </label>
                <fieldset class="grid gap-1">
                    <legend class="mb-1 text-sm font-medium">المصدر</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2"><x-filament::input.checkbox wire:model="sources" value="Foundation" /> Foundation</label>
                        <label class="inline-flex items-center gap-2"><x-filament::input.checkbox wire:model="sources" value="SR Legacy" /> SR Legacy</label>
                    </div>
                </fieldset>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled" wire:target="search">ابحث</x-filament::button>
                <span class="text-sm text-gray-500">القيم لكل 100 غ. ما بيطلع أكل الأطفال ولا الوجبات السريعة ولا أكل المطاعم.</span>
            </div>
        </form>
    </x-filament::section>

    @if ($errorMessage)
        <x-filament::section>
            <p class="font-bold text-danger-600">{{ $errorMessage }}</p>
        </x-filament::section>
    @endif

    @php
        $results = $this->results();
        $existing = $results ? $this->existing() : [];
    @endphp

    @if ($results)
        <x-filament::section>
            <x-slot name="heading">
                @if ($results['total'])
                    {{ number_format($results['total']) }} نتيجة بتطابق شروطك
                @else
                    ولا نتيجة بتطابق شروطك
                @endif
            </x-slot>
            <x-slot name="description">
                USDA رجّعت {{ number_format($results['received']) }}@if ($results['hits'] > $results['received']) من أصل {{ number_format($results['hits']) }}@endif،
                ونحنا شلنا {{ count($results['dropped']) }} ما بتطابق.
                @if ($results['dropped'])
                    <button type="button" wire:click="$toggle('showDropped')" class="font-medium text-primary-600 underline dark:text-primary-400">
                        {{ $showDropped ? 'خبّي المستبعد' : 'اعرض المستبعد' }}
                    </button>
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
                                @php
                                    $record = $existing[$food['fdcId']] ?? null;
                                @endphp
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
                        <x-filament::button size="sm" color="gray" wire:click="goTo({{ $results['page'] - 1 }})" :disabled="$results['page'] <= 1">السابق</x-filament::button>
                        <span>صفحة {{ $results['page'] }} من {{ $results['pages'] }}</span>
                        <x-filament::button size="sm" color="gray" wire:click="goTo({{ $results['page'] + 1 }})" :disabled="$results['page'] >= $results['pages']">التالي</x-filament::button>
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-500">جرّب كلمات أقل (اسم الغذاء بس)، أو «أي شكل»، أو امسح «استبعد»، أو اضغط «اعرض المستبعد» لتعرف ليش انشالوا.</p>
            @endif
        </x-filament::section>

        @if ($showDropped && $results['dropped'])
            <x-filament::section heading="المستبعد" description="هالنتائج رجّعتها USDA بس ما بتطابق شروطك. إذا لقيت شي مهم، غيّر الشروط وابحث من جديد.">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-gray-500 dark:border-white/10">
                                <th class="py-2 text-start font-medium">الغذاء في USDA</th>
                                <th class="px-2 py-2 text-start font-medium">المصدر</th>
                                <th class="px-2 py-2 text-start font-medium">ليش انشال</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results['dropped'] as $item)
                                <tr class="border-b dark:border-white/10" wire:key="dropped-{{ $item['food']['fdcId'] }}">
                                    <td class="py-2" dir="ltr" style="text-align: start">{{ $item['food']['description'] }} <span class="text-xs text-gray-500">FDC {{ $item['food']['fdcId'] }}</span></td>
                                    <td class="px-2 py-2 whitespace-nowrap" dir="ltr">{{ $item['food']['dataType'] }}</td>
                                    <td class="px-2 py-2 text-warning-600 dark:text-warning-400">{{ $item['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
