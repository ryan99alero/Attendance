<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoundingRuleResource\Pages;
use App\Filament\Resources\RoundingRuleResource\RelationManagers;
use App\Models\RoundingRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoundingRuleResource extends Resource
{
    protected static ?string $model = RoundingRule::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->disabled()
                    ->label('ID'),
                Forms\Components\TextInput::make('rule_name')
                    ->required()
                    ->maxLength(50)
                    ->label('Name'),
                Forms\Components\TextInput::make('lower_limit')
                    ->numeric()
                    ->required()
                    ->label('Start Minute (Lower Limit)'),
                Forms\Components\TextInput::make('upper_limit')
                    ->numeric()
                    ->required()
                    ->label('End Minute (Upper Limit)'),
                Forms\Components\TextInput::make('rounded_value')
                    ->numeric()
                    ->required()
                    ->label('Rounded Minute'),
                Forms\Components\TextInput::make('interval_minutes')
                    ->numeric()
                    ->required()
                    ->label('Interval Minutes'),
                Forms\Components\Select::make('apply_to')
                    ->options([
                        'check_in' => 'Check In',
                        'check_out' => 'Check Out',
                        'both' => 'Both',
                    ])
                    ->required()
                    ->label('Applies To'),
                Forms\Components\Select::make('created_by')
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->label('Created By'),
                Forms\Components\Select::make('updated_by')
                    ->relationship('updater', 'name')
                    ->disabled()
                    ->label('Updated By'),
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
                Tables\Columns\TextColumn::make('rule_name')->label('Name'),
                Tables\Columns\TextColumn::make('lower_limit')->label('Start Minute (Lower Limit)'),
                Tables\Columns\TextColumn::make('upper_limit')->label('End Minute (Upper Limit)'),
                Tables\Columns\TextColumn::make('rounded_value')->label('Rounded Minute'),
                Tables\Columns\TextColumn::make('interval_minutes')->label('Interval Minutes'),
                Tables\Columns\TextColumn::make('apply_to')->label('Applies To'),
            ])
            ->filters([
                // Add specific filters if needed
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Define any relation managers here if needed
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
