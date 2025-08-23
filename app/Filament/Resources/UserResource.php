<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Employee;
use App\Exports\DataExport;
use App\Imports\DataImport;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
                    return Employee::whereDoesntHave('user')
                        ->orWhereHas('user', fn ($query) => $query->where('id', $record?->id))
                        ->get()
                        ->pluck('full_names', 'id');
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
            TextColumn::make('employee.full_names')
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

    public static function getActions(): array
    {
        return [
            Action::make('Export Users')
                ->label('Export')
                ->color('warning')
                ->icon('heroicon-o-download')
                ->action(function () {
                    return Excel::download(new DataExport(User::class), 'users.xlsx');
                }),
            Action::make('Import Users')
                ->label('Import')
                ->color('primary')
                ->icon('heroicon-o-upload')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required(),
                ])
                ->action(function ($data) {
                    $filePath = DataImport::resolveFilePath($data['file']);
                    Excel::import(new DataImport(User::class), $filePath);
                }),
        ];
    }

    public static function saving($user)
    {
        if ($user->employee_id) {
            Employee::where('user_id', $user->id)->update(['user_id' => null]);
            Employee::where('id', $user->employee_id)->update(['user_id' => $user->id]);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
