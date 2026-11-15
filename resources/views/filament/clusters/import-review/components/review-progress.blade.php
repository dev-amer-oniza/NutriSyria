{{-- How far the review of one batch is: one bar, then the counts it is made of.
     Spans only: this lands inside the table's <p> description, where a <div> would break out. --}}
@php
    $total = max(1, $progress['total']);
    $share = fn (int $n) => round($n / $total * 100, 2);
    $clean = $progress['pending'] - $progress['flagged'];
@endphp

<span class="grid gap-2 pt-1">
    <span class="flex h-2.5 w-full max-w-md overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" role="img"
         aria-label="معتمد {{ $progress['active'] }} من {{ $progress['total'] }}">
        <span class="bg-success-500" style="width: {{ $share($progress['active']) }}%"></span>
        <span class="bg-danger-500" style="width: {{ $share($progress['rejected']) }}%"></span>
        <span class="bg-warning-400" style="width: {{ $share($progress['flagged']) }}%"></span>
    </span>
    <span class="text-sm text-gray-600 dark:text-gray-300">
        <span class="font-semibold text-gray-950 dark:text-white">{{ $progress['total'] }}</span> سجل
        · <span class="text-success-600 dark:text-success-400">{{ $progress['active'] }} معتمد</span>
        · {{ $progress['pending'] }} باقٍ
        @if ($progress['pending'] > 0)
            (<span class="text-warning-600 dark:text-warning-400">{{ $progress['flagged'] }} عليها تنبيه</span>، {{ $clean }} سليم)
        @endif
        · <span class="text-danger-600 dark:text-danger-400">{{ $progress['rejected'] }} مرفوض</span>
        @if ($progress['other'] > 0)
            · {{ $progress['other'] }} مستبدَل
        @endif
    </span>
</span>
