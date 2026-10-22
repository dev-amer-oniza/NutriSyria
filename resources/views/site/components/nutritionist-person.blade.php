{{-- A nutritionist's photo, name and specialties in one row. Params: $profile (NutritionistProfile), $link (true). --}}
<div class="ns-person">
    <span class="ns-person__photo">
        @if ($profile->photoUrl())
            <img src="{{ $profile->photoUrl() }}" alt="">
        @else
            @include('site.components.chain-tiles', ['pattern' => 'step-4', 'tile' => 28])
        @endif
    </span>
    <span>
        @if ($link ?? true)
            <a class="ns-person__name" href="{{ route('site.nutritionists.show', $profile) }}">{{ $profile->display_name }}</a>
        @else
            <span class="ns-person__name">{{ $profile->display_name }}</span>
        @endif
        <span class="ns-person__meta">{{ implode('، ', $profile->specialtyLabels()) }}</span>
    </span>
</div>
