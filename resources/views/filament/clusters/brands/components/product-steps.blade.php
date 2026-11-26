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
    $next = match ($status) {
        null, S::NoRecipe => 'عبّي الخطوات الثلاث تحت واضغط «حفظ». بيوصل للأخصائي لحاله.',
        S::Awaiting => 'وصلت للأخصائي. بيراجع الحساب، وبيوصلك إشعار لما يعتمد أو إذا طلب تعديل.',
        S::Changed => 'عدّلت الوصفة بعد الاعتماد، فرجعت للأخصائي. القيم القديمة ظاهرة لحتى يعتمد الجديدة.',
        S::ChangesRequested => 'شوف ملاحظة الأخصائي تحت، عدّل الوصفة واحفظ، وبعدين اضغط «تم التعديل».',
        S::Approved => 'قيمها الرسمية ظاهرة للناس. أي تعديل على الوصفة بيرجّعها للأخصائي.',
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
    <p @class(['text-sm', 'text-warning-700 dark:text-warning-300 font-medium' => $attention, 'text-gray-600 dark:text-gray-300' => ! $attention])>{{ $next }}</p>
</div>
