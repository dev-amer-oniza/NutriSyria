<?php

namespace Tests\Feature;

use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Pages\EditFoodSourceRecord;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\RelationManagers\PortionsRelationManager;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use Filament\Actions\AssociateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "ربط" on a record's portions lists the food's other portions, each with its unit name. */
class PortionsAssociateTest extends TestCase
{
    use RefreshDatabase;

    public function test_associate_lists_several_portions_without_loading_units_one_by_one(): void
    {
        $this->signInAs();
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $raw = FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'dimension' => 'count']);
        $egg = Food::create(['name_ar' => 'بيض']);
        $record = FoodSourceRecord::create(['food_id' => $egg->id, 'food_form_id' => $raw->id, 'data_source_id' => $source->id, 'external_ref' => '171287', 'status' => 'active']);
        foreach (['small' => 38, 'medium' => 44, 'large' => 50] as $size => $grams) {
            FoodPortion::create(['food_id' => $egg->id, 'measure_unit_id' => $piece->id, 'amount' => 1, 'gram_weight' => $grams, 'description' => $size]);
        }

        Livewire::test(PortionsRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditFoodSourceRecord::class])
            ->mountAction(TestAction::make(AssociateAction::class)->table())
            // Before the fix this threw LazyLoadingViolationException while building the options.
            ->assertActionMounted(TestAction::make(AssociateAction::class)->table());
    }
}
