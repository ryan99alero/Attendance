<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Tables;
use App\Models\CompanySetup;
use App\Filament\Resources\CompanySetupResource\Pages;

class CompanySetupResource extends Resource
{
    protected static ?string $model = CompanySetup::class;

    protected static ?string $navigationLabel = 'Company Setup';
    protected static ?string $navigationGroup = 'System & Hardware';
    protected static ?string $slug = 'company-setup';
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?int $navigationSort = 20;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('id')
                    ->disabled()
                    ->dehydrated(false),

                Tabs::make('Company Setup')
                    ->tabs([
                        // =====================================================
                        // TAB 1: COMPANY & PAYROLL
                        // =====================================================
                        Tabs\Tab::make('Company & Payroll')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Section::make('Payroll Settings')
                                    ->description('Configure company-wide payroll and attendance settings')
                                    ->schema([
                                        Forms\Components\Select::make('payroll_frequency_id')
                                            ->label('Payroll Frequency')
                                            ->relationship('payrollFrequency', 'frequency_name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('All employees will follow this payroll schedule'),

                                        Forms\Components\TextInput::make('attendance_flexibility_minutes')
                                            ->numeric()
                                            ->default(30)
                                            ->required()
                                            ->label('Attendance Flexibility (Minutes)')
                                            ->helperText('Minutes allowed before/after shift for attendance matching'),

                                        Forms\Components\TextInput::make('max_shift_length')
                                            ->numeric()
                                            ->default(12)
                                            ->required()
                                            ->label('Max Shift Length (Hours)')
                                            ->helperText('Maximum hours in a shift before requiring approval'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Attendance Rules')
                                    ->schema([
                                        Forms\Components\Toggle::make('enforce_shift_schedules')
                                            ->default(true)
                                            ->label('Enforce Shift Schedules')
                                            ->helperText('Require employees to follow assigned shift times'),

                                        Forms\Components\Toggle::make('allow_manual_time_edits')
                                            ->default(true)
                                            ->label('Allow Manual Time Edits')
                                            ->helperText('Allow administrators to manually edit time records'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 2: TIME & ATTENDANCE
                        // =====================================================
                        Tabs\Tab::make('Time & Attendance')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\Section::make('Punch Processing Engine')
                                    ->description('Configure how punches are classified and processed')
                                    ->schema([
                                        Forms\Components\Select::make('debug_punch_assignment_mode')
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

                                        Forms\Components\TextInput::make('heuristic_min_punch_gap')
                                            ->label('Minimum Punch Gap (Hours)')
                                            ->numeric()
                                            ->default(6)
                                            ->required()
                                            ->helperText('Minimum hours between punches for auto Clock In/Out'),

                                        Forms\Components\Toggle::make('use_ml_for_punch_matching')
                                            ->label('Use ML for Punch Matching')
                                            ->default(true)
                                            ->helperText('Enable machine learning for punch classification'),

                                        Forms\Components\Toggle::make('auto_adjust_punches')
                                            ->label('Auto Adjust Punches')
                                            ->default(false)
                                            ->helperText('Automatically adjust punch types for incomplete records'),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Clock Event Sync')
                                    ->description('Control how clock events are processed into attendance records')
                                    ->schema([
                                        Forms\Components\Select::make('clock_event_sync_frequency')
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

                                        Forms\Components\TextInput::make('clock_event_batch_size')
                                            ->label('Batch Size')
                                            ->numeric()
                                            ->default(100)
                                            ->required()
                                            ->helperText('Number of clock events per batch'),

                                        Forms\Components\Toggle::make('clock_event_auto_retry_failed')
                                            ->label('Auto-retry Failed Events')
                                            ->default(true)
                                            ->helperText('Automatically retry events that failed processing'),

                                        Forms\Components\TimePicker::make('clock_event_daily_sync_time')
                                            ->label('Daily Sync Time')
                                            ->default('06:00:00')
                                            ->helperText('Time of day for daily sync')
                                            ->visible(fn (Forms\Get $get): bool => in_array($get('clock_event_sync_frequency'), ['daily', 'twice_daily'])),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 3: DEVICES
                        // =====================================================
                        Tabs\Tab::make('Devices')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema([
                                Forms\Components\Section::make('Device Polling')
                                    ->description('Configure how often devices check for updates')
                                    ->schema([
                                        Forms\Components\TextInput::make('config_poll_interval_minutes')
                                            ->label('Configuration Poll Interval (Minutes)')
                                            ->numeric()
                                            ->default(5)
                                            ->required()
                                            ->helperText('How often devices check for configuration updates'),

                                        Forms\Components\TextInput::make('firmware_check_interval_hours')
                                            ->label('Firmware Check Interval (Hours)')
                                            ->numeric()
                                            ->default(24)
                                            ->required()
                                            ->helperText('How often devices check for firmware updates'),

                                        Forms\Components\Toggle::make('allow_device_poll_override')
                                            ->label('Allow Device Poll Override')
                                            ->default(false)
                                            ->helperText('Allow individual devices to override company polling settings'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Device Offline Alerts')
                                    ->description('Get notified when time clocks go offline')
                                    ->schema([
                                        Forms\Components\TextInput::make('device_alert_email')
                                            ->label('Alert Email Address')
                                            ->email()
                                            ->nullable()
                                            ->helperText('Primary email for device offline/online alerts (also sends to department manager)'),

                                        Forms\Components\TextInput::make('device_offline_threshold_minutes')
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
                        Tabs\Tab::make('Email / SMTP')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Forms\Components\Section::make('SMTP Server Configuration')
                                    ->description('Configure outgoing email server settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('smtp_enabled')
                                            ->label('Enable SMTP')
                                            ->default(false)
                                            ->helperText('Enable to send emails via SMTP instead of system default')
                                            ->live()
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('smtp_host')
                                                    ->label('SMTP Host')
                                                    ->placeholder('smtp.example.com')
                                                    ->helperText('Mail server hostname or IP address')
                                                    ->required(fn (Forms\Get $get): bool => $get('smtp_enabled')),

                                                Forms\Components\TextInput::make('smtp_port')
                                                    ->label('SMTP Port')
                                                    ->numeric()
                                                    ->default(587)
                                                    ->helperText('Common ports: 25, 465 (SSL), 587 (TLS)')
                                                    ->required(fn (Forms\Get $get): bool => $get('smtp_enabled')),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('smtp_username')
                                                    ->label('SMTP Username')
                                                    ->placeholder('user@example.com')
                                                    ->helperText('Authentication username (often your email address)'),

                                                Forms\Components\TextInput::make('smtp_password')
                                                    ->label('SMTP Password')
                                                    ->password()
                                                    ->revealable()
                                                    ->helperText('Authentication password (stored encrypted)'),
                                            ]),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Select::make('smtp_encryption')
                                                    ->label('Encryption')
                                                    ->options([
                                                        'none' => 'None',
                                                        'tls' => 'TLS (Recommended)',
                                                        'ssl' => 'SSL',
                                                    ])
                                                    ->default('tls')
                                                    ->helperText('TLS on port 587 is recommended'),

                                                Forms\Components\TextInput::make('smtp_timeout')
                                                    ->label('Timeout (Seconds)')
                                                    ->numeric()
                                                    ->default(30)
                                                    ->helperText('Connection timeout'),

                                                Forms\Components\Toggle::make('smtp_verify_peer')
                                                    ->label('Verify SSL Certificate')
                                                    ->default(true)
                                                    ->helperText('Disable only for self-signed certs'),
                                            ]),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => true),

                                Forms\Components\Section::make('Sender Information')
                                    ->description('Configure the "From" address for outgoing emails')
                                    ->schema([
                                        Forms\Components\TextInput::make('smtp_from_address')
                                            ->label('From Email Address')
                                            ->email()
                                            ->placeholder('noreply@yourcompany.com')
                                            ->helperText('The email address that appears in the "From" field')
                                            ->required(fn (Forms\Get $get): bool => $get('smtp_enabled')),

                                        Forms\Components\TextInput::make('smtp_from_name')
                                            ->label('From Name')
                                            ->placeholder('Attend Time Clock System')
                                            ->helperText('The name that appears in the "From" field'),

                                        Forms\Components\TextInput::make('smtp_reply_to')
                                            ->label('Reply-To Address')
                                            ->email()
                                            ->placeholder('support@yourcompany.com')
                                            ->helperText('Optional: Where replies should be sent (if different from "From")'),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('Test Email')
                                    ->description('Send a test email to verify your configuration')
                                    ->schema([
                                        Forms\Components\Placeholder::make('test_email_info')
                                            ->content('Save your settings first, then use the "Send Test Email" action to verify your SMTP configuration works correctly.')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsed(),
                            ]),

                        // =====================================================
                        // TAB 5: VACATION
                        // =====================================================
                        Tabs\Tab::make('Vacation')
                            ->icon('heroicon-o-sun')
                            ->schema([
                                Forms\Components\Section::make('Accrual Method')
                                    ->description('Choose how employees earn vacation time')
                                    ->schema([
                                        Forms\Components\Select::make('vacation_accrual_method')
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
                                Forms\Components\Section::make('Calendar Year Settings')
                                    ->schema([
                                        Forms\Components\DatePicker::make('calendar_year_award_date')
                                            ->label('Annual Award Date')
                                            ->helperText('Date each year when vacation is awarded')
                                            ->default('2024-01-01'),

                                        Forms\Components\Toggle::make('calendar_year_prorate_partial')
                                            ->label('Prorate Partial Year Employment')
                                            ->default(true)
                                            ->helperText('Prorate vacation for employees hired mid-year'),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'calendar_year')
                                    ->columns(2),

                                // Pay Period Method
                                Forms\Components\Section::make('Pay Period Settings')
                                    ->schema([
                                        Forms\Components\TextInput::make('pay_period_hours_per_period')
                                            ->label('Hours Per Pay Period')
                                            ->numeric()
                                            ->step(0.0001)
                                            ->helperText('Vacation hours accrued each pay period'),

                                        Forms\Components\Toggle::make('pay_period_accrue_immediately')
                                            ->label('Accrue Immediately')
                                            ->default(true)
                                            ->helperText('Start accruing from first pay period'),

                                        Forms\Components\TextInput::make('pay_period_waiting_periods')
                                            ->label('Waiting Periods')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Pay periods to wait before starting accrual'),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'pay_period')
                                    ->columns(3),

                                // Anniversary Method
                                Forms\Components\Section::make('Anniversary Settings')
                                    ->schema([
                                        Forms\Components\Toggle::make('anniversary_first_year_waiting_period')
                                            ->label('First Year Waiting Period')
                                            ->default(true)
                                            ->helperText('Must wait until first anniversary'),

                                        Forms\Components\Toggle::make('anniversary_award_on_anniversary')
                                            ->label('Award on Anniversary Date')
                                            ->default(true)
                                            ->helperText('Award full year vacation on anniversary'),

                                        Forms\Components\TextInput::make('anniversary_max_days_cap')
                                            ->label('Maximum Days Cap')
                                            ->numeric()
                                            ->nullable()
                                            ->helperText('Global max vacation days'),

                                        Forms\Components\Toggle::make('anniversary_allow_partial_year')
                                            ->label('Allow Partial Year Accrual')
                                            ->default(false)
                                            ->helperText('Partial accrual in first year'),
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'anniversary')
                                    ->columns(2),

                                // General Vacation Settings
                                Forms\Components\Section::make('General Vacation Rules')
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_carryover')
                                            ->label('Allow Vacation Carryover')
                                            ->default(true)
                                            ->helperText('Allow unused vacation to carry over'),

                                        Forms\Components\TextInput::make('max_carryover_hours')
                                            ->label('Max Carryover Hours')
                                            ->numeric()
                                            ->step(0.01)
                                            ->nullable()
                                            ->helperText('Maximum hours to carry over (empty = no limit)'),

                                        Forms\Components\TextInput::make('max_accrual_balance')
                                            ->label('Max Accrual Balance')
                                            ->numeric()
                                            ->step(0.01)
                                            ->nullable()
                                            ->helperText('Maximum balance cap (empty = no limit)'),

                                        Forms\Components\Toggle::make('prorate_new_hires')
                                            ->label('Prorate for New Hires')
                                            ->default(true)
                                            ->helperText('Prorate for employees hired mid-period'),
                                    ])
                                    ->columns(2),
                            ]),

                        // =====================================================
                        // TAB 6: SYSTEM
                        // =====================================================
                        Tabs\Tab::make('System')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Logging')
                                    ->description('Configure system logging level')
                                    ->schema([
                                        Forms\Components\Select::make('logging_level')
                                            ->label('System Logging Level')
                                            ->options([
                                                'none' => 'None',
                                                'error' => 'Error',
                                                'warning' => 'Warning',
                                                'info' => 'Info',
                                                'debug' => 'Debug',
                                            ])
                                            ->default('error')
                                            ->required()
                                            ->helperText('Level of system logging detail'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payrollFrequency.frequency_name')
                    ->label('Payroll Frequency')
                    ->sortable()
                    ->placeholder('Not set'),

                Tables\Columns\TextColumn::make('attendance_flexibility_minutes')
                    ->label('Flex (min)')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('smtp_enabled')
                    ->label('SMTP')
                    ->boolean(),

                Tables\Columns\TextColumn::make('device_offline_threshold_minutes')
                    ->label('Offline Alert (min)')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('vacation_accrual_method')
                    ->label('Vacation Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'calendar_year' => 'info',
                        'pay_period' => 'warning',
                        'anniversary' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('clock_event_sync_frequency')
                    ->label('Clock Sync')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'real_time' => 'success',
                        'every_minute', 'every_5_minutes' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanySetups::route('/'),
            'edit' => Pages\EditCompanySetup::route('/{record}/edit'),
        ];
    }
}
