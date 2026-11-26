<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\AccessControl\Resources\Users\UserResource;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The admin's view of who is joining (the owner's decision a: no bell for every plain sign-up,
 * a number here instead): new accounts this week and requests for a role still waiting.
 */
class AccountsStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'الحسابات';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        // One query for both numbers.
        $counts = User::query()
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as week_new', [$week])
            ->selectRaw('sum(case when requested_role is not null then 1 else 0 end) as waiting')
            ->toBase()
            ->first();

        $new = (int) ($counts->week_new ?? 0);
        $waiting = (int) ($counts->waiting ?? 0);
        $users = UserResource::getUrl();

        return [
            Stat::make('حسابات جديدة هالأسبوع', number_format($new))
                ->description('آخر 7 أيام، كل الأنواع')
                ->icon('heroicon-o-user-group')
                ->color($new ? 'success' : 'gray')
                ->url($users),
            Stat::make('طلبات أدوار تنتظر', number_format($waiting))
                ->description($waiting ? 'صاحب مطعم أو أخصائي: اعتمد أو ارفض' : 'لا طلبات الآن')
                ->icon('heroicon-o-user-plus')
                ->color($waiting ? 'warning' : 'gray')
                ->url($users),
        ];
    }
}
