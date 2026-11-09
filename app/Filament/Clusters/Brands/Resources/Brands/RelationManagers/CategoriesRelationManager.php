<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * The sections of this brand's menu (the owner's decisions 1a 3a): added, renamed, removed
 * and dragged into order here. Removing one leaves its products without a section.
 */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'التصنيفات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'التصنيفات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            self::nameField(fn () => $this->getOwnerRecord()->getKey()),
        ]);
    }

    /** The name field, unique within one brand; also used to add a section from a product form. */
    public static function nameField(\Closure $brandId): TextInput
    {
        return TextInput::make('name')
            ->label('اسم التصنيف')
            ->placeholder('مثلاً: مقبلات، أطباق رئيسية، حلويات')
            ->required()
            ->maxLength(80)
            ->unique('product_categories', 'name', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('brand_id', $brandId()))
            ->validationMessages(['unique' => 'عندك تصنيف بهذا الاسم.']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description('رتّبها بالسحب كما تريدها في منيو المطعم على الموقع.')
            ->columns([
                TextColumn::make('name')->label('التصنيف'),
                TextColumn::make('products_count')->label('المنتجات')->counts('products'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data) => $data + [
                        'sort_order' => (int) $this->getOwnerRecord()->categories()->max('sort_order') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('منتجات هذا التصنيف تبقى، وتصير بلا تصنيف.'),
            ])
            ->emptyStateHeading('لا تصنيفات بعد')
            ->emptyStateDescription('أضف أقسام منيو مطعمك، مثل مقبلات وأطباق رئيسية وحلويات.');
    }
}
