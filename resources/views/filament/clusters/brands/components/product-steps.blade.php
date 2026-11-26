{{--
    Where the dish stands, as a path to read at a glance instead of a status to choose (the owner's
    request): الوصفة ← عند الأخصائي ← ظاهر بالموقع. Param $status (App\Enums\NutritionApprovalStatus,
    null on a new product). "مطلوب تعديل" and "تغيّرت بعد الاعتماد" mark the step they are at.
--}}
@php
    use App\Enums\NutritionApprovalStatus as S;

    $at = match ($status) {
        null, S::NoRecipe => 1,
        S::Awaiting, S::Changed, S::ChangesRequested => 2,
        S::Approved => 3,
    };
    $steps = [
        1 => 'اكتب الوصفة',
        2 => $status === S::ChangesRequested ? 'الأخصائي طلب تعديل' : 'عند الأخصائي',
        3 => 'معتمد وظاهر بالموقع',
    ];
    // What it means for the owner right now, and what happens next (the owner's request: say it plainly).
    $next = match ($status) {
        null, S::NoRecipe => [
            'title' => 'ابدأ بالوصفة',
            'text' => 'عبّي الخطوات ١ و٢ و٣ تحت واضغط «حفظ». الوصفة بتروح لأخصائي التغذية لحالها، ما في شي تاني تعمله.',
        ],
        S::Awaiting => [
            'title' => 'الوصفة عند أخصائي التغذية هلق',
            'text' => 'ما عليك شي. هو عم يتأكد من المكوّنات والكميات. لما يخلص بيوصلك إشعار على الجرس فوق: يا «اعتُمد» وبيطلع الطبق للناس بالموقع، يا «بدها تعديل» مع ملاحظة بتقلك شو تغيّر.',
        ],
        S::Changed => [
            'title' => 'عدّلت الوصفة، فرجعت للأخصائي',
            'text' => 'لحتى يراجع التعديل ويعتمده، الناس بيشوفوا بالموقع القيم القديمة. بيوصلك إشعار على الجرس لما يخلص.',
        ],
        S::ChangesRequested => [
            'title' => 'الأخصائي طلب تعديل',
            'text' => 'اقرأ ملاحظته تحت، عدّل الوصفة واضغط «حفظ»، وبعدين اضغط «تم التعديل» لترجع له.',
        ],
        S::Approved => [
            'title' => 'معتمد وظاهر للناس',
            'text' => 'قيمه الرسمية ظاهرة بالموقع. إذا غيّرت أي مكوّن أو كمية، بترجع الوصفة للأخصائي ليراجعها من جديد.',
        ],
    };
    $attention = $status === S::ChangesRequested;
@endphp

<div class="flex flex-col gap-3">
    <ol class="flex flex-wrap items-center gap-2" aria-label="مراحل المنتج">
        @foreach ($steps as $n => $label)
            @php
                $done = $n < $at || ($n === 3 && $at === 3);
                $current = $n === $at && ! $done;
            @endphp
            <li @class([
                'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
                'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-300' => $done,
                'bg-warning-50 text-warning-700 ring-2 ring-warning-400 dark:bg-warning-400/10 dark:text-warning-300' => $current && $attention,
                'bg-primary-50 text-primary-700 ring-2 ring-primary-500 dark:bg-primary-400/10 dark:text-primary-300' => $current && ! $attention,
                'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $done && ! $current,
            ]) @if ($current) aria-current="step" @endif>
                <span @class([
                    'grid h-6 w-6 place-items-center rounded-full text-xs',
                    'bg-success-600 text-white' => $done,
                    'bg-warning-500 text-white' => $current && $attention,
                    'bg-primary-600 text-white' => $current && ! $attention,
                    'bg-gray-300 text-white dark:bg-white/20' => ! $done && ! $current,
                ])>{{ $done ? '✓' : $n }}</span>
                {{ $label }}
            </li>
            @if (! $loop->last)
                <li aria-hidden="true" class="text-gray-300 dark:text-gray-600">←</li>
            @endif
        @endforeach
    </ol>
    <div @class([
        'rounded-xl border p-3 sm:p-4',
        'border-warning-300 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-400/10' => $attention,
        'border-success-300 bg-success-50 dark:border-success-400/30 dark:bg-success-400/10' => $status === S::Approved,
        'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5' => ! $attention && $status !== S::Approved,
    ])>
        <p class="text-base font-bold text-gray-950 dark:text-white">{{ $next['title'] }}</p>
        <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $next['text'] }}</p>
    </div>
</div>
