<x-filament-panels::page>
    @unless ($this->canPlan())
        <x-filament::section>
            <p>لا يمكن وضع خطة بعد: بيانات المراجع ناقصة، أو عمره أقل من {{ config('nutrition_criteria.min_age') }} سنة. أكملها من "تعديل بيانات المراجع" في ملفه.</p>
        </x-filament::section>
    @else
        @php
            $result = $this->preview();
            $profile = $this->record->user->profile;
            $macroLabels = ['carbs' => 'الكربوهيدرات', 'protein' => 'البروتين', 'fat' => 'الدهون'];
        @endphp

        <div class="grid gap-6 lg:grid-cols-3">
            <form wire:submit="save" class="space-y-6 lg:col-span-2">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button type="submit" :disabled="$result && ! $result->isValid()">حفظ الخطة</x-filament::button>
                    <span class="text-sm text-gray-500 dark:text-gray-400">الحفظ يجعل سعرات المراجع ومغذياته من هذه الخطة، ويصله إشعار.</span>
                </div>
            </form>

            <aside class="lg:sticky lg:top-20 lg:self-start">
                <x-filament::section heading="الملخّص" :description="'الوزن '.round($profile->weight_kg, 1).' كغ · الطول '.round($profile->height_cm).' سم · '.$profile->age().' سنة'">
                    @if ($result)
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">الأيض الأساسي (BMR)</dt><dd class="font-semibold">{{ $result->bmr !== null ? number_format($result->bmr) : '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">× معامل النشاط</dt><dd class="font-semibold">{{ $result->activityFactor }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">الاحتياج اليومي</dt><dd class="font-semibold">{{ $result->tdee !== null ? number_format($result->tdee) : '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">التعديل</dt><dd class="font-semibold" dir="ltr">{{ $this::signed($result->adjustment) }}</dd></div>
                            <div class="flex justify-between gap-4 border-t border-gray-200 pt-2 dark:border-white/10">
                                <dt class="font-semibold">السعرات النهائية</dt>
                                <dd class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ $result->target !== null ? number_format($result->target) : '—' }}</dd>
                            </div>
                        </dl>

                        <table class="mt-4 w-full text-sm">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="py-1 text-start font-medium"></th>
                                    <th class="py-1 text-start font-medium">النسبة</th>
                                    <th class="py-1 text-start font-medium">غرام</th>
                                    <th class="py-1 text-start font-medium">سعرة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result->macros as $macro => $m)
                                    <tr class="border-t border-gray-200 dark:border-white/10">
                                        <th class="py-1.5 text-start font-semibold">{{ $macroLabels[$macro] }}</th>
                                        <td>{{ $m['pct'] }}٪</td>
                                        <td class="font-semibold">{{ $m['g'] }}</td>
                                        <td>{{ number_format($m['kcal']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <th class="py-1.5 text-start">المجموع</th>
                                    <td @class(['font-semibold', 'text-danger-600 dark:text-danger-400' => $result->macroTotal() !== 100])>{{ $result->macroTotal() }}٪</td>
                                    <td></td>
                                    <td>{{ number_format(array_sum(array_column($result->macros, 'kcal'))) }}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <dl class="mt-4 space-y-2 text-sm">
                            @if ($result->proteinPerKg !== null)
                                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">البروتين لكل كغ</dt><dd class="font-semibold">{{ $result->proteinPerKg }} غ</dd></div>
                            @endif
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400">السوائل</dt>
                                <dd class="font-semibold">{{ $result->fluids['ml'] ? number_format($result->fluids['ml'] / 1000, 2).' ل' : 'يحددها الأخصائي' }}</dd>
                            </div>
                        </dl>

                        @foreach ($result->errors as $error)
                            <p class="mt-3 rounded-lg bg-danger-50 p-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">{{ $error }}</p>
                        @endforeach
                        @foreach ($result->warnings as $warning)
                            <p class="mt-3 rounded-lg bg-warning-50 p-2 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ $warning }}</p>
                        @endforeach
                    @endif
                </x-filament::section>
            </aside>
        </div>
    @endunless
</x-filament-panels::page>
