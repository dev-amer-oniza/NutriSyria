<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UseArabicInPanel;
use App\Support\ArabicSearch;
use Closure;
use Filament\Actions\Action;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Pine, the site's main colour (#0f3d32 at 600, the shade Filament gives buttons and links),
     * set shade by shade from the site's own pine, pine-soft and pine-strong, in OKLCH. The
     * panel wears the site identity (the owner's decision 1a): pine, terracotta as the accent
     * (theme.css), warm sand greys, Sora for numbers.
     */
    private const PINE = [
        50 => 'oklch(0.963 0.006 154)',
        100 => 'oklch(0.917 0.015 136)',
        200 => 'oklch(0.837 0.026 160)',
        300 => 'oklch(0.702 0.043 168)',
        400 => 'oklch(0.552 0.056 172)',
        500 => 'oklch(0.429 0.058 172)',
        600 => 'oklch(0.326 0.053 174)',
        700 => 'oklch(0.291 0.047 173)',
        800 => 'oklch(0.262 0.041 174)',
        900 => 'oklch(0.233 0.036 173)',
        950 => 'oklch(0.183 0.026 173)',
    ];

    public function boot(): void
    {
        // Every table in the panel: light row stripes for long lists of foods and values, and
        // 25 rows a page (the food list is a few hundred rows).
        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25));

        // Yes/no columns: "no" is a neutral dash, not a red cross that reads as an error.
        IconColumn::configureUsing(fn (IconColumn $column) => $column
            ->falseIcon(Heroicon::OutlinedMinus)
            ->falseColor('gray'));

        // Every dropdown opens with its choices already listed (owner's request), in forms and filters.
        Select::configureUsing(fn (Select $select) => $select->preload());
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->preload());

        // Search a relationship dropdown whatever the Arabic spelling ("ارز" finds "أرز").
        // Call after ->relationship(); $scope repeats its modifyQueryUsing, if it has one.
        Select::macro('arabicSearch', function (?Closure $scope = null): Select {
            /** @var Select $this */
            return $this->getSearchResultsUsing(function (Select $component, string $search) use ($scope): array {
                $related = $component->getRelationship()->getRelated();
                $title = $component->getRelationshipTitleAttribute();
                $query = $related->newQuery();
                $query = $scope ? ($scope($query) ?? $query) : $query;

                return ArabicSearch::constrain($query, [$title], $search)
                    ->orderBy($title)
                    ->limit(50)
                    ->pluck($title, $related->getKeyName())
                    ->all();
            });
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // The site identity: pine on warm greys (sand surfaces in theme.css). Status colors stay
            // the usual green / amber / red so approved, to-review and rejected read at a glance.
            ->colors([
                'primary' => self::PINE,
                'gray' => Color::Stone,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            // The bell: review notes reach brand owners and nutritionists here.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Livewire nearly stops polling in a background tab, so a note sent meanwhile would wait
            // minutes. Check again the moment the user comes back to the tab.
            // Sora for numbers and the logo's wordmark, as on the site.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => <<<'HTML'
                <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap">
                HTML)
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => <<<'HTML'
                <script>
                    document.addEventListener('visibilitychange', () => {
                        if (! document.hidden) window.Livewire?.dispatch('databaseNotificationsSent')
                    })
                </script>
                HTML)
            // Deny by default: a model without a policy, or a policy without the asked ability,
            // is an error instead of silently allowed (see App\Policies\Concerns\DeniesByDefault).
            ->strictAuthorization()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('NutriSyria')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/site/mark.svg'))
            // The account menu, for every role: a way back to the public site (the owner's request).
            ->userMenuItems([
                'site' => Action::make('site')
                    ->label('الذهاب للموقع')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn (): string => route('home')),
            ])
            ->navigationGroups(['البيانات الغذائية', 'المطاعم والبراندات', 'الاستشارات', 'الإعدادات'])
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Also on Livewire requests from the panel, so a refreshed component stays Arabic.
            ->middleware([UseArabicInPanel::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
