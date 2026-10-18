{{-- Everything a reviewer needs to judge one imported record, without leaving the batch. --}}
@php
    $values = $record->nutrientValues()->with('nutrient')->get()->sortBy('nutrient.display_order');
    $portions = $record->portions()->with('measureUnit')->orderBy('gram_weight')->get();
@endphp

<div class="grid gap-6">
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-gray-500 dark:text-gray-400">الغذاء</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $record->food?->name_ar }} @if ($record->food?->name_en) <span class="font-normal text-gray-500" dir="ltr">({{ $record->food->name_en }})</span> @endif</dd></div>
        <div><dt class="text-gray-500 dark:text-gray-400">الشكل</dt><dd class="text-gray-950 dark:text-white">{{ $record->foodForm?->name_ar ?? '—' }}</dd></div>
        <div><dt class="text-gray-500 dark:text-gray-400">المصدر</dt><dd class="text-gray-950 dark:text-white">{{ $record->dataSource?->name }}</dd></div>
        <div><dt class="text-gray-500 dark:text-gray-400">الرقم المرجعي</dt><dd class="tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $record->external_ref }}</dd></div>
        @if ($record->reviewed_at)
            <div class="col-span-2"><dt class="text-gray-500 dark:text-gray-400">المراجعة</dt><dd class="text-gray-950 dark:text-white">{{ $record->status->getLabel() }} — {{ $record->reviewedBy?->name ?? 'مستخدم محذوف' }}، <span dir="ltr">{{ $record->reviewed_at->format('Y-m-d H:i') }}</span></dd></div>
        @endif
        @if ($record->notes)
            <div class="col-span-2"><dt class="text-gray-500 dark:text-gray-400">ملاحظات</dt><dd class="whitespace-pre-line text-gray-950 dark:text-white">{{ $record->notes }}</dd></div>
        @endif
    </dl>

    <section class="grid gap-2">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">القيم لكل 100 غ ({{ $values->count() }})</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($values as $value)
                        <tr class="odd:bg-gray-50 dark:odd:bg-white/5">
                            <td class="px-3 py-1.5 text-gray-700 dark:text-gray-200">{{ $value->nutrient?->name_ar }}</td>
                            <td class="px-3 py-1.5 text-end tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ rtrim(rtrim(number_format((float) $value->amount_per_100g, 3, '.', ''), '0'), '.') }} {{ $value->unit }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-3 text-gray-500">لا توجد قيم.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($portions->isNotEmpty())
        <section class="grid gap-2">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">الحصص</h3>
            <ul class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                @foreach ($portions as $portion)
                    <li><span class="tabular-nums" dir="ltr">{{ rtrim(rtrim(number_format((float) $portion->amount, 2, '.', ''), '0'), '.') }}</span> {{ $portion->measureUnit?->name_ar }}@if ($portion->description) <span class="text-gray-500" dir="ltr">({{ $portion->description }})</span>@endif = <span class="tabular-nums" dir="ltr">{{ (float) $portion->gram_weight }}</span> غ</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
