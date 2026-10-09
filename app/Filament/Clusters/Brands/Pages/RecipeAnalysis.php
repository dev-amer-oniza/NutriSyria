<?php
namespace App\Filament\Clusters\Brands\Pages;

use App\Filament\Clusters\Brands\BrandsCluster;
use App\Models\Product;
use App\Services\Nutrition\RecipeAnalysisService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class RecipeAnalysis extends Page
{
    protected string $view = 'filament.clusters.brands.pages.recipe-analysis';

    protected static ?string $cluster = BrandsCluster::class;

    protected static bool $shouldRegisterNavigation = false; // تُفتح فقط من زر على صفحة المنتج

    protected static ?string $title = 'تحليل الوصفة';

    public ?Product $product = null;

    public ?array $result = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $productId     = (int) request()->query('product');
        $this->product = Product::with('brand')->find($productId);

        if (! $this->product) {
            $this->errorMessage = 'لم يُحدَّد منتج صالح.';

            return;
        }

        $this->runAnalysis();
    }

    public function runAnalysis(): void
    {
        $this->errorMessage = null;
        $this->result       = null;

        try {
            $this->result = app(RecipeAnalysisService::class)->analyze($this->product)->toArray();
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }
    public function approveAnalysis(): void
    {
        try {
            $freshResult = app(RecipeAnalysisService::class)->analyze($this->product);
            app(RecipeAnalysisService::class)->approve($this->product, $freshResult);

            Notification::make()
                ->title('تم اعتماد التحليل')
                ->body('أصبحت هذه القيم هي التحليل الرسمي للمنتج.')
                ->success()
                ->send();

            $this->redirect(\App\Filament\Clusters\Brands\Resources\Products\ProductResource::getUrl('edit', [
                'record' => $this->product->id,
            ]));
        } catch (Throwable $e) {
            Notification::make()
                ->title('تعذّر الاعتماد')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
