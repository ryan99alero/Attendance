<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoundingRuleResource\Pages;
use App\Models\RoundingRule;
use App\Models\RoundGroup; // Ensure this model exists and is imported
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;

class RoundingRuleResource extends Resource
{
    protected static ?string $model = RoundingRule::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->disabled()
                    ->label('ID'),

                Forms\Components\Select::make('round_group_id')
                    ->relationship('roundGroup', 'group_name') // Link to group_name from round_groups
                    ->required()
                    ->label('Round Group'),

                Forms\Components\TextInput::make('minute_min')
                    ->numeric()
                    ->required()
                    ->label('Start Minute (Lower Limit)'),

                Forms\Components\TextInput::make('minute_max')
                    ->numeric()
                    ->required()
                    ->label('End Minute (Upper Limit)'),

                Forms\Components\TextInput::make('new_minute')
                    ->numeric()
                    ->required()
                    ->label('Rounded Minute'),

                Forms\Components\TextInput::make('new_minute_decimal')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->label('Rounded Minute (Decimal Equivalent)'),

                Forms\Components\DateTimePicker::make('created_at')
                    ->disabled()
                    ->label('Created At'),

                Forms\Components\DateTimePicker::make('updated_at')
                    ->disabled()
                    ->label('Updated At'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID'),

                Tables\Columns\TextColumn::make('roundGroup.group_name') // Adjusted to use the correct column
                ->label('Round Group'),

                Tables\Columns\TextColumn::make('minute_min')
                    ->label('Start Minute (Lower Limit)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('minute_max')
                    ->label('End Minute (Upper Limit)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('new_minute')
                    ->label('Rounded Minute')
                    ->sortable(),

                Tables\Columns\TextColumn::make('new_minute_decimal')
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
            'index' => Pages\ListRoundingRules::route('/'),
            'create' => Pages\CreateRoundingRule::route('/create'),
            'edit' => Pages\EditRoundingRule::route('/{record}/edit'),
        ];
    }
}
