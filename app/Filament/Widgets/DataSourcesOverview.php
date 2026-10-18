<?php

namespace App\Filament\Widgets;

use App\Auth\Access;
use App\Services\Dashboard\DatabaseOverview;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class DataSourcesOverview extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    /** Database-wide numbers: for those who build the food database; the other roles get their own widgets. */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->allows(Access::FOODS_VIEW) || $user->allows(Access::FOODS_MANAGE) || $user->allows(Access::IMPORTS_UPLOAD));
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('السجلات حسب المصدر')
            ->description('المصدر الأعلى أولوية أولاً: أرقامه تُستعمل في الوصفات قبل غيره.')
            ->records(fn () => collect((new DatabaseOverview())->sources())->keyBy('id')->all())
            ->columns([
                TextColumn::make('name')->label('المصدر')->weight('bold'),
                TextColumn::make('approved')->label('معتمد')->badge()->color('success'),
                TextColumn::make('review')->label('للمراجعة')->badge()
                    ->color(fn (int $state) => $state ? 'warning' : 'gray'),
                TextColumn::make('rejected')->label('مرفوض')->badge()
                    ->color(fn (int $state) => $state ? 'danger' : 'gray'),
                TextColumn::make('last_import')->label('آخر استيراد')
                    ->formatStateUsing(fn (?string $state) => $state ? Carbon::parse($state)->format('Y-m-d') : null)
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('لم يُستورد أي مصدر بعد')
            ->paginated(false);
    }
}
