<?php

namespace App\Filament\Clusters\Consultations\Resources\Clients\Pages;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Filament\Clusters\Consultations\Resources\Clients\ClientResource;
use App\Models\ConsultationRequest;
use App\Services\Consultations\ClientWorkspace;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One client's file: profile and health record (read only), daily needs, the diet template
 * and every criterion with where it comes from. The nutritionist changes the template and
 * criteria here (ClientWorkspace), or ends the link.
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
            $this->endLinkAction(),
        ];
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
                    ->options(collect(config('nutrition_criteria.templates'))->map(fn (array $t) => $t['label'].' (كربوهيدرات '.$t['macros']['carbs'].'٪، بروتين '.$t['macros']['protein'].'٪، دهون '.$t['macros']['fat'].'٪)')->all())
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
            ->modalDescription('تُحذف كل المعايير والقالب الذي حددته، وتعود معاييره إلى قيمه هو. لن ترى ملفه بعدها، ويصله إيميل.')
            ->modalSubmitActionLabel('إنهاء الارتباط')
            ->schema([
                Textarea::make('reason')
                    ->label('السبب (اختياري، يصله في الإيميل)')
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
