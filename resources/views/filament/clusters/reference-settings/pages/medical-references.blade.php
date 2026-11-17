<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-[auto_1fr_1fr_1fr] md:items-end">
            <div class="flex flex-wrap gap-2 text-sm">
                <x-filament::badge color="success">موثّق: {{ $totals['verified'] }}</x-filament::badge>
                <x-filament::badge color="warning">قيد المراجعة: {{ $totals['pending'] }}</x-filament::badge>
                <x-filament::badge color="gray">من {{ $totals['total'] }} حداً</x-filament::badge>
            </div>
            <label class="grid gap-1">
                <span class="text-sm font-medium">الحالة</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        <option value="all">الكل</option>
                        <option value="pending">قيد المراجعة فقط</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1">
                <span class="text-sm font-medium">النوع</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="kind">
                        <option value="">كل الأنواع</option>
                        @foreach ($this->kinds() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-1">
                <span class="text-sm font-medium">المجال</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="area">
                        <option value="">الكل</option>
                        @foreach ($this->areas() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
    </x-filament::section>

    @forelse ($rows as ['card' => $card, 'rules' => $rules])
        <x-filament::section :description="$card->description" id="{{ $card->anchor }}">
            <x-slot name="heading">
                <span class="inline-flex flex-wrap items-center gap-2">
                    {{ $card->title }}
                    <span class="text-xs font-normal text-gray-500">{{ ['general' => 'عام', 'template' => 'قالب', 'condition' => 'حالة'][$card->type] }}</span>
                    <x-filament::badge :color="$card->statusColor()">{{ $card->statusLabel() }}</x-filament::badge>
                </span>
            </x-slot>

            @if ($card->template)
                <p class="mb-3 text-sm">تجلب قالب <strong>{{ $card->template['label'] }}</strong> بحدوده، وتُحسب حالتها معه.</p>
            @endif
            @if ($card->refs)
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">المراجع لا تعطي لها أرقاماً خاصة: يُطبَّق الاحتياج العام الموثّق.</p>
                @include('filament.clusters.reference-settings.pages.reference-details', ['keys' => $card->refs])
            @elseif ($card->status === 'general' && ! $card->template)
                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">بلا حدود خاصة: يُطبَّق الاحتياج العام.</p>
            @endif

            @if ($rules)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-start text-xs text-gray-500 dark:border-white/10">
                                <th class="py-2 text-start font-medium">الحد</th>
                                <th class="py-2 text-start font-medium">القيمة</th>
                                <th class="py-2 text-start font-medium">النوع</th>
                                <th class="min-w-28 py-2 text-start font-medium">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                    <td class="py-2 pe-3 font-medium">{{ $rule->label }}</td>
                                    <td class="py-2 pe-3">
                                        {{ $rule->value }}
                                        @if ($rule->note)<p class="mt-1 text-xs text-gray-500">{{ $rule->note }}</p>@endif
                                        @if ($rule->refs)
                                            @include('filament.clusters.reference-settings.pages.reference-details', ['keys' => $rule->refs])
                                        @endif
                                    </td>
                                    <td class="py-2 pe-3 whitespace-nowrap">{{ $rule->kindLabel() }}</td>
                                    <td class="min-w-28 py-2 whitespace-nowrap">
                                        @if ($rule->infoOnly)
                                            <x-filament::badge color="gray">للمعلومة</x-filament::badge>
                                        @elseif ($rule->verified)
                                            <x-filament::badge color="success">موثّق</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">قيد المراجعة</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($card->advice)
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $card->advice }}</p>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500">لا شيء بهذه الشروط.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
