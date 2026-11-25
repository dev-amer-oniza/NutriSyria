{{--
    "باقيلك اليوم": the ring of what was eaten, the calories left, and the three main values
    in their own colours (ns-key-*). Param $day (App\Data\Daily\DayLog) with needs.
--}}
@php
    $over = $day->over();
    $bars = [['بروتين', 'protein_g', 'protein'], ['كربوهيدرات', 'carbohydrate_g', 'carbs'], ['دهون', 'fat_g', 'fat']];
@endphp
<section @class(['ns-today', 'is-over' => $over > 0]) style="--p: {{ $day->percent() }}%" data-fill aria-labelledby="today-title">
    <div class="ns-today__ring" role="img" aria-label="أكلت {{ $day->percent() }}% من احتياجك اليوم">
        <span class="ns-today__ring-in">
            <span>أكلت</span>
            <b class="ns-num"><span data-count>{{ $day->percent() }}</span>%</b>
        </span>
    </div>
    <div class="ns-today__main">
        <h2 class="ns-today__label" id="today-title">{{ $over > 0 ? 'زدت اليوم' : ($day->isToday ? 'باقيلك اليوم' : 'باقيلك بهاليوم') }}</h2>
        <p class="ns-today__value">
            <span class="ns-num" data-count>{{ number_format($over > 0 ? $over : $day->left()) }}</span>
            <small>سعرة</small>
        </p>
        <p class="ns-today__sub">
            أكلت <span class="ns-num">{{ number_format(round($day->eaten['energy_kcal'])) }}</span>
            من <span class="ns-num">{{ number_format(round($day->needs['energy_kcal'])) }}</span> سعرة
            @if ($day->waiting())
                <span class="ns-today__wait">· {{ $day->waiting() === 1 ? 'أكلة' : $day->waiting().' أكلات' }} ما انحسبت لسا</span>
            @endif
        </p>
    </div>
    <ul class="ns-today__bars">
        @foreach ($bars as [$label, $code, $key])
            @php($need = $day->needs[$code] ?? 0)
            <li class="ns-today__bar">
                <span class="ns-today__bar-head"><span class="ns-today__dot ns-key-{{ $key }}"></span>{{ $label }}</span>
                <i class="ns-today__track"><b class="ns-key-{{ $key }}" style="--w: {{ $need > 0 ? min(100, round($day->eaten[$code] / $need * 100)) : 0 }}%"></b></i>
                <span class="ns-today__bar-num"><span class="ns-num">{{ round($day->eaten[$code]) }}</span> من <span class="ns-num">{{ round($need) }}</span> غ</span>
            </li>
        @endforeach
    </ul>
</section>
