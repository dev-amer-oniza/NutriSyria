<?php
namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Schemas;

use App\Enums\ImportBatchStatus;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('معلومات الدفعة')
                    ->columns(2)
                    ->components([
                        TextEntry::make('original_filename')
                            ->label('اسم الملف')
                            ->state(fn($record) => $record->original_filename),
                        TextEntry::make('user.name')
                            ->label('استوردها')
                            ->state(fn($record) => $record->user?->name ?? '—'),
                        TextEntry::make('file_sha256')
                            ->label('بصمة الملف')
                            ->state(fn($record) => substr($record->file_sha256, 0, 16) . '…'),
                        TextEntry::make('committed_at')
                            ->label('تاريخ الحفظ')
                            ->state(fn($record) => $record->committed_at?->format('Y-m-d H:i') ?? '—'),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(ImportBatchStatus::class)
                            ->disabled()
                            ->dehydrated(false),
                        Section::make('نتيجة الحفظ')
                            ->columns(4)
                            ->visible(fn($record) => ! empty($record?->stats['commit'] ?? null))
                            ->components([
                                TextEntry::make('created')
                                    ->label('سجلات جديدة')
                                    ->state(fn($record) => $record->stats['commit']['records_created'] ?? 0)
                                    ->badge()->color('success'),
                                TextEntry::make('updated')
                                    ->label('سجلات محدَّثة')
                                    ->state(fn($record) => $record->stats['commit']['records_updated'] ?? 0)
                                    ->badge()->color('warning'),
                                TextEntry::make('unchanged')
                                    ->label('لم تتغيّر (بقيت تابعة لدفعتها الأصلية)')
                                    ->state(fn($record) => $record->stats['commit']['records_unchanged'] ?? 0)
                                    ->badge()->color('gray'),
                                TextEntry::make('skipped')
                                    ->label('محجوبة (بها خطأ)')
                                    ->state(fn($record) => $record->stats['commit']['records_skipped'] ?? 0)
                                    ->badge()->color('danger'),
                            ]),
                    ]),
            ]);
    }
}
