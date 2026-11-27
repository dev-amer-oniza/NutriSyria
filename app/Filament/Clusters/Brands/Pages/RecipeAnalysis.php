<?php

namespace App\Filament\Clusters\Brands\Pages;

use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use App\Models\Preparation;
use App\Models\Product;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Nutrition\RecipePreview;
use App\Support\Amount;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Throwable;

class RecipeAnalysis extends Page
{
    use HiddenFromAdmin;

    protected string $view = 'filament.clusters.brands.pages.recipe-analysis';

    protected static ?string $cluster = BrandsCluster::class;

    protected static bool $shouldRegisterNavigation = false; // تُفتح فقط من زر على صفحة المنتج

    protected static ?string $title = 'تحليل الوصفة';

    /** Set once in mount() after the access check; the browser cannot swap it for another product. */
    #[Locked]
    public ?Product $product = null;

    public ?array $result = null;

    public ?string $errorMessage = null;

    /** Brand owners, the nutritionist and admins; which product is checked in mount(). */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Product::class);
    }

    public function mount(): void
    {
        $productId = (int) request()->query('product');
        $this->product = Product::with('brand')->find($productId);

        // Another brand's product answers exactly like a missing one: no hint that it exists.
        if (! $this->product || ! Gate::allows('view', $this->product)) {
            abort(404);
        }

        $this->runAnalysis();
    }

    public function hasOpenNotes(): bool
    {
        return $this->product !== null && $this->product->reviewNotes()->open()->exists();
    }

    /** A note was sent or closed in the notes panel below: show or hide the approve button. */
    #[On('review-notes-changed')]
    public function refreshNotes(): void {}

    public function canApprove(): bool
    {
        return $this->product !== null && Gate::allows('approveNutrition', $this->product);
    }

    /** The product's page to go back to: the nutritionist may approve but not edit, so the one they may open. */
    protected function productUrl(): string
    {
        return ProductResource::pageUrlFor($this->product);
    }

    /** «رجوع للمنتج»: every page with a way back (the owners' report). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('رجوع للمنتج')
                ->icon('heroicon-o-arrow-right')
                ->color('gray')
                ->url(fn () => $this->productUrl()),
        ];
    }

    /**
     * The brand's own preparations in this recipe (كريمة تمر): what is in each, and per 100 g
     * of it, so the reviewer sees what the line holds, not an id.
     *
     * @return list<array{name: string, note: ?string, ingredients: list<string>, weight: ?float, per100g: array<string, ?float>, unconverted: list<string>}>
     */
    public function preparations(): array
    {
        if (! $this->product) {
            return [];
        }
        $ids = $this->product->recipeIngredients()->whereNotNull('preparation_id')->distinct()->pluck('preparation_id');
        if ($ids->isEmpty()) {
            return [];
        }

        $preview = app(RecipePreview::class);

        return Preparation::query()->whereKey($ids)->orderBy('name')
            ->with(['ingredients.ingredientRecord.food', 'ingredients.measureUnit'])
            ->get()
            ->map(function (Preparation $prep) use ($preview) {
                $values = $preview->per100g([
                    'ingredients' => $prep->ingredients->map->only(['food_source_record_id', 'measure_unit_id', 'amount', 'food_portion_id'])->all(),
                    'weight_after_g' => $prep->weight_after_g,
                ]);

                return [
                    'name' => $prep->name,
                    'note' => $prep->note,
                    'ingredients' => $prep->ingredients->map(fn ($line) => trim(($line->ingredientRecord?->food?->name_ar ?? '—').' '
                        .Amount::short($line->amount).' '.($line->measureUnit?->name_ar ?? '')))->all(),
                    'weight' => $values['grams'],
                    'per100g' => $values['per100g'],
                    'unconverted' => $values['unconverted'],
                ];
            })->all();
    }

    public function runAnalysis(): void
    {
        $this->errorMessage = null;
        $this->result = null;

        try {
            $this->result = app(RecipeAnalysisService::class)->analyze($this->product)->toArray();
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function approveAnalysis(): void
    {
        // Checked again here, not only by hiding the button.
        abort_unless($this->canApprove(), 403);

        try {
            $freshResult = app(RecipeAnalysisService::class)->analyze($this->product);
            app(RecipeAnalysisService::class)->approve($this->product, $freshResult, auth()->user());

            Notification::make()
                ->title('تم اعتماد التحليل')
                ->body('أصبحت هذه القيم هي التحليل الرسمي للمنتج.')
                ->success()
                ->send();

            $this->redirect($this->productUrl());
        } catch (Throwable $e) {
            Notification::make()
                ->title('تعذّر الاعتماد')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
