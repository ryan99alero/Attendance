<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayTemplateResource\Pages\CreateHolidayTemplate;
use App\Filament\Resources\HolidayTemplateResource\Pages\EditHolidayTemplate;
use App\Filament\Resources\HolidayTemplateResource\Pages\ListHolidayTemplates;
use App\Models\HolidayTemplate;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HolidayTemplateResource extends Resource
{
    protected static ?string $model = HolidayTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Holidays';

    protected static string|\UnitEnum|null $navigationGroup = 'Time Off Management';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Holiday Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Christmas Day, New Year\'s Day'),

                        Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->placeholder('Brief description of the holiday')
                            ->helperText('Optional description explaining the holiday'),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable/disable this holiday template'),

                                Toggle::make('applies_to_all_employees')
                                    ->label('All Eligible Employees')
                                    ->default(true)
                                    ->helperText('Apply to all holiday-eligible employees (salary + full-time hourly)'),

                                TextInput::make('auto_create_days_ahead')
                                    ->label('Auto-Create Days Ahead')
                                    ->numeric()
                                    ->default(365)
                                    ->suffix('days')
                                    ->helperText('How many days in advance to auto-create holidays'),
                            ]),
                    ]),

                Section::make('Employee Eligibility')
                    ->description('Define which employees are eligible for this holiday')
                    ->schema([
                        CheckboxList::make('eligible_pay_types')
                            ->label('Eligible Pay Types')
                            ->options([
                                'salary' => 'Salary Employees',
                                'hourly_fulltime' => 'Full-Time Hourly Employees',
                                'hourly_parttime' => 'Part-Time Hourly Employees',
                                'contract' => 'Contract Employees',
                            ])
                            ->default(['salary', 'hourly_fulltime'])
                            ->descriptions([
                                'salary' => 'Salaried employees (typically eligible)',
                                'hourly_fulltime' => 'Full-time hourly employees (typically eligible)',
                                'hourly_parttime' => 'Part-time hourly employees (typically not eligible - temps)',
                                'contract' => 'Contract employees (typically not eligible)',
                            ])
                            ->columns(2)
                            ->helperText('Select which employee types should receive this holiday'),

                        Placeholder::make('eligibility_note')
                            ->label('Current Setup')
                            ->content('Based on your employee data: Salary employees and Full-Time Hourly employees are typically eligible for holiday pay, while Part-Time Hourly (temps) and Contract employees are not.')
                            ->extraAttributes(['class' => 'text-sm text-gray-600']),
                    ]),

                Section::make('Date Calculation')
                    ->schema([
                        Select::make('type')
                            ->label('Holiday Type')
                            ->options([
                                'fixed_date' => 'Fixed Date (e.g., Christmas - Dec 25)',
                                'relative' => 'Relative Date (e.g., Thanksgiving - 4th Thursday in November)',
                                'custom' => 'Custom Calculation (e.g., Easter-based)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('calculation_rule', null)),

                        // Fixed Date Configuration
                        Group::make([
                            Select::make('calculation_rule.month')
                                ->label('Month')
                                ->options([
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                                ])
                                ->required(),

                            Select::make('calculation_rule.day')
                                ->label('Day')
                                ->options(array_combine(range(1, 31), range(1, 31)))
                                ->required(),
                        ])
                            ->columns(2)
                            ->visible(fn (Get $get) => $get('type') === 'fixed_date'),

                        // Relative Date Configuration
                        Group::make([
                            Select::make('calculation_rule.month')
                                ->label('Month')
                                ->options([
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                                ])
                                ->required(),

                            Select::make('calculation_rule.day_of_week')
                                ->label('Day of Week')
                                ->options([
                                    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                                    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
                                ])
                                ->required(),

                            Select::make('calculation_rule.occurrence')
                                ->label('Occurrence')
                                ->options([
                                    1 => '1st (First)', 2 => '2nd (Second)', 3 => '3rd (Third)',
                                    4 => '4th (Fourth)', 5 => '5th (Fifth)', -1 => 'Last',
                                ])
                                ->required()
                                ->helperText('e.g., "4th" Thursday for Thanksgiving'),
                        ])
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('type') === 'relative'),

                        // Custom Date Configuration
                        Group::make([
                            Select::make('calculation_rule.base')
                                ->label('Base Holiday')
                                ->options([
                                    'easter' => 'Easter Sunday',
                                ])
                                ->required(),

                            TextInput::make('calculation_rule.offset_days')
                                ->label('Offset Days')
                                ->numeric()
                                ->default(0)
                                ->helperText('Days before (-) or after (+) the base holiday'),
                        ])
                            ->columns(2)
                            ->visible(fn (Get $get) => $get('type') === 'custom'),
                    ]),

                Section::make('Holiday Pay & Overtime')
                    ->description('Configure how employees are paid for this holiday')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('holiday_multiplier')
                                    ->label('Holiday Pay Multiplier')
                                    ->numeric()
                                    ->default(2.00)
                                    ->step(0.01)
                                    ->suffix('x')
                                    ->helperText('Pay multiplier for hours worked on this holiday (e.g., 2.0 for double-time)'),

                                TextInput::make('standard_holiday_hours')
                                    ->label('Standard Holiday Hours')
                                    ->numeric()
                                    ->default(8.00)
                                    ->step(0.5)
                                    ->suffix('hours')
                                    ->helperText('Hours credited for this holiday when not worked'),

                                Toggle::make('paid_if_not_worked')
                                    ->label('Paid If Not Worked')
                                    ->default(true)
                                    ->helperText('Employees receive holiday pay even if they do not work'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('require_day_before')
                                    ->label('Require Day Before Worked')
                                    ->default(false)
                                    ->helperText('Employee must work the scheduled day before the holiday to qualify for holiday pay'),

                                Toggle::make('require_day_after')
                                    ->label('Require Day After Worked')
                                    ->default(false)
                                    ->helperText('Employee must work the scheduled day after the holiday to qualify for holiday pay'),
                            ]),

                        Placeholder::make('qualification_note')
                            ->label('Qualification Requirements')
                            ->content(function (Get $get): string {
                                $requireBefore = $get('require_day_before');
                                $requireAfter = $get('require_day_after');

                                if (! $requireBefore && ! $requireAfter) {
                                    return 'No additional requirements - all eligible employees receive holiday pay.';
                                }

                                $requirements = [];
                                if ($requireBefore) {
                                    $requirements[] = 'work the day before';
                                }
                                if ($requireAfter) {
                                    $requirements[] = 'work the day after';
                                }

                                return 'Employees must '.implode(' AND ', $requirements).' the holiday to qualify for holiday pay.';
                            })
                            ->extraAttributes(['class' => 'text-sm text-amber-600']),
                    ]),

                Section::make('Preview')
                    ->schema([
                        Placeholder::make('preview')
                            ->label('Holiday Dates Preview')
                            ->content(function (Get $get): string {
                                $type = $get('type');
                                $rule = $get('calculation_rule');

                                if (! $type || ! $rule) {
                                    return 'Configure the holiday type and calculation rule to see preview dates.';
                                }

                                try {
                                    $template = new HolidayTemplate([
                                        'type' => $type,
                                        'calculation_rule' => $rule,
                                    ]);

                                    $dates = [];
                                    for ($year = now()->year; $year <= now()->year + 2; $year++) {
                                        $date = $template->calculateDateForYear($year);
                                        $dates[] = "{$year}: {$date->format('l, F j')} ({$date->toDateString()})";
                                    }

                                    return implode("\n", $dates);
                                } catch (Exception $e) {
                                    return 'Error calculating dates: '.$e->getMessage();
                                }
                            })
                            ->extraAttributes(['style' => 'white-space: pre-line;']),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Holiday Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed_date' => 'success',
                        'relative' => 'warning',
                        'custom' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('next_date')
                    ->label('Next Date')
                    ->getStateUsing(function (HolidayTemplate $record): string {
                        try {
                            $date = $record->calculateDateForYear(now()->year);
                            if ($date->isPast()) {
                                $date = $record->calculateDateForYear(now()->year + 1);
                            }

                            return $date->format('M j, Y');
                        } catch (Exception $e) {
                            return 'Error';
                        }
                    })
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('applies_to_all_employees')
                    ->label('All Employees')
                    ->boolean(),

                TextColumn::make('created_entries')
                    ->label('Created Entries')
                    ->default('0'),

                TextColumn::make('auto_create_days_ahead')
                    ->label('Days Ahead')
                    ->suffix(' days')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All templates')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('type')
                    ->label('Holiday Type')
                    ->options([
                        'fixed_date' => 'Fixed Date',
                        'relative' => 'Relative Date',
                        'custom' => 'Custom',
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview Dates')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->modalHeading(fn (HolidayTemplate $record) => "Preview: {$record->name}")
                    ->modalContent(function (HolidayTemplate $record) {
                        $dates = [];
                        try {
                            for ($year = now()->year - 1; $year <= now()->year + 3; $year++) {
                                $date = $record->calculateDateForYear($year);
                                $dates[] = "{$year}: {$date->format('l, F j, Y')}";
                            }

                            return view('filament.components.holiday-preview', ['dates' => $dates]);
                        } catch (Exception $e) {
                            return view('filament.components.holiday-preview', ['error' => $e->getMessage()]);
                        }
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidayTemplates::route('/'),
            'create' => CreateHolidayTemplate::route('/create'),
            'edit' => EditHolidayTemplate::route('/{record}/edit'),
        ];
    }
}
