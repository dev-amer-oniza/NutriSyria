{{-- Share of energy from protein, carbs and fat (4/4/9 kcal per gram). Params: $protein, $carbs, $fat in grams. --}}
<div class="ns-macrobar" role="img" aria-label="توزّع الطاقة بين البروتين والكربوهيدرات والدهون">
    <span class="ns-key-protein" style="flex-grow: {{ 4 * $protein }}"></span>
    <span class="ns-key-carbs" style="flex-grow: {{ 4 * $carbs }}"></span>
    <span class="ns-key-fat" style="flex-grow: {{ 9 * $fat }}"></span>
</div>
