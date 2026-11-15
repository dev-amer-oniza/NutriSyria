<x-filament-panels::page>
    @include('filament.clusters.import-review.components.import-steps', ['current' => 2])

    @php
        $draft = $this->draft();
    @endphp

    @if (! $draft)
        <x-filament::section heading="المصدر" description="يُختار مرة واحدة ويُستعمل في الخطوتين.">
            {{ $this->sourceForm }}
        </x-filament::section>

        <x-filament::section heading="الخطوة 1: اقتراح المطابقة" description="ارفع قائمة الأغذية، فيقترح النظام لكل غذاء وشكل أقرب عنصر في USDA، وتراجع الاقتراحات هنا في جدول.">
            <form wire:submit.prevent="generateDraft">
                {{ $this->matchForm }}
                <div class="mt-4">
                    <x-filament::button type="submit" icon="heroicon-o-sparkles">
                        اقترح المطابقة
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section heading="عندك مسودة راجعتها بالإكسل؟" description="الطريقة القديمة، احتياطية: ارفع المسودة التي وضعت فيها yes." collapsible collapsed>
            <form wire:submit.prevent="buildBundle">
                {{ $this->buildForm }}
                <div class="mt-4">
                    <x-filament::button type="submit" color="gray" icon="heroicon-o-check">
                        ابنِ الدفعة من المسودة
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @else
        @php
            $summary = $draft->summary($picks);
            $problems = $this->getProblems();
            $forms = $this->getFormNames();
            $rows = $draft->rows();
        @endphp

        <x-filament::section heading="الخطوة 2: اختر لكل غذاء" description="اضغط على المرشح الصحيح. لكل غذاء وشكل اختيار واحد من Foundation وواحد من SR Legacy (الاثنان معاً أفضل: يكمل أحدهما ناقص الآخر). الضغط على المختار يلغيه.">
            <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span><span class="font-semibold text-gray-950 dark:text-white">{{ $summary['pairs'] }}</span> غذاء/شكل</span>
                <span class="text-success-600 dark:text-success-400">{{ $summary['chosen'] }} مختار</span>
                <span class="text-warning-600 dark:text-warning-400">{{ $summary['undecided'] }} يحتاج قرارك</span>
                <span class="text-gray-500 dark:text-gray-400">{{ $summary['no_candidate'] }} بلا مرشح</span>
                <label class="ms-auto inline-flex items-center gap-2">
                    <x-filament::input.checkbox wire:model.live="onlyUndecided" />
                    <span>اعرض فقط ما يحتاج قرارك</span>
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-start text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th class="w-12 px-3 py-2"></th>
                            <th class="px-3 py-2 text-start">المصدر</th>
                            <th class="px-3 py-2 text-start">عنصر USDA</th>
                            <th class="px-3 py-2 text-start">الشكل</th>
                            <th class="px-3 py-2 text-start">ملاحظة</th>
                        </tr>
                    </thead>
                    @foreach ($draft->groups() as $group)
                        @php
                            $chosen = count(array_filter($group['rows'], fn ($i) => ! empty($picks[$i])));
                            $needsYou = ! $group['empty'] && $chosen === 0;
                        @endphp
                        @continue($onlyUndecided && ! $needsYou)
                        <tbody wire:key="group-{{ md5($group['key']) }}" class="border-t border-gray-200 dark:border-white/10">
                            <tr class="bg-gray-50/60 dark:bg-white/5">
                                <th colspan="5" class="px-3 py-2 text-start">
                                    <span class="font-bold text-gray-950 dark:text-white">{{ $group['food_name_ar'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $forms[$group['target_form']] ?? ($group['target_form'] === 'any' ? 'بلا شكل محدد' : $group['target_form']) }}</span>
                                    @if ($group['empty'])
                                        <x-filament::badge color="gray" class="ms-2 inline-flex">لا يوجد مرشح: ابحث يدوياً في «بحث في USDA»</x-filament::badge>
                                    @elseif ($needsYou)
                                        <x-filament::badge color="warning" class="ms-2 inline-flex">يحتاج قرارك</x-filament::badge>
                                    @endif
                                    @if ($group['note'])
                                        <span class="ms-2 text-xs text-gray-500 dark:text-gray-400">{{ $group['note'] }}</span>
                                    @endif
                                </th>
                            </tr>
                            @unless ($group['empty'])
                                @foreach ($group['rows'] as $i)
                                    @php
                                        $row = $rows[$i];
                                        $on = ! empty($picks[$i]);
                                        $hint = \App\Services\Import\MatchingDraft::hintAr($row['hint'] ?? '');
                                    @endphp
                                    <tr wire:key="row-{{ $i }}" wire:click="togglePick({{ $i }})" @class([
                                        'cursor-pointer border-t border-gray-100 dark:border-white/5',
                                        'bg-success-50 dark:bg-success-500/10' => $on,
                                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $on,
                                    ])>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" @checked($on) aria-label="اختر {{ $row['description'] }}" class="pointer-events-none rounded border-gray-300 text-primary-600 dark:border-white/20" tabindex="-1">
                                        </td>
                                        <td class="px-3 py-2">
                                            <span @class([
                                                'inline-block whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400' => $row['data_source'] === 'usda_fdc_foundation',
                                                'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300' => $row['data_source'] !== 'usda_fdc_foundation',
                                            ])>{{ \App\Services\Import\MatchingDraft::sourceLabel($row['data_source'] ?? '') }}</span>
                                        </td>
                                        <td class="px-3 py-2" dir="ltr">
                                            <span class="text-gray-950 dark:text-white">{{ $row['description'] }}</span>
                                            <a href="https://fdc.nal.usda.gov/food-details/{{ $row['fdc_id'] }}/nutrients" target="_blank" rel="noopener" wire:click.stop
                                               class="ms-1 text-xs text-primary-600 underline dark:text-primary-400">{{ $row['fdc_id'] }}</a>
                                        </td>
                                        <td class="px-3 py-2">
                                            {{ $forms[$row['food_form_code']] ?? $row['food_form_code'] }}
                                            @if (($row['form_note'] ?? '') !== '')
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \App\Services\Import\MatchingDraft::formNoteAr($row['form_note']) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs">
                                            @if ($hint !== '')
                                                <span class="block text-warning-600 dark:text-warning-400">{{ $hint }}</span>
                                            @endif
                                            @if (($row['missing'] ?? '') !== '')
                                                <span class="block text-gray-500 dark:text-gray-400">ينقصه: {{ \App\Services\Import\MatchingDraft::nutrientsAr($row['missing']) }}</span>
                                            @endif
                                            @if (($row['match'] ?? '') !== '' && $row['match'] !== 'exact')
                                                <span class="block text-gray-500 dark:text-gray-400">{{ \App\Services\Import\MatchingDraft::matchAr($row['match']) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endunless
                        </tbody>
                    @endforeach
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="ابنِ الدفعة">
            @if ($problems)
                <ul class="mb-4 grid gap-1 text-sm text-danger-600 dark:text-danger-400">
                    @foreach ($problems as $problem)
                        <li>• {{ $problem }}</li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit.prevent="buildFromPicks" class="grid gap-4">
                <label class="grid max-w-xl gap-1">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">اسم الدفعة<span class="text-danger-600">*</span></span>
                    <x-filament::input.wrapper :valid="! $errors->has('batchLabel')">
                        <x-filament::input type="text" wire:model="batchLabel" placeholder="مثال: أغذية USDA - تشرين الثاني 2026" />
                    </x-filament::input.wrapper>
                    @error('batchLabel')
                        <span class="text-sm text-danger-600">{{ $message }}</span>
                    @enderror
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button type="submit" color="success" icon="heroicon-o-check" :disabled="(bool) $problems">
                        ابنِ الدفعة وافحصها ({{ count(array_filter($picks)) }} عنصر)
                    </x-filament::button>
                    <x-filament::button wire:click="downloadDraft" color="gray" icon="heroicon-o-arrow-down-tray">
                        نزّل المسودة (إكسل)
                    </x-filament::button>
                    <x-filament::button wire:click="discardDraft" wire:confirm="تُحذف الاقتراحات واختياراتك وتبدأ من جديد. متأكد؟" color="gray" icon="heroicon-o-arrow-path">
                        ابدأ من جديد
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif

    @if ($errorMessage)
        <x-filament::section>
            <pre class="whitespace-pre-wrap text-sm text-danger-600">{{ $errorMessage }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
