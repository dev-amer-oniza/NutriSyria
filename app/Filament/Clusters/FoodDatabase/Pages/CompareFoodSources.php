<?php

namespace App\Filament\Clusters\FoodDatabase\Pages;

use App\Support\ArabicSearch;
use App\Auth\Access;
use App\Filament\Clusters\FoodDatabase\FoodDatabaseCluster;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Models\Nutrient;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompareFoodSources extends Page implements HasForms
{
    use InteractsWithForms;

    /** Reads the food database. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->allows(Access::FOODS_VIEW) || $user->allows(Access::FOODS_MANAGE));
    }

    protected string $view = 'filament.clusters.food-database.pages.compare-food-sources';

    protected static ?string $cluster = FoodDatabaseCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Scale;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'مقارنة مصادر غذاء';

    protected static ?string $title = 'مقارنة غذاء بين مصدرين';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('اختر الغذاء والشكل والمصدرين')
                    ->columns(4)
                    ->components([
                        Select::make('food_id')
                            ->label('الغذاء')
                            ->options(fn () => Food::query()->orderBy('name_ar')->pluck('name_ar', 'id'))
                            ->getSearchResultsUsing(fn (string $search) => ArabicSearch::constrain(Food::query(), ['name_ar', 'name_en'], $search)
                                ->orderBy('name_ar')->limit(50)->pluck('name_ar', 'id')->all())
                            ->searchable()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function ($set) {
                                $set('food_form_id', null);
                                $set('record_a', null);
                                $set('record_b', null);
                            }),

                        // مقصورة على شكل واحد فقط: مقارنة نيء بمسلوق غير منطقية (كثافة مختلفة تماماً)
                        Select::make('food_form_id')
                            ->label('الشكل')
                            ->helperText('يجب اختيار نفس الشكل لجعل المقارنة ذات معنى')
                            ->options(fn (Get $get) => $this->formOptions($get('food_id')))
                            ->searchable()
                            ->live()
                            ->native(false)
                            ->disabled(fn (Get $get) => ! $get('food_id'))
                            ->afterStateUpdated(function ($set) {
                                $set('record_a', null);
                                $set('record_b', null);
                            }),

                        Select::make('record_a')
                            ->label('المصدر الأول')
                            ->options(fn (Get $get) => $this->recordOptions($get('food_id'), $get('food_form_id')))
                            ->searchable()
                            ->live()
                            ->native(false)
                            ->disabled(fn (Get $get) => ! $get('food_form_id')),

                        Select::make('record_b')
                            ->label('المصدر الثاني')
                            ->options(fn (Get $get) => $this->recordOptions($get('food_id'), $get('food_form_id')))
                            ->searchable()
                            ->live()
                            ->native(false)
                            ->disabled(fn (Get $get) => ! $get('food_form_id')),
                    ]),
            ]);
    }

    private function formOptions(?int $foodId): array
    {
        if (! $foodId) {
            return [];
        }

        return FoodSourceRecord::query()
            ->where('food_id', $foodId)
            ->with('foodForm')
            ->get()
            ->pluck('foodForm.name_ar', 'food_form_id')
            ->filter()
            ->unique()
            ->all();
    }

    private function recordOptions(?int $foodId, ?int $formId): array
    {
        if (! $foodId || ! $formId) {
            return [];
        }

        return FoodSourceRecord::query()
            ->where('food_id', $foodId)
            ->where('food_form_id', $formId)
            ->with(['dataSource'])
            ->get()
            ->mapWithKeys(fn ($r) => [
                $r->id => sprintf('%s (%s)', $r->dataSource?->name, $r->status->getLabel()),
            ])
            ->all();
    }

    /** @return array<int, array{name:string, unit:string, a:?float, b:?float, diff_pct:?float}> */
    public function getComparisonRows(): array
    {
        $state = $this->form->getState();
        if (empty($state['record_a']) || empty($state['record_b'])) {
            return [];
        }

        $a = FoodSourceRecord::find($state['record_a'])?->nutrientValues->keyBy('nutrient_id');
        $b = FoodSourceRecord::find($state['record_b'])?->nutrientValues->keyBy('nutrient_id');

        if (! $a || ! $b) {
            return [];
        }

        $nutrients = Nutrient::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $rows = [];
        foreach ($nutrients as $n) {
            $va = $a->get($n->id)?->amount_per_100g;
            $vb = $b->get($n->id)?->amount_per_100g;

            if ($va === null && $vb === null) {
                continue;
            }

            $diff = null;
            if ($va !== null && $vb !== null && (float) $va != 0.0) {
                $diff = round((((float) $vb - (float) $va) / (float) $va) * 100, 1);
            }

            $rows[] = [
                'name' => $n->name_ar,
                'unit' => $n->unit,
                'a' => $va !== null ? (float) $va : null,
                'b' => $vb !== null ? (float) $vb : null,
                'diff_pct' => $diff,
            ];
        }

        return $rows;
    }
}
