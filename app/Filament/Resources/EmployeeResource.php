<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\IntegrationConnection;
use App\Models\ShiftSchedule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    // Navigation Configuration
    protected static string|\UnitEnum|null $navigationGroup = 'Employee Management';

    protected static ?string $navigationLabel = 'Employees';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Employee')
                ->tabs([
                    // =====================================================
                    // TAB 1: PERSONAL INFORMATION
                    // =====================================================
                    Tab::make('Personal')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Section::make('Basic Information')
                                ->description('Employee name and contact details')
                                ->schema([
                                    TextInput::make('first_name')
                                        ->label('First Name')
                                        ->required(),
                                    TextInput::make('last_name')
                                        ->label('Last Name')
                                        ->required(),
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email()
                                        ->nullable(),
                                    TextInput::make('phone')
                                        ->label('Phone')
                                        ->tel()
                                        ->nullable(),
                                    DatePicker::make('birth_date')
                                        ->label('Date of Birth')
                                        ->nullable(),
                                    TextInput::make('external_id')
                                        ->label('External ID / Payroll ID')
                                        ->nullable()
                                        ->helperText('External system identifier'),
                                ])
                                ->columns(3),

                            Section::make('Address')
                                ->description('Employee mailing address')
                                ->schema([
                                    TextInput::make('address')
                                        ->label('Street Address')
                                        ->nullable()
                                        ->columnSpan(2),
                                    TextInput::make('address2')
                                        ->label('Address Line 2')
                                        ->nullable()
                                        ->helperText('Apt, suite, unit, etc.'),
                                    TextInput::make('city')
                                        ->label('City')
                                        ->nullable(),
                                    TextInput::make('state')
                                        ->label('State')
                                        ->nullable(),
                                    TextInput::make('zip')
                                        ->label('ZIP Code')
                                        ->nullable(),
                                    TextInput::make('country')
                                        ->label('Country')
                                        ->nullable(),
                                ])
                                ->columns(3)
                                ->collapsed(),

                            Section::make('Emergency Contact')
                                ->description('Emergency contact information')
                                ->schema([
                                    TextInput::make('emergency_contact')
                                        ->label('Contact Name')
                                        ->nullable(),
                                    TextInput::make('emergency_phone')
                                        ->label('Contact Phone')
                                        ->tel()
                                        ->nullable(),
                                ])
                                ->columns(2)
                                ->collapsed(),

                            Section::make('Notes')
                                ->schema([
                                    Textarea::make('notes')
                                        ->label('Employee Notes')
                                        ->nullable()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->collapsed(),
                        ]),

                    // =====================================================
                    // TAB 2: EMPLOYMENT
                    // =====================================================
                    Tab::make('Employment')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Section::make('Employment Dates')
                                ->description('Key dates for employment history')
                                ->schema([
                                    DatePicker::make('date_of_hire')
                                        ->label('Date of Hire')
                                        ->nullable()
                                        ->helperText('Original hire date'),
                                    DatePicker::make('seniority_date')
                                        ->label('Seniority Date')
                                        ->nullable()
                                        ->helperText('Date used for vacation/benefits calculations'),
                                    DatePicker::make('termination_date')
                                        ->label('Termination Date')
                                        ->nullable()
                                        ->helperText('Leave empty if currently employed'),
                                ])
                                ->columns(3),

                            Section::make('Department & Schedule')
                                ->description('Organizational assignment and work schedule')
                                ->schema([
                                    Select::make('department_id')
                                        ->label('Department')
                                        ->relationship('department', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),
                                    Select::make('shift_schedule_id')
                                        ->label('Shift Schedule')
                                        ->options(ShiftSchedule::with(['shift'])
                                            ->get()
                                            ->mapWithKeys(function ($schedule) {
                                                $shiftName = $schedule->shift ? $schedule->shift->shift_name : 'No Shift';

                                                return [$schedule->id => "{$schedule->schedule_name} ({$shiftName})"];
                                            })
                                            ->toArray())
                                        ->searchable()
                                        ->nullable(),
                                ])
                                ->columns(2),

                            Section::make('Employment Status')
                                ->schema([
                                    Toggle::make('is_active')
                                        ->label('Active Employee')
                                        ->default(true)
                                        ->helperText('Inactive employees cannot clock in'),
                                    Toggle::make('full_time')
                                        ->label('Full Time')
                                        ->default(false)
                                        ->helperText('Full-time vs part-time classification'),
                                ])
                                ->columns(2),
                        ]),

                    // =====================================================
                    // TAB 3: PAY & OVERTIME
                    // =====================================================
                    Tab::make('Pay & Overtime')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Section::make('Pay Information')
                                ->description('Compensation type and rate')
                                ->schema([
                                    Select::make('pay_type')
                                        ->label('Pay Type')
                                        ->options([
                                            'hourly' => 'Hourly',
                                            'salary' => 'Salary',
                                            'contract' => 'Contract',
                                        ])
                                        ->default('hourly')
                                        ->required(),
                                    TextInput::make('pay_rate')
                                        ->label('Pay Rate')
                                        ->numeric()
                                        ->prefix('$')
                                        ->step(0.01)
                                        ->nullable()
                                        ->helperText('Hourly rate or salary amount'),
                                ])
                                ->columns(2),

                            Section::make('Overtime Settings')
                                ->description('Configure overtime calculation rules')
                                ->schema([
                                    Toggle::make('overtime_exempt')
                                        ->label('Overtime Exempt')
                                        ->default(false)
                                        ->helperText('Exempt employees do not receive overtime pay')
                                        ->columnSpanFull(),
                                    TextInput::make('overtime_rate')
                                        ->label('Overtime Rate Multiplier')
                                        ->numeric()
                                        ->step(0.001)
                                        ->default(1.500)
                                        ->helperText('e.g., 1.5 = time and a half'),
                                    TextInput::make('double_time_threshold')
                                        ->label('Double Time Threshold (Hours)')
                                        ->numeric()
                                        ->step(0.01)
                                        ->nullable()
                                        ->helperText('Hours after which double time applies'),
                                ])
                                ->columns(2),

                            Section::make('Payroll Rounding')
                                ->description('Time rounding rules for this employee')
                                ->schema([
                                    Select::make('round_group_id')
                                        ->label('Rounding Group')
                                        ->relationship('roundGroup', 'group_name')
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->helperText('Leave empty to use company default'),
                                ]),

                            Section::make('Payroll Provider')
                                ->description('Payroll export destination for this employee')
                                ->schema([
                                    Select::make('payroll_provider_id')
                                        ->label('Payroll Provider')
                                        ->options(fn () => IntegrationConnection::where('is_payroll_provider', true)
                                            ->pluck('name', 'id'))
                                        ->searchable()
                                        ->nullable()
                                        ->helperText('Select which payroll system to export this employee\'s time to'),
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
                TextColumn::make('full_names')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('shiftSchedule.schedule_name')
                    ->label('Schedule')
                    ->sortable(),
                TextColumn::make('shift.shift_name') // Through relationship
                    ->label('Shift')
                    ->sortable(),
                TextColumn::make('roundGroup.group_name') // Ensure the relationship is defined in the Employee model
                    ->label('Payroll Rounding')
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('Payroll ID')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
