<?php

namespace Tests\Feature;

use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Support\Placeholders;
use App\Http\Middleware\UseArabicInPanel;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class PanelPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_batch_list_counts_records_and_names_cli_imports(): void
    {
        // Users and permissions live on the fixed "central" connection: point it at the test database.
        config(['database.connections.central' => config('database.connections.sqlite')]);
        DB::purge('central');
        DB::connection('central')->setPdo(DB::connection()->getPdo());
        $this->actingAs(User::forceCreate(['name' => 'admin', 'email' => 'admin@example.test', 'password' => 'x']));
        Filament::setCurrentPanel('admin');

        $batch = ImportBatch::create(['original_filename' => 'usda.zip', 'file_path' => 'x', 'file_sha256' => str_repeat('a', 64), 'status' => 'committed']);
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        foreach (['170000', '170001', '170002'] as $ref) {
            FoodSourceRecord::create(['food_id' => Food::create(['name_ar' => "غذاء {$ref}"])->id, 'data_source_id' => $source->id,
                'external_ref' => $ref, 'status' => 'pending_review', 'import_batch_id' => $batch->id]);
        }

        $list = Livewire::test(ListImportBatches::class)->assertSee('سطر الأوامر');

        // The rows as the table loaded them (with its withCount): the column used to read a
        // misnamed attribute and stayed empty.
        $record = $list->instance()->getTableRecords()->first();
        $column = $list->instance()->getTable()->getColumn('food_source_records_count');
        $this->assertNotNull($column, 'the count column must be named after the withCount attribute');
        $this->assertSame(3, (int) $column->record($record)->getState());
    }

    public function test_validation_messages_are_arabic_in_the_panel(): void
    {
        (new UseArabicInPanel())->handle(new Request(), fn () => response(''));

        $message = Validator::make(['amount' => ''], ['amount' => 'required'], [], ['amount' => 'الكمية'])->errors()->first('amount');

        $this->assertSame('حقل الكمية مطلوب.', $message);
    }

    public function test_placeholders_are_inline_svg(): void
    {
        foreach (['food', 'brand', 'product'] as $kind) {
            $uri = Placeholders::image($kind);
            $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
            $this->assertStringContainsString('<svg', base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))));
        }
    }
}
