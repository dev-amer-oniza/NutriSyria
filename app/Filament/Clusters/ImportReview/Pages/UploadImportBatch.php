<?php
namespace App\Filament\Clusters\ImportReview\Pages;

use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Services\Import\BundleImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadImportBatch extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.clusters.import-review.pages.upload-import-batch';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'رفع دفعة جديدة';

    protected static ?string $title = 'رفع دفعة استيراد جديدة';

    public ?array $data         = [];
    public ?array $selectorData = [];

    public ?int $batchId = null;

    public ?array $report = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill();
        $this->selectorForm->fill();

        $existing = (int) request()->query('batch');
        if (! $existing) {
            return;
        }

        $batch = DB::table('import_batches')->find($existing);
        if (! $batch) {
            return;
        }

        // دفعة محفوظة سابقاً: نذهب مباشرة لصفحة مراجعتها
        if ($batch->status === 'committed') {
            $this->redirect(ImportBatchResource::getUrl('edit', ['record' => $existing]));

            return;
        }

        $this->batchId = $existing;
        $stats         = json_decode($batch->stats ?? '[]', true) ?: [];
        $this->report  = $stats['validation'] ?? (new BundleImporter())->validate($existing);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ملفات الدفعة')
                    ->description('ناتج أمر build من السكربت: records.csv و nutrient_values.csv، والحصص إن وُجدت')
                    ->components([
                        TextInput::make('batch_label')
                            ->label('اسم الدفعة')
                            ->helperText('مثال: sr_legacy_vegetables أو "دفعة الخضار - أيلول 2026"')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('records_file')
                            ->label('ملف السجلات (records.csv)')
                            ->disk('local')
                            ->directory('import-uploads')
                            ->required(),
                        FileUpload::make('nutrient_values_file')
                            ->label('ملف القيم الغذائية (nutrient_values.csv)')
                            ->helperText(' القيم الغذائية لكل 100 غرام')
                            ->disk('local')
                            ->directory('import-uploads')
                            ->required(),
                        FileUpload::make('portions_file')
                            ->label('ملف الحصص (portions.csv)')
                            ->helperText('اختياري — بعض الدفعات لا تحتوي حصصاً')
                            ->disk('local')
                            ->directory('import-uploads'),
                    ]),
            ]);
    }

    public function validateBundle(): void
    {
        $state = $this->form->getState();

        $tempDir = storage_path('app/import-staging/' . (string) Str::uuid());
        File::ensureDirectoryExists($tempDir);

        $map = [
            'records_file'         => 'records.csv',
            'nutrient_values_file' => 'nutrient_values.csv',
            'portions_file'        => 'portions.csv',
        ];

        foreach ($map as $field => $canonicalName) {
            if (empty($state[$field])) {
                continue;
            }
            File::copy(Storage::disk('local')->path($state[$field]), $tempDir . DIRECTORY_SEPARATOR . $canonicalName);
        }

        $this->report       = null;
        $this->errorMessage = null;

        try {
            $importer      = new BundleImporter();
            $this->batchId = $importer->stage($tempDir, auth()->id());
            DB::table('import_batches')
                ->where('id', $this->batchId)
                ->update(['original_filename' => $state['batch_label']]);
            $this->report = $importer->validate($this->batchId);

            Notification::make()
                ->title('تم الفحص. راجع التقرير أدناه قبل الحفظ')
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->batchId      = null;
            $this->errorMessage = $e->getMessage();

            Notification::make()
                ->title('تعذّر فحص الملفات')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function commitBundle(): void
    {
        if (! $this->batchId) {
            return;
        }

        try {
            $stats = (new BundleImporter())->commit($this->batchId);

            Notification::make()
                ->title('تم الحفظ بنجاح')
                ->body("جديدة: {$stats['records_created']} | محدَّثة: {$stats['records_updated']} | لم تتغيّر: {$stats['records_unchanged']} (موجودة مسبقاً بنفس القيم) | محجوبة: {$stats['records_skipped']}".(($stats['notes_updated'] ?? 0) ? " | أُضيفت ملاحظة لـ{$stats['notes_updated']}" : ''))->success()
                ->send();

            $this->redirect(ImportBatchResource::getUrl('edit', ['record' => $this->batchId]));
        } catch (Throwable $e) {
            Notification::make()
                ->title('تعذّر الحفظ')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getForms(): array
    {
        return [
            'form'         => $this->form(Schema::make($this)),
            'selectorForm' => $this->makeSelectorForm(),
        ];
    }

    public function makeSelectorForm(): Schema
    {
        return Schema::make($this)
            ->statePath('selectorData')
            ->components([
                Select::make('batch_id')
                    ->label('فتح دفعة سابقة')
                    ->helperText('اختر دفعة لعرض تفاصيلها ومراجعتها')
                    ->options(fn() => DB::table('import_batches')
                            ->orderByDesc('id')
                            ->get(['id', 'original_filename'])
                            ->mapWithKeys(fn($b) => [$b->id => "{$b->original_filename} (#{$b->id})"])
                            ->all())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->redirect(static::getUrl() . '?batch=' . $state);
                        }
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template_records')
                ->label('قالب السجلات')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('import.template', 'records'))
                ->openUrlInNewTab(),
            Action::make('template_values')
                ->label('قالب القيم الغذائية')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('import.template', 'nutrient_values'))
                ->openUrlInNewTab(),
            Action::make('template_portions')
                ->label('قالب الحصص')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('import.template', 'portions'))
                ->openUrlInNewTab(),
        ];
    }
}
