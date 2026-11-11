{{-- One USDA food as an import would bring it. Params: $error, or $food, $values, $portions (FdcImporter::preview). --}}
@if ($error)
    <p class="font-bold text-danger-600">{{ $error }}</p>
@else
    <div class="space-y-6 text-sm">
        <p class="text-gray-500">
            <span dir="ltr">FDC {{ $food['fdcId'] }}</span> · {{ $food['dataType'] }}
            @if ($food['publicationDate'] ?? null) · نُشر <span dir="ltr">{{ $food['publicationDate'] }}</span> @endif
        </p>

        <div>
            <h3 class="mb-2 font-bold">القيم لكل 100 غ ({{ count($values) }})</h3>
            @if ($values)
                <dl class="grid grid-cols-1 gap-x-8 sm:grid-cols-2">
                    @foreach ($values as $value)
                        <div class="flex items-baseline justify-between gap-3 border-b py-1.5 dark:border-white/10">
                            <dt>{{ $value['name'] }}</dt>
                            <dd dir="ltr" class="font-medium">{{ rtrim(rtrim(number_format($value['amount'], 3), '0'), '.') }} {{ $value['unit'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-gray-500">لا قيم نعرفها لهذا الغذاء.</p>
            @endif
        </div>

        <div>
            <h3 class="mb-2 font-bold">الحصص ({{ count($portions) }})</h3>
            @if ($portions)
                <ul class="space-y-1">
                    @foreach ($portions as $portion)
                        <li class="flex items-baseline justify-between gap-3 border-b py-1.5 dark:border-white/10">
                            <span dir="ltr">{{ rtrim(rtrim(number_format($portion['amount'], 2), '0'), '.') }} {{ $portion['label'] }}@if ($portion['modifier'] && $portion['modifier'] !== $portion['label']), {{ $portion['modifier'] }}@endif</span>
                            <span dir="ltr" class="font-medium">{{ rtrim(rtrim(number_format($portion['grams'], 1), '0'), '.') }} g</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-gray-500">USDA لا تذكر حصصاً لهذا الغذاء؛ تُكتب الكمية بالغرام.</p>
            @endif
        </div>
    </div>
@endif
