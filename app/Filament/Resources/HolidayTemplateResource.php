<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayTemplateResource\Pages;
use App\Models\HolidayTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HolidayTemplateResource extends Resource
{
    protected static ?string $model = HolidayTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Holidays';
    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Holiday Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Christmas Day, New Year\'s Day'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->placeholder('Brief description of the holiday')
                            ->helperText('Optional description explaining the holiday'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable/disable this holiday template'),

                                Forms\Components\Toggle::make('applies_to_all_employees')
                                    ->label('All Eligible Employees')
                                    ->default(true)
                                    ->helperText('Apply to all holiday-eligible employees (salary + full-time hourly)'),

                                Forms\Components\TextInput::make('auto_create_days_ahead')
                                    ->label('Auto-Create Days Ahead')
                                    ->numeric()
                                    ->default(365)
                                    ->suffix('days')
                                    ->helperText('How many days in advance to auto-create holidays'),
                            ]),
                    ]),

                Forms\Components\Section::make('Employee Eligibility')
                    ->description('Define which employees are eligible for this holiday')
                    ->schema([
                        Forms\Components\CheckboxList::make('eligible_pay_types')
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

                        Forms\Components\Placeholder::make('eligibility_note')
                            ->label('Current Setup')
                            ->content('Based on your employee data: Salary employees and Full-Time Hourly employees are typically eligible for holiday pay, while Part-Time Hourly (temps) and Contract employees are not.')
                            ->extraAttributes(['class' => 'text-sm text-gray-600']),
                    ]),

                Forms\Components\Section::make('Date Calculation')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Holiday Type')
                            ->options([
                                'fixed_date' => 'Fixed Date (e.g., Christmas - Dec 25)',
                                'relative' => 'Relative Date (e.g., Thanksgiving - 4th Thursday in November)',
                                'custom' => 'Custom Calculation (e.g., Easter-based)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('calculation_rule', null)),

                        // Fixed Date Configuration
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('calculation_rule.month')
                                ->label('Month')
                                ->options([
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                ])
                                ->required(),

                            Forms\Components\Select::make('calculation_rule.day')
                                ->label('Day')
                                ->options(array_combine(range(1, 31), range(1, 31)))
                                ->required(),
                        ])
                        ->columns(2)
                        ->visible(fn (Forms\Get $get) => $get('type') === 'fixed_date'),

                        // Relative Date Configuration
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('calculation_rule.month')
                                ->label('Month')
                                ->options([
                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                ])
                                ->required(),

                            Forms\Components\Select::make('calculation_rule.day_of_week')
                                ->label('Day of Week')
                                ->options([
                                    0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                                    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
                                ])
                                ->required(),

                            Forms\Components\Select::make('calculation_rule.occurrence')
                                ->label('Occurrence')
                                ->options([
                                    1 => '1st (First)', 2 => '2nd (Second)', 3 => '3rd (Third)',
                                    4 => '4th (Fourth)', 5 => '5th (Fifth)', -1 => 'Last'
                                ])
                                ->required()
                                ->helperText('e.g., "4th" Thursday for Thanksgiving'),
                        ])
                        ->columns(3)
                        ->visible(fn (Forms\Get $get) => $get('type') === 'relative'),

                        // Custom Date Configuration
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('calculation_rule.base')
                                ->label('Base Holiday')
                                ->options([
                                    'easter' => 'Easter Sunday',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('calculation_rule.offset_days')
                                ->label('Offset Days')
                                ->numeric()
                                ->default(0)
                                ->helperText('Days before (-) or after (+) the base holiday'),
                        ])
                        ->columns(2)
                        ->visible(fn (Forms\Get $get) => $get('type') === 'custom'),
                    ]),

                Forms\Components\Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Holiday Dates Preview')
                            ->content(function (Forms\Get $get): string {
                                $type = $get('type');
                                $rule = $get('calculation_rule');

                                if (!$type || !$rule) {
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
                                } catch (\Exception $e) {
                                    return "Error calculating dates: " . $e->getMessage();
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Holiday Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed_date' => 'success',
                        'relative' => 'warning',
                        'custom' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('next_date')
                    ->label('Next Date')
                    ->getStateUsing(function (HolidayTemplate $record): string {
                        try {
                            $date = $record->calculateDateForYear(now()->year);
                            if ($date->isPast()) {
                                $date = $record->calculateDateForYear(now()->year + 1);
                            }
                            return $date->format('M j, Y');
                        } catch (\Exception $e) {
                            return 'Error';
                        }
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('applies_to_all_employees')
                    ->label('All Employees')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_entries')
                    ->label('Created Entries')
                    ->default('0'),

                Tables\Columns\TextColumn::make('auto_create_days_ahead')
                    ->label('Days Ahead')
                    ->suffix(' days')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All templates')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Holiday Type')
                    ->options([
                        'fixed_date' => 'Fixed Date',
                        'relative' => 'Relative Date',
                        'custom' => 'Custom',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
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
                        } catch (\Exception $e) {
                            return view('filament.components.holiday-preview', ['error' => $e->getMessage()]);
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHolidayTemplates::route('/'),
            'create' => Pages\CreateHolidayTemplate::route('/create'),
            'edit' => Pages\EditHolidayTemplate::route('/{record}/edit'),
        ];
    }
}