{{-- "مطعم ولا براند؟" for a brand owner signing up (users.brand_kind). Param: $selected (value, optional). --}}
@include('site.components.form.choice-cards', [
    'name' => 'brand_kind',
    'legend' => 'شو عندك؟',
    'hint' => 'نعرض كل واحد بمكانه بالموقع. منراجع الاختيار معك قبل التفعيل.',
    'options' => collect(\App\Enums\BrandKind::cases())->mapWithKeys(fn ($k) => [$k->value => ['label' => $k->getLabel(), 'hint' => $k->hint()]])->all(),
    'selected' => $selected ?? null,
    'cols' => 2,
])
