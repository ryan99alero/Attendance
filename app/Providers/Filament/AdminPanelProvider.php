<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AttendanceStatsOverview;
use App\Filament\Widgets\AttendanceTrendsChart;
use App\Filament\Widgets\DepartmentBreakdownWidget;
use App\Filament\Widgets\PayrollSummaryWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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
            ->darkMode(true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('@livewire(\'announcement-handler\')'),
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label('Employee Portal')
                    ->icon('heroicon-o-user')
                    ->url('/portal')
                    ->visible(fn (): bool => Auth::user()?->employee_id !== null),
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
                    ->label('Reports & Analytics')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Settings')
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
                AttendanceStatsOverview::class,
                AttendanceTrendsChart::class,
                PayrollSummaryWidget::class,
                DepartmentBreakdownWidget::class,
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
