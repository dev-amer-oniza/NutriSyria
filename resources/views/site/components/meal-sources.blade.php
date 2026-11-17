{{--
    "من أين؟" for one nutrient of one meal (step 4): the ingredients that give it, largest
    first. The platform's kitchen in grams and shares; a restaurant by rank only, its recipe
    being private. Params: $meal (MealFacts), $code, optional $heading (default true).
--}}
@php
    [$name, $unit] = \App\Services\Site\MealCatalog::nutrientName($code);
    $rows = $meal->sourcesOf($code);
    $total = $meal->amount($code);
    $ranked = $rows !== [] && $rows[0]['amount'] === null;
    $keys = ['protein_g' => 'protein', 'carbohydrate_g' => 'carbs', 'fat_g' => 'fat', 'fat_sat_g' => 'fat', 'fiber_g' => 'fiber', 'energy_kcal' => 'energy'];
@endphp
@if ($rows)
    <div class="ns-src">
        @if ($heading ?? true)
            <p class="ns-src__title">من أين {{ $name }}؟@if ($total !== null && ! $ranked) <span class="ns-muted">(<span class="ns-num">{{ \App\Services\Nutrition\Fit\MealFitCalculator::number($total) }}</span> {{ $unit }} في الحصة)</span>@endif</p>
        @endif
        @if ($ranked)
            <ol class="ns-src__ranks">
                @foreach ($rows as $row)<li>{{ $row['label'] }}</li>@endforeach
            </ol>
            <p class="ns-src__note">الأكثر أولاً. نذكر الترتيب فقط، لأن كميات وصفة المطعم سرّية.</p>
        @else
            <ul class="ns-src__list">
                @foreach ($rows as $row)
                    <li>
                        <span class="ns-src__label">{{ $row['label'] }}</span>
                        <span class="ns-src__amount"><span class="ns-num">{{ \App\Services\Nutrition\Fit\MealFitCalculator::number($row['amount']) }}</span> {{ $unit }}</span>
                        <span class="ns-src__pct ns-num">{{ $row['pct'] }}%</span>
                        <span class="ns-src__bar"><i class="ns-key-{{ $keys[$code] ?? 'protein' }}" style="width: {{ max(2, $row['pct']) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
