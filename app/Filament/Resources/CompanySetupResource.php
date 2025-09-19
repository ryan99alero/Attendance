<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use App\Models\CompanySetup;
use App\Filament\Resources\CompanySetupResource\Pages;
use Filament\Tables\Filters\TrashedFilter;

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
                // Hidden field for ID (not editable or included in form submission)
                Forms\Components\Hidden::make('id')
                    ->disabled()
                    ->dehydrated(false),

                // Number of minutes allowed before/after a shift for attendance matching
                Forms\Components\TextInput::make('attendance_flexibility_minutes')
                    ->numeric()
                    ->default(30)
                    ->required()
                    ->label('Attendance Flexibility (Minutes)'),

                // Defines the level of logging in the system (None, Error, Warning, Info, Debug)
                Forms\Components\Select::make('logging_level')
                    ->options([
                        'none' => 'None',      // No logging
                        'error' => 'Error',    // Only critical errors
                        'warning' => 'Warning',// Errors + potential issues
                        'info' => 'Info',      // General system events
                        'debug' => 'Debug',    // Most detailed logs for troubleshooting
                    ])
                    ->default('error')
                    ->required()
                    ->label('Logging Level'),
                            // Temporary Debug Mode in  for Development (None, Error, Warning, Info, Debug)
                Forms\Components\Select::make('debug_punch_assignment_mode')
                    ->options([
                        'heuristic' => 'Heuristic Only',
                        'ml' => 'Machine Learning Only',
                        'consensus' => 'Consensus (Both Engines)',
                        'all' => 'All Engines',
                    ])
                    ->default('all')
                    ->required()
                    ->label('PunchType Debug Mode'),

                // Whether to automatically adjust punch types for incomplete records
                Forms\Components\Toggle::make('auto_adjust_punches')
                    ->default(false)
                    ->label('Auto Adjust Punches'),

                // Defines the minimum hours required between two punches for auto-assigning Clock In/Out instead of Needs Review.
                Forms\Components\TextInput::make('heuristic_min_punch_gap')
                    ->numeric()
                    ->default(6)
                    ->required()
                    ->label('heuristic Min Punch Gap'),

                // Enable ML-based punch classification for better accuracy in punch assignments
                Forms\Components\Toggle::make('use_ml_for_punch_matching')
                    ->default(true)
                    ->label('Use ML for Punch Matching'),

                // If enabled, employees must adhere to assigned shift schedules
                Forms\Components\Toggle::make('enforce_shift_schedules')
                    ->default(true)
                    ->label('Enforce Shift Schedules'),

                // Allow admins to manually edit time records (If disabled, all changes must be system-generated)
                Forms\Components\Toggle::make('allow_manual_time_edits')
                    ->default(true)
                    ->label('Allow Manual Time Edits'),

                // Maximum shift length (in hours) before requiring admin approval
                Forms\Components\TextInput::make('max_shift_length')
                    ->numeric()
                    ->default(12)
                    ->required()
                    ->label('Max Shift Length (Hours)'),

                // Clock Event Processing Section
                Forms\Components\Section::make('Clock Event Processing Settings')
                    ->schema([
                        // How frequently to sync clock events to attendance records
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

                        // Number of events to process per batch
                        Forms\Components\TextInput::make('clock_event_batch_size')
                            ->label('Batch Size')
                            ->numeric()
                            ->default(100)
                            ->required()
                            ->helperText('Number of clock events to process in each batch'),

                        // Auto-retry failed processing
                        Forms\Components\Toggle::make('clock_event_auto_retry_failed')
                            ->label('Auto-retry Failed Events')
                            ->default(true)
                            ->helperText('Automatically retry events that failed processing'),

                        // Time for daily sync
                        Forms\Components\TimePicker::make('clock_event_daily_sync_time')
                            ->label('Daily Sync Time')
                            ->default('06:00:00')
                            ->helperText('Time of day for daily sync (when using daily frequency)')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('clock_event_sync_frequency'), ['daily', 'twice_daily'])),
                    ])
                    ->columns(2),

                // Company-wide payroll frequency
                Forms\Components\Select::make('payroll_frequency_id')
                    ->label('Payroll Frequency')
                    ->relationship('payrollFrequency', 'frequency_name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('All employees will follow this payroll schedule'),

                // Vacation Configuration Section
                Forms\Components\Section::make('Vacation Configuration')
                    ->schema([
                        // Primary vacation accrual method
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

                        // Calendar Year Method Fields
                        Forms\Components\Section::make('Calendar Year Settings')
                            ->schema([
                                Forms\Components\DatePicker::make('calendar_year_award_date')
                                    ->label('Annual Award Date')
                                    ->helperText('Date each year when vacation is awarded (e.g., January 1st)')
                                    ->default('2024-01-01'),

                                Forms\Components\Toggle::make('calendar_year_prorate_partial')
                                    ->label('Prorate Partial Year Employment')
                                    ->default(true)
                                    ->helperText('Prorate vacation for employees hired mid-year'),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'calendar_year')
                            ->columns(2),

                        // Pay Period Method Fields
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
                                    ->helperText('Start accruing vacation from the first pay period'),

                                Forms\Components\TextInput::make('pay_period_waiting_periods')
                                    ->label('Waiting Periods')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Number of pay periods to wait before starting accrual'),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'pay_period')
                            ->columns(3),

                        // Anniversary Method Fields
                        Forms\Components\Section::make('Anniversary Settings')
                            ->schema([
                                Forms\Components\Toggle::make('anniversary_first_year_waiting_period')
                                    ->label('First Year Waiting Period')
                                    ->default(true)
                                    ->helperText('Employees must wait until their first anniversary to receive vacation'),

                                Forms\Components\Toggle::make('anniversary_award_on_anniversary')
                                    ->label('Award on Anniversary Date')
                                    ->default(true)
                                    ->helperText('Award full year vacation on anniversary date'),

                                Forms\Components\TextInput::make('anniversary_max_days_cap')
                                    ->label('Maximum Days Cap')
                                    ->numeric()
                                    ->nullable()
                                    ->helperText('Global maximum vacation days (leave empty to use policy-based caps)'),

                                Forms\Components\Toggle::make('anniversary_allow_partial_year')
                                    ->label('Allow Partial Year Accrual')
                                    ->default(false)
                                    ->helperText('Allow partial vacation accrual in the first year of employment'),
                            ])
                            ->visible(fn (Forms\Get $get): bool => $get('vacation_accrual_method') === 'anniversary')
                            ->columns(2),

                        // General Vacation Settings (visible for all methods)
                        Forms\Components\Section::make('General Vacation Settings')
                            ->schema([
                                Forms\Components\Toggle::make('allow_carryover')
                                    ->label('Allow Vacation Carryover')
                                    ->default(true)
                                    ->helperText('Allow employees to carry over unused vacation to the next period'),

                                Forms\Components\TextInput::make('max_carryover_hours')
                                    ->label('Max Carryover Hours')
                                    ->numeric()
                                    ->step(0.01)
                                    ->nullable()
                                    ->helperText('Maximum vacation hours that can be carried over (leave empty for no limit)'),

                                Forms\Components\TextInput::make('max_accrual_balance')
                                    ->label('Max Accrual Balance')
                                    ->numeric()
                                    ->step(0.01)
                                    ->nullable()
                                    ->helperText('Maximum vacation balance cap in hours (leave empty for no limit)'),

                                Forms\Components\Toggle::make('prorate_new_hires')
                                    ->label('Prorate for New Hires')
                                    ->default(true)
                                    ->helperText('Prorate vacation accrual for employees hired mid-period'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attendance_flexibility_minutes')->sortable()
                    ->label('Attendance Flexibility (Minutes)'),

                Tables\Columns\TextColumn::make('logging_level')->sortable()
                    ->label('Logging Level'),

                Tables\Columns\TextColumn::make('debug_punch_assignment_mode')->sortable()
                    ->label('debug punch assignment mode'),

                Tables\Columns\IconColumn::make('auto_adjust_punches')->boolean()
                    ->label('Auto Adjust Punches'),

                Tables\Columns\TextColumn::make('heuristic_min_punch_gap')->sortable()
                    ->label('Heuristic Min Punch Gap'),

                Tables\Columns\IconColumn::make('use_ml_for_punch_matching')->boolean()
                    ->label('Use ML for Punch Matching'),

                Tables\Columns\IconColumn::make('enforce_shift_schedules')->boolean()
                    ->label('Enforce Shift Schedules'),

                Tables\Columns\IconColumn::make('allow_manual_time_edits')->boolean()
                    ->label('Allow Manual Time Edits'),

                Tables\Columns\TextColumn::make('max_shift_length')->sortable()
                    ->label('Max Shift Length (Hours)'),

                Tables\Columns\TextColumn::make('payrollFrequency.frequency_name')
                    ->label('Payroll Frequency')
                    ->sortable(),

                // Clock Event Processing
                Tables\Columns\TextColumn::make('clock_event_sync_frequency')
                    ->label('Clock Event Sync')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'real_time' => 'success',
                        'every_minute', 'every_5_minutes' => 'info',
                        'every_15_minutes', 'every_30_minutes', 'hourly' => 'warning',
                        'daily', 'twice_daily' => 'gray',
                        'manual_only' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('clock_event_batch_size')
                    ->label('Batch Size')
                    ->numeric(),

                // Vacation Configuration
                Tables\Columns\TextColumn::make('vacation_accrual_method')
                    ->label('Vacation Accrual Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'calendar_year' => 'info',
                        'pay_period' => 'warning',
                        'anniversary' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('allow_carryover')
                    ->label('Allow Carryover')
                    ->boolean(),

                Tables\Columns\TextColumn::make('max_carryover_hours')
                    ->label('Max Carryover Hours')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('No limit'),

                Tables\Columns\TextColumn::make('created_at')->dateTime()
                    ->label('Created At'),

                Tables\Columns\TextColumn::make('updated_at')->dateTime()
                    ->label('Updated At'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]); // No bulk actions added yet
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanySetups::route('/'),
            'edit' => Pages\EditCompanySetup::route('/{record}/edit'),
        ];
    }
}
