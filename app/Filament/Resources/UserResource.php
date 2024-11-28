<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    return empty($state) ? null : Hash::make($state); // Only hash if a new password is provided
                })
                ->nullable()
                ->maxLength(255)
                ->dehydrated(static fn ($state) => !empty($state)), // Prevent saving if blank
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
            IconColumn::make('is_admin')
                ->label('Admin')
                ->boolean(),
            IconColumn::make('is_manager')
                ->label('Manager')
                ->boolean(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
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
