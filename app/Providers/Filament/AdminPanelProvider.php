<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use App\Filament\Widgets\AttendanceStatsWidget;
use App\Filament\Widgets\AttendanceChartWidget;
use App\Filament\Widgets\DepartmentBreakdownWidget;
use App\Filament\Widgets\KoolReportWidget;
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
                    ->collapsible(),
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

                // Reports & Analytics
                NavigationItem::make('Reports Dashboard')
                    ->url('/reports/dashboard')
                    ->icon('heroicon-o-chart-pie')
                    ->group('Reports & Analytics')
                    ->openUrlInNewTab(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                AttendanceStatsWidget::class,
                AttendanceChartWidget::class,
                DepartmentBreakdownWidget::class,
                KoolReportWidget::class,
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
