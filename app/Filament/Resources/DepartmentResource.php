<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Resources\DepartmentResource\RelationManagers\EmployeesRelationManager;
use App\Models\Department;
use App\Models\Employee; // Import Employee model
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-office-building';
    protected static ?string $navigationLabel = 'Departments';
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {

        return $form->schema([
            TextInput::make('name')
                ->label('Department Name')
                ->required()
                ->unique(ignorable: fn ($record) => $record)
                ->maxLength(100)
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    Log::info("TACO1: Department Name updated to: {$state}");
                }),
            TextInput::make('external_department_id')
                ->label('External Department ID')
                ->numeric()
                ->nullable()
                ->unique(ignorable: fn ($record) => $record)
                ->afterStateUpdated(function ($state) {
                    Log::info("TACO2: External Department ID updated to: {$state}");
                }),
            Select::make('manager_id')
                ->label('Manager')
                ->options(function () {
                    return Employee::orderBy('full_names')
                        ->pluck('full_names', 'id')
                        ->toArray();
                })
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return Employee::where('full_names', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orderBy('full_names')
                        ->limit(50)
                        ->pluck('full_names', 'id')
                        ->toArray();
                })
                ->placeholder('Select a Manager')
                ->required()
                ->afterStateUpdated(function ($state) {
                    Log::info("TACO2.5: Manager ID updated to: {$state}");
                }),
        ]);
    }

    public static function table(Table $table): Table
    {

        return $table->columns([
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('name')
                ->label('Department Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('external_department_id')
                ->label('External Department ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('manager.full_names') // Assuming the relationship is correctly defined
            ->label('Manager Name') // Display the related manager's name
            ->sortable()
                ->searchable(),
        ])->actions([
            EditAction::make()
                ->after(function ($record) {
                }),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
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
