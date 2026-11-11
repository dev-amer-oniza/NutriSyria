{{--
    Numbered steps on a sand band. Params: $id (title id), $eyebrow, $title, $lead,
    $steps (list of {title, text, icon?}; four fit one row). A step with an icon shows it, not the tiles.
--}}
<section class="ns-section ns-section--sand" aria-labelledby="{{ $id }}">
    <div class="ns-wrap">
        <div class="ns-section__head ns-section__head--center">
            <div>
                <p class="ns-eyebrow">{{ $eyebrow }}</p>
                <h2 class="t-title" id="{{ $id }}">{{ $title }}</h2>
                <p class="t-lead ns-muted">{{ $lead }}</p>
            </div>
        </div>

        <ol class="ns-steps">
            @foreach ($steps as $i => $step)
                <li class="ns-step">
                    <div class="ns-step__art">
                        @if ($step['icon'] ?? null)
                            <span class="ns-step__icon">@include('site.partials.icon', ['name' => $step['icon']])</span>
                        @else
                            @include('site.components.chain-tiles', ['pattern' => 'step-'.($i + 1), 'tile' => $i === 3 ? 48 : 96])
                        @endif
                    </div>
                    <span class="ns-step__n ns-num">{{ sprintf('%02d', $i + 1) }}</span>
                    <h3 class="ns-step__title">{{ $step['title'] }}</h3>
                    <p class="ns-step__text">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
