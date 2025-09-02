<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Http\Middleware\Authenticate;
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
                    ->label('Punch & Attendance')
                    ->collapsible()
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Employee Management')
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
                    ->label('Reports & Stats')
                    ->collapsible()
                    ->collapsed(), // Placeholder for future reports and statistics
                NavigationGroup::make()
                    ->label('Settings')
                    ->collapsible()
                    ->collapsed(),
            ])
            ->navigationItems([
                // Punch & Attendance
                NavigationItem::make('Attendances')
                    ->url('/admin/attendances')
                    ->icon('heroicon-o-finger-print')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Attendance Summary')
                    ->url('/admin/attendance-summary')
                    ->icon('heroicon-o-document-text')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Attendance Summary (Claude)')
                    ->url('/admin/c-attendance-summary')
                    ->icon('heroicon-o-clock')
                    ->group('Punch & Attendance')
                    ->badge('New'),
                NavigationItem::make('Punch Summary')
                    ->url('/admin/punch-summary')
                    ->icon('heroicon-o-finger-print')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Punches')
                    ->url('/admin/punches')
                    ->icon('heroicon-o-finger-print')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Devices')
                    ->url('/admin/devices')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Cards')
                    ->url('/admin/cards')
                    ->icon('heroicon-o-credit-card')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Employee Stats')
                    ->url('/admin/employee-stats')
                    ->icon('heroicon-o-chart-bar')
                    ->group('Punch & Attendance'),
                NavigationItem::make('Punch Types') // Added Punch Types to Punch & Attendance
                ->url('/admin/punch-types')
                    ->icon('heroicon-o-rectangle-stack')
                    ->group('Punch & Attendance'),

                // Employee Management
                NavigationItem::make('Employees')
                    ->url('/admin/employees')
                    ->icon('heroicon-o-users')
                    ->group('Employee Management'),
                NavigationItem::make('Users')
                    ->url('/admin/users')
                    ->icon('heroicon-o-user-circle')
                    ->group('Employee Management'),
                NavigationItem::make('Departments')
                    ->url('/admin/departments')
                    ->icon('heroicon-o-building-office')
                    ->group('Employee Management'),
                NavigationItem::make('Vacation Balances')
                    ->url('/admin/vacation-balances')
                    ->icon('heroicon-o-scale')
                    ->group('Employee Management'),
                NavigationItem::make('Vacation Calendars')
                    ->url('/admin/vacation-calendars')
                    ->icon('heroicon-o-calendar-days')
                    ->group('Employee Management'),

                // Scheduling & Shifts
                NavigationItem::make('Shift Schedules')
                    ->url('/admin/shift-schedules')
                    ->icon('heroicon-o-calendar')
                    ->group('Scheduling & Shifts'),
                NavigationItem::make('Shifts')
                    ->url('/admin/shifts')
                    ->icon('heroicon-o-clock')
                    ->group('Scheduling & Shifts'),
                NavigationItem::make('Holidays')
                    ->url('/admin/holidays')
                    ->icon('heroicon-o-gift')
                    ->group('Scheduling & Shifts'),

                // Payroll & Overtime
                NavigationItem::make('Pay Periods')
                    ->url('/admin/pay-periods')
                    ->icon('heroicon-o-calendar')
                    ->group('Payroll & Overtime'),
                NavigationItem::make('Payroll Frequencies')
                    ->url('/admin/payroll-frequencies')
                    ->icon('heroicon-o-banknotes')
                    ->group('Payroll & Overtime'),
                NavigationItem::make('Overtime Rules')
                    ->url('/admin/overtime-rules')
                    ->icon('heroicon-o-document-chart-bar')
                    ->group('Payroll & Overtime'),
                NavigationItem::make('Rounding Rules') // Moved to Payroll & Overtime
                ->url('/admin/rounding-rules')
                    ->icon('heroicon-o-adjustments-vertical')
                    ->group('Payroll & Overtime'),

                // Reports & Stats
                NavigationItem::make('Placeholder')
                    ->url('/admin/reports-placeholder')
                    ->icon('heroicon-o-chart-pie')
                    ->group('Reports & Stats'), // Placeholder for future reports
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
