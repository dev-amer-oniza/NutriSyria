{{--
    How the day's calories were reached, one step a line (CriteriaCalculator::energy).
    Param $energy: bmr, factor, base, exercise, tdee, pal, unusual, adjustment, floored, held.
--}}
@php($n = fn ($v) => '<span class="ns-num">'.number_format(abs($v)).'</span>')
<ol class="ns-energy-steps">
    <li><span>جسمك وأنت مرتاح (Mifflin-St Jeor)</span> <b>{!! $n($energy['bmr']) !!}</b></li>
    <li><span>مع نهارك بلا رياضة (× <span class="ns-num">{{ rtrim(rtrim(number_format($energy['factor'], 3), '0'), '.') }}</span>)</span> <b>{!! $n($energy['base']) !!}</b></li>
    @if ($energy['exercise'] > 0)
        <li><span>رياضتك، بمعدّل اليوم</span> <b dir="ltr">+ {!! $n($energy['exercise']) !!}</b></li>
    @endif
    <li class="ns-energy-steps__total"><span>احتياجك لتحافظ على وزنك</span> <b>{!! $n($energy['tdee']) !!}</b></li>
    @if ($energy['adjustment'] !== 0)
        <li><span>{{ $energy['adjustment'] < 0 ? 'ناقص لهدف إنقاص الوزن' : 'زائد لهدف زيادة الوزن (رقم اختيار منّا)' }}</span>
            <b dir="ltr">{{ $energy['adjustment'] < 0 ? '−' : '+' }} {!! $n($energy['adjustment']) !!}</b></li>
    @endif
</ol>
@if ($energy['floored'])
    <p class="ns-energy-steps__note">لم ننزل تحت الحد الأدنى (<span class="ns-num">{{ number_format($energy['tdee'] + $energy['adjustment']) }}</span> سعرة)، فالإنقاص سيكون أبطأ.</p>
@elseif ($energy['held'])
    <p class="ns-energy-steps__note">احتياجك قريب من الحد الأدنى، فلم ننقص منه. لتنقص وزنك: زِد نشاطك، أو استشر أخصائي تغذية.</p>
@endif
@if ($energy['unusual'])
    <p class="ns-energy-steps__note">الرقم غير معتاد لنشاطك (<span class="ns-num">{{ $energy['pal'] }}</span> ضعف احتياجك وأنت مرتاح). راجع أجوبتك عن نهارك ورياضتك.</p>
@endif
