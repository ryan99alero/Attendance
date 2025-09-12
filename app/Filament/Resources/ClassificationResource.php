<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassificationResource\Pages;
use App\Models\Classification;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Resources\Resource;

class ClassificationResource extends Resource
{
    protected static ?string $model = Classification::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Classifications';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->date(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->date(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassifications::route('/'),
            'create' => Pages\CreateClassification::route('/create'),
            'edit' => Pages\EditClassification::route('/{record}/edit'),
        ];
    }
}
