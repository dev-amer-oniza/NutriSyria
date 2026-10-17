<?php
namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches;

use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\EditImportBatch;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers\FoodSourceRecordsRelationManager;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Schemas\ImportBatchForm;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\Tables\ImportBatchesTable;
use App\Models\ImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static ?string $recordTitleAttribute = 'original_filename';
    protected static ?string $navigationLabel = 'دفعات الاستيراد';
    protected static ?string $modelLabel = 'دفعة استيراد';
    protected static ?string $pluralModelLabel = 'دفعات الاستيراد';

    public static function form(Schema $schema): Schema
    {
        return ImportBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FoodSourceRecordsRelationManager::class,
        ];
    }
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportBatches::route('/'),
            'edit'  => EditImportBatch::route('/{record}/edit'),
        ];
    }
}

