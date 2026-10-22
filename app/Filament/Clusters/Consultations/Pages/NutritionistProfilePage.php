<?php

namespace App\Filament\Clusters\Consultations\Pages;

use App\Auth\Access;
use App\Enums\ConsultationMode;
use App\Enums\NutritionistSpecialty;
use App\Enums\SyrianGovernorate;
use App\Filament\Clusters\Consultations\ConsultationsCluster;
use App\Models\NutritionistProfile;
use App\Services\Consultations\NutritionistDirectory;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** "My public profile": what visitors see in the site's nutritionist directory. */
class NutritionistProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    /** Only a nutritionist edits their own profile (not the admin, who has no profile). */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole(Access::NUTRITIONIST) && $user->allows(Access::CONSULTATIONS_PROVIDE);
    }

    protected string $view = 'filament.clusters.consultations.pages.nutritionist-profile';

    protected static ?string $cluster = ConsultationsCluster::class;

    protected static ?string $slug = 'profile';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Identification;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'ملفي العام';

    protected static ?string $title = 'ملفي العام في الموقع';

    public ?array $data = [];

    public function mount(NutritionistDirectory $directory): void
    {
        $this->form->fill($directory->profileOf(auth()->user())->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('كيف يراك المراجعون')
                    ->columns(2)
                    ->components([
                        TextInput::make('display_name')->label('الاسم المعروض')->placeholder('د. ريم الأحمد')
                            ->required()->maxLength(80),
                        FileUpload::make('photo')->label('الصورة')->image()->disk('public')->directory('nutritionists')
                            ->imageEditor()->avatar()->maxSize(2048),
                        Select::make('city')->label('المحافظة')->options(SyrianGovernorate::options())->required(),
                        TextInput::make('years_experience')->label('سنوات الخبرة')->numeric()->minValue(0)->maxValue(60),
                        CheckboxList::make('specialties')->label('التخصصات')->options(NutritionistSpecialty::options())
                            ->required()->columns(3)->columnSpanFull(),
                        Textarea::make('bio')->label('نبذة عنك')->rows(4)->required()
                            ->minLength(NutritionistProfile::MIN_BIO)->maxLength(1000)
                            ->helperText('كيف تعمل مع مراجعيك، وما الذي يميّزك. تظهر في صفحتك.')->columnSpanFull(),
                    ]),
                Section::make('الاستشارة')
                    ->columns(2)
                    ->components([
                        Radio::make('consultation_mode')->label('طريقة الاستشارة')->options(ConsultationMode::options())
                            ->required()->live(),
                        TextInput::make('clinic_address')->label('عنوان العيادة')->maxLength(200)
                            ->visible(fn (Get $get) => ConsultationMode::tryFrom((string) $get('consultation_mode'))?->needsAddress() ?? false)
                            ->required(fn (Get $get) => ConsultationMode::tryFrom((string) $get('consultation_mode'))?->needsAddress() ?? false),
                        Toggle::make('accepting_clients')->label('أستقبل مراجعين جدداً')
                            ->helperText('عند إيقافه يختفي ملفك من قائمة الأخصائيين، ولا يتأثر مراجعوك الحاليون.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(NutritionistDirectory $directory): void
    {
        $profile = $directory->save(auth()->user(), $this->form->getState());

        Notification::make()
            ->title($profile->isListed() ? 'حُفظ ملفك، وهو ظاهر في الموقع.' : 'حُفظ ملفك.')
            ->success()
            ->send();
    }

    /** For the status box above the form. */
    public function profile(): NutritionistProfile
    {
        return app(NutritionistDirectory::class)->profileOf(auth()->user());
    }
}
