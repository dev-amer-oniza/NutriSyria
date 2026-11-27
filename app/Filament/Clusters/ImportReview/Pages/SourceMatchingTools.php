<?php

namespace App\Filament\Clusters\ImportReview\Pages;

use App\Auth\Access;
use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Models\FoodForm;
use App\Services\Import\BundleImporter;
use App\Services\Import\MatchingDraft;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class SourceMatchingTools extends Page implements HasForms
{
    use InteractsWithForms;

    /** Matching runs tools on the server and builds import files: upload rights only. */
    /** «رجوع إلى إضافة أغذية» (App\Filament\Support\BackLink). */
    public function backLink(): array
    {
        return ['url' => AddFoods::getUrl(), 'label' => 'رجوع إلى إضافة أغذية'];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->allows(Access::IMPORTS_UPLOAD);
    }

    protected string $view = 'filament.clusters.import-review.pages.source-matching-tools';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::ArrowsRightLeft;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'أدوات مطابقة المصادر';

    protected static ?string $title = 'مطابقة قائمة أغذية';

    /** A step of "إضافة أغذية", reached from its card. */
    protected static bool $shouldRegisterNavigation = false;

    private const TOOL_TIMEOUT_SECONDS = 600;

    public ?array $sourceData = [];

    public ?array $matchData = [];

    public ?array $buildData = [];

    public ?string $errorMessage = null;

    /** The draft being reviewed on the page (storage/app/suggestions/<id>.csv), null before step 1. */
    public ?string $draftId = null;

    /** The source and releases the draft was made from: build uses the same. */
    public ?array $draftSource = null;

    /** @var array<int, bool> chosen draft rows */
    public array $picks = [];

    public string $batchLabel = '';

    /** Show only the foods that still need a decision. */
    public bool $onlyUndecided = false;

    private ?MatchingDraft $draft = null;

    public function mount(): void
    {
        $adapter = array_key_first(config('source_adapters')) ?: null;
        $this->source()->fill([
            'adapter' => $adapter,
            'release' => $this->recommendedReleases($adapter),
        ]);
        $this->matchForm->fill();
        $this->buildForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'sourceForm' => $this->makeSourceForm(),
            'matchForm' => $this->makeMatchForm(),
            'buildForm' => $this->makeBuildForm(),
        ];
    }

    private function adapterOptions(): array
    {
        return collect(config('source_adapters'))
            ->mapWithKeys(fn ($cfg, $key) => [$key => $cfg['label']])
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
            ->mapWithKeys(fn ($path) => [basename($path) => basename($path)])
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
                array_push($args, '--fdc-dir', base_path($adapter['releases_path'].'/'.$release));
            }
        }

        return $args;
    }

    /**
     * The releases matched by default: the newest Foundation and SR Legacy, together (the
     * project's rule: FNDDS is for ready dishes only, never for ingredients).
     *
     * @return list<string>
     */
    private function recommendedReleases(?string $adapterKey): array
    {
        $releases = array_keys($this->releaseOptions($adapterKey));
        $foundation = array_values(array_filter($releases, fn (string $r) => str_contains(strtolower($r), 'foundation')));
        rsort($foundation);
        $legacy = array_values(array_filter($releases, fn (string $r) => str_contains(strtolower($r), 'sr_legacy')));

        return array_values(array_filter([$foundation[0] ?? null, $legacy[0] ?? null]));
    }

    private function source(): Schema
    {
        return $this->getForm('sourceForm') ?? throw new LogicException('The source form is missing.');
    }

    public function makeSourceForm(): Schema
    {
        return Schema::make($this)
            ->statePath('sourceData')
            ->components([
                Select::make('adapter')
                    ->label('المصدر')
                    ->options($this->adapterOptions())
                    ->required()
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(fn ($state, $set) => $set('release', $this->recommendedReleases($state))),
                Select::make('release')
                    ->label('النسخ')
                    ->multiple()
                    ->options(fn (Get $get) => $this->releaseOptions($get('adapter')))
                    ->required()
                    ->native(false)
                    ->helperText(fn (Get $get) => $get('adapter') && empty($this->releaseOptions($get('adapter')))
                        ? 'لا توجد نسخة مفكوكة الضغط لهذا المصدر في '.config("source_adapters.{$get('adapter')}.releases_path").' بعد.'
                        : 'المنصوح: Foundation وSR Legacy معاً (مختاران سلفاً). كل نسخة تُرتَّب وحدها.'),
            ]);
    }

    public function makeMatchForm(): Schema
    {
        return Schema::make($this)
            ->statePath('matchData')
            ->components([
                FileUpload::make('wanted_file')
                    ->label('ملف الأغذية المطلوبة')
                    ->helperText('CSV بأعمدة: food_name_ar، food_name_en، forms، search_terms، exclude، note (الأولان إلزاميان). ضع الأغذية الجديدة فقط.')
                    ->disk('local')
                    ->directory('import-uploads')
                    ->required(),
            ]);
    }

    public function makeBuildForm(): Schema
    {
        return Schema::make($this)
            ->statePath('buildData')
            ->components([
                TextInput::make('batch_label')
                    ->label('اسم الدفعة')
                    ->helperText('مثال: "أغذية USDA - تشرين الثاني 2026"')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('crosswalk_file')
                    ->label('المسودة المراجَعة')
                    ->helperText('نفس الملف الذي نزّلته من الخطوة 1، بعد وضع yes على الاختيار الصحيح. احفظه من الإكسل بصيغة CSV UTF-8.')
                    ->disk('local')
                    ->directory('import-uploads')
                    ->required(),
            ]);
    }

    public function generateDraft()
    {
        $this->allowLongRun();
        $source = $this->source()->getState();
        $state = $this->matchForm->getState();
        $this->errorMessage = null;

        $wantedPath = Storage::disk('local')->path($state['wanted_file']);
        $outPath = storage_path('app/suggestions/'.Str::uuid().'.csv');
        File::ensureDirectoryExists(dirname($outPath));

        $adapter = config("source_adapters.{$source['adapter']}");

        $result = Process::timeout(self::TOOL_TIMEOUT_SECONDS)->run([
            config('services.python.bin'),
            base_path('tools/'.$adapter['script']),
            'suggest',
            ...$this->releaseArgs($source['adapter'], $source['release']),
            '--wanted', $wantedPath,
            '--out', $outPath,
        ]);

        if (! $result->successful()) {
            $this->errorMessage = $result->errorOutput() ?: $result->output();
            Notification::make()->title('فشل توليد المسودة')->body($this->errorMessage)->danger()->send();

            return null;
        }

        $this->draftId = pathinfo($outPath, PATHINFO_FILENAME);
        $this->draftSource = $source;
        $this->draft = null;
        $this->picks = $this->draft()?->initialPicks() ?? [];
        $summary = $this->draft()?->summary($this->picks);

        Notification::make()
            ->title('الاقتراحات جاهزة: راجعها في الجدول')
            ->body($summary ? "مختار سلفاً: {$summary['chosen']} من {$summary['pairs']} · تحتاج قرارك: {$summary['undecided']} · بلا مرشح: {$summary['no_candidate']}" : null)
            ->success()
            ->send();

        return null;
    }

    /** The draft on the page, or null when there is none (or its file is gone). */
    public function draft(): ?MatchingDraft
    {
        if ($this->draftId === null || ! Str::isUuid($this->draftId)) {
            return null;
        }
        $path = storage_path("app/suggestions/{$this->draftId}.csv");

        return $this->draft ??= File::exists($path) ? MatchingDraft::fromCsv(File::get($path)) : null;
    }

    public function togglePick(int $row): void
    {
        if ($draft = $this->draft()) {
            $this->picks = $draft->toggle($this->picks, $row);
        }
    }

    /** @return list<string> */
    public function getProblems(): array
    {
        return $this->draft()?->problems($this->picks) ?? [];
    }

    /** @return array<string, string> form code => Arabic name */
    public function getFormNames(): array
    {
        return FoodForm::query()->pluck('name_ar', 'code')->all();
    }

    /** The draft with the page's picks, as a CSV: the Excel way stays open. */
    public function downloadDraft()
    {
        $draft = $this->draft();
        if ($draft === null) {
            return null;
        }

        return response()->streamDownload(fn () => print ($draft->toCsv($this->picks)), 'draft_crosswalk.csv');
    }

    public function discardDraft(): void
    {
        if ($this->draftId !== null && Str::isUuid($this->draftId)) {
            File::delete(storage_path("app/suggestions/{$this->draftId}.csv"));
        }
        $this->reset('draftId', 'draftSource', 'picks', 'batchLabel', 'onlyUndecided', 'errorMessage');
        $this->draft = null;
    }

    /** Step 2 without Excel: the picks made on the page go straight to build. */
    public function buildFromPicks(): void
    {
        $this->validate(['batchLabel' => ['required', 'string', 'max:255']], attributes: ['batchLabel' => 'اسم الدفعة']);
        $draft = $this->draft();
        if ($draft === null || $this->draftSource === null) {
            Notification::make()->title('المسودة غير موجودة: اقترح المطابقة من جديد')->danger()->send();

            return;
        }
        if ($problems = $draft->problems($this->picks)) {
            Notification::make()->title('لا يمكن بناء الدفعة بعد')->body(implode(' · ', $problems))->danger()->persistent()->send();

            return;
        }

        $reviewed = storage_path("app/suggestions/{$this->draftId}-reviewed.csv");
        File::put($reviewed, $draft->toCsv($this->picks));

        try {
            $this->runBuild($reviewed, trim($this->batchLabel), $this->draftSource);
        } finally {
            File::delete($reviewed);
        }
    }

    /** Step 2 the Excel way: a draft reviewed outside and uploaded. */
    public function buildBundle(): void
    {
        $source = $this->source()->getState();
        $state = $this->buildForm->getState();

        $this->runBuild(Storage::disk('local')->path($state['crosswalk_file']), $state['batch_label'], $source);
    }

    /** @param  array<string, mixed>  $source  the source form state (or the one a draft was made from) */
    private function runBuild(string $crosswalkPath, string $batchLabel, array $source): void
    {
        $this->allowLongRun();
        $this->errorMessage = null;
        if (! is_array(config('source_adapters.'.($source['adapter'] ?? '')))) {
            Notification::make()->title('المصدر غير معروف: اختره من جديد')->danger()->send();

            return;
        }

        $outDir = storage_path('app/bundles/'.Str::uuid());
        File::ensureDirectoryExists($outDir);

        $adapter = config("source_adapters.{$source['adapter']}");

        $result = Process::timeout(self::TOOL_TIMEOUT_SECONDS)->run([
            config('services.python.bin'),
            base_path('tools/'.$adapter['script']),
            'build',
            ...$this->releaseArgs($source['adapter'], $source['release']),
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
            $importer = new BundleImporter;
            $batchId = $importer->stage($outDir, auth()->id());
            DB::table('import_batches')
                ->where('id', $batchId)
                ->update(['original_filename' => $batchLabel]);
            $importer->validate($batchId);

            Notification::make()->title('بُنيت الدفعة وفُحصت: راجع النتيجة ثم احفظ')->success()->send();
            $this->redirect(UploadImportBatch::getUrl().'?batch='.$batchId.'&from=matching');
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
            Notification::make()->title('تعذّر فحص الحزمة الناتجة')->body($e->getMessage())->danger()->send();
        } finally {
            File::deleteDirectory($outDir);
        }
    }
}
