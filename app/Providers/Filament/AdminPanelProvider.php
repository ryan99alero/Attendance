<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Employee Management')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Time Tracking')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Scheduling & Shifts')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Payroll & Overtime')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Time Off Management')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('System & Hardware')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Reports & Analytics')
                    ->collapsible()
                    ->collapsed(),
            ])
            ->navigationItems([
                // Custom pages/items that don't have resources
                NavigationItem::make('Attendance Summary')
                    ->url('/admin/attendance-summary')
                    ->icon('heroicon-o-document-text')
                    ->group('Time Tracking'),
                NavigationItem::make('Punch Summary')
                    ->url('/admin/punch-summary')
                    ->icon('heroicon-o-finger-print')
                    ->group('Time Tracking'),

                // Reports & Analytics (placeholder for future reports)
                NavigationItem::make('Reports Dashboard')
                    ->url('/admin/reports-placeholder')
                    ->icon('heroicon-o-chart-pie')
                    ->group('Reports & Analytics'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
