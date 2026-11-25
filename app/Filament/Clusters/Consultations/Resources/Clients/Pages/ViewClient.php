<?php

namespace App\Filament\Clusters\Consultations\Resources\Clients\Pages;

use App\Data\Daily\Diary;
use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\NutritionPlanResult;
use App\Enums\Allergen;
use App\Enums\CriterionSource;
use App\Enums\DailyLife;
use App\Enums\Goal;
use App\Enums\Sex;
use App\Filament\Clusters\Consultations\Resources\Clients\ClientResource;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Daily\ClientDiary;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\Criteria\SportAnswers;
use App\Services\Nutrition\Plan\NutritionPlans;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

/**
 * One client's file: profile and health record, daily needs, the diet template and every
 * criterion with where it comes from. While linked, the nutritionist (not the person) keeps
 * the profile and health record current, changes the template and criteria here
 * (ClientWorkspace), or ends the link.
 *
 * @property ConsultationRequest $record
 */
class ViewClient extends Page
{
    use InteractsWithRecord;

    public const NOTE_MAX = 300;

    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.clusters.consultations.pages.view-client';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->record), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->user->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->clientSummary();
    }

    /** The saved nutrition plan on the client's current profile; null without one. */
    public function plan(): ?NutritionPlanResult
    {
        return app(NutritionPlans::class)->current($this->record);
    }

    /** The client's "يومي": the last two weeks of meals and the last weighings (read only). */
    public function diary(): Diary
    {
        return app(ClientDiary::class)->for($this->record->user);
    }

    public function sheet(): ?CriteriaSheet
    {
        return app(PersonalCriteria::class)->for($this->record->user);
    }

    /** How a source reads to the nutritionist (CriterionSource::label speaks to the person). */
    public static function sourceLabel(CriterionSource $source): string
    {
        return match ($source) {
            CriterionSource::Default => 'محسوب',
            CriterionSource::User => 'حدده المراجع',
            CriterionSource::Doctor => 'من طبيب المراجع',
            CriterionSource::Nutritionist => 'حددته أنت',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('واتساب')
                ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                ->color('gray')
                ->visible(fn () => filled($this->record->user->phone))
                ->url(fn () => ConsultationRequest::whatsappUrl($this->record->user->phone), shouldOpenInNewTab: true),
            Action::make('plan')
                ->label('خطة التغذية')
                ->icon(Heroicon::OutlinedCalculator)
                ->url(fn () => ClientPlan::getUrl(['record' => $this->record])),
            $this->editProfileAction(),
            $this->endLinkAction(),
        ];
    }

    /** The person cannot change these while linked; the nutritionist keeps them current. */
    public function editProfileAction(): Action
    {
        $options = fn (array $cases) => collect($cases)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
        $year = (int) now()->year;

        return Action::make('editProfile')
            ->label('تعديل بيانات المراجع')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->authorize('view', $this->record)
            ->modalHeading(fn () => 'بيانات '.$this->record->user->name)
            ->modalDescription('لا يستطيع المراجع تعديلها ما دام الارتباط قائماً. يصله إشعار بالتحديث.')
            ->fillForm(function () {
                $profile = $this->record->user->profile;
                $answers = SportAnswers::fromRows($profile?->exercises, $profile?->training_days);

                return Arr::only($profile?->attributesToArray() ?? [], ['sex', 'birth_year', 'height_cm', 'weight_kg', 'daily_life', 'work_days', 'goal', 'allergies'])
                    + ['training_days' => $answers['days'], 'sports' => collect($answers['sports'])->map(fn ($s, $type) => ['type' => $type] + $s)->values()->all()];
            })
            ->schema([
                Grid::make(2)->schema([
                    Select::make('sex')->label('الجنس')->options($options(Sex::cases()))->required(),
                    TextInput::make('birth_year')->label('سنة الميلاد')->integer()->required()->minValue($year - 100)->maxValue($year - 10),
                    TextInput::make('height_cm')->label('الطول')->suffix('سم')->numeric()->required()->minValue(120)->maxValue(230),
                    TextInput::make('weight_kg')->label('الوزن')->suffix('كغ')->numeric()->required()->minValue(30)->maxValue(300),
                    Select::make('daily_life')->label('نهاره بلا رياضة')->live()->required()
                        ->options(collect(DailyLife::cases())->mapWithKeys(fn (DailyLife $d) => [$d->value => $d->label().' (×'.$d->factor().')'])->all()),
                    TextInput::make('work_days')->label('أيام الدوام بالأسبوع')->integer()->minValue(1)->maxValue(7)
                        ->visible(fn (Get $get) => DailyLife::tryFrom((string) $get('daily_life'))?->hasWorkDays() ?? false)
                        ->required(fn (Get $get) => DailyLife::tryFrom((string) $get('daily_life'))?->hasWorkDays() ?? false),
                    Select::make('goal')->label('الهدف')->options($options(Goal::cases()))->required(),
                ]),
                // The site's three questions: the training days, the sports, and each one's days and minutes.
                Select::make('training_days')->label('كم يوم بالأسبوع يتمرّن؟')->required()->live()
                    ->options([0 => 'ولا يوم'] + array_combine(range(1, 7), array_map('strval', range(1, 7))))
                    ->helperText('يحدّد قالب النشاط الرياضي (3 أيام فأكثر)، لا السعرات.'),
                Repeater::make('sports')->label('شو بيعمل بهالأيام؟')->defaultItems(0)->maxItems((int) config('nutrition_criteria.max_exercises'))
                    ->addActionLabel('أضف رياضة')->columns(3)->live()
                    ->visible(fn (Get $get) => (int) $get('training_days') > 0)
                    ->minItems(fn (Get $get) => (int) $get('training_days') > 0 ? 1 : 0)
                    ->schema([
                        Select::make('type')->label('الرياضة')->required()->distinct()
                            ->options(collect(config('nutrition_criteria.exercises'))->map(fn (array $e) => $e['label'].' ('.$e['met'].' MET)')->all()),
                        Select::make('days')->label('كم يوم منها')->required()
                            ->options(fn (Get $get) => array_combine(range(1, max(1, (int) $get('../../training_days'))), array_map('strval', range(1, max(1, (int) $get('../../training_days')))))),
                        Select::make('minutes')->label('كم دقيقة تقريباً')->required()
                            ->options(collect(SportAnswers::minuteOptions())->mapWithKeys(fn (int $m, int $i) => [$m => $m.($i === count(SportAnswers::minuteOptions()) - 1 ? '+' : '')])->all()),
                    ]),
                CheckboxList::make('allergies')->label('الحساسيات')->options($options(Allergen::cases()))->columns(4),
            ])
            ->action(function (array $data) {
                $days = (int) ($data['training_days'] ?? 0);
                $sports = $days > 0 ? collect($data['sports'] ?? [])->mapWithKeys(fn (array $s) => [$s['type'] => ['days' => min($days, (int) $s['days']), 'minutes' => (int) $s['minutes']]])->all() : [];
                if ($days > 0 && array_sum(array_column($sports, 'days')) < $days) {
                    Notification::make()->title("قلت إنه يتمرّن {$days} أيام، والرياضات موزّعة على أيام أقل. زِد أيام رياضة أو قلّل أيام التمرين.")->danger()->send();

                    return;
                }
                $data['exercises'] = SportAnswers::toRows($sports);
                $data['training_days'] = $days ?: null;
                unset($data['sports']);
                app(ClientWorkspace::class)->updateProfile(auth()->user(), $this->record, $data);
                Notification::make()->title('حُفظت البيانات، وأُعيد حساب معاييره.')->success()->send();
            });
    }

    public function chooseTemplateAction(): Action
    {
        return Action::make('chooseTemplate')
            ->label('تغيير القالب')
            ->icon(Heroicon::OutlinedSwatch)
            ->authorize('view', $this->record)
            ->modalHeading('اختيار القالب الغذائي')
            ->modalDescription('يُعاد توزيع السعرات على الكربوهيدرات والبروتين والدهون حسبه، ويبقى ثابتاً للمراجع حتى تغيّره.')
            ->fillForm(fn () => ['template' => $this->sheet()?->template['key']])
            ->schema([
                Select::make('template')
                    ->label('القالب')
                    ->options(collect(config('nutrition_criteria.templates'))->map(fn (array $t) => $t['label'].' (بروتين '.(is_array($t['protein_per_kg']) ? implode('–', array_unique($t['protein_per_kg'])) : $t['protein_per_kg']).' غ/كغ)')->all())
                    ->required()
                    ->searchable(),
                Textarea::make('note')
                    ->label('ملاحظة للمراجع (اختيارية)')
                    ->placeholder('مثلاً: اخترته لضبط السكر بعد الوجبات.')
                    ->rows(2)
                    ->maxLength(self::NOTE_MAX),
            ])
            ->action(function (array $data) {
                app(ClientWorkspace::class)->chooseTemplate(auth()->user(), $this->record, $data['template'], $data['note'] ?? null);
                Notification::make()->title('تغيّر القالب، وأُعيد حساب معاييره.')->success()->send();
            });
    }

    public function resetTemplateAction(): Action
    {
        return Action::make('resetTemplate')
            ->label('إرجاع القالب')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->authorize('view', $this->record)
            ->visible(fn () => $this->sheet()?->template['source'] === CriterionSource::Nutritionist)
            ->requiresConfirmation()
            ->modalDescription('يعود إلى ما اختاره المراجع، أو إلى ما يناسب حالته تلقائياً.')
            ->action(function () {
                app(ClientWorkspace::class)->resetTemplate(auth()->user(), $this->record);
                Notification::make()->title('عاد القالب.')->success()->send();
            });
    }

    public function editCriterionAction(): Action
    {
        return Action::make('editCriterion')
            ->label('تعديل')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->size('sm')
            ->link()
            ->authorize('view', $this->record)
            ->modalHeading(fn (array $arguments) => config('nutrition_criteria.criteria.'.self::criterionKey($arguments).'.label'))
            ->fillForm(fn (array $arguments) => [
                'value' => $this->sheet()?->get(self::criterionKey($arguments))?->value,
                'note' => $this->sheet()?->get(self::criterionKey($arguments))?->source === CriterionSource::Nutritionist
                    ? $this->sheet()->get(self::criterionKey($arguments))->note
                    : null,
            ])
            ->schema(fn (array $arguments) => [
                TextInput::make('value')
                    ->label('القيمة')
                    ->suffix(config('nutrition_criteria.criteria.'.self::criterionKey($arguments).'.unit'))
                    ->numeric()
                    ->required()
                    ->minValue(PersonalCriteria::range(self::criterionKey($arguments))[0])
                    ->maxValue(PersonalCriteria::range(self::criterionKey($arguments))[1]),
                Textarea::make('note')
                    ->label('ملاحظة للمراجع (اختيارية)')
                    ->placeholder('مثلاً: خفّضته لأن ضغطك مرتفع.')
                    ->rows(2)
                    ->maxLength(self::NOTE_MAX),
            ])
            ->action(function (array $data, array $arguments) {
                app(ClientWorkspace::class)->setCriterion(auth()->user(), $this->record, self::criterionKey($arguments), (float) $data['value'], $data['note'] ?? null);
                Notification::make()->title('حُفظ المعيار. صار مقفولاً على المراجع.')->success()->send();
            });
    }

    public function resetCriterionAction(): Action
    {
        return Action::make('resetCriterion')
            ->label('إرجاع')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->size('sm')
            ->link()
            ->color('gray')
            ->authorize('view', $this->record)
            ->requiresConfirmation()
            ->modalDescription('يعود إلى قيمة المراجع، أو إلى المحسوب تلقائياً.')
            ->action(function (array $arguments) {
                app(ClientWorkspace::class)->resetCriterion(auth()->user(), $this->record, self::criterionKey($arguments));
                Notification::make()->title('عاد المعيار.')->success()->send();
            });
    }

    public function endLinkAction(): Action
    {
        return Action::make('endLink')
            ->label('إنهاء الارتباط')
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('danger')
            ->authorize('view', $this->record)
            ->modalHeading(fn () => 'إنهاء الارتباط مع '.$this->record->user->name)
            ->modalDescription('تُحذف كل المعايير والقالب الذي حددته، وتعود معاييره إلى قيمه هو. لن ترى ملفه بعدها، ويصله إشعار.')
            ->modalSubmitActionLabel('إنهاء الارتباط')
            ->schema([
                Textarea::make('reason')
                    ->label('السبب (اختياري، يصله في الإشعار)')
                    ->rows(2)
                    ->maxLength(self::NOTE_MAX),
            ])
            ->action(function (array $data) {
                app(ClientWorkspace::class)->end(auth()->user(), $this->record, $data['reason'] ?? null);
                Notification::make()->title('انتهى الارتباط.')->success()->send();
                $this->redirect(ClientResource::getUrl());
            });
    }

    /** A criterion key from the action arguments; an unknown one is refused. */
    private static function criterionKey(array $arguments): string
    {
        $key = (string) ($arguments['criterion'] ?? '');
        abort_unless(array_key_exists($key, config('nutrition_criteria.criteria')), 404);

        return $key;
    }
}
