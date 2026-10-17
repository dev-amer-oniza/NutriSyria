<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Services\Dashboard\DatabaseOverview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class FoodDatabaseStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'قاعدة بيانات الأغذية';

    protected function getStats(): array
    {
        $overview = new DatabaseOverview();
        $foods = $overview->foods();
        $sources = $overview->sources();
        $batches = $overview->batches();
        $reviewUrl = ImportBatchResource::getUrl('index');

        $stats = [
            Stat::make('أغذية معتمدة', number_format($foods['approved_foods']))
                ->description(number_format($foods['approved_records']).' سجل معتمد، جاهزة للوصفات')
                ->icon('heroicon-o-check-badge')
                ->color('success'),
            Stat::make('أغذية للمراجعة', number_format($foods['review_foods']))
                ->description($foods['review_records']
                    ? number_format($foods['review_records']).' سجل ينتظر الاعتماد'
                    : 'لا شيء ينتظر المراجعة')
                ->icon('heroicon-o-clock')
                ->color($foods['review_foods'] ? 'warning' : 'gray')
                ->url($foods['review_foods'] ? $reviewUrl : null),
            Stat::make('مصادر البيانات', count($sources))
                ->description(implode('، ', array_column($sources, 'name')) ?: 'لم يُستورد شيء بعد')
                ->icon('heroicon-o-book-open')
                ->color('info'),
            Stat::make('دفعات مستوردة', number_format($batches['committed']))
                ->description(self::batchNote($batches))
                ->icon('heroicon-o-arrow-down-tray')
                ->color($batches['unfinished'] || $batches['failed'] ? 'warning' : 'primary')
                ->url($reviewUrl),
        ];

        if ($foods['rejected_records']) {
            $stats[] = Stat::make('سجلات مرفوضة', number_format($foods['rejected_records']))
                ->description('لا تُستعمل في الوصفات')
                ->icon('heroicon-o-x-circle')
                ->color('danger');
        }

        return $stats;
    }

    private static function batchNote(array $batches): string
    {
        $parts = [];
        if ($batches['last_committed_at']) {
            // LTR isolate: inside Arabic text the date would otherwise read 17-10-2026.
            $parts[] = "آخر دفعة: \u{2066}".Carbon::parse($batches['last_committed_at'])->format('Y-m-d')."\u{2069}";
        }
        if ($batches['unfinished']) {
            $parts[] = "{$batches['unfinished']} لم تكتمل";
        }
        if ($batches['failed']) {
            $parts[] = "{$batches['failed']} فشلت";
        }

        return $parts ? implode(' · ', $parts) : 'لا توجد دفعات بعد';
    }
}
