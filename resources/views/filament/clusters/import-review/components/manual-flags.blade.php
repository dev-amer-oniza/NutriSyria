@if ($flags)
    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-200">
        <p class="font-semibold">راجع القيم قبل الحفظ:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($flags as $flag)
                <li>{{ $flag->detail }}</li>
            @endforeach
        </ul>
        <p class="mt-1">إن كانت صحيحة كما في مصدرها، اكتب السبب تحت واحفظ.</p>
    </div>
@endif
