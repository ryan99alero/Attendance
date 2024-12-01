<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Employee;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->required()
                ->email()
                ->maxLength(255)
                ->unique(ignorable: fn ($record) => $record),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->dehydrateStateUsing(static function (?string $state) {
                    return empty($state) ? null : Hash::make($state);
                })
                ->nullable()
                ->maxLength(255)
                ->dehydrated(static fn ($state) => !empty($state)),
            Select::make('employee_id')
                ->label('Employee')
                ->nullable()
                ->options(function (?User $record) {
                    return Employee::whereDoesntHave('user') // Employees without linked users
                    ->orWhereHas('user', fn ($query) => $query->where('id', $record?->id))
                        ->get()
                        ->pluck('full_name', 'id');
                })
                ->searchable()
                ->placeholder('Select an Employee'),
            Toggle::make('is_admin')
                ->label('Admin')
                ->default(false),
            Toggle::make('is_manager')
                ->label('Manager')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('email')
                ->label('Email')
                ->sortable()
                ->searchable(),
            TextColumn::make('employee.full_name')
                ->label('Employee')
                ->sortable()
                ->searchable(),
            IconColumn::make('is_admin')
                ->label('Admin')
                ->boolean(),
            IconColumn::make('is_manager')
                ->label('Manager')
                ->boolean(),
        ]);
    }

    /**
     * Update the employee's user association when saving a user.
     */
    public static function saving($user)
    {
        if ($user->employee_id) {
            // Clear previous user associations for this employee
            Employee::where('user_id', $user->id)
                ->update(['user_id' => null]);

            // Set the new association
            Employee::where('id', $user->employee_id)
                ->update(['user_id' => $user->id]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
