<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\EditImportBatch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers\FoodSourceRecordsRelationManager;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers\PortionDecisionsRelationManager;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodNutrientValue;
use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Services\Import\BatchReview;
use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\GramsConverter;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class BatchReviewTest extends TestCase
{
    use RefreshDatabase;

    private ImportBatch $batch;

    private DataSource $usda;

    private FoodForm $raw;

    /** @var array<string, Nutrient> */
    private array $nutrients = [];

    private FoodSourceRecord $clean;

    private FoodSourceRecord $flagged;

    private FoodSourceRecord $approved;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['energy_kcal' => ['الطاقة', 'kcal'], 'protein_g' => ['البروتين', 'g'], 'fat_g' => ['الدهون', 'g'], 'carbohydrate_g' => ['الكربوهيدرات', 'g']] as $code => [$name, $unit]) {
            $this->nutrients[$code] = Nutrient::create(['code' => $code, 'name_ar' => $name, 'name_en' => $code, 'unit' => $unit, 'group' => 'proximates']);
        }
        $this->usda = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $this->raw = FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        $this->batch = ImportBatch::create(['original_filename' => 'أغذية أيلول', 'file_path' => 'x', 'file_sha256' => str_repeat('a', 64), 'status' => 'committed']);

        $this->clean = $this->record('عدس', '172420', ['energy_kcal' => 352, 'protein_g' => 24.6, 'fat_g' => 1.1, 'carbohydrate_g' => 63.4]);
        // No fat: flagged "ناقص: الدهون".
        $this->flagged = $this->record('برغل', '170285', ['energy_kcal' => 342, 'protein_g' => 12.3, 'carbohydrate_g' => 75.9]);
        $this->approved = $this->record('أرز', '169756', ['energy_kcal' => 365, 'protein_g' => 7.1, 'fat_g' => 0.7, 'carbohydrate_g' => 80], FoodSourceStatus::Active);
    }

    private function record(string $food, string $ref, array $values, FoodSourceStatus $status = FoodSourceStatus::PendingReview, ?string $notes = null): FoodSourceRecord
    {
        $record = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => $food])->id, 'food_form_id' => $this->raw->id, 'data_source_id' => $this->usda->id,
            'external_ref' => $ref, 'status' => $status, 'import_batch_id' => $this->batch->id, 'notes' => $notes,
        ]);
        foreach ($values as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $record->id, 'nutrient_id' => $this->nutrients[$code]->id, 'amount_per_100g' => $amount, 'unit' => $this->nutrients[$code]->unit]);
        }

        return $record;
    }

    private function queue(): mixed
    {
        return Livewire::test(FoodSourceRecordsRelationManager::class, ['ownerRecord' => $this->batch, 'pageClass' => EditImportBatch::class]);
    }

    public function test_the_page_shows_progress_and_flags_flagged_records_first(): void
    {
        $this->signInAs();

        $this->queue()
            ->assertSee('3')->assertSee('1 معتمد')->assertSee('1 عليها تنبيه')
            ->assertCanSeeTableRecords([$this->flagged, $this->clean], inOrder: true)
            ->assertCanNotSeeTableRecords([$this->approved])
            ->assertTableColumnStateSet('review_flags', ['ناقص: الدهون'], $this->flagged)
            ->assertTableColumnStateNotSet('review_flags', ['ناقص: الدهون'], $this->clean)
            ->filterTable('show', 'clean')
            ->assertCanSeeTableRecords([$this->clean])
            ->assertCanNotSeeTableRecords([$this->flagged]);
    }

    public function test_approve_all_clean_approves_the_whole_batch_but_not_the_flagged(): void
    {
        $admin = $this->signInAs();
        // More clean records than one page holds.
        $more = collect(range(1, 30))->map(fn (int $i) => $this->record("غذاء {$i}", (string) (900000 + $i), ['energy_kcal' => 100, 'protein_g' => 5, 'fat_g' => 0, 'carbohydrate_g' => 20]));

        $this->queue()
            ->assertActionVisible(TestAction::make('approveClean')->table())
            ->callAction(TestAction::make('approveClean')->table())
            ->assertNotified('تم اعتماد 31 سجلاً');

        $this->assertSame(FoodSourceStatus::Active, $this->clean->fresh()->status);
        $this->assertSame($admin->id, $this->clean->fresh()->reviewed_by);
        $this->assertSame(31, FoodSourceRecord::whereIn('id', [$this->clean->id, ...$more->pluck('id')])->where('status', 'active')->count());
        $this->assertSame(FoodSourceStatus::PendingReview, $this->flagged->fresh()->status);
        $this->assertTrue($this->clean->food->fresh()->is_active);
    }

    public function test_approving_selected_records_leaves_the_flagged_ones(): void
    {
        $this->signInAs();

        $this->queue()
            ->selectTableRecords([$this->clean, $this->flagged])
            ->callAction(TestAction::make('approveSelected')->table()->bulk())
            ->assertNotified('تم اعتماد 1 سجلاً');

        $this->assertSame(FoodSourceStatus::Active, $this->clean->fresh()->status);
        $this->assertSame(FoodSourceStatus::PendingReview, $this->flagged->fresh()->status);
    }

    public function test_a_flagged_record_is_approved_alone_and_only_with_a_reason(): void
    {
        $admin = $this->signInAs();

        $this->queue()
            ->callAction(TestAction::make('approve')->table($this->flagged), ['reason' => ''])
            ->assertHasFormErrors(['reason' => 'required']);
        $this->assertSame(FoodSourceStatus::PendingReview, $this->flagged->fresh()->status);

        $this->queue()
            ->callAction(TestAction::make('approve')->table($this->flagged), ['reason' => 'البرغل شبه خالٍ من الدهون، وسيكمل من سجل آخر'])
            ->assertNotified('تم الاعتماد');

        $record = $this->flagged->fresh();
        $this->assertSame(FoodSourceStatus::Active, $record->status);
        $this->assertStringContainsString('اعتُمد رغم التنبيه ('.$admin->name.') [ناقص: الدهون]: البرغل شبه خالٍ من الدهون', $record->notes);
    }

    public function test_a_clean_record_needs_no_reason(): void
    {
        $this->signInAs();

        $this->queue()->callAction(TestAction::make('approve')->table($this->clean))->assertNotified('تم الاعتماد');

        $this->assertSame(FoodSourceStatus::Active, $this->clean->fresh()->status);
        $this->assertNull($this->clean->fresh()->notes);
    }

    public function test_the_service_refuses_a_flagged_record_without_a_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new BatchReview)->approve($this->flagged, null);
    }

    public function test_a_large_change_from_the_re_import_is_flagged(): void
    {
        $this->signInAs();
        $this->batch->update(['stats' => ['commit' => ['changed_values' => [
            ['external_ref' => '172420', 'nutrient' => 'protein_g', 'old' => 18.0, 'new' => 24.6],
        ]]]]);

        $flags = (new BatchReview)->flagsFor($this->clean->fresh());

        $this->assertSame(['تغيّر كبير'], array_map(fn ($f) => $f->label, $flags));
        $this->assertStringContainsString('البروتين: من 18 إلى 24.6', $flags[0]->detail);
    }

    public function test_a_proxy_is_flagged(): void
    {
        $labneh = $this->record('لبنة', '170903', ['energy_kcal' => 97, 'protein_g' => 9, 'fat_g' => 5, 'carbohydrate_g' => 4], notes: 'مقاربة: زبادي يوناني | FDC: 170903');

        $this->assertSame(['مقاربة'], array_map(fn ($f) => $f->label, (new BatchReview)->flagsFor($labneh)));
    }

    public function test_the_reviewer_reviews_the_queue_without_the_portions_tab(): void
    {
        $this->signInAs(Access::REVIEWER);

        $this->queue()->callAction(TestAction::make('approveClean')->table());

        $this->assertSame(FoodSourceStatus::Active, $this->clean->fresh()->status);
        $this->assertFalse(PortionDecisionsRelationManager::canViewForRecord($this->batch, EditImportBatch::class));
    }

    public function test_the_batch_list_shows_how_far_the_review_is(): void
    {
        $this->signInAs();

        Livewire::test(ListImportBatches::class)
            ->assertSee('1 / 3 معتمد')
            ->assertSee('2 باقٍ')
            ->filterTable('awaiting_review')
            ->assertCanSeeTableRecords([$this->batch]);

        FoodSourceRecord::whereKey([$this->clean->id, $this->flagged->id])->update(['status' => 'active']);

        Livewire::test(ListImportBatches::class)
            ->assertSee('3 / 3 معتمد')
            ->filterTable('awaiting_review')
            ->assertCanNotSeeTableRecords([$this->batch]);
    }

    public function test_a_food_with_several_sizes_waits_for_a_default_portion(): void
    {
        $this->signInAs();
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'name_en' => 'piece', 'dimension' => 'count']);
        $egg = $this->record('بيض', '171287', ['energy_kcal' => 143, 'protein_g' => 12.6, 'fat_g' => 9.5, 'carbohydrate_g' => 0.7]);
        $portion = fn (string $size, float $grams) => FoodPortion::create([
            'food_id' => $egg->food_id, 'measure_unit_id' => $piece->id, 'amount' => 1, 'gram_weight' => $grams,
            'description' => $size, 'food_source_record_id' => $egg->id,
        ]);
        $portion('small', 38);
        $medium = $portion('medium', 44);
        $portion('large', 50);

        $this->assertTrue(PortionDecisionsRelationManager::canViewForRecord($this->batch, EditImportBatch::class));
        $this->assertSame('1', PortionDecisionsRelationManager::getBadge($this->batch, EditImportBatch::class));

        Livewire::test(PortionDecisionsRelationManager::class, ['ownerRecord' => $this->batch, 'pageClass' => EditImportBatch::class])
            ->assertCanSeeTableRecords([$egg])
            ->assertCanNotSeeTableRecords([$this->clean])
            ->callAction(TestAction::make('chooseDefault')->table($egg), ['unit_'.$piece->id => $medium->id])
            ->assertNotified('حُفظت الحصة الافتراضية')
            ->assertCanNotSeeTableRecords([$egg]);

        $this->assertTrue($medium->fresh()->is_default);
        $conversion = app(GramsConverter::class)->resolve($egg->id, $piece->id, 2);
        $this->assertSame(GramsConversion::OK, $conversion->status);
        $this->assertEqualsWithDelta(88, $conversion->grams, 0.001);
    }

    public function test_a_portion_of_another_record_cannot_be_made_the_default(): void
    {
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'name_en' => 'piece', 'dimension' => 'count']);
        foreach ([38, 50] as $grams) {
            FoodPortion::create(['food_id' => $this->clean->food_id, 'measure_unit_id' => $piece->id, 'amount' => 1, 'gram_weight' => $grams, 'food_source_record_id' => $this->clean->id]);
        }
        $foreign = FoodPortion::create(['food_id' => $this->flagged->food_id, 'measure_unit_id' => $piece->id, 'amount' => 1, 'gram_weight' => 60, 'food_source_record_id' => $this->flagged->id]);

        $this->assertFalse((new BatchReview)->makeDefaultPortion($this->clean, $piece->id, $foreign->id));
        $this->assertFalse($foreign->fresh()->is_default);
    }
}
