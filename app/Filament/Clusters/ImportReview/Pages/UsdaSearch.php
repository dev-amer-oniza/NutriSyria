<?php

namespace App\Filament\Clusters\ImportReview\Pages;

use App\Exceptions\FdcUnavailable;
use App\Exceptions\ImportFileRejected;
use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Services\Usda\FdcClient;
use App\Services\Usda\FdcImporter;
use App\Support\ArabicSearch;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Search USDA FoodData Central live and add one food to the database (the owner's decisions
 * 1a 2a 3a 4a): Foundation and SR Legacy only, the admin alone, and an added food arrives as
 * a one-food import batch, pending review like any other. One already in the database is
 * pointed out instead of added again.
 */
class UsdaSearch extends Page
{
    protected string $view = 'filament.clusters.import-review.pages.usda-search';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::MagnifyingGlass;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'بحث في USDA';

    protected static ?string $title = 'بحث في USDA وإضافة غذاء';

    /** In the address too (?q=lentils), so a search can be bookmarked or sent. */
    #[Url(as: 'q')]
    public string $query = '';

    public int $page = 1;

    /** FdcClient::search(), or null before the first search. */
    public ?array $results = null;

    public ?string $errorMessage = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function mount(): void
    {
        if (trim($this->query) !== '') {
            $this->load();
        }
    }

    public function configured(): bool
    {
        return app(FdcClient::class)->configured();
    }

    public function search(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function goTo(int $page): void
    {
        $this->page = max(1, min($page, $this->results['pages'] ?? 1));
        $this->load();
    }

    private function load(): void
    {
        $this->query = trim($this->query);
        $this->errorMessage = null;

        if (mb_strlen($this->query) < 2) {
            $this->results = null;
            $this->errorMessage = 'اكتب اسم الغذاء بالإنكليزية (حرفان على الأقل)، مثل: chickpeas cooked.';

            return;
        }

        try {
            $this->results = app(FdcClient::class)->search($this->query, $this->page);
        } catch (FdcUnavailable $e) {
            $this->results = null;
            $this->errorMessage = $e->getMessage();
        }
    }

    /** The found foods the database already holds, by fdc id (for the "موجود" mark). */
    public function existing(): array
    {
        return app(FdcImporter::class)->existing(array_column($this->results['foods'] ?? [], 'fdcId'));
    }

    /** "أضف للقاعدة": says so when it is already there, otherwise asks which food it is. */
    public function add(int $fdcId): void
    {
        if ($record = app(FdcImporter::class)->existing([$fdcId])[$fdcId] ?? null) {
            Notification::make()->title('هذا الغذاء موجود من قبل')->body(FdcImporter::existsMessage($record))->warning()->persistent()->send();

            return;
        }

        $this->mountAction('addFood', ['fdcId' => $fdcId]);
    }

    public function detailsAction(): Action
    {
        return Action::make('details')
            ->modalHeading(fn (array $arguments) => $this->found($arguments['fdcId'])['description'] ?? 'تفاصيل الغذاء')
            ->modalContent(fn (array $arguments): View => $this->detailsView((int) $arguments['fdcId']))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalWidth('3xl');
    }

    public function addFoodAction(): Action
    {
        return Action::make('addFood')
            ->modalHeading('إضافة إلى قاعدة الأغذية')
            ->modalDescription(fn (array $arguments) => ($this->found($arguments['fdcId'])['description'] ?? '').' · '.($this->found($arguments['fdcId'])['dataType'] ?? ''))
            ->modalSubmitActionLabel('أضف بانتظار المراجعة')
            ->fillForm(fn (array $arguments) => ['target' => 'new', 'food_name_en' => $this->found($arguments['fdcId'])['description'] ?? null])
            ->schema([
                Radio::make('target')
                    ->label('هذا الغذاء في قاعدتنا')
                    ->options(['new' => 'غذاء جديد', 'existing' => 'غذاء موجود (مصدر أو شكل إضافي له)'])
                    ->inline()->live()->required(),
                TextInput::make('food_name_ar')
                    ->label('الاسم بالعربية')
                    ->helperText('إن كان الاسم موجوداً عندنا يُضاف السجل إلى ذلك الغذاء.')
                    ->maxLength(255)
                    ->visible(fn (Get $get) => $get('target') === 'new')
                    ->required(fn (Get $get) => $get('target') === 'new'),
                TextInput::make('food_name_en')
                    ->label('الاسم بالإنكليزية')
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
                    ->helperText('نيء، مسلوق، معلّب… كما هو في وصف USDA.')
                    ->options(fn () => FoodForm::query()->where('is_active', true)->orderBy('name_ar')->pluck('name_ar', 'code')->all())
                    ->searchable()->required(),
                Textarea::make('note')
                    ->label('ملاحظة (اختيارية)')
                    ->helperText('إن كان مقاربة لا الغذاء نفسه، ابدأها بكلمة "مقاربة:".')
                    ->rows(2)->maxLength(300),
            ])
            ->action(function (array $data, array $arguments): void {
                try {
                    $result = app(FdcImporter::class)->add((int) $arguments['fdcId'], [
                        'food_id' => $data['target'] === 'existing' ? (int) $data['food_id'] : null,
                        'food_name_ar' => $data['target'] === 'new' ? trim($data['food_name_ar']) : null,
                        'food_name_en' => $data['target'] === 'new' ? ($data['food_name_en'] ?? null) : null,
                        'food_form_code' => $data['food_form_code'],
                        'note' => $data['note'] ?? null,
                    ], auth()->id());
                } catch (ImportFileRejected|FdcUnavailable $e) {
                    Notification::make()->title('لم يُضف الغذاء')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('لم يُضف الغذاء')->body('حدث خطأ غير متوقع. حاول مرة أخرى.')->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('أُضيف بانتظار المراجعة')
                    ->body('راجعه واعتمده من صفحة الدفعة حتى يُستعمل في الوصفات.')
                    ->success()
                    ->actions([Action::make('open')->label('افتح الدفعة')->url(ImportBatchResource::getUrl('edit', ['record' => $result['batch']]))])
                    ->send();
            });
    }

    /** A food of the current results, by its fdc id. */
    private function found(int|string $fdcId): ?array
    {
        return collect($this->results['foods'] ?? [])->firstWhere('fdcId', (int) $fdcId);
    }

    private function detailsView(int $fdcId): View
    {
        try {
            return view('filament.clusters.import-review.components.usda-food', app(FdcImporter::class)->preview($fdcId) + ['error' => null]);
        } catch (FdcUnavailable $e) {
            return view('filament.clusters.import-review.components.usda-food', ['error' => $e->getMessage()]);
        }
    }
}
