<?php

namespace App\Filament\Resources\RoundGroupResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class RoundingRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'roundingRules';

    protected static ?string $title = 'Rounding Rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('minute_min')
                    ->numeric()
                    ->required()
                    ->label('Start Minute (Min)'),

                TextInput::make('minute_max')
                    ->numeric()
                    ->required()
                    ->label('End Minute (Max)'),

                TextInput::make('new_minute')
                    ->numeric()
                    ->required()
                    ->label('Rounds To (Minute)'),

                TextInput::make('new_minute_decimal')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->label('Decimal Equivalent'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('minute_min')
                    ->label('From (Min)')
                    ->sortable(),
                TextColumn::make('minute_max')
                    ->label('To (Max)')
                    ->sortable(),
                TextColumn::make('new_minute')
                    ->label('Rounds To')
                    ->sortable(),
                TextColumn::make('new_minute_decimal')
                    ->label('Decimal')
                    ->sortable(),
            ])
            ->defaultSort('minute_min')
            ->headerActions([
                CreateAction::make(),
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
}
