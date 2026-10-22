<?php

namespace App\Filament\Clusters\Consultations\Resources\ConsultationRequests;

use App\Filament\Clusters\Consultations\ConsultationsCluster;
use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\Pages\ListConsultationRequests;
use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\Tables\ConsultationRequestsTable;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ConsultationService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** "Consultation requests": the ones sent to the signed-in nutritionist, to accept or decline. */
class ConsultationRequestResource extends Resource
{
    protected static ?string $model = ConsultationRequest::class;

    protected static ?string $cluster = ConsultationsCluster::class;

    protected static ?string $slug = 'requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::InboxArrowDown;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'طلبات الاستشارة';

    protected static ?string $modelLabel = 'طلب استشارة';

    protected static ?string $pluralModelLabel = 'طلبات الاستشارة';

    /** Only the requests sent to me, whatever the role (the policy also checks each one). */
    public static function getEloquentQuery(): Builder
    {
        app(ConsultationService::class)->expireStale();

        return parent::getEloquentQuery()
            ->where('nutritionist_id', auth()->id())
            ->with('user.profile');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $pending = $user ? app(ConsultationService::class)->pendingCountFor($user) : 0;

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'طلبات تنتظر ردّك';
    }

    public static function table(Table $table): Table
    {
        return ConsultationRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsultationRequests::route('/'),
        ];
    }
}
