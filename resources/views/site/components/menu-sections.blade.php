{{-- A menu in its sections (product categories), with jump chips when there are several. Params: $sections (id, name, dishes), $empty (text when none), $showBrand (false). --}}
@if ($sections)
    {{-- The restaurant's own menu sections, in its order (the owner's decision 4a). --}}
    @if (count($sections) > 1)
        <nav class="ns-menu__nav" aria-label="أقسام المنيو">
            @foreach ($sections as $section)
                <a class="ns-chip" href="#cat-{{ $section['id'] ?? 'other' }}">{{ $section['name'] }}</a>
            @endforeach
        </nav>
    @endif
    @foreach ($sections as $section)
        <section class="ns-menu__section" id="cat-{{ $section['id'] ?? 'other' }}" @if ($section['name']) aria-labelledby="cat-{{ $section['id'] ?? 'other' }}-title" @else aria-label="الوجبات" @endif>
            @if ($section['name'])
                <h3 class="ns-menu__title" id="cat-{{ $section['id'] ?? 'other' }}-title">{{ $section['name'] }} <span class="ns-num ns-muted">{{ count($section['dishes']) }}</span></h3>
            @endif
            <div class="ns-dishes">
                @foreach ($section['dishes'] as $dish)
                    @include('site.components.dish-card', ['dish' => $dish, 'showBrand' => $showBrand ?? false])
                @endforeach
            </div>
        </section>
    @endforeach
@else
    <p class="t-body ns-muted">{{ $empty }}</p>
@endif
