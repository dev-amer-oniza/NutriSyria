{{-- The references of one rule: who and when, what it says in Arabic, and its verbatim sentence on demand. Params: $keys, $references. --}}
<ul class="ns-ref-quotes">
    @foreach ($keys as $key)
        @php($ref = $references->reference($key))
        @if ($ref)
            <li>
                <a class="ns-ref-quotes__src" href="{{ $ref['url'] }}" target="_blank" rel="noopener">{{ $ref['org'] }} · <span class="ns-num">{{ $ref['year'] }}</span> ↗</a>
                <p>{{ $ref['summary'] }}</p>
                <details>
                    <summary>النص كما في المرجع</summary>
                    <blockquote dir="ltr" lang="en">«{{ $ref['quote'] }}»<cite>{{ $ref['title'] }} · {{ $ref['where'] }}</cite></blockquote>
                </details>
            </li>
        @endif
    @endforeach
</ul>
