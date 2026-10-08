<?php
namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortionsRelationManager extends RelationManager
{
    protected static ?string $modelLabel = 'حصة';

    protected static ?string $pluralModelLabel = 'الحصص';
    protected static string $relationship      = 'portions';

    protected static ?string $title = 'الحصص';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('measure_unit_id')
                    ->label('وحدة القياس')
                    ->relationship('measureUnit', 'name_ar')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('amount')
                    ->label('الكمية')
                    ->numeric()
                    ->default(1)
                    ->minValue(0)
                    ->required(),
                TextInput::make('gram_weight')
                    ->label('الوزن بالغرام')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->suffix('غرام'),
                TextInput::make('description')
                    ->label('الوصف')
                    ->helperText('مثال: كوب مفروم')
                    ->maxLength(255),
                Toggle::make('is_default')
                    ->label('الحصة الافتراضية لهذا الغذاء'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn($record) => sprintf(
                '%s %s = %s غ%s',
                $record->amount,
                $record->measureUnit?->name_ar,
                $record->gram_weight,
                $record->description ? " ({$record->description})" : '',
            ))
            ->columns([
                TextColumn::make('measureUnit.name_ar')
                    ->label('الوحدة')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('الكمية'),
                TextColumn::make('gram_weight')
                    ->label('الوزن (غرام)')
                    ->suffix(' غ'),
                TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('is_default')
                    ->label('افتراضية')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['food_id'] = $this->getOwnerRecord()->food_id;

                        return $data;
                    }),
                AssociateAction::make()
                    ->recordSelectOptionsQuery(fn($query) => $query->where('food_id', $this->getOwnerRecord()->food_id))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
