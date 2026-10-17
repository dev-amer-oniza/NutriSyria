<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UseArabicInPanel;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
     * Olive, set shade by shade: Color::hex('#5b7a29') generates a much brighter, lime-like
     * scale. 600 (buttons) keeps white text readable (contrast 4.6:1).
     */
    private const OLIVE = [
        50 => 'oklch(0.975 0.014 126)',
        100 => 'oklch(0.948 0.028 126)',
        200 => 'oklch(0.895 0.052 126)',
        300 => 'oklch(0.825 0.078 126)',
        400 => 'oklch(0.735 0.1 126)',
        500 => 'oklch(0.645 0.112 126)',
        600 => 'oklch(0.555 0.11 126)',
        700 => 'oklch(0.475 0.096 126)',
        800 => 'oklch(0.4 0.078 126)',
        900 => 'oklch(0.345 0.062 126)',
        950 => 'oklch(0.245 0.045 126)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Olive (Syrian kitchen: olive oil, za'atar) on warm stone greys. Status colors stay
            // the usual green / amber / red so approved, to-review and rejected read at a glance.
            ->colors([
                'primary' => self::OLIVE,
                'gray' => Color::Stone,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('NutriSyria')
            ->brandLogo(fn () => view('filament.admin.brand'))
            ->brandLogoHeight('2rem')
            ->navigationGroups(['البيانات الغذائية', 'المطاعم', 'الإعدادات'])
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
