<x-filament-panels::page>
    @php
        $client = $this->record->user;
        $profile = $client->profile;
        $sheet = $this->sheet();
        $bmi = $profile?->height_cm && $profile?->weight_kg ? round($profile->weight_kg / (($profile->height_cm / 100) ** 2), 1) : null;
        $conditions = collect($profile?->health_conditions ?? [])->map(fn ($c) => \App\Enums\HealthCondition::tryFrom($c)?->label())->filter();
        $allergies = collect($profile?->allergies ?? [])->map(fn ($a) => \App\Enums\Allergen::tryFrom($a)?->label())->filter();
        $facts = array_filter([
            'العمر' => $profile?->age() ? $profile->age().' سنة' : null,
            'الجنس' => $profile?->sex?->label(),
            'الطول' => $profile?->height_cm ? round($profile->height_cm).' سم' : null,
            'الوزن' => $profile?->weight_kg ? round($profile->weight_kg, 1).' كغ' : null,
            'مؤشر كتلة الجسم' => $bmi,
            'النشاط' => $profile?->activity_level?->label(),
            'الهدف' => $profile?->goal?->label(),
            'مرتبط منذ' => $this->record->responded_at?->format('Y-m-d'),
        ]);
    @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="عن المراجع" description="للقراءة فقط: يعدّلها المراجع من حسابه.">
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($facts as $label => $value)
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">سبب الاستشارة</p>
                <p class="whitespace-pre-line">{{ $this->record->reason }}</p>
            </div>
        </x-filament::section>

        <x-filament::section heading="السجل الصحي" description="شاركه المراجع معك عند طلب الاستشارة.">
            <p class="text-sm text-gray-500 dark:text-gray-400">الحالات الصحية</p>
            <div class="mb-4 mt-1 flex flex-wrap gap-2">
                @forelse ($conditions as $label)
                    <x-filament::badge color="warning">{{ $label }}</x-filament::badge>
                @empty
                    <span class="text-sm">لا حالات مسجّلة.</span>
                @endforelse
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">الحساسيات</p>
            <div class="mt-1 flex flex-wrap gap-2">
                @forelse ($allergies as $label)
                    <x-filament::badge color="danger">{{ $label }}</x-filament::badge>
                @empty
                    <span class="text-sm">لا حساسيات مسجّلة.</span>
                @endforelse
            </div>
        </x-filament::section>
    </div>

    @unless ($sheet)
        <x-filament::section>
            <p>لا يمكن حساب معاييره بعد: بياناته ناقصة، أو عمره أقل من {{ config('nutrition_criteria.min_age') }} سنة.</p>
        </x-filament::section>
    @else
        <x-filament::section heading="القالب الغذائي">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-lg font-bold">{{ $sheet->template['label'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this::sourceLabel($sheet->template['source']) }} ·
                        كربوهيدرات {{ $sheet->template['macros']['carbs'] }}٪، بروتين {{ $sheet->template['macros']['protein'] }}٪، دهون {{ $sheet->template['macros']['fat'] }}٪
                    </p>
                    @if (! empty($sheet->template['note']))
                        <p class="mt-1 text-sm">ملاحظتك: {{ $sheet->template['note'] }}</p>
                    @endif
                    <p class="mt-2 text-sm">
                        السعرات: <span class="font-semibold">{{ number_format($sheet->get('energy_kcal')->value) }}</span> في اليوم،
                        <span class="font-semibold">{{ number_format(round($sheet->get('energy_kcal')->perMeal($sheet->mealShare))) }}</span> للوجبة.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    {{ $this->chooseTemplateAction }}
                    @if ($this->resetTemplateAction->isVisible())
                        {{ $this->resetTemplateAction }}
                    @endif
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="المعايير" description="ما تحدده يبقى مقفولاً على المراجع حتى تغيّره أو ينتهي الارتباط. يصله إيميل واحد في كل جلسة تعديل.">
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach (config('nutrition_criteria.criteria') as $key => $definition)
                    @php
                        $c = $sheet->get($key);
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ $definition['label'] }}
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                    {{ ['min' => 'حد أدنى', 'max' => 'حد أعلى', 'target' => 'هدف'][$c?->bound ?? $definition['bound']] }}{{ $definition['scope'] === 'meal' ? ' للوجبة' : ' في اليوم' }}
                                </span>
                            </p>
                            @if ($c)
                                <p class="text-sm">
                                    <span class="font-semibold">{{ number_format($c->value) }}</span> {{ $c->unit }}
                                    · <x-filament::badge :color="$c->source === \App\Enums\CriterionSource::Nutritionist ? 'primary' : 'gray'" class="inline-flex">{{ $this::sourceLabel($c->source) }}</x-filament::badge>
                                    @if ($c->defaultValue !== null)
                                        <span class="text-gray-500 dark:text-gray-400">(المحسوب {{ number_format($c->defaultValue) }})</span>
                                    @endif
                                </p>
                                @if ($c->note)<p class="text-sm text-gray-500 dark:text-gray-400">{{ $c->note }}</p>@endif
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">غير مفعّل له. فعّله بتحديد قيمة.</p>
                            @endif
                        </div>
                        <div class="flex gap-3">
                            {{ ($this->editCriterionAction)(['criterion' => $key])->label($c ? 'تعديل' : 'تحديد') }}
                            @if ($c?->source === \App\Enums\CriterionSource::Nutritionist)
                                {{ ($this->resetCriterionAction)(['criterion' => $key]) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endunless

</x-filament-panels::page>
