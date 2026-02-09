<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\RoundingRuleResource\Pages\ListRoundingRules;
use App\Filament\Resources\RoundingRuleResource\Pages\CreateRoundingRule;
use App\Filament\Resources\RoundingRuleResource\Pages\EditRoundingRule;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\RoundingRuleResource\Pages;
use App\Models\RoundingRule;
use App\Models\RoundGroup; // Ensure this model exists and is imported
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoundingRuleResource extends Resource
{
    protected static ?string $model = RoundingRule::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Rounding Rules';
    protected static string | \UnitEnum | null $navigationGroup = 'Payroll & Overtime';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->disabled()
                    ->label('ID'),

                Select::make('round_group_id')
                    ->relationship('roundGroup', 'group_name') // Link to group_name from round_groups
                    ->required()
                    ->label('Round Group'),

                TextInput::make('minute_min')
                    ->numeric()
                    ->required()
                    ->label('Start Minute (Lower Limit)'),

                TextInput::make('minute_max')
                    ->numeric()
                    ->required()
                    ->label('End Minute (Upper Limit)'),

                TextInput::make('new_minute')
                    ->numeric()
                    ->required()
                    ->label('Rounded Minute'),

                TextInput::make('new_minute_decimal')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->label('Rounded Minute (Decimal Equivalent)'),

                DateTimePicker::make('created_at')
                    ->disabled()
                    ->label('Created At'),

                DateTimePicker::make('updated_at')
                    ->disabled()
                    ->label('Updated At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),

                TextColumn::make('roundGroup.group_name') // Adjusted to use the correct column
                ->label('Round Group'),

                TextColumn::make('minute_min')
                    ->label('Start Minute (Lower Limit)')
                    ->sortable(),

                TextColumn::make('minute_max')
                    ->label('End Minute (Upper Limit)')
                    ->sortable(),

                TextColumn::make('new_minute')
                    ->label('Rounded Minute')
                    ->sortable(),

                TextColumn::make('new_minute_decimal')
                    ->label('Rounded Minute (Decimal Equivalent)')
                    ->sortable(),
            ])
            ->filters([
                // Add any relevant filters here
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define any relation managers if necessary
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoundingRules::route('/'),
            'create' => CreateRoundingRule::route('/create'),
            'edit' => EditRoundingRule::route('/{record}/edit'),
        ];
    }
}
