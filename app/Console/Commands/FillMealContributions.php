<?php

namespace App\Console\Commands;

use App\Enums\NutritionApprovalStatus;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\PublicCatalogCache;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Fills "من أين؟" (step 4, decision 1a) for the meals approved before it existed: what each
 * ingredient brings, from the recipe as it stands. Only meals still "approved" (the recipe
 * unchanged since), so the numbers explain the official values. The approval time and the
 * recipe's own timestamps are left as they are, or the meals would read "changed after approval".
 */
class FillMealContributions extends Command
{
    protected $signature = 'meals:contributions {--all : Recompute the meals that already have them too}';

    protected $description = 'Fill what each ingredient brings to the approved meals ("من أين؟")';

    public function handle(RecipeAnalysisService $analysis, PublicCatalogCache $cache): int
    {
        $filled = 0;
        $failed = 0;

        NutritionApprovalStatus::withApprovalData(Product::query())
            ->with('recipeIngredients')
            ->lazyById(50)
            ->each(function (Model $product) use ($analysis, &$filled, &$failed) {
                $recipe = $product instanceof Product ? $product->recipe : null;
                if (! $product instanceof Product || ! $recipe instanceof ProductRecipe
                    || NutritionApprovalStatus::of($product) !== NutritionApprovalStatus::Approved
                    || ($recipe->contributions !== null && ! $this->option('all'))) {
                    return;
                }
                try {
                    $recipe->timestamps = false;
                    $recipe->forceFill(['contributions' => $analysis->analyze($product)->contributions()])->saveQuietly();
                    $filled++;
                } catch (Throwable $e) {
                    $this->warn("#{$product->id} {$product->name}: {$e->getMessage()}");
                    $failed++;
                }
            });

        // saveQuietly: no model events, so the site's cache is renewed here once.
        $cache->flush();
        $this->info("Filled {$filled} meals".($failed ? ", {$failed} failed." : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
