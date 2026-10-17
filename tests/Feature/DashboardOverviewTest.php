<?php

namespace Tests\Feature;

use App\Filament\Widgets\DataSourcesOverview;
use App\Filament\Widgets\FoodDatabaseStats;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Dashboard\DatabaseOverview;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $foundation = DataSource::create(['code' => 'usda_fdc_foundation', 'name' => 'USDA Foundation', 'type' => 'usda_fdc', 'priority' => 90]);
        $sr = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc', 'priority' => 100]);
        DataSource::create(['code' => 'lab', 'name' => 'مخبر', 'type' => 'lab', 'priority' => 10]);   // nothing imported

        $batch = ImportBatch::create(['original_filename' => 'a.zip', 'file_path' => 'x', 'file_sha256' => str_repeat('a', 64),
            'status' => 'committed', 'committed_at' => '2026-10-17 10:00:00']);
        ImportBatch::create(['original_filename' => 'b.csv', 'file_path' => 'y', 'file_sha256' => str_repeat('b', 64), 'status' => 'uploaded']);
        ImportBatch::create(['original_filename' => 'c.csv', 'file_path' => 'z', 'file_sha256' => str_repeat('c', 64), 'status' => 'failed']);

        $record = fn (Food $food, DataSource $source, string $status) => FoodSourceRecord::create([
            'food_id' => $food->id, 'data_source_id' => $source->id, 'external_ref' => (string) random_int(1, 1e9),
            'status' => $status, 'import_batch_id' => $batch->id, 'imported_at' => '2026-10-17 10:00:00',
        ]);
        $labneh = Food::create(['name_ar' => 'لبنة']);
        $record($labneh, $foundation, 'active');
        $record($labneh, $sr, 'active');                 // one food, two approved records
        $lentils = Food::create(['name_ar' => 'عدس']);
        $record($lentils, $sr, 'active');
        $record($lentils, $sr, 'pending_review');        // approved AND waiting for review
        $record(Food::create(['name_ar' => 'زعتر']), $sr, 'pending_review');
        $record(Food::create(['name_ar' => 'محلب']), $sr, 'rejected');
    }

    public function test_foods_are_counted_once_and_records_per_status(): void
    {
        $this->assertSame([
            'approved_foods' => 2, 'review_foods' => 2, 'records' => 6,
            'approved_records' => 3, 'review_records' => 2, 'rejected_records' => 1,
        ], (new DatabaseOverview())->foods());
    }

    public function test_sources_with_records_only_best_priority_first(): void
    {
        $sources = (new DatabaseOverview())->sources();

        $this->assertSame(['USDA Foundation', 'USDA SR Legacy'], array_column($sources, 'name'));
        $this->assertSame([1, 0, 0], [$sources[0]['approved'], $sources[0]['review'], $sources[0]['rejected']]);
        $this->assertSame([2, 2, 1], [$sources[1]['approved'], $sources[1]['review'], $sources[1]['rejected']]);
    }

    public function test_batches(): void
    {
        $batches = (new DatabaseOverview())->batches();

        $this->assertSame([1, 1, 1], [$batches['committed'], $batches['unfinished'], $batches['failed']]);
        $this->assertStringStartsWith('2026-10-17', $batches['last_committed_at']);
    }

    public function test_the_widgets_render(): void
    {
        // Users and permissions live on the fixed "central" connection: point it at the test database.
        config(['database.connections.central' => config('database.connections.sqlite')]);
        DB::purge('central');
        DB::connection('central')->setPdo(DB::connection()->getPdo());
        $this->actingAs(User::forceCreate(['name' => 'admin', 'email' => 'admin@example.test', 'password' => 'x']));
        Filament::setCurrentPanel('admin');

        Livewire::test(FoodDatabaseStats::class)
            ->assertSee('أغذية معتمدة')
            ->assertSee('3 سجل معتمد')
            ->assertSee('USDA Foundation، USDA SR Legacy')
            ->assertSee('آخر دفعة: 2026-10-17')
            ->assertSee('1 لم تكتمل')
            ->assertSee('سجلات مرفوضة');

        Livewire::test(DataSourcesOverview::class)
            ->assertSee('USDA SR Legacy')
            ->assertDontSee('مخبر');
    }
}
