<?php

namespace App\Filament\Clusters\Consultations\Resources\Clients;

use App\Filament\Clusters\Consultations\ConsultationsCluster;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ClientPlan;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "My clients": the people linked to the signed-in nutritionist (accepted requests). A link
 * that ended, or someone else's, is "not found" (ClientWorkspace, ConsultationRequestPolicy).
 */
class ClientResource extends Resource
{
    protected static ?string $model = ConsultationRequest::class;

    protected static ?string $cluster = ConsultationsCluster::class;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'مراجعيني';

    protected static ?string $modelLabel = 'مراجع';

    protected static ?string $pluralModelLabel = 'مراجعيني';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        /** @var User $me */
        $me = auth()->user();

        return app(ClientWorkspace::class)->clients($me)->with(['user.profile']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('responded_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('المراجع')
                    ->weight('bold')
                    ->description(fn (ConsultationRequest $record) => $record->clientSummary()),
                TextColumn::make('template')
                    ->label('القالب')
                    ->state(fn (ConsultationRequest $record) => app(PersonalCriteria::class)->for($record->user)?->template['label'])
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label('سبب الاستشارة')
                    ->limit(60)
                    ->tooltip(fn (ConsultationRequest $record) => $record->reason),
                TextColumn::make('responded_at')
                    ->label('مرتبط منذ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label('واتساب')
                    ->url(fn (ConsultationRequest $record) => ConsultationRequest::whatsappUrl($record->user->phone), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (ConsultationRequest $record) => ViewClient::getUrl(['record' => $record]))
            ->emptyStateHeading('لا مراجعين بعد')
            ->emptyStateDescription('يظهر هنا كل من تقبل طلبه من "طلبات الاستشارة".');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'view' => ViewClient::route('/{record}'),
            'plan' => ClientPlan::route('/{record}/plan'),
        ];
    }
}
