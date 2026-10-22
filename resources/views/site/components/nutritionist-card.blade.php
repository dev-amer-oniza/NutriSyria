{{-- One nutritionist in the directory. Param $profile (App\Models\NutritionistProfile, listed). --}}
<a class="ns-nutri" href="{{ route('site.nutritionists.show', $profile) }}">
    <div class="ns-nutri__photo">
        @if ($profile->photoUrl())
            <img src="{{ $profile->photoUrl() }}" alt="{{ $profile->display_name }}" loading="lazy">
        @else
            @include('site.components.chain-tiles', ['pattern' => \App\Support\Site\ChainPatterns::dishCover($profile->id), 'tile' => 40])
        @endif
    </div>
    <div class="ns-nutri__body">
        <h3 class="ns-nutri__name">{{ $profile->display_name }}</h3>
        <p class="ns-nutri__meta">
            {{ $profile->city->label() }} · {{ $profile->consultation_mode->label() }}
            @if ($profile->years_experience) · خبرة <span class="ns-num">{{ $profile->years_experience }}</span> سنة @endif
        </p>
        <ul class="ns-nutri__tags">
            @foreach (array_slice($profile->specialtyLabels(), 0, 3) as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ul>
        <p class="ns-nutri__free">الاستشارة الأولى مجانية</p>
    </div>
</a>
