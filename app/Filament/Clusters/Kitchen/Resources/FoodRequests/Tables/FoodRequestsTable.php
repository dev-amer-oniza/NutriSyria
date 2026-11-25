<?php

namespace App\Filament\Clusters\Kitchen\Resources\FoodRequests\Tables;

use App\Data\Nutrition\MealFacts;
use App\Enums\FoodRequestStatus;
use App\Models\FoodRequest;
use App\Services\Daily\FoodRequests;
use App\Services\Site\MealCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class FoodRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // How many asked for the same dish (one subquery, not a query per row), most asked first.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select('food_requests.*')
                ->selectSub(FoodRequest::query()->from('food_requests as same')
                    ->selectRaw('count(*)')
                    ->whereColumn('same.name_key', 'food_requests.name_key')
                    ->where('same.status', FoodRequestStatus::Pending->value), 'asked_count')
                ->orderByRaw('case when status = ? then 0 else 1 end', [FoodRequestStatus::Pending->value])
                ->orderByDesc('asked_count')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('name')
                    ->label('الأكلة')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (FoodRequest $record) => $record->ingredients)
                    ->wrap(),
                TextColumn::make('asked_count')
                    ->label('طلبها')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state, FoodRequest $record) => $record->isPending() ? ((int) $state === 1 ? 'شخص واحد' : $state.' أشخاص') : null)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('من')
                    ->description(fn (FoodRequest $record) => $record->created_at?->locale('ar')->diffForHumans()),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->description(fn (FoodRequest $record) => $record->product?->name ?? $record->admin_note),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(FoodRequestStatus::class)
                    ->default(FoodRequestStatus::Pending->value),
            ])
            ->recordActions([
                self::addedAction(),
                self::dismissAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->emptyStateHeading('لا أكلات مطلوبة')
            ->emptyStateDescription('حين يكتب أحد أكلة ما لقيها بـ"يومي"، تظهر هنا مع مكوّناتها.');
    }

    private static function addedAction(): Action
    {
        return Action::make('added')
            ->label('أُضيفت')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('answer')
            ->modalHeading(fn (FoodRequest $record) => "ربط «{$record->name}» بوجبة")
            ->modalDescription('اختر الوجبة المعتمدة التي تمثّلها. يصل إشعار لكل من طلبها، ومن المرة القادمة تُحسب من يومه.')
            ->modalSubmitActionLabel('اربط وأرسل الإشعار')
            ->schema([
                Select::make('product_id')
                    ->label('الوجبة')
                    ->helperText('الوجبات الظاهرة بالموقع فقط (قيمها معتمدة).')
                    ->options(fn () => collect(app(MealCatalog::class)->meals())
                        ->mapWithKeys(fn (MealFacts $m) => [$m->id => "{$m->name} · {$m->brand}"])->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(fn (FoodRequest $record, array $data) => self::run(
                fn () => app(FoodRequests::class)->resolve($record, (int) $data['product_id'], auth()->user()),
                'رُبطت الطلبات بالوجبة، ووصل الإشعار لكل من طلبها.',
            ));
    }

    private static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('لن تُضاف')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->authorize('answer')
            ->modalHeading(fn (FoodRequest $record) => "«{$record->name}» لن تُضاف")
            ->modalSubmitActionLabel('أرسل')
            ->schema([
                Textarea::make('note')
                    ->label('السبب (اختياري)')
                    ->helperText('يصل لمن طلبها بالإشعار. مثلاً: "مكوّناتها غير متوفرة بمصادرنا بعد".')
                    ->rows(3)
                    ->maxLength(300),
            ])
            ->action(fn (FoodRequest $record, array $data) => self::run(
                fn () => app(FoodRequests::class)->dismiss($record, auth()->user(), $data['note'] ?? null),
                'أُرسل الرد لكل من طلبها.',
            ));
    }

    private static function run(callable $step, string $done): void
    {
        try {
            $step();
        } catch (InvalidArgumentException) {
            Notification::make()->title('هذه الوجبة غير ظاهرة بالموقع بعد، أو الطلب أُجيب من قبل.')->warning()->send();

            return;
        }

        Notification::make()->title($done)->success()->send();
    }
}
