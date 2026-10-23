<?php

namespace App\Filament\Clusters\Consultations\Resources\Clients\Pages;

use App\Data\Nutrition\NutritionPlanResult;
use App\Enums\ActivityLevel;
use App\Enums\EnergyEquation;
use App\Filament\Clusters\Consultations\Resources\Clients\ClientResource;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Plan\NutritionPlans;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use App\Services\Nutrition\Plan\NutritionPlanCalculator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

/**
 * The nutrition plan for one client (the nutritionist's plan, phases 1 and 2): equation and
 * activity, the calorie change (presets or the slider), the macro shares, the fluids, the
 * meals with their times, shares and grams (with what is left to share), and a live summary. Saving sets the client's calories and macros
 * (ClientWorkspace::savePlan).
 *
 * @property ConsultationRequest $record
 */
class ClientPlan extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    /** "+300" / "−500" on the slider, kept left-to-right inside the Arabic page. */
    private const SIGNED_JS = "'\\u2066' + (\$value > 0 ? '+' : (\$value < 0 ? '−' : '')) + Math.abs(Math.round(\$value)) + '\\u2069'";

    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.clusters.consultations.pages.client-plan';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canView($this->record), 403);

        if ($this->canPlan()) {
            $plan = $this->record->plan;
            $activity = $this->record->user->profile->activity_level->value;
            $this->form->fill($plan
                ? $plan->inputs($activity)
                : app(NutritionPlans::class)->defaults($this->record, app(PersonalCriteria::class)->for($this->record->user)->template['macros']));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'خطة التغذية: '.$this->record->user->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->clientSummary();
    }

    /** The profile has what the equations need (body, activity, adult). */
    public function canPlan(): bool
    {
        return (bool) $this->record->user->profile?->canCalculate();
    }

    public function form(Schema $schema): Schema
    {
        $plan = config('nutrition_criteria.plan');
        [$macroMin, $macroMax] = $plan['macro_range'];

        return $schema
            ->statePath('data')
            ->components([
                Section::make('1. الأساس')
                    ->description('معادلة الأيض الأساسي ومعامل النشاط.')
                    ->columns(2)
                    ->components([
                        Radio::make('equation')->label('المعادلة')->required()->live()
                            ->options(EnergyEquation::options())
                            ->descriptions(collect(EnergyEquation::cases())->mapWithKeys(fn (EnergyEquation $e) => [$e->value => $e->description()])->all()),
                        TextInput::make('body_fat_pct')->label('نسبة الدهون في الجسم')->suffix('%')->numeric()->live(onBlur: true)
                            ->minValue($plan['body_fat_range'][0])->maxValue($plan['body_fat_range'][1])
                            ->visible(fn (Get $get) => EnergyEquation::tryFrom((string) $get('equation'))?->needsBodyFat() ?? false)
                            ->required(fn (Get $get) => EnergyEquation::tryFrom((string) $get('equation'))?->needsBodyFat() ?? false),
                        Select::make('activity')->label('معامل النشاط')->required()->live()->selectablePlaceholder(false)
                            ->options(collect(ActivityLevel::cases())->mapWithKeys(fn (ActivityLevel $a) => [$a->value => $a->label().' (×'.$a->factor().')'])->all()),
                    ]),

                Section::make('2. السعرات')
                    ->description('اختر تعديلاً جاهزاً، أو حرّك الخط بحرية.')
                    ->components([
                        ToggleButtons::make('preset')->label('تعديل جاهز')->inline()->live()->dehydrated(false)
                            ->options(collect($plan['adjustments'])->mapWithKeys(fn (string $label, int $kcal) => [(string) $kcal => $label.' '.self::signed($kcal)])->all())
                            ->formatStateUsing(fn (Get $get) => array_key_exists((int) $get('adjustment'), $plan['adjustments']) ? (string) (int) $get('adjustment') : null)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $state === null ? null : $set('adjustment', (int) $state)),
                        Slider::make('adjustment')->label('التعديل على الاحتياج اليومي')->required()->live()
                            ->range(...$plan['adjustment_range'])->step($plan['adjustment_step'])
                            ->pips(PipsMode::Values)->pipsValues(array_keys($plan['adjustments']))
                            ->pipsFormatter(RawJs::make(self::SIGNED_JS))
                            ->tooltips(RawJs::make(self::SIGNED_JS))
                            ->afterStateUpdated(fn ($state, Set $set) => $set('preset', array_key_exists((int) $state, $plan['adjustments']) ? (string) (int) $state : null))
                            ->helperText(fn (Get $get) => 'التعديل: '.self::signed((int) $get('adjustment')).' سعرة في اليوم.'),
                    ]),

                Section::make('3. توزيع المغذيات')
                    ->description('نسبة كل واحد من السعرات النهائية. المجموع 100%.')
                    ->headerActions([$this->templateSharesAction()])
                    ->columns(3)
                    ->components(collect(['carbs_pct' => 'الكربوهيدرات', 'protein_pct' => 'البروتين', 'fat_pct' => 'الدهون'])
                        ->map(fn (string $label, string $field) => Slider::make($field)->label($label)->required()->live()
                            ->range($macroMin, $macroMax)->step(1)->decimalPlaces(0)->tooltips(RawJs::make('Math.round($value)'))
                            ->helperText(fn (Get $get) => ((int) $get($field)).'%'))
                        ->values()->all()),

                Section::make('4. السوائل')
                    ->components([
                        TextInput::make('fluids_ml')->label('السوائل في اليوم')->suffix('مل')->integer()->live(onBlur: true)
                            ->minValue($plan['fluids_range'][0])->maxValue($plan['fluids_range'][1])
                            ->placeholder(fn () => $this->preview()?->fluids['suggested'])
                            ->required(fn () => $this->preview()?->fluids['manual'] ?? false)
                            ->helperText(fn () => ($this->preview()?->fluids['manual'] ?? false)
                                ? 'حالة المراجع (القلب أو الكلى) تحتاج تحديد السوائل حسب توصية الطبيب، فلا نقترح رقماً.'
                                : 'اتركه فارغاً لنستعمل المقترح: '.$plan['fluids_ml_per_kg'].' مل لكل كغ.'),
                    ]),

                Section::make('5. الوجبات')->key('mealsSection')
                    ->description('اختياري: عدد الوجبات وساعاتها، وحصة كل وجبة من السعرات، وكم فيها من كل مغذٍّ.')
                    ->headerActions([
                        ...array_map(fn (string $key) => $this->mealPresetAction($key), array_keys($plan['meal_presets'])),
                        $this->redistributeAction(),
                    ])
                    ->components([
                        TextEntry::make('remaining')->hiddenLabel()
                            ->state(fn () => new HtmlString(view('filament.clusters.consultations.pages.plan-remaining', ['result' => $this->preview()])->render()))
                            ->visible(fn () => ! empty($this->data['meals'])),
                        // Two rows a meal (a table of eight columns is too narrow beside the summary).
                        Repeater::make('meals')->hiddenLabel()
                            ->columns(4)
                            ->itemLabel(fn (array $state) => trim(($state['time'] ?? '').' · '.(($state['type'] ?? '') === 'other' && filled($state['label'] ?? null)
                                ? $state['label'] : ($plan['meal_types'][$state['type'] ?? ''] ?? '')), ' ·'))
                            ->schema([
                                Select::make('type')->label('الوجبة')->options($plan['meal_types'])->required()->live()->selectablePlaceholder(false),
                                TimePicker::make('time')->label('الساعة')->seconds(false)->required()->live(onBlur: true),
                                TextInput::make('share_pct')->label('% من السعرات')->numeric()->minValue(0)->maxValue(100)->required()->live(onBlur: true),
                                TextInput::make('label')->label('الاسم')->placeholder('مثلاً: بعد التمرين')->maxLength(40)
                                    ->visible(fn (Get $get) => $get('type') === 'other')
                                    ->required(fn (Get $get) => $get('type') === 'other'),
                                ...array_map(fn (string $f, string $label) => TextInput::make($f)->label($label)->suffix('غ')->integer()->minValue(0)->maxValue(500)->required()->live(onBlur: true),
                                    ['carbs_g', 'protein_g', 'fat_g'], ['كربوهيدرات', 'بروتين', 'دهون']),
                                TextEntry::make('kcal')->label('السعرات (الحصة · الفرق)')->state(fn (Get $get) => $this->mealCalories($get)),
                            ])
                            ->default([])
                            ->maxItems($plan['max_meals'])
                            ->reorderable(false)
                            ->addActionLabel('إضافة وجبة'),
                    ]),
            ]);
    }

    /** Fills the meals from a ready-made split (standard five, or three), grams shared by calories (2a). */
    public function mealPresetAction(string $key): Action
    {
        $preset = config("nutrition_criteria.plan.meal_presets.$key");

        return Action::make('preset_'.$key)
            ->label($preset['label'])
            ->icon(Heroicon::OutlinedQueueList)
            ->link()
            ->requiresConfirmation()
            ->modalHidden(fn () => empty($this->data['meals']))
            ->modalDescription('تُستبدل الوجبات الحالية.')
            ->modalSubmitActionLabel('استبدال')
            ->action(function () use ($preset) {
                $meals = array_map(fn (array $m) => ['type' => $m[0], 'label' => null, 'time' => $m[1], 'share_pct' => $m[2]], $preset['meals']);
                $this->data['meals'] = $this->withGrams($meals);
            });
    }

    /** Shares the day's grams again by each meal's calorie share, replacing what was typed. */
    public function redistributeAction(): Action
    {
        return Action::make('redistribute')
            ->label('إعادة التوزيع التلقائي')
            ->icon(Heroicon::OutlinedArrowPath)
            ->link()
            ->color('gray')
            ->visible(fn () => ! empty($this->data['meals']))
            ->action(fn () => $this->data['meals'] = $this->withGrams(array_values($this->data['meals'])));
    }

    /** @return array<string, array<string, mixed>> the meals keyed for the repeater, grams by share */
    private function withGrams(array $meals): array
    {
        $result = $this->preview();
        $grams = array_map(fn (array $m) => $m['g'], $result?->macros ?? ['carbs' => ['g' => 0], 'protein' => ['g' => 0], 'fat' => ['g' => 0]]);
        $split = NutritionPlanCalculator::distribute($grams, array_map(fn (array $m) => (float) ($m['share_pct'] ?? 0), $meals));

        $keyed = [];
        foreach ($meals as $i => $meal) {
            $keyed[(string) Str::uuid()] = $split[$i] + $meal;
        }

        return $keyed;
    }

    /** "784 · 782 (−2)": the meal's share of the calories, and what its macros give (4a). */
    private function mealCalories(Get $get): ?HtmlString
    {
        $target = $this->preview()?->target;
        if ($target === null) {
            return null;
        }

        $share = (int) round($target * (float) $get('share_pct') / 100);
        $perGram = config('nutrition_criteria.kcal_per_g');
        $fromMacros = $perGram['carbs'] * (int) $get('carbs_g') + $perGram['protein'] * (int) $get('protein_g') + $perGram['fat'] * (int) $get('fat_g');
        $diff = $fromMacros - $share;

        return new HtmlString(e(number_format($share)).($diff === 0 ? '' :
            ' <span class="text-xs text-gray-500 dark:text-gray-400" title="من المغذيات '.e(number_format($fromMacros)).'" dir="ltr">('.e(self::signed($diff)).')</span>'));
    }

    /** Fills the three shares from the client's current diet template. */
    public function templateSharesAction(): Action
    {
        return Action::make('templateShares')
            ->label('خذ نسب القالب')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->link()
            ->action(function () {
                $macros = app(PersonalCriteria::class)->for($this->record->user)->template['macros'];
                $this->data = ['carbs_pct' => $macros['carbs'], 'protein_pct' => $macros['protein'], 'fat_pct' => $macros['fat']] + $this->data;
            });
    }

    /** The plan worked out from what is on the form now (nothing is saved). */
    public function preview(): ?NutritionPlanResult
    {
        if (! $this->canPlan() || empty($this->data['activity'])) {
            return null;
        }

        // Asked for by every meal row: worked out once per state of the form.
        $key = md5(json_encode($this->data));
        if (($this->previewCache[0] ?? null) !== $key) {
            $this->previewCache = [$key, app(NutritionPlans::class)->calculate($this->record->user->profile, $this->data)];
        }

        return $this->previewCache[1];
    }

    /** @var array{0?: string, 1?: NutritionPlanResult} not a Livewire property: rebuilt each request */
    protected array $previewCache = [];

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            app(ClientWorkspace::class)->savePlan(auth()->user(), $this->record, $data);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('لم تُحفظ الخطة')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->record->unsetRelation('plan');
        Notification::make()->title('حُفظت الخطة')->body('صارت سعرات المراجع ومغذياته منها، وتُقيَّم الوجبات عليها.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('ملف المراجع')
                ->icon(Heroicon::OutlinedArrowUturnRight)
                ->color('gray')
                ->url(ViewClient::getUrl(['record' => $this->record])),
            Action::make('removePlan')
                ->label('إلغاء الخطة')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn () => $this->record->plan()->exists())
                ->authorize('view', $this->record)
                ->requiresConfirmation()
                ->modalDescription('تعود سعرات المراجع ومغذياته إلى القالب أو إلى قيمه هو.')
                ->action(function () {
                    app(ClientWorkspace::class)->removePlan(auth()->user(), $this->record);
                    Notification::make()->title('أُلغيت الخطة.')->success()->send();
                    $this->redirect(ViewClient::getUrl(['record' => $this->record]));
                }),
        ];
    }

    /** "+300", "−500", "0" */
    public static function signed(int $kcal): string
    {
        return match (true) {
            $kcal > 0 => '+'.number_format($kcal),
            $kcal < 0 => '−'.number_format(abs($kcal)),
            default => '0',
        };
    }
}
