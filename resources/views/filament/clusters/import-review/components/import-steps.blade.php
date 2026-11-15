{{--
    Where you are on the way from "I have foods" to "they are approved".
    $current: 1 the way, 2 matching, 3 check and save, 4 review. $matching: false hides step 2
    (ready files skip it).
--}}
@php
    $matching ??= true;
    $steps = array_filter([
        1 => 'الطريقة',
        2 => $matching ? 'المطابقة' : null,
        3 => 'الفحص والحفظ',
        4 => 'المراجعة',
    ]);
    $number = 0;
@endphp

<nav aria-label="مراحل إضافة الأغذية" class="mb-6">
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        @foreach ($steps as $step => $label)
            @php
                $number++;
                $state = $step < $current ? 'done' : ($step === $current ? 'current' : 'next');
            @endphp
            <li class="flex items-center gap-2" @if ($state === 'current') aria-current="step" @endif>
                <span @class([
                    'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                    'bg-primary-600 text-white dark:bg-primary-500 dark:text-gray-950' => $state === 'current',
                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $state === 'done',
                    'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $state === 'next',
                ])>
                    @if ($state === 'done') ✓ @else {{ $number }} @endif
                </span>
                <span @class([
                    'font-semibold text-gray-950 dark:text-white' => $state === 'current',
                    'text-gray-600 dark:text-gray-300' => $state !== 'current',
                ])>{{ $label }}</span>
                @if (! $loop->last)
                    <span class="mx-1 h-px w-6 bg-gray-300 dark:bg-white/20" aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
