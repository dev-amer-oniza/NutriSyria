{{--
    The client's "يومي" in their file (ViewClient::diary): two weeks of meals against the day's
    need, and the last weighings. Read only. Params $diary (App\Data\Daily\Diary), $need (the
    daily energy need, or null when the criteria cannot be computed).
--}}
@php
    $average = $diary->average();
    $change = $diary->weightChange();
    $latest = $diary->weights[0] ?? null;
    $tz = config('site.daily.timezone');
@endphp
<x-filament::section heading="سجل الأكل والوزن" description="آخر {{ \App\Services\Daily\ClientDiary::DAYS }} يوماً من «يومي» عند المراجع، كما سجّلها هو. للقراءة فقط.">
    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">أيام سجّل فيها</dt>
            <dd class="font-semibold">{{ $diary->loggedDays() }} من {{ count($diary->days) }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">متوسط السعرات بأيامه</dt>
            <dd class="font-semibold">{{ $average !== null ? number_format(round($average)) : '—' }}@if ($average !== null && $need) <span class="text-sm font-normal text-gray-500 dark:text-gray-400">من {{ number_format($need) }}</span>@endif</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">آخر وزن</dt>
            <dd class="font-semibold">{{ $latest ? $latest->weight_kg.' كغ' : '—' }}@if ($latest) <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ $latest->weighed_on->format('Y-m-d') }}</span>@endif</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">تغيّر الوزن</dt>
            <dd class="font-semibold" dir="ltr" style="text-align: right">{{ $change === null ? '—' : ($change > 0 ? '+' : '').$change.' كغ' }}</dd>
        </div>
    </dl>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <p class="mb-2 text-sm font-semibold">الأيام</p>
            <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($diary->days as $d)
                    @php
                        $kcal = $d['eaten']['energy_kcal'];
                        $pct = $need ? min(100, round($kcal / $need * 100)) : 0;
                    @endphp
                    <li class="px-4 py-3">
                        @if ($d['entries'])
                            <details>
                                <summary class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1">
                                    <span class="w-36 font-semibold">{{ \App\Support\Site\SyrianDate::long($d['date']) }}</span>
                                    <span class="min-w-40 flex-1">
                                        <span class="block h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                            <span class="block h-full rounded-full bg-primary-600" style="width: {{ $pct }}%"></span>
                                        </span>
                                    </span>
                                    <span class="text-sm">
                                        <span class="font-semibold">{{ number_format(round($kcal)) }}</span>@if ($need) / {{ number_format($need) }}@endif سعرة
                                        · ب {{ round($d['eaten']['protein_g']) }} · ك {{ round($d['eaten']['carbohydrate_g']) }} · د {{ round($d['eaten']['fat_g']) }} غ
                                    </span>
                                    @if ($d['waiting'])
                                        <x-filament::badge color="warning">{{ $d['waiting'] }} غير محسوبة</x-filament::badge>
                                    @endif
                                </summary>
                                <ul class="mt-3 space-y-1 text-sm">
                                    @foreach ($d['entries'] as $entry)
                                        <li class="flex flex-wrap gap-x-3">
                                            <span class="w-14 font-semibold">{{ $entry->meal_type->label() }}</span>
                                            <span class="text-gray-500 dark:text-gray-400">{{ $entry->eaten_at->setTimezone($tz)->format('G:i') }}</span>
                                            <span class="flex-1">{{ $entry->name }}
                                                <span class="text-gray-500 dark:text-gray-400">· {{ $entry->counted() ? \App\Services\Daily\DailyLog::amountLabel($entry->amount, $entry->per_100g) : 'طلبها للإضافة، غير محسوبة' }}</span>
                                            </span>
                                            <span class="font-semibold">{{ $entry->counted() ? number_format(round($entry->energy_kcal)).' سعرة' : '—' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <div class="flex flex-wrap items-center gap-x-4 text-gray-400 dark:text-gray-500">
                                <span class="w-36">{{ \App\Support\Site\SyrianDate::long($d['date']) }}</span>
                                <span class="text-sm">ما سجّل شيئاً</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold">الوزنات</p>
            @if ($diary->weights)
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($diary->weights as $w)
                        <li class="flex justify-between px-4 py-2 text-sm">
                            <span>{{ \App\Support\Site\SyrianDate::long($w->weighed_on) }}</span>
                            <span class="font-semibold">{{ $w->weight_kg }} كغ</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">لم يسجّل وزنه بعد. يذكّره الموقع كل أسبوع.</p>
            @endif
        </div>
    </div>
</x-filament::section>
