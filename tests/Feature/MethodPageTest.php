<?php

namespace Tests\Feature;

use App\Enums\FoodSourceStatus;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Services\Import\BundleImporter;
use App\Services\Site\DataCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** "How we verify" (the owner's decisions 1a 2a 3a 4a): live, approved-only coverage. */
class MethodPageTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals(); // one approved SR Legacy food: lentils
    }

    public function test_the_page_tells_the_whole_journey_of_a_number(): void
    {
        $this->get(route('site.method'))->assertOk()
            ->assertSee('كيف نصل إلى رقم تثق به')
            ->assertSeeInOrder(['كل رقم له مصدر', 'من الكمية إلى الغرام', 'الحساب', 'تكميل الناقص بلا تخمين', 'يعتمدها أخصائي تغذية'])
            ->assertSee('طبخة مجدرة، خطوة بخطوة')
            ->assertSee('مثال توضيحي')
            ->assertSee('ما لا نستطيع أن نعدك به')
            ->assertDontSee('10–20%')                          // no accuracy figure (1a)
            ->assertSee('30%')                                 // a meal's share of the day
            ->assertSee('مناسبة جداً')
            ->assertDontSee('اقرأ منهجية التحقق')              // no link to the page itself
            ->assertDontSee('قريباً');
    }

    public function test_sources_count_approved_foods_only(): void
    {
        $foundation = DataSource::create(['code' => 'usda_fdc_foundation', 'name' => 'Foundation', 'type' => 'usda_fdc', 'priority' => 90]);
        DataSource::where('code', 'usda_fdc_sr_legacy')->update(['priority' => 100]);
        $this->ingredient('حمص', $foundation->id);
        $this->ingredient('برغل', $foundation->id);
        $pending = Food::create(['name_ar' => 'فريكة']);
        FoodSourceRecord::create(['food_id' => $pending->id, 'data_source_id' => $foundation->id, 'external_ref' => 'x', 'status' => FoodSourceStatus::PendingReview]);

        $this->assertSame([
            ['code' => 'usda_fdc_foundation', 'name' => 'USDA Foundation Foods', 'text' => config('site.data_sources.usda_fdc_foundation.text'), 'foods' => 2],
            ['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'text' => config('site.data_sources.usda_fdc_sr_legacy.text'), 'foods' => 1],
        ], app(DataCoverage::class)->sources());

        $this->get(route('site.method'))->assertOk()
            ->assertSeeInOrder(['USDA Foundation Foods', 'USDA SR Legacy'])
            ->assertSee('<span class="ns-num">3</span> غذاءً معتمداً', false);
    }

    public function test_approved_proxies_are_listed_by_their_readable_note(): void
    {
        $this->ingredient('لبنة', notes: 'مقاربة: زبادي يوناني كامل الدسم بدل اللبنة | FDC: 170903');
        $cheese = $this->ingredient('جبنة بيضاء', notes: 'مقاربة: جبنة فيتا بدل الجبنة البيضاء السورية | FDC: 173420');
        $cheese->update(['status' => FoodSourceStatus::PendingReview]);

        $this->get(route('site.method'))->assertOk()
            ->assertSee('زبادي يوناني كامل الدسم بدل اللبنة')
            ->assertDontSee('FDC: 170903')
            ->assertDontSee('جبنة فيتا');                       // not approved yet

        $this->assertSame('زبادي يوناني بدل اللبنة', DataCoverage::proxyNote("مقاربة: زبادي يوناني بدل اللبنة | FDC: 1\nرُفض: سبب"));
        $this->assertSame('', DataCoverage::proxyNote('مقاربة'));
    }

    public function test_approving_a_batch_refreshes_the_cached_numbers(): void
    {
        $this->get(route('site.method'))->assertSee('<span class="ns-num">1</span> غذاءً معتمداً', false);

        $batch = DB::table('import_batches')->insertGetId(['original_filename' => 'b.zip', 'file_path' => 'imports/b', 'file_sha256' => str_repeat('a', 64), 'status' => 'committed', 'created_at' => now(), 'updated_at' => now()]);
        $food = Food::create(['name_ar' => 'حمص']);
        FoodSourceRecord::create(['food_id' => $food->id, 'data_source_id' => $this->lentils->data_source_id, 'external_ref' => 'h',
            'status' => FoodSourceStatus::PendingReview, 'import_batch_id' => $batch]);
        $this->get(route('site.method'))->assertSee('<span class="ns-num">1</span> غذاءً معتمداً', false);

        app(BundleImporter::class)->approve($batch);   // DB::table update: no model events

        $this->get(route('site.method'))->assertSee('<span class="ns-num">2</span> غذاءً معتمداً', false);
    }
}
