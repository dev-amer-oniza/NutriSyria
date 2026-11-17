{{-- The references of one rule, each opening to its verbatim quote. Params: $keys, $references (MedicalReferences). --}}
<div class="mt-1 grid gap-1">
    @foreach ($keys as $key)
        @php($ref = $references->reference($key))
        @if ($ref)
            <details class="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
                <summary class="cursor-pointer font-medium text-primary-700 dark:text-primary-300">{{ $ref['org'] }} · {{ $ref['year'] }}</summary>
                <div class="mt-2 grid gap-2">
                    <p><a class="underline" href="{{ $ref['url'] }}" target="_blank" rel="noopener" dir="ltr">{{ $ref['title'] }}</a> <span dir="ltr">({{ $ref['where'] }})</span></p>
                    <blockquote class="border-s-2 border-primary-500 ps-3 italic" dir="ltr" lang="en">{{ $ref['quote'] }}</blockquote>
                    <p>{{ $ref['summary'] }}</p>
                    <p class="text-gray-500">ينطبق على: {{ $ref['applies'] }} · قُرئ في <span dir="ltr">{{ $ref['read_at'] }}</span></p>
                    <p class="flex flex-wrap gap-1">
                        @foreach (['source' => 'المصدر قُرئ', 'applicability' => 'ينطبق على حالتنا', 'test' => 'اختبار يطابق الكود', 'specialist' => 'مراجعة مختص'] as $gate => $label)
                            <x-filament::badge :color="($ref['gates'][$gate] ?? false) ? 'success' : 'gray'" size="sm">{{ ($ref['gates'][$gate] ?? false) ? '✓' : '—' }} {{ $label }}</x-filament::badge>
                        @endforeach
                    </p>
                </div>
            </details>
        @endif
    @endforeach
</div>
