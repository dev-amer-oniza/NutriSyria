<?php
namespace App\Filament\Clusters\ImportReview\Pages;

use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Services\Import\BundleImporter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SourceMatchingTools extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.clusters.import-review.pages.source-matching-tools';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'أدوات مطابقة المصادر';

    protected static ?string $title = 'أدوات مطابقة المصادر الكبيرة (USDA وغيره)';

    private const TOOL_TIMEOUT_SECONDS = 600;

    public ?array $matchData = [];

    public ?array $buildData = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->matchForm->fill();
        $this->buildForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'matchForm' => $this->makeMatchForm(),
            'buildForm' => $this->makeBuildForm(),
        ];
    }
    private function adapterOptions(): array
    {
        return collect(config('source_adapters'))
            ->mapWithKeys(fn($cfg, $key) => [$key => $cfg['label']])
            ->all();
    }

    private function releaseOptions(?string $adapterKey): array
    {
        if (! $adapterKey || ! ($cfg = config("source_adapters.{$adapterKey}"))) {
            return [];
        }

        $dir = base_path($cfg['releases_path']);
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::directories($dir))
            ->mapWithKeys(fn($path) => [basename($path) => basename($path)])
            ->all();
    }

    /**
     * Matching several large releases (Foundation + SR Legacy) takes longer than PHP's default
     * 60 s request limit, which killed the request with "Maximum execution time exceeded" while
     * the Python tool was still running. Give this request the same 10 minutes the tool gets.
     */
    private function allowLongRun(): void
    {
        @set_time_limit(self::TOOL_TIMEOUT_SECONDS + 60);
    }

    /** "--fdc-dir <folder>" once per chosen release, limited to folders that really exist. */
    private function releaseArgs(string $adapterKey, array|string $releases): array
    {
        $adapter = config("source_adapters.{$adapterKey}");
        $known = array_keys($this->releaseOptions($adapterKey));
        $args = [];
        foreach ((array) $releases as $release) {
            if (in_array($release, $known, true)) {
                array_push($args, '--fdc-dir', base_path($adapter['releases_path'] . '/' . $release));
            }
        }

        return $args;
    }

    public function makeMatchForm(): Schema
    {
        return Schema::make($this)
            ->statePath('matchData')
            ->components([
                Section::make('الخطوة 1: توليد مسودة المطابقة')
                    ->description('ارفع قائمة الأغذية المطلوبة (نفس صيغة wanted_foods.csv)، واختر نسخة USDA')
                    ->components([
                        Select::make('adapter')
                            ->label('المصدر')
                            ->options($this->adapterOptions())
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(fn($set) => $set('release', null)),
                        Select::make('release')
                            ->label('النسخ/الإصدارات')
                            ->multiple()
                            ->options(fn(Get $get) => $this->releaseOptions($get('adapter')))
                            ->required()
                            ->native(false)
                            ->helperText(fn(Get $get) => empty($this->releaseOptions($get('adapter')))
                                    ? 'لا توجد أي نسخة مفكوكة الضغط لهذا المصدر بعد'
                                    : 'يمكن اختيار أكثر من نسخة (مثلاً Foundation وSR Legacy): يُقترح الأفضل من كل منها، والوصفات تأخذ من الأعلى أولوية وتكمل الناقص من الأخرى'),
                        FileUpload::make('wanted_file')
                            ->label('ملف الأغذية المطلوبة')
                            ->disk('local')
                            ->directory('import-uploads')
                            ->required(),
                    ]),
            ]);
    }

    public function makeBuildForm(): Schema
    {
        return Schema::make($this)
            ->statePath('buildData')
            ->components([
                Section::make('الخطوة 2: بناء الحزمة من المسودة المراجَعة')
                    ->description('بعد أن تراجع المسودة بالإكسل وتضع yes على الاختيار الصحيح، ارفعها هنا')
                    ->components([
                        TextInput::make('batch_label')
                            ->label('اسم الدفعة')
                            ->helperText('مثال: "أغذية USDA - أيلول 2026"')
                            ->required()
                            ->maxLength(255),
                      
                        Select::make('adapter')
                            ->label('المصدر')
                            ->options($this->adapterOptions())
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(fn($set) => $set('release', null)),
                        Select::make('release')
                            ->label('النسخ/الإصدارات')
                            ->multiple()
                            ->options(fn(Get $get) => $this->releaseOptions($get('adapter')))
                            ->required()
                            ->native(false)
                            ->helperText(fn(Get $get) => empty($this->releaseOptions($get('adapter')))
                                    ? 'لا توجد أي نسخة مفكوكة الضغط لهذا المصدر بعد'
                                    : 'يمكن اختيار أكثر من نسخة (مثلاً Foundation وSR Legacy): يُقترح الأفضل من كل منها، والوصفات تأخذ من الأعلى أولوية وتكمل الناقص من الأخرى'),
                        FileUpload::make('crosswalk_file')
                            ->label('ملف المطابقة المراجَع')
                            ->disk('local')
                            ->directory('import-uploads')
                            ->required(),
                    ]),
            ]);
    }

    public function generateDraft()
    {
        $this->allowLongRun();
        $state              = $this->matchForm->getState();
        $this->errorMessage = null;

        $wantedPath = Storage::disk('local')->path($state['wanted_file']);
        $outPath    = storage_path('app/suggestions/' . Str::uuid() . '.csv');
        File::ensureDirectoryExists(dirname($outPath));

        $adapter = config("source_adapters.{$state['adapter']}");

        $result = Process::timeout(self::TOOL_TIMEOUT_SECONDS)->run([
            env('PYTHON_BIN', 'python'),
            base_path('tools/' . $adapter['script']),
            'suggest',
            ...$this->releaseArgs($state['adapter'], $state['release']),
            '--wanted', $wantedPath,
            '--out', $outPath,
        ]);

        if (! $result->successful()) {
            $this->errorMessage = $result->errorOutput() ?: $result->output();
            Notification::make()->title('فشل توليد المسودة')->body($this->errorMessage)->danger()->send();

            return null;
        }

        Notification::make()->title('تم توليد المسودة')->body(trim($result->output()))->success()->send();

        return response()->streamDownload(
            fn() => print(File::get($outPath)),
            'draft_crosswalk.csv',
        );
    }

    public function buildBundle()
    {
        $this->allowLongRun();
        $state              = $this->buildForm->getState();
        $this->errorMessage = null;

        $crosswalkPath = Storage::disk('local')->path($state['crosswalk_file']);
        $outDir        = storage_path('app/bundles/' . Str::uuid());
        File::ensureDirectoryExists($outDir);

        $adapter = config("source_adapters.{$state['adapter']}");

        $result = Process::timeout(self::TOOL_TIMEOUT_SECONDS)->run([
            env('PYTHON_BIN', 'python'),
            base_path('tools/' . $adapter['script']),
            'build',
            ...$this->releaseArgs($state['adapter'], $state['release']),
            '--nutrients', database_path('seeders/data/nutrients.csv'),
            '--source-codes', database_path('seeders/data/nutrient_source_codes.csv'),
            '--crosswalk', $crosswalkPath,
            '--out', $outDir,
        ]);

        if (! $result->successful()) {
            $this->errorMessage = $result->errorOutput() ?: $result->output();
            Notification::make()->title('فشل بناء الحزمة')->body($this->errorMessage)->danger()->send();

            return;
        }

        try {
            $importer = new BundleImporter();
            $batchId  = $importer->stage($outDir, auth()->id());
            DB::table('import_batches')
                ->where('id', $batchId)
                ->update(['original_filename' => $state['batch_label']]);
            $importer->validate($batchId);

            Notification::make()->title('تم البناء والفحص، جارٍ الانتقال للمراجعة')->success()->send();
            $this->redirect(UploadImportBatch::getUrl() . '?batch=' . $batchId);
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            Notification::make()->title('تعذّر فحص الحزمة الناتجة')->body($e->getMessage())->danger()->send();
        } finally {
            File::deleteDirectory($outDir);
        }
    }
}
