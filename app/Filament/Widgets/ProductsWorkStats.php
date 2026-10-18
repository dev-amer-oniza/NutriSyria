<?php

namespace App\Filament\Widgets;

use App\Auth\Access;
use App\Auth\BrandScope;
use App\Enums\FoodSourceStatus;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Models\FoodSourceRecord;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What waits for the signed-in user: a brand owner sees where their products stand, the
 * nutritionist what waits for their review. Counts only what the user may see (BrandScope).
 */
class ProductsWorkStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Product::class);
    }

    protected function getHeading(): ?string
    {
        $user = auth()->user();

        return $user?->can('reviewAny', FoodSourceRecord::class) || $user?->allows(Access::NUTRITION_APPROVE)
            ? 'بانتظار مراجعتك'
            : 'منتجاتي';
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $statuses = BrandScope::products(NutritionApprovalStatus::withApprovalData(Product::query()))
            ->get()
            ->map(fn (Product $product) => NutritionApprovalStatus::of($product));
        $count = fn (NutritionApprovalStatus ...$wanted) => $statuses->filter(fn ($s) => in_array($s, $wanted, true))->count();
        $productsUrl = ProductResource::getUrl('index');

        $stats = [];
        if ($user->can('reviewAny', FoodSourceRecord::class)) {
            $pending = FoodSourceRecord::query()->where('status', FoodSourceStatus::PendingReview)->count();
            $stats[] = Stat::make('سجلات أغذية تنتظر المراجعة', number_format($pending))
                ->description($pending ? 'من دفعات الاستيراد' : 'لا شيء ينتظر')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($pending ? 'warning' : 'gray')
                ->url(ImportBatchResource::getUrl('index'));
        }

        $waiting = $count(NutritionApprovalStatus::Awaiting, NutritionApprovalStatus::Changed);

        // Products with an open review note: the brand owner's move.
        if ($requested = $count(NutritionApprovalStatus::ChangesRequested)) {
            $stats[] = Stat::make($user->can('create', Product::class) ? 'مطلوب منك تعديل' : 'تنتظر تعديل صاحب البراند', number_format($requested))
                ->description('ملاحظات أخصائي التغذية مفتوحة')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('danger')
                ->url($productsUrl);
        }

        return [
            ...$stats,
            Stat::make('المنتجات', number_format($statuses->count()))
                ->description($count(NutritionApprovalStatus::NoRecipe).' بلا وصفة بعد')
                ->icon('heroicon-o-shopping-bag')
                ->url($productsUrl),
            Stat::make('تنتظر اعتماد القيم الغذائية', number_format($waiting))
                ->description($count(NutritionApprovalStatus::Changed).' تغيّرت وصفتها بعد الاعتماد')
                ->icon('heroicon-o-clock')
                ->color($waiting ? 'warning' : 'gray')
                ->url($productsUrl),
            Stat::make('بقيم رسمية معتمدة', number_format($count(NutritionApprovalStatus::Approved)))
                ->description('جاهزة للعرض على الزبائن')
                ->icon('heroicon-o-check-badge')
                ->color('success'),
        ];
    }
}
