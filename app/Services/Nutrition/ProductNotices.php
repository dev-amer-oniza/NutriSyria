<?php

namespace App\Services\Nutrition;

use App\Auth\Access;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The panel bell around a restaurant's product (the owner's choices 1 and 2):
 *  - its recipe waits for approval: the nutritionists hear, once per review (product_recipes
 *    .review_requested_at, written without touching the recipe's own dates, which tell whether
 *    it changed after approval);
 *  - its values are approved: the restaurant's managers hear, and the review starts over.
 * The platform's own kitchen is the admin's and approved by them: nobody is told.
 */
class ProductNotices
{
    /** Called when a recipe or one of its lines is saved or deleted. */
    public function recipeChanged(int $productId): void
    {
        /** @var Product|null $product */
        $product = NutritionApprovalStatus::withApprovalData(Product::query()->with('brand'))->find($productId);
        if (! $product || $product->brand?->is_platform || ! $product->recipe || $product->recipe->review_requested_at) {
            return;
        }
        if (! NutritionApprovalStatus::of($product)->needsReview()) {
            return;
        }

        $nutritionists = $this->nutritionists();
        if ($nutritionists->isNotEmpty()) {
            Notification::make()
                ->title("«{$product->name}» من {$product->brand->name} ينتظر اعتمادك")
                ->body('أضاف صاحب المطعم الوصفة أو عدّلها. راجع التحليل واعتمد القيم.')
                ->icon('heroicon-o-clipboard-document-check')
                ->warning()
                ->actions([Action::make('open')->label('تحليل الوصفة')->url(RecipeAnalysis::getUrl().'?product='.$product->id)->markAsRead()])
                ->sendToDatabase($nutritionists);
        }

        DB::table('product_recipes')->where('id', $product->recipe->id)->update(['review_requested_at' => now()]);
    }

    /** The values became official: the restaurant hears, and a later change asks for review again. */
    public function approved(Product $product): void
    {
        DB::table('product_recipes')->where('product_id', $product->id)->update(['review_requested_at' => null]);

        $product->loadMissing('brand');
        /** @var Brand|null $brand */
        $brand = $product->brand;
        if (! $brand || $brand->is_platform) {
            return;
        }

        $managers = $brand->managers()->where('is_active', true)->get();
        if ($managers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title("اعتُمدت قيم «{$product->name}»")
            ->body('صارت قيمه الغذائية رسمية، وظهر بصفحة مطعمك بالموقع.')
            ->icon('heroicon-o-check-badge')
            ->success()
            ->actions([
                Action::make('open')->label('المنتج')->url(EditProduct::getUrl(['record' => $product]))->markAsRead(),
                Action::make('site')->label('بالموقع')->url(route('site.meals.show', $product->id), shouldOpenInNewTab: true),
            ])
            ->sendToDatabase($managers);
    }

    /** @return Collection<int, User> active accounts allowed to approve official values */
    private function nutritionists(): Collection
    {
        return User::permission(Access::NUTRITION_APPROVE)->where('is_active', true)->get();
    }
}
