<?php

namespace App\Filament\Clusters\Kitchen\Pages;

use App\Filament\Clusters\Kitchen\KitchenCluster;
use App\Models\Brand;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * "معلومات المطبخ": the kitchen's logo and description on its site page, as a restaurant's
 * (the owner's decision 1a: these two only; the name "المطبخ السوري" stays fixed). Admin only.
 */
class KitchenSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.clusters.kitchen.pages.kitchen-settings';

    protected static ?string $cluster = KitchenCluster::class;

    protected static ?string $slug = 'settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Identification;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'معلومات المطبخ';

    protected static ?string $title = 'معلومات المطبخ';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->managesKitchen();
    }

    public function mount(): void
    {
        $kitchen = Brand::platform();
        $this->getForm('form')->fill(['logo' => $kitchen->logo, 'description' => $kitchen->description]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('كيف يظهر المطبخ في الموقع')
                    ->description('في صفحة "المطبخ السوري". الاسم ثابت.')
                    ->components([
                        FileUpload::make('logo')
                            ->label('الشعار')
                            ->helperText('إن لم ترفع شعاراً تظهر رسمة المطبخ الموحّدة.')
                            ->image()
                            ->disk('public')
                            ->directory('brands')
                            ->imageEditor()
                            ->avatar()
                            ->maxSize(2048),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->placeholder('أكلات البيت السوري بقيمها الغذائية، من وصفات المنصة.')
                            ->helperText('إن تركته فارغاً يظهر الوصف المكتوب هنا كمثال.')
                            ->rows(4)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->getForm('form')->getState();
        $kitchen = Brand::platform();
        $kitchen->logo = $state['logo'] ?: null;
        $kitchen->description = trim((string) ($state['description'] ?? '')) ?: null;
        $kitchen->save(); // a Brand save renews the site's cache

        Notification::make()->title('حُفظت معلومات المطبخ، وظهرت في الموقع.')->success()->send();
    }
}
