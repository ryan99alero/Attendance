<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PunchTypeResource\Pages;
use App\Models\PunchType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PunchTypeResource extends Resource
{
    protected static ?string $model = PunchType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Punch Types';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Punch Type Name')->required(),
            Forms\Components\Textarea::make('description')->label('Description')->nullable(),
            Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Punch Type Name'),
            Tables\Columns\TextColumn::make('description')->label('Description'),
            Tables\Columns\IconColumn::make('is_active')->label('Active'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPunchTypes::route('/'),
            'create' => Pages\CreatePunchType::route('/create'),
            'edit' => Pages\EditPunchType::route('/{record}/edit'),
        ];
    }
}