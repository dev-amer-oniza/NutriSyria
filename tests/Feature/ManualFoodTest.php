<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\ImportReview\Pages\AddFoods;
use App\Filament\Clusters\ImportReview\Pages\ManualFood;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use App\Models\Nutrient;
use App\Services\Import\ManualFoodImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "من مصدر آخر": a food USDA does not have, typed in per 100 g (the owner's decisions 1a 2a). */
class ManualFoodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        FoodForm::create(['code' => 'dried', 'name_ar' => 'مجفف']);
        foreach ([['energy_kcal', 'الطاقة', 'kcal', 'energy'], ['protein_g', 'البروتين', 'g', 'proximate'], ['fat_g', 'الدهون', 'g', 'proximate'],
            ['carbohydrate_g', 'الكربوهيدرات', 'g', 'carbohydrate'], ['fiber_g', 'الألياف', 'g', 'carbohydrate'], ['sugars_g', 'السكريات', 'g', 'carbohydrate'],
            ['fat_sat_g', 'المشبعة', 'g', 'fat'], ['sodium_mg', 'الصوديوم', 'mg', 'mineral'], ['iron_mg', 'الحديد', 'mg', 'mineral']] as $i => [$code, $name, $unit, $group]) {
            Nutrient::create(['code' => $code, 'name_ar' => $name, 'name_en' => $code, 'unit' => $unit, 'group' => $group, 'display_order' => $i]);
        }
        $spoon = MeasureUnit::create(['code' => 'tsp', 'name_ar' => 'ملعقة صغيرة', 'dimension' => 'household']);
        MeasureUnitAlias::create(['measure_unit_id' => $spoon->id, 'label' => 'tsp']);
    }

    /** Made-up test numbers whose calories match 4/9/4 (4×18 + 9×22 + 4×45 = 450). */
    private function form(array $more = []): array
    {
        return array_replace_recursive([
            'target' => 'new', 'food_name_ar' => 'حبة البركة', 'food_form_code' => 'dried',
            'source' => 'ملصق المنتج',
            'values' => ['energy_kcal' => 450, 'protein_g' => 18, 'fat_g' => 22, 'carbohydrate_g' => 45],
        ], $more);
    }

    public function test_a_nutritionist_does_not_open_the_page(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get(ManualFood::getUrl())->assertForbidden();
    }

    public function test_the_admin_opens_the_page_and_finds_it_among_the_ways(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $this->get(ManualFood::getUrl())->assertOk()->assertSee('من وين جبت الأرقام؟')->assertSee('رجوع إلى إضافة أغذية');
        $this->get(AddFoods::getUrl())->assertOk()->assertSee('من مصدر آخر');
    }

    public function test_the_four_main_values_and_the_source_are_asked_for(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        Livewire::test(ManualFood::class)
            ->fillForm(['target' => 'new', 'food_name_ar' => 'حبة البركة', 'food_form_code' => 'dried', 'values' => ['energy_kcal' => 450]])
            ->call('save')
            ->assertHasFormErrors(['values.protein_g' => 'required', 'values.fat_g' => 'required', 'values.carbohydrate_g' => 'required', 'source' => 'required']);
        $this->assertSame(0, FoodSourceRecord::count());
    }

    public function test_a_food_is_added_approved_under_the_manual_source_after_usda(): void
    {
        $admin = $this->signInAs(Access::SUPER_ADMIN);
        Livewire::test(ManualFood::class)
            ->fillForm($this->form([
                'values' => ['fiber_g' => 10, 'iron_mg' => 9],
                'portions' => [['measure_unit_id' => MeasureUnit::where('code', 'tsp')->value('id'), 'amount' => 1, 'gram_weight' => 3]],
                'allergens_checked' => true,
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        $record = FoodSourceRecord::with(['food', 'nutrientValues.nutrient', 'portions', 'dataSource'])->sole();
        $this->assertSame('حبة البركة', $record->food->name_ar);
        $this->assertSame(FoodSourceStatus::Active, $record->status);
        $this->assertSame($admin->id, $record->reviewed_by);
        $this->assertSame(ManualFoodImporter::SOURCE, $record->dataSource->code);
        $this->assertGreaterThan(120, $record->dataSource->priority);   // after every USDA source (1a)
        $this->assertStringContainsString('ملصق المنتج', $record->notes);
        $this->assertEqualsCanonicalizing(['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'fiber_g', 'iron_mg'], $record->nutrientValues->pluck('nutrient.code')->all());
        $this->assertSame(3.0, (float) $record->portions->sole()->gram_weight);
        $this->assertTrue($record->food->allergens_checked);
    }

    public function test_a_warning_needs_a_reason_written_into_the_note(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        // 4×18 + 9×22 + 4×45 = 450, typed as 250: far from 4/9/4.
        $page = Livewire::test(ManualFood::class)
            ->fillForm($this->form(['values' => ['energy_kcal' => 250]]))
            ->assertSee('راجع القيم قبل الحفظ')
            ->call('save')
            ->assertHasFormErrors(['reason' => 'required']);
        $this->assertSame(0, FoodSourceRecord::count());

        $page->fillForm($this->form(['values' => ['energy_kcal' => 250], 'reason' => 'هكذا على الملصق']))
            ->call('save')
            ->assertHasNoFormErrors();
        $record = FoodSourceRecord::sole();
        $this->assertSame(FoodSourceStatus::Active, $record->status);
        $this->assertStringContainsString('هكذا على الملصق', $record->notes);
    }

    public function test_a_form_of_an_existing_food_is_added_to_it(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $food = Food::create(['name_ar' => 'كريمة تمر']);
        Livewire::test(ManualFood::class)
            ->fillForm($this->form(['target' => 'existing', 'food_id' => $food->id]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($food->id, FoodSourceRecord::sole()->food_id);
        $this->assertSame(1, Food::count());
        $this->assertSame(1, DataSource::where('code', ManualFoodImporter::SOURCE)->count());
    }
}
