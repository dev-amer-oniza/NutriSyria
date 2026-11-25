{{--
    "أكلت اليوم": each meal logged, in the order eaten; a dish asked for shows with no value
    (it is not counted until we add it). Param $day (DayLog). The add button opens the
    page's "شو أكلت؟" sheet (Alpine `sheet`).
--}}
<section class="ns-eaten" aria-labelledby="eaten-title">
    <div class="ns-eaten__head">
        <h2 class="ns-eaten__title" id="eaten-title">{{ $day->isToday ? 'أكلت اليوم' : 'أكلت بهاليوم' }}</h2>
        @if ($day->entries)
            <span class="ns-eaten__count">{{ count($day->entries) }} {{ count($day->entries) === 1 ? 'أكلة' : (count($day->entries) <= 10 ? 'أكلات' : 'أكلة') }}</span>
        @endif
    </div>

    @if ($day->entries)
        <ul class="ns-eaten__list">
            @foreach ($day->entries as $entry)
                <li @class(['ns-eaten__item', 'is-waiting' => ! $entry->counted()])>
                    <span class="ns-eaten__type ns-eaten__type--{{ $entry->meal_type->value }}">{{ $entry->meal_type->label() }}</span>
                    <span class="ns-eaten__what">
                        @if ($entry->product_id)
                            <a href="{{ route('site.meals.show', $entry->product_id) }}">{{ $entry->name }}</a>
                        @else
                            {{ $entry->name }}
                        @endif
                        <small>
                            @if ($entry->counted())
                                {{ \App\Services\Daily\DailyLog::amountLabel($entry->amount, $entry->per_100g) }}
                            @else
                                <span class="ns-eaten__asked">طلبنا إضافتها، ما انحسبت لسا</span>
                            @endif
                            · <span class="ns-num">{{ $entry->eaten_at->setTimezone(config('site.daily.timezone'))->format('G:i') }}</span>
                        </small>
                    </span>
                    <span class="ns-eaten__kcal">
                        @if ($entry->counted())
                            <b class="ns-num">{{ round($entry->energy_kcal) }}</b><small>سعرة</small>
                        @else
                            <b>—</b>
                        @endif
                    </span>
                    <form class="ns-eaten__remove" method="post" action="{{ route('site.daily.destroy', $entry->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" aria-label="شيل {{ $entry->name }} من يومك" title="شيلها">
                            @include('site.partials.icon', ['name' => 'close', 'size' => 'sm'])
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @else
        <div class="ns-eaten__empty">
            <span class="ns-eaten__empty-mark">@include('site.partials.icon', ['name' => 'bowl'])</span>
            <p>{{ $day->isToday ? 'لسا ما سجّلت شي اليوم.' : 'ما سجّلت شي بهاليوم.' }}</p>
            @if ($day->isToday)
                <small>افتح أي وجبة واكبس «أكلتها»، أو اكتبلنا أكلتك إذا ما لقيتها.</small>
            @endif
        </div>
    @endif

    @if ($day->isToday)
        <div class="ns-eaten__actions">
            <a class="ns-btn ns-btn--primary" href="{{ route('site.meals') }}">@include('site.partials.icon', ['name' => 'search'])دوّر على وجبة</a>
            <button class="ns-btn ns-btn--secondary ns-eaten__add" type="button" @click="sheet = 'request'">@include('site.partials.icon', ['name' => 'plus'])أكلت شي مو موجود</button>
        </div>
    @endif
</section>
