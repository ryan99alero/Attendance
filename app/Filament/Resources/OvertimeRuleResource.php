<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimeRuleResource\Pages\CreateOvertimeRule;
use App\Filament\Resources\OvertimeRuleResource\Pages\EditOvertimeRule;
use App\Filament\Resources\OvertimeRuleResource\Pages\ListOvertimeRules;
use App\Models\OvertimeRule;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class OvertimeRuleResource extends Resource
{
    protected static ?string $model = OvertimeRule::class;

    protected static string|UnitEnum|null $navigationGroup = 'Payroll & Overtime';

    protected static ?string $navigationLabel = 'Overtime Rules';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Overtime Rule')
                ->tabs([
                    Tab::make('General')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Section::make('Basic Information')
                                ->description('Rule identification and assignment')
                                ->schema([
                                    TextInput::make('rule_name')
                                        ->label('Rule Name')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g., 1st Shift Weekly Overtime'),

                                    Select::make('rule_type')
                                        ->label('Rule Type')
                                        ->options(OvertimeRule::getRuleTypes())
                                        ->required()
                                        ->live()
                                        ->helperText('Determines how this overtime rule is applied'),

                                    Select::make('shift_id')
                                        ->label('Shift')
                                        ->options(Shift::all()->pluck('shift_name', 'id'))
                                        ->searchable()
                                        ->nullable()
                                        ->helperText('Leave empty to apply to all shifts'),

                                    TextInput::make('priority')
                                        ->label('Priority')
                                        ->numeric()
                                        ->default(100)
                                        ->helperText('Lower number = higher priority'),

                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true)
                                        ->helperText('Enable/disable this rule'),

                                    Textarea::make('description')
                                        ->label('Description')
                                        ->maxLength(500)
                                        ->placeholder('Describe what this rule does...')
                                        ->columnSpan(2),
                                ])
                                ->columns(3),
                        ]),

                    Tab::make('Thresholds')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Section::make('Hours & Multipliers')
                                ->description('Configure when overtime kicks in and pay rates')
                                ->schema([
                                    TextInput::make('hours_threshold')
                                        ->label('Hours Threshold')
                                        ->numeric()
                                        ->default(40)
                                        ->suffix('hours')
                                        ->helperText('Hours before overtime kicks in')
                                        ->visible(fn (Get $get) => in_array($get('rule_type'), [
                                            OvertimeRule::TYPE_WEEKLY_THRESHOLD,
                                            OvertimeRule::TYPE_DAILY_THRESHOLD,
                                        ])),

                                    TextInput::make('consecutive_days_threshold')
                                        ->label('Consecutive Days')
                                        ->numeric()
                                        ->nullable()
                                        ->suffix('days')
                                        ->helperText('Number of consecutive days to trigger')
                                        ->visible(fn (Get $get) => $get('rule_type') === OvertimeRule::TYPE_CONSECUTIVE_DAY),

                                    TextInput::make('multiplier')
                                        ->label('Overtime Multiplier')
                                        ->numeric()
                                        ->default(1.5)
                                        ->step(0.01)
                                        ->suffix('x')
                                        ->helperText('e.g., 1.5 for time-and-a-half'),

                                    TextInput::make('double_time_multiplier')
                                        ->label('Double-Time Multiplier')
                                        ->numeric()
                                        ->default(2.0)
                                        ->step(0.01)
                                        ->suffix('x')
                                        ->helperText('e.g., 2.0 for double-time'),
                                ])
                                ->columns(2),

                            Section::make('Additional Conditions')
                                ->description('Special requirements for this rule to apply')
                                ->schema([
                                    Toggle::make('requires_prior_day_worked')
                                        ->label('Requires Prior Day Worked')
                                        ->default(false)
                                        ->helperText('Employee must work the day before for this rule to apply')
                                        ->visible(fn (Get $get) => in_array($get('rule_type'), [
                                            OvertimeRule::TYPE_WEEKEND_DAY,
                                            OvertimeRule::TYPE_CONSECUTIVE_DAY,
                                        ])),

                                    Toggle::make('only_applies_to_final_day')
                                        ->label('Only Applies to Threshold Day')
                                        ->default(false)
                                        ->helperText('Only apply on the exact threshold day (not days after)')
                                        ->visible(fn (Get $get) => $get('rule_type') === OvertimeRule::TYPE_CONSECUTIVE_DAY),

                                    Toggle::make('applies_on_weekends')
                                        ->label('Applies on Weekends')
                                        ->default(false)
                                        ->helperText('Legacy field for backward compatibility')
                                        ->visible(fn (Get $get) => in_array($get('rule_type'), [
                                            OvertimeRule::TYPE_WEEKLY_THRESHOLD,
                                            OvertimeRule::TYPE_DAILY_THRESHOLD,
                                        ])),
                                ])
                                ->columns(3)
                                ->collapsed(),
                        ]),

                    Tab::make('Eligibility')
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Section::make('Days of Week')
                                ->description('Select which days this rule applies to')
                                ->schema([
                                    CheckboxList::make('applies_to_days')
                                        ->label('Applies to Days')
                                        ->options(OvertimeRule::getDaysOfWeek())
                                        ->columns(7)
                                        ->helperText('Leave all unchecked to apply to every day'),
                                ])
                                ->visible(fn (Get $get) => in_array($get('rule_type'), [
                                    OvertimeRule::TYPE_WEEKEND_DAY,
                                    OvertimeRule::TYPE_DAILY_THRESHOLD,
                                ])),

                            Section::make('Employee Pay Types')
                                ->description('Select which employee pay types are eligible for this overtime rule')
                                ->schema([
                                    CheckboxList::make('eligible_pay_types')
                                        ->label('Eligible Pay Types')
                                        ->options([
                                            OvertimeRule::PAY_TYPE_HOURLY => 'Hourly Employees',
                                            OvertimeRule::PAY_TYPE_SALARY => 'Salary Employees',
                                            OvertimeRule::PAY_TYPE_CONTRACT => 'Contract Employees',
                                        ])
                                        ->columns(3)
                                        ->helperText('Leave all unchecked to apply to all pay types. Note: Salary employees are typically exempt from overtime.'),
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
                TextColumn::make('rule_name')
                    ->label('Rule Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OvertimeRule::TYPE_WEEKLY_THRESHOLD => 'primary',
                        OvertimeRule::TYPE_DAILY_THRESHOLD => 'info',
                        OvertimeRule::TYPE_WEEKEND_DAY => 'warning',
                        OvertimeRule::TYPE_CONSECUTIVE_DAY => 'success',
                        OvertimeRule::TYPE_HOLIDAY => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => OvertimeRule::getRuleTypes()[$state] ?? $state),

                TextColumn::make('hours_threshold')
                    ->label('Threshold')
                    ->suffix(' hrs')
                    ->sortable(),

                TextColumn::make('multiplier')
                    ->label('OT Rate')
                    ->suffix('x')
                    ->sortable(),

                TextColumn::make('double_time_multiplier')
                    ->label('DT Rate')
                    ->suffix('x')
                    ->sortable(),

                TextColumn::make('shift.shift_name')
                    ->label('Shift')
                    ->default('All Shifts')
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('requires_prior_day_worked')
                    ->label('Prior Day Req.')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('priority', 'asc')
            ->filters([
                SelectFilter::make('rule_type')
                    ->label('Rule Type')
                    ->options(OvertimeRule::getRuleTypes()),

                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->options(Shift::all()->pluck('shift_name', 'id'))
                    ->placeholder('All Shifts'),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All rules')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
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
            'index' => ListOvertimeRules::route('/'),
            'create' => CreateOvertimeRule::route('/create'),
            'edit' => EditOvertimeRule::route('/{record}/edit'),
        ];
    }
}
