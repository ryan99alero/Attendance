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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->disabled()
                    ->label('ID'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(50)
                    ->label('Name'),
                Forms\Components\TextInput::make('minute_min')
                    ->numeric()
                    ->label('Minimum Minute'),
                Forms\Components\TextInput::make('minute_max')
                    ->numeric()
                    ->label('Maximum Minute'),
                Forms\Components\TextInput::make('new_minute')
                    ->numeric()
                    ->label('New Minute'),
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
                Tables\Columns\TextColumn::make('name')->label('Name'),
                Tables\Columns\TextColumn::make('minute_min')->label('Minimum Minute'),
                Tables\Columns\TextColumn::make('minute_max')->label('Maximum Minute'),
                Tables\Columns\TextColumn::make('new_minute')->label('New Minute'),
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
