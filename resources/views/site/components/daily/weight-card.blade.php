{{--
    "وزنك": the latest weight, how it moved over the last weighings (a little line), and the
    button that opens the page's weight sheet (Alpine `sheet` = 'weight'). Param $weight (WeightCard).
--}}
@php
    $points = $weight->points;
    $line = null;
    if (count($points) > 1) {
        $min = min($points);
        $span = max(max($points) - $min, 0.5);
        $step = 300 / (count($points) - 1);
        $line = collect($points)->map(fn ($kg, $i) => round($i * $step, 1).','.round(8 + (1 - ($kg - $min) / $span) * 44, 1))->all();
    }
    $change = $weight->change;
@endphp
<section class="ns-weight" id="weight" aria-labelledby="weight-title">
    <div class="ns-weight__head">
        <h2 class="ns-weight__title" id="weight-title">وزنك</h2>
        @if ($weight->due)
            <span class="ns-weight__due">صار وقت توزن</span>
        @endif
    </div>

    @if ($weight->current !== null)
        <div class="ns-weight__now">
            <p class="ns-weight__value"><span class="ns-num">{{ rtrim(rtrim(number_format($weight->current, 1), '0'), '.') }}</span> <small>كغ</small></p>
            <div class="ns-weight__meta">
                @if ($change !== null)
                    <p @class(['ns-weight__change', 'is-down' => $change < 0, 'is-up' => $change > 0])>
                        @if ($change < 0)
                            نزلت <span class="ns-num">{{ abs($change) }}</span> كغ
                        @elseif ($change > 0)
                            زدت <span class="ns-num">{{ $change }}</span> كغ
                        @else
                            ثابت
                        @endif
                        بـ<span class="ns-num">{{ $weight->changeWeeks }}</span> {{ $weight->changeWeeks === 1 ? 'أسبوع' : ($weight->changeWeeks <= 10 ? 'أسابيع' : 'أسبوعاً') }}
                    </p>
                @endif
                <p class="ns-weight__when">
                    @if ($weight->daysSince === null)
                        من ملفك. سجّله أول مرة لنتابع معك.
                    @elseif ($weight->daysSince === 0)
                        وزنت اليوم
                    @elseif ($weight->daysSince === 1)
                        آخر مرة مبارح
                    @else
                        آخر مرة من <span class="ns-num">{{ $weight->daysSince }}</span> {{ $weight->daysSince <= 10 ? 'أيام' : 'يوم' }}
                    @endif
                </p>
            </div>
        </div>

        @if ($line)
            <svg class="ns-weight__line" viewBox="-6 0 312 60" preserveAspectRatio="none" role="img" aria-label="وزنك بآخر {{ count($points) }} مرات">
                <polyline points="{{ implode(' ', $line) }}" />
                {{-- The last weighing: a round dot that keeps its shape when the line stretches. --}}
                <line x1="{{ explode(',', end($line))[0] }}" y1="{{ explode(',', end($line))[1] }}" x2="{{ explode(',', end($line))[0] }}" y2="{{ explode(',', end($line))[1] }}" />
            </svg>
        @endif
    @else
        <p class="ns-weight__when">سجّل وزنك مرة بالأسبوع، ومنحدّث احتياجك عليه لحاله.</p>
    @endif

    <button @class(['ns-btn', 'ns-weight__go', 'ns-btn--primary' => $weight->due, 'ns-btn--secondary' => ! $weight->due]) type="button" @click="sheet = 'weight'">
        @include('site.partials.icon', ['name' => 'scale'])سجّل وزنك
    </button>
</section>
