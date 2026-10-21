{{-- Choice of account on the sign-up page. Params: $types (AccountType cases); binds Alpine `type`. --}}
@include('site.components.form.choice-cards', [
    'name' => 'account_type',
    'legend' => 'أنشئ حسابك بصفتك',
    'options' => collect($types)->mapWithKeys(fn ($t) => [$t->value => ['label' => $t->label(), 'hint' => $t->hint()]])->all(),
    'selected' => 'member',
    'model' => 'type',
])
