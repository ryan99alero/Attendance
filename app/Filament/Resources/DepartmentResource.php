<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-office-building';
    protected static ?string $navigationLabel = 'Departments';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Department Name')
                ->required(),
            Select::make('manager_id')
                ->relationship('manager', 'first_name')
                ->label('Manager')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->label('Department Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('manager.first_name')
                ->label('Manager')
                ->sortable()
                ->searchable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
