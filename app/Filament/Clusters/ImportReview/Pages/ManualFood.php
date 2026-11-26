<?php

namespace App\Filament\Clusters\ImportReview\Pages;

use App\Enums\Allergen;
use App\Exceptions\ImportFileRejected;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Services\Import\ManualFoodImporter;
use App\Support\ArabicSearch;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Throwable;

/**
 * "من مصدر آخر": a food USDA does not have, typed in by hand per 100 g (the owner's decisions
 * 1a 2a). Calories, protein, fat and carbohydrate are asked for; the label four help the meal
 * checks; the rest is folded away. Saved through ManualFoodImporter (a one-food batch, the
 * same checks, approved at once). A step of "إضافة أغذية", for the admin alone.
 */
class ManualFood extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.clusters.import-review.pages.manual-food';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static ?string $slug = 'manual-food';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'إضافة غذاء من مصدر آخر';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getSubheading(): ?string
    {
        return 'لغذاء غير موجود في USDA. اكتب القيم لكل 100 غ كما هي في مصدرها.';
    }

    public function mount(): void
    {
        $this->getForm('form')->fill(['target' => 'new', 'portions' => []]);
    }

    public function form(Schema $schema): Schema
    {
        $nutrients = Nutrient::query()->orderBy('display_order')->get(['code', 'name_ar', 'unit'])->keyBy('code');
        $field = fn (string $code, bool $required = false) => TextInput::make('values.'.$code)
            ->label(($nutrients[$code]->name_ar ?? $code).' ('.self::unit($nutrients[$code]->unit ?? '').')')
            ->numeric()->inputMode('decimal')->minValue(0)
            ->maxValue(($nutrients[$code]->unit ?? '') === 'g' ? 100 : null)
            ->required($required)
            ->live(onBlur: true);
        $more = $nutrients->keys()->diff([...ManualFoodImporter::REQUIRED, ...ManualFoodImporter::LABEL, 'energy_kj']);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('١. الغذاء')
                    ->columns(2)
                    ->components([
                        Radio::make('target')
                            ->label('هذا الغذاء في قاعدتنا')
                            ->options(['new' => 'غذاء جديد', 'existing' => 'غذاء موجود (شكل أو مصدر إضافي له)'])
                            ->inline()->live()->required()->columnSpanFull(),
                        TextInput::make('food_name_ar')
                            ->label('الاسم بالعربية')
                            ->placeholder('حبة البركة')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('target') === 'new')
                            ->required(fn (Get $get) => $get('target') === 'new'),
                        TextInput::make('food_name_en')
                            ->label('الاسم بالإنكليزية (اختياري)')
                            ->placeholder('Nigella seeds')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('target') === 'new'),
                        Select::make('food_id')
                            ->label('الغذاء')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => ArabicSearch::constrain(Food::query(), ['name_ar', 'name_en'], $search)->orderBy('name_ar')->limit(50)->pluck('name_ar', 'id')->all())
                            ->getOptionLabelUsing(fn ($value) => Food::find($value)?->name_ar)
                            ->options(fn () => Food::query()->orderBy('name_ar')->limit(50)->pluck('name_ar', 'id')->all())
                            ->visible(fn (Get $get) => $get('target') === 'existing')
                            ->required(fn (Get $get) => $get('target') === 'existing'),
                        Select::make('food_form_code')
                            ->label('الشكل')
                            ->options(fn () => FoodForm::query()->where('is_active', true)->orderBy('name_ar')->pluck('name_ar', 'code')->all())
                            ->searchable()->required(),
                        Textarea::make('source')
                            ->label('من وين جبت الأرقام؟')
                            ->placeholder('ملصق المنتج، اسم الجدول، أو رابط الموقع')
                            ->rows(2)->maxLength(300)->required()->columnSpanFull(),
                    ]),
                Section::make('٢. القيم لكل 100 غ')
                    ->components([
                        Grid::make(['default' => 2, 'lg' => 4])->components(array_map(fn ($c) => $field($c, true), ManualFoodImporter::REQUIRED)),
                        Grid::make(['default' => 2, 'lg' => 4])->components(array_map(fn ($c) => $field($c), ManualFoodImporter::LABEL)),
                        Section::make('المزيد (اختياري)')
                            ->collapsible()->collapsed()->compact()
                            ->components([
                                Grid::make(['default' => 2, 'lg' => 4])->components($more->map(fn ($c) => $field($c))->values()->all()),
                            ]),
                        View::make('filament.clusters.import-review.components.manual-flags')
                            ->viewData(fn (Get $get) => ['flags' => app(ManualFoodImporter::class)->flags((array) $get('values'))]),
                        Textarea::make('reason')
                            ->label('سبب الحفظ رغم التنبيه')
                            ->placeholder('مثلاً: هكذا هي على الملصق')
                            ->rows(2)->maxLength(300)
                            ->visible(fn (Get $get) => app(ManualFoodImporter::class)->flags((array) $get('values')) !== [])
                            ->required(fn (Get $get) => app(ManualFoodImporter::class)->flags((array) $get('values')) !== []),
                    ]),
                Section::make('٣. الحصص (اختياري)')
                    ->description('لتُكتب في الوصفة بالملعقة أو الحبة، لا بالغرام فقط.')
                    ->collapsible()
                    ->components([
                        Repeater::make('portions')->hiddenLabel()
                            ->addActionLabel('أضف حصة')
                            ->defaultItems(0)
                            ->columns(3)
                            ->components([
                                Select::make('measure_unit_id')
                                    ->label('الوحدة')
                                    ->options(fn () => MeasureUnit::query()->where('dimension', '!=', 'mass')->orderBy('name_ar')->pluck('name_ar', 'id')->all())
                                    ->searchable()->required(),
                                TextInput::make('amount')->label('الكمية')->numeric()->inputMode('decimal')->minValue(0.01)->default(1)->required(),
                                TextInput::make('gram_weight')->label('= كم غرام')->numeric()->inputMode('decimal')->minValue(0.01)->required(),
                            ]),
                    ]),
                Section::make('٤. مسبّبات الحساسية')
                    ->visible(fn (Get $get) => $get('target') === 'new')
                    ->collapsible()
                    ->components([
                        CheckboxList::make('allergens')
                            ->label('يحتوي على')
                            ->options(collect(Allergen::cases())->mapWithKeys(fn (Allergen $a) => [$a->value => $a->label()])->all())
                            ->columns(4),
                        Toggle::make('allergens_checked')
                            ->label('تحققتُ من مسبّبات الحساسية لهذا الغذاء')
                            ->helperText('فعّله حتى لو لم تحدد شيئاً، ليُعرف أن الغذاء خالٍ منها.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->getForm('form')->getState();
        $new = $state['target'] === 'new';

        try {
            $result = app(ManualFoodImporter::class)->add(
                [
                    'food_id' => $new ? null : (int) $state['food_id'],
                    'food_name_ar' => $new ? $state['food_name_ar'] : null,
                    'food_name_en' => $new ? ($state['food_name_en'] ?? null) : null,
                    'food_form_code' => $state['food_form_code'],
                ],
                (array) ($state['values'] ?? []),
                array_values((array) ($state['portions'] ?? [])),
                (string) $state['source'],
                $state['reason'] ?? null,
                auth()->user(),
                $new && ($state['allergens_checked'] ?? false) ? (array) ($state['allergens'] ?? []) : null,
            );
        } catch (ImportFileRejected $e) {
            Notification::make()->title('لم يُحفظ الغذاء')->body($e->getMessage())->danger()->persistent()->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('لم يُحفظ الغذاء')->body('حدث خطأ غير متوقع. حاول مرة أخرى.')->danger()->send();

            return;
        }

        $record = $result['record'];
        Notification::make()
            ->title('أُضيف للقاعدة ومعتمد')
            ->body($record->food?->name_ar.' صار يُستعمل في الوصفات مباشرة.')
            ->success()
            ->actions([Action::make('open')->label('افتح السجل')->url(FoodSourceRecordResource::getUrl('edit', ['food' => $record->food_id, 'record' => $record->id]))])
            ->send();

        $this->getForm('form')->fill(['target' => 'new', 'portions' => []]);
    }

    private static function unit(string $unit): string
    {
        return ['g' => 'غ', 'mg' => 'ملغ', 'ug' => 'مكغ', 'kcal' => 'سعرة', 'kJ' => 'كيلوجول'][$unit] ?? $unit;
    }
}
