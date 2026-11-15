<?php

namespace Tests\Feature;

use App\Filament\Clusters\ImportReview\Pages\SourceMatchingTools;
use App\Filament\Clusters\ImportReview\Pages\UploadImportBatch;
use App\Models\DataSource;
use App\Models\FoodForm;
use App\Models\Nutrient;
use App\Services\Import\MatchingDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 2 of matching without Excel: the tool's draft becomes a table on the page, the picks are
 * made there, and build gets the same reviewed CSV the Excel way would have given it.
 * The Python tool itself is faked: these tests are about the page.
 */
class MatchingTableTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    /** @var list<string> the crosswalk build was given */
    private array $built = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->root = 'storage/framework/testing/fdc-'.uniqid();
        foreach (['FoodData_Central_foundation_food_csv_2026-04-30', 'sr_legacy'] as $release) {
            File::ensureDirectoryExists(base_path("{$this->root}/{$release}"));
        }
        config(['source_adapters' => ['usda_fdc' => ['label' => 'USDA', 'script' => 'usda_to_template.py', 'releases_path' => $this->root]]]);

        DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        foreach (['raw' => 'نيء', 'boiled' => 'مسلوق', 'dried' => 'مجفف', 'as_sold' => 'كما يُباع'] as $code => $name) {
            FoodForm::create(['code' => $code, 'name_ar' => $name]);
        }
        Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy']);

        $fixture = (string) file_get_contents(base_path('tests/Fixtures/matching/draft.csv'));
        Process::fake(function (PendingProcess $process) use ($fixture) {
            $args = $process->command;
            $out = $args[array_search('--out', $args, true) + 1];
            if (in_array('suggest', $args, true)) {
                File::put($out, $fixture);
            } else {
                $this->built[] = File::get($args[array_search('--crosswalk', $args, true) + 1]);
                File::ensureDirectoryExists($out);
                File::put("{$out}/records.csv", "food_name_ar,food_form_code,data_source_code,external_ref\nعدس,raw,usda_fdc_sr_legacy,172420\n");
                File::put("{$out}/nutrient_values.csv", "data_source_code,external_ref,nutrient_code,amount_per_100g,unit\nusda_fdc_sr_legacy,172420,energy_kcal,352,kcal\n");
            }

            return Process::result('draft: 15 rows');
        });
        $this->signInAs();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    private function suggested(): mixed
    {
        return Livewire::test(SourceMatchingTools::class)
            ->set('matchData.wanted_file', UploadedFile::fake()->createWithContent('wanted.csv', "food_name_ar,food_name_en\nعدس,Lentils\n"))
            ->call('generateDraft');
    }

    public function test_the_suggestions_open_as_a_table_with_the_tools_picks(): void
    {
        $page = $this->suggested()
            ->assertNotified('الاقتراحات جاهزة: راجعها في الجدول')
            ->assertSee('الخطوة 2: اختر لكل غذاء')
            ->assertSee('زعتر يابس')
            ->assertSee('Lentils, mature seeds, cooked, boiled, without salt')
            ->assertSee('الاسم لا يطابق تماماً: تأكد')  // the weak Foundation egg, in Arabic
            ->assertSee('8 مختار')
            ->assertSee('مسلوق');

        $this->assertCount(11, array_filter($page->get('picks')));
        Process::assertRan(fn (PendingProcess $p) => in_array('suggest', $p->command, true)
            && count(array_keys($p->command, '--fdc-dir', true)) === 2);
    }

    public function test_the_picks_made_on_the_page_reach_build(): void
    {
        $page = $this->suggested();
        $rows = collect(MatchingDraft::fromCsv((string) file_get_contents(base_path('tests/Fixtures/matching/draft.csv')))->rows());
        $salted = $rows->search(fn ($r) => $r['fdc_id'] === '175254');
        $thyme = $rows->search(fn ($r) => $r['fdc_id'] === '171328');

        $page->call('togglePick', $salted)   // salted instead of unsalted boiled lentils
            ->call('togglePick', $thyme)      // no thyme this time
            ->set('onlyUndecided', true)
            ->assertSee('زعتر يابس')->assertDontSee('Lentils, raw')
            ->set('batchLabel', 'بقول')
            ->call('buildFromPicks')
            ->assertRedirectContains(UploadImportBatch::getUrl().'?batch=');

        $reviewed = MatchingDraft::fromCsv($this->built[0]);
        $picked = array_column(array_intersect_key($reviewed->rows(), $reviewed->initialPicks()), 'fdc_id');
        $this->assertContains('175254', $picked);
        $this->assertNotContains('172421', $picked);
        $this->assertNotContains('171328', $picked);
        $this->assertSame('بقول', DB::table('import_batches')->value('original_filename'));
    }

    public function test_build_waits_for_a_name_and_a_valid_choice(): void
    {
        $page = $this->suggested()->call('buildFromPicks')->assertHasErrors(['batchLabel' => 'required']);

        // Nothing picked: refused before running the tool.
        foreach (array_keys(array_filter($page->get('picks'))) as $i) {
            $page->call('togglePick', $i);
        }
        $page->set('batchLabel', 'بقول')
            ->call('buildFromPicks')
            ->assertNotified('لا يمكن بناء الدفعة بعد')
            ->assertSee('لم تختر أي غذاء بعد.');

        $this->assertSame([], $this->built);
    }

    public function test_the_excel_way_stays_open(): void
    {
        $page = $this->suggested();

        $page->call('downloadDraft')->assertFileDownloaded('draft_crosswalk.csv');

        $page->call('discardDraft')
            ->assertSet('draftId', null)
            ->assertSee('الخطوة 1: اقتراح المطابقة')
            ->assertSee('عندك مسودة راجعتها بالإكسل؟');
    }

    public function test_a_draft_id_from_outside_is_never_read_as_a_path(): void
    {
        Livewire::test(SourceMatchingTools::class)
            ->set('draftId', '../../.env')
            ->assertSee('الخطوة 1: اقتراح المطابقة')
            ->call('togglePick', 0)
            ->assertSet('picks', []);
    }
}
