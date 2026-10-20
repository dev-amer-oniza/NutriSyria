{{-- How well a meal fits one person. Params: $level 1..4 (labels in config/site.php fit_levels). --}}
<div class="ns-verdict" data-level="{{ $level }}">
    <span class="ns-verdict__meter" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
    <span class="ns-verdict__label">{{ config("site.fit_levels.$level") }}</span>
</div>
