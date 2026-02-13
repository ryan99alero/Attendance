<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanySetupResource\Pages\EditCompanySetup;
use App\Filament\Resources\CompanySetupResource\Pages\ListCompanySetups;
use App\Models\CompanySetup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanySetupResource extends Resource
{
    protected static ?string $model = CompanySetup::class;

    protected static ?string $navigationLabel = 'Company Setup';

    protected static string|\UnitEnum|null $navigationGroup = 'System & Hardware';

    protected static ?string $slug = 'company-setup';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('id')
                    ->disabled()
                    ->dehydrated(false),

                Tabs::make('Company Setup')
                    ->tabs([
                        // =====================================================
                        // TAB 1: COMPANY & PAYROLL
                        // =====================================================
                        Tab::make('Company & Payroll')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Section::make('Payroll Settings')
                                    ->description('Configure company-wide payroll and attendance settings')
                                    ->schema([
                                        Select::make('payroll_frequency_id')
                                            ->label('Payroll Frequency')
                                            ->relationship('payrollFrequency', 'frequency_name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('All employees will follow this payroll schedule'),

                                        TextInput::make('attendance_flexibility_minutes')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->label('Attendance Flexibility (Minutes)')
                                            ->helperText('Minutes allowed before/after shift for attendance matching'),

                                        TextInput::make('max_shift_length')
                                            ->numeric()
                                            ->default(12)
                                            ->required()
                                            ->label('Max Shift Length (Hours)')
                                            ->helperText('Maximum hours in a shift before requiring approval'),
                                    ])
                                    ->columns(3),

                                Section::make('Pay Period Naming')
                                    ->description('Configure automatic naming for generated pay periods')
                                    ->schema([
                                        TextInput::make('pay_period_naming_pattern')
                                            ->label('Naming Pattern')
                                            ->default('Week {week_number}, {year}')
                                            ->helperText('Variables: {week_number}, {year}, {start_date}, {end_date}, {start_month}, {end_month}, {start_day}, {end_day}, {sequence}')
                                            ->placeholder('Week {week_number}, {year}')
                                            ->maxLength(255),
                                    ]),

                                Section::make('Attendance Rules')
                                    ->schema([
                                        Toggle::make('enforce_shift_schedules')
                                            ->default(true)
                                            ->label('Enforce Shift Schedules')
                                            ->helperText('Require employees to follow assigned shift times'),

                                        Toggle::make('allow_manual_time_edits')
                                            ->default(true)
                                            ->label('Allow Manual Time Edits')
                                            ->helperText('Allow administrators to manually edit time records'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 2: TIME & ATTENDANCE
                        // =====================================================
                        Tab::make('Time & Attendance')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Punch Processing Engine')
                                    ->description('Configure how punches are classified and processed')
                                    ->schema([
                                        Select::make('debug_punch_assignment_mode')
                                            ->label('Punch Assignment Engine')
                                            ->options([
                                                'heuristic' => 'Heuristic Only',
                                                'ml' => 'Machine Learning Only',
                                                'consensus' => 'Consensus (Both Engines)',
                                                'all' => 'All Engines',
                                            ])
                                            ->default('all')
                                            ->required()
                                            ->helperText('Which engine(s) to use for punch type assignment'),

                                        TextInput::make('heuristic_min_punch_gap')
                                            ->label('Minimum Punch Gap (Hours)')
                                            ->numeric()
                                            ->default(6)
                                            ->required()
                                            ->helperText('Minimum hours between punches for auto Clock In/Out'),

                                        Toggle::make('use_ml_for_punch_matching')
                                            ->label('Use ML for Punch Matching')
                                            ->default(true)
                                            ->helperText('Enable machine learning for punch classification'),

                                        Toggle::make('auto_adjust_punches')
                                            ->label('Auto Adjust Punches')
                                            ->default(false)
                                            ->helperText('Automatically adjust punch types for incomplete records'),
                                    ])
                                    ->columns(2),

                                Section::make('Clock Event Sync')
                                    ->description('Control how clock events are processed into attendance records')
                                    ->schema([
                                        Select::make('clock_event_sync_frequency')
                                            ->label('Sync Frequency')
                                            ->options([
                                                'real_time' => 'Real-time (Push)',
                                                'every_minute' => 'Every Minute',
                                                'every_5_minutes' => 'Every 5 Minutes',
                                                'every_15_minutes' => 'Every 15 Minutes',
                                                'every_30_minutes' => 'Every 30 Minutes',
                                                'hourly' => 'Hourly',
                                                'twice_daily' => 'Twice Daily (8am, 6pm)',
                                                'daily' => 'Daily (6am)',
                                                'manual_only' => 'Manual Only',
                                            ])
                                            ->default('every_5_minutes')
                                            ->required()
                                            ->helperText('How often to process ClockEvents into Attendance records'),

                                        TextInput::make('clock_event_batch_size')
                                            ->label('Batch Size')
                                            ->numeric()
                                            ->default(100)
                                            ->required()
                                            ->helperText('Number of clock events per batch'),

                                        Toggle::make('clock_event_auto_retry_failed')
                                            ->label('Auto-retry Failed Events')
                                            ->default(true)
                                            ->helperText('Automatically retry events that failed processing'),

                                        TimePicker::make('clock_event_daily_sync_time')
                                            ->label('Daily Sync Time')
                                            ->default('06:00:00')
                                            ->helperText('Time of day for daily sync')
                                            ->visible(fn (Get $get): bool => in_array($get('clock_event_sync_frequency'), ['daily', 'twice_daily'])),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 3: DEVICES
                        // =====================================================
                        Tab::make('Devices')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema([
                                Section::make('Device Polling')
                                    ->description('Configure how often devices check for updates')
                                    ->schema([
                                        TextInput::make('config_poll_interval_minutes')
                                            ->label('Configuration Poll Interval (Minutes)')
                                            ->numeric()
                                            ->default(5)
                                            ->required()
                                            ->helperText('How often devices check for configuration updates'),

                                        TextInput::make('firmware_check_interval_hours')
                                            ->label('Firmware Check Interval (Hours)')
                                            ->numeric()
                                            ->default(24)
                                            ->required()
                                            ->helperText('How often devices check for firmware updates'),

                                        Toggle::make('allow_device_poll_override')
                                            ->label('Allow Device Poll Override')
                                            ->default(false)
                                            ->helperText('Allow individual devices to override company polling settings'),
                                    ])
                                    ->columns(3),

                                Section::make('Device Offline Alerts')
                                    ->description('Get notified when time clocks go offline')
                                    ->schema([
                                        TextInput::make('device_alert_email')
                                            ->label('Alert Email Address')
                                            ->email()
                                            ->nullable()
                                            ->helperText('Primary email for device offline/online alerts (also sends to department manager)'),

                                        TextInput::make('device_offline_threshold_minutes')
                                            ->label('Offline Threshold (Minutes)')
                                            ->numeric()
                                            ->default(5)
                                            ->required()
                                            ->helperText('Minutes of no heartbeat before sending an offline alert'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 4: EMAIL / SMTP
                        // =====================================================
                        Tab::make('Email / SMTP')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('SMTP Server Configuration')
                                    ->description('Configure outgoing email server settings')
                                    ->schema([
                                        Toggle::make('smtp_enabled')
                                            ->label('Enable SMTP')
                                            ->default(false)
                                            ->helperText('Enable to send emails via SMTP instead of system default')
                                            ->live()
                                            ->columnSpanFull(),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('smtp_host')
                                                    ->label('SMTP Host')
                                                    ->placeholder('smtp.example.com')
                                                    ->helperText('Mail server hostname or IP address')
                                                    ->required(fn (Get $get): bool => $get('smtp_enabled')),

                                                TextInput::make('smtp_port')
                                                    ->label('SMTP Port')
                                                    ->numeric()
                                                    ->default(587)
                                                    ->helperText('Common ports: 25, 465 (SSL), 587 (TLS)')
                                                    ->required(fn (Get $get): bool => $get('smtp_enabled')),
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('smtp_username')
                                                    ->label('SMTP Username')
                                                    ->placeholder('user@example.com')
                                                    ->helperText('Authentication username (often your email address)'),

                                                TextInput::make('smtp_password')
                                                    ->label('SMTP Password')
                                                    ->password()
                                                    ->revealable()
                                                    ->helperText('Authentication password (stored encrypted)'),
                                            ]),

                                        Grid::make(3)
                                            ->schema([
                                                Select::make('smtp_encryption')
                                                    ->label('Encryption')
                                                    ->options([
                                                        'none' => 'None',
                                                        'tls' => 'TLS (Recommended)',
                                                        'ssl' => 'SSL',
                                                    ])
                                                    ->default('tls')
                                                    ->helperText('TLS on port 587 is recommended'),

                                                TextInput::make('smtp_timeout')
                                                    ->label('Timeout (Seconds)')
                                                    ->numeric()
                                                    ->default(30)
                                                    ->helperText('Connection timeout'),

                                                Toggle::make('smtp_verify_peer')
                                                    ->label('Verify SSL Certificate')
                                                    ->default(true)
                                                    ->helperText('Disable only for self-signed certs'),
                                            ]),
                                    ])
                                    ->visible(fn (Get $get): bool => true),

                                Section::make('Sender Information')
                                    ->description('Configure the "From" address for outgoing emails')
                                    ->schema([
                                        TextInput::make('smtp_from_address')
                                            ->label('From Email Address')
                                            ->email()
                                            ->placeholder('noreply@yourcompany.com')
                                            ->helperText('The email address that appears in the "From" field')
                                            ->required(fn (Get $get): bool => $get('smtp_enabled')),

                                        TextInput::make('smtp_from_name')
                                            ->label('From Name')
                                            ->placeholder('Attend Time Clock System')
                                            ->helperText('The name that appears in the "From" field'),

                                        TextInput::make('smtp_reply_to')
                                            ->label('Reply-To Address')
                                            ->email()
                                            ->placeholder('support@yourcompany.com')
                                            ->helperText('Optional: Where replies should be sent (if different from "From")'),
                                    ])
                                    ->columns(3),

                                Section::make('Test Email')
                                    ->description('Send a test email to verify your configuration')
                                    ->schema([
                                        Placeholder::make('test_email_info')
                                            ->content('Save your settings first, then use the "Send Test Email" action to verify your SMTP configuration works correctly.')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsed(),
                            ]),

                        // =====================================================
                        // TAB 5: VACATION
                        // =====================================================
                        Tab::make('Vacation')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Section::make('Accrual Method')
                                    ->description('Choose how employees earn vacation time')
                                    ->schema([
                                        Select::make('vacation_accrual_method')
                                            ->label('Vacation Accrual Method')
                                            ->options([
                                                'calendar_year' => 'Calendar Year Front-Load',
                                                'pay_period' => 'Pay Period Accrual',
                                                'anniversary' => 'Anniversary Date (Step Blocks)',
                                            ])
                                            ->default('anniversary')
                                            ->required()
                                            ->live()
                                            ->helperText('Choose how employees accrue vacation time'),
                                    ]),

                                // Calendar Year Method
                                Section::make('Calendar Year Settings')
                                    ->schema([
                                        DatePicker::make('calendar_year_award_date')
                                            ->label('Annual Award Date')
                                            ->helperText('Date each year when vacation is awarded')
                                            ->default('2024-01-01'),

                                        Toggle::make('calendar_year_prorate_partial')
                                            ->label('Prorate Partial Year Employment')
                                            ->default(true)
                                            ->helperText('Prorate vacation for employees hired mid-year'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('vacation_accrual_method') === 'calendar_year')
                                    ->columns(2),

                                // Pay Period Method
                                Section::make('Pay Period Settings')
                                    ->schema([
                                        TextInput::make('pay_period_hours_per_period')
                                            ->label('Hours Per Pay Period')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->helperText('Vacation hours accrued each pay period'),

                                        Toggle::make('pay_period_accrue_immediately')
                                            ->label('Accrue Immediately')
                                            ->default(true)
                                            ->helperText('Start accruing from first pay period'),

                                        TextInput::make('pay_period_waiting_periods')
                                            ->label('Waiting Periods')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Pay periods to wait before starting accrual'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('vacation_accrual_method') === 'pay_period')
                                    ->columns(3),

                                // Anniversary Method
                                Section::make('Anniversary Settings')
                                    ->schema([
                                        Toggle::make('anniversary_first_year_waiting_period')
                                            ->label('First Year Waiting Period')
                                            ->default(true)
                                            ->helperText('Must wait until first anniversary'),

                                        Toggle::make('anniversary_award_on_anniversary')
                                            ->label('Award on Anniversary Date')
                                            ->default(true)
                                            ->helperText('Award full year vacation on anniversary'),

                                        TextInput::make('anniversary_max_days_cap')
                                            ->label('Maximum Days Cap')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Global max vacation days'),

                                        Toggle::make('anniversary_allow_partial_year')
                                            ->label('Allow Partial Year Accrual')
                                            ->default(false)
                                            ->helperText('Partial accrual in first year'),
                                    ])
                                    ->visible(fn (Get $get): bool => $get('vacation_accrual_method') === 'anniversary')
                                    ->columns(2),

                                // General Vacation Settings
                                Section::make('General Vacation Rules')
                                    ->schema([
                                        Toggle::make('allow_carryover')
                                            ->label('Allow Vacation Carryover')
                                            ->default(true)
                                            ->helperText('Allow unused vacation to carry over'),

                                        TextInput::make('max_carryover_hours')
                                            ->label('Max Carryover Hours')
                                            ->numeric()
                                            ->step(0.01)
                                            ->nullable()
                                            ->helperText('Maximum hours to carry over (empty = no limit)'),

                                        TextInput::make('max_accrual_balance')
                                            ->label('Max Accrual Balance')
                                            ->numeric()
                                            ->step(0.01)
                                            ->nullable()
                                            ->helperText('Maximum balance cap (empty = no limit)'),

                                        Toggle::make('prorate_new_hires')
                                            ->label('Prorate for New Hires')
                                            ->default(true)
                                            ->helperText('Prorate for employees hired mid-period'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 6: SYSTEM
                        // =====================================================
                        Tab::make('System')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('Developer Tools')
                                    ->description('Enable debugging tools for development and troubleshooting')
                                    ->schema([
                                        Toggle::make('debugbar_enabled')
                                            ->label('Enable Debugbar')
                                            ->default(false)
                                            ->helperText('Shows debug toolbar at bottom of page with queries, views, timing info. Disable in production for performance.'),

                                        Toggle::make('telescope_enabled')
                                            ->label('Enable Telescope')
                                            ->default(true)
                                            ->helperText('Dashboard for viewing exceptions, queries, jobs, and more. Access at /telescope'),
                                    ])
                                    ->columns(2),

                                Section::make('Logging Level')
                                    ->description('Configure system logging level for all components')
                                    ->schema([
                                        Select::make('logging_level')
                                            ->label('System Logging Level')
                                            ->options([
                                                'none' => 'None - No logging',
                                                'error' => 'Error - Only errors',
                                                'warning' => 'Warning - Errors and warnings',
                                                'info' => 'Info - General information',
                                                'debug' => 'Debug - Detailed debugging',
                                            ])
                                            ->default('warning')
                                            ->required()
                                            ->helperText('Controls verbosity of application logging'),
                                    ]),

                                Section::make('Integration Sync Logs')
                                    ->description('Configure retention and detail level for API integration logs')
                                    ->schema([
                                        TextInput::make('log_retention_days')
                                            ->label('Log Retention (Days)')
                                            ->numeric()
                                            ->default(30)
                                            ->minValue(1)
                                            ->maxValue(365)
                                            ->required()
                                            ->helperText('Number of days to keep integration sync logs before auto-purge'),

                                        Toggle::make('log_request_payloads')
                                            ->label('Log Request Payloads')
                                            ->default(true)
                                            ->helperText('Store full API request data in logs (useful for debugging)'),

                                        Toggle::make('log_response_data')
                                            ->label('Log Response Data')
                                            ->default(true)
                                            ->helperText('Store API response summaries in logs'),
                                    ])
                                    ->columns(3),

                                Section::make('View Logs')
                                    ->schema([
                                        Placeholder::make('view_logs_link')
                                            ->content('View integration sync logs in the Logs menu to see API call history, errors, and sync statistics.')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payrollFrequency.frequency_name')
                    ->label('Payroll Frequency')
                    ->sortable()
                    ->placeholder('Not set'),

                TextColumn::make('attendance_flexibility_minutes')
                    ->label('Flex (min)')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                IconColumn::make('smtp_enabled')
                    ->label('SMTP')
                    ->boolean(),

                TextColumn::make('device_offline_threshold_minutes')
                    ->label('Offline Alert (min)')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('vacation_accrual_method')
                    ->label('Vacation Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'calendar_year' => 'info',
                        'pay_period' => 'warning',
                        'anniversary' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('clock_event_sync_frequency')
                    ->label('Clock Sync')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'real_time' => 'success',
                        'every_minute', 'every_5_minutes' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySetups::route('/'),
            'edit' => EditCompanySetup::route('/{record}/edit'),
        ];
    }
}
