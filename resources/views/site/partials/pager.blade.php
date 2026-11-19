{{--
    Page buttons under a site list. Param: $page (LengthAwarePaginator from App\Support\Site\Pages).
    Shows nothing for a single page. Numbers: the first, the last, and the current one with its
    neighbours; "…" fills the gaps.
--}}
@if ($page->hasPages())
    @php
        $current = $page->currentPage();
        $last = $page->lastPage();
        $numbers = collect([1, $current - 1, $current, $current + 1, $last])
            ->filter(fn ($n) => $n >= 1 && $n <= $last)->unique()->sort()->values();
    @endphp
    <nav class="ns-pager" aria-label="صفحات القائمة">
        @if ($page->onFirstPage())
            <span class="ns-pager__step" aria-disabled="true">@include('site.partials.icon', ['name' => 'back', 'size' => 'sm'])السابق</span>
        @else
            <a class="ns-pager__step" href="{{ $page->previousPageUrl() }}" rel="prev">@include('site.partials.icon', ['name' => 'back', 'size' => 'sm'])السابق</a>
        @endif

        <ol class="ns-pager__numbers">
            @foreach ($numbers as $i => $n)
                @if ($i > 0 && $n - $numbers[$i - 1] > 1)
                    <li class="ns-pager__gap" aria-hidden="true">…</li>
                @endif
                <li>
                    @if ($n === $current)
                        <span class="ns-pager__n ns-num" aria-current="page">{{ $n }}</span>
                    @else
                        <a class="ns-pager__n ns-num" href="{{ $page->url($n) }}" aria-label="الصفحة {{ $n }}">{{ $n }}</a>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($page->hasMorePages())
            <a class="ns-pager__step" href="{{ $page->nextPageUrl() }}" rel="next">التالي@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</a>
        @else
            <span class="ns-pager__step" aria-disabled="true">التالي@include('site.partials.icon', ['name' => 'arrow', 'size' => 'sm'])</span>
        @endif
    </nav>
@endif
