<?php

namespace App\Filament\Clusters\ImportReview\Pages;

use App\Auth\Access;
use App\Exceptions\ImportFileRejected;
use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Services\Import\BundleImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadImportBatch extends Page implements HasForms
{
    use InteractsWithForms;

    /** Uploading and saving batches: upload rights only. */
    /** «رجوع إلى إضافة أغذية» (App\Filament\Support\BackLink). */
    public function backLink(): array
    {
        return ['url' => AddFoods::getUrl(), 'label' => 'رجوع إلى إضافة أغذية'];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->allows(Access::IMPORTS_UPLOAD);
    }

    protected string $view = 'filament.clusters.import-review.pages.upload-import-batch';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::ArrowUpTray;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'رفع دفعة جديدة';

    protected static ?string $title = 'رفع ملفات جاهزة';

    /** A step of "إضافة أغذية", reached from its card (or from matching, with ?batch=). */
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    /** Came from the matching page: the steps show "المطابقة" as done. */
    public bool $fromMatching = false;

    public ?int $batchId = null;

    public ?array $report = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill();
        $this->fromMatching = request()->query('from') === 'matching';

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
        $stats = json_decode($batch->stats ?? '[]', true) ?: [];
        $this->report = $stats['validation'] ?? (new BundleImporter)->validate($existing);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('ملفات الدفعة')
                    ->description('ملف السجلات وملف القيم (وملف الحصص إن وُجد). الأعمدة المطلوبة في القوالب أعلى الصفحة.')
                    ->components([
                        TextInput::make('batch_label')
                            ->label('اسم الدفعة')
                            ->helperText('مثال: sr_legacy_vegetables أو "دفعة الخضار - أيلول 2026"')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('records_file')
                            ->label('ملف السجلات (records.csv)')
                            ->helperText('ملف CSV غير فارغ، مفصول بفاصلة أو فاصلة منقوطة، حتى 10 ميغا. أسماء الأعمدة كما في قالب السجلات أعلاه.')
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

        $tempDir = storage_path('app/import-staging/'.(string) Str::uuid());
        File::ensureDirectoryExists($tempDir);

        $map = [
            'records_file' => 'records.csv',
            'nutrient_values_file' => 'nutrient_values.csv',
            'portions_file' => 'portions.csv',
        ];

        foreach ($map as $field => $canonicalName) {
            if (empty($state[$field])) {
                continue;
            }
            File::copy(Storage::disk('local')->path($state[$field]), $tempDir.DIRECTORY_SEPARATOR.$canonicalName);
        }

        $this->report = null;
        $this->errorMessage = null;

        $importer = new BundleImporter;

        try {
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
            // A batch staged but not checked would hang in the list: remove it.
            if ($this->batchId) {
                $importer->discard($this->batchId);
            }
            $this->batchId = null;
            // Our own messages are written for the reader; anything else is a bug, logged.
            $this->errorMessage = $e instanceof ImportFileRejected ? $e->getMessage() : 'حدث خطأ غير متوقع أثناء فحص الملفات. أرسل الملفات للدعم.';
            if (! $e instanceof ImportFileRejected) {
                report($e);
            }

            Notification::make()
                ->title('تعذّر فحص الملفات')
                ->body($this->errorMessage)
                ->danger()
                ->persistent()
                ->send();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /** Drops the checked but unsaved batch, so it does not linger in the batch list. */
    public function startOver(): void
    {
        if ($this->batchId) {
            (new BundleImporter)->discard($this->batchId); // a saved batch is left alone
        }

        $this->redirect(static::getUrl());
    }

    public function commitBundle(): void
    {
        if (! $this->batchId) {
            return;
        }

        try {
            $stats = (new BundleImporter)->commit($this->batchId);

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
