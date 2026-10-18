<?php

namespace Tests\Feature;

use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Filament\Clusters\FoodDatabase\Resources\Food\FoodResource;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\ListFood;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodAlias;
use App\Models\FoodSourceRecord;
use App\Support\ArabicSearch;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/** "ارز" finds "أرز", "جبنه" finds "جبنة": brand owners type without hamza or ta marbuta. */
class ArabicSearchTest extends TestCase
{
    use RefreshDatabase;

    private Food $rice;

    private Food $cheese;

    private Food $sugar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->rice = Food::create(['name_ar' => 'أرز أبيض', 'name_en' => 'Rice, white']);
        $this->cheese = Food::create(['name_ar' => 'جبنة بيضاء', 'name_en' => 'Cheese, feta']);
        $this->sugar = Food::create(['name_ar' => 'سُكَّر', 'name_en' => 'Sugar']);
    }

    /** @return list<string> */
    private function find(string $search): array
    {
        return ArabicSearch::constrain(Food::query(), ['name_ar', 'name_en'], $search)->orderBy('id')->pluck('name_ar')->all();
    }

    public function test_spelling_variants_match(): void
    {
        $this->assertSame(['أرز أبيض'], $this->find('ارز'));
        $this->assertSame(['جبنة بيضاء'], $this->find('جبنه'));
        $this->assertSame(['جبنة بيضاء'], $this->find('بيضا'));
        $this->assertSame(['سُكَّر'], $this->find('سكر'), 'diacritics in the name are ignored');
        $this->assertSame(['أرز أبيض'], $this->find('أَرز'), 'and in the search');
        $this->assertSame(['جبنة بيضاء'], $this->find('FETA'), 'English is case-insensitive');
        $this->assertSame([], $this->find('عدس'));
        $this->assertSame([], $this->find('%'), 'a wildcard is a plain character');
    }

    public function test_the_ingredient_picker_finds_approved_records_by_any_spelling_or_alias(): void
    {
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $record = fn (Food $food, FoodSourceStatus $status) => FoodSourceRecord::create([
            'food_id' => $food->id, 'data_source_id' => $source->id, 'external_ref' => (string) random_int(1, 999999), 'status' => $status,
        ]);
        $rice = $record($this->rice, FoodSourceStatus::Active);
        $record($this->rice, FoodSourceStatus::PendingReview);
        $cheese = $record($this->cheese, FoodSourceStatus::Active);
        FoodAlias::create(['food_id' => $this->cheese->id, 'name_ar' => 'جبنة عكاوي', 'search_normalized' => 'جبنه عكاوي']);

        $options = new ReflectionMethod(RecipeIngredientsRelationManager::class, 'ingredientOptions');

        $this->assertSame([$rice->id], array_keys($options->invoke(null, 'ارز')), 'approved only');
        $this->assertSame([$cheese->id], array_keys($options->invoke(null, 'عكاوى')), 'through an alias');
        $this->assertSame([$rice->id, $cheese->id], array_keys($options->invoke(null)), 'preloaded list, by name');
    }

    public function test_the_food_table_and_the_top_search_bar_ignore_spelling(): void
    {
        $this->signInAs();

        Livewire::test(ListFood::class)
            ->searchTable('جبنه')
            ->assertCanSeeTableRecords([$this->cheese])
            ->assertCanNotSeeTableRecords([$this->rice, $this->sugar]);

        $this->assertSame(['أرز أبيض'], FoodResource::getGlobalSearchResults('ارز')->pluck('title')->map(fn ($t) => (string) $t)->all());
    }

    public function test_every_dropdown_opens_preloaded(): void
    {
        $this->assertTrue(Select::make('anything')->isPreloaded());
        $this->assertTrue(SelectFilter::make('anything')->isPreloaded());
    }
}
