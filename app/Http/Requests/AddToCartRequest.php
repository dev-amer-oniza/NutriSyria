<?php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Restaurants\Models\MenuItem;
use Modules\Restaurants\Models\Restaurant;
use Stancl\Tenancy\Facades\Tenancy;

class AddToCartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'restaurant_id'                           => ['required', 'string', 'exists:restaurants,id'],
            'item_id'                                 => ['required', 'string'],
            'variant_id'                              => ['nullable', 'string'],
            'quantity'                                => ['required', 'integer', 'min:1', 'max:99'],
            'description'                             => ['nullable', 'string', 'max:255'],
            'modifier_selections'                     => ['nullable', 'array'],
            'modifier_selections.*.modifier_group_id' => ['required_with:modifier_selections', 'integer'],
            'modifier_selections.*.modifier_id'       => ['required_with:modifier_selections', 'integer'],
        ];
    }
    public function after(): array
    {
        return [
            function ($validator) {

                $restaurant = Restaurant::find($this->input('restaurant_id'));

                if (! $restaurant) {
                    $validator->errors()->add('restaurant_id', 'Restaurant not found.');
                    return;
                }

                try {
                    Tenancy::initialize($restaurant->tenant_id);

                    $item = MenuItem::with('modifierGroups.modifiers')
                        ->find($this->item_id);

                    if (! $item) {
                        $validator->errors()->add('item_id', 'Item not found.');
                        return;
                    }

                    if ($item->variants()->exists() && ! $this->filled('variant_id')) {
                        $validator->errors()->add(
                            'variant_id',
                            'يجب اختيار Variant لهذا العنصر.'
                        );
                    }

                    $selections = collect($this->modifier_selections ?? []);

                    foreach ($item->modifierGroups as $group) {
                        $selectedForGroup = $selections->where('modifier_group_id', $group->id);
                        $count            = $selectedForGroup->count();

                        if ($group->is_required && $count < $group->min_selections) {
                            $validator->errors()->add(
                                'modifier_selections',
                                "يجب اختيار خيار من: {$group->name}"
                            );
                        }

                        if ($count > $group->max_selections) {
                            $validator->errors()->add(
                                'modifier_selections',
                                "تجاوزت الحد المسموح في: {$group->name}"
                            );
                        }

                        $validModifierIds = $group->modifiers->pluck('id');

                        foreach ($selectedForGroup as $sel) {
                            if (! $validModifierIds->contains($sel['modifier_id'])) {
                                $validator->errors()->add(
                                    'modifier_selections',
                                    "الخيار المحدد غير صالح في: {$group->name}"
                                );
                            }
                        }
                    }

                } finally {
                    Tenancy::end();
                }
            }
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
