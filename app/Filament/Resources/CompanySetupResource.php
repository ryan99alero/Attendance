<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use App\Models\CompanySetup;
use App\Filament\Resources\CompanySetupResource\Pages;
use Filament\Tables\Filters\TrashedFilter;

class CompanySetupResource extends Resource
{
    protected static ?string $model = CompanySetup::class;

    protected static ?string $navigationLabel = 'Company Setup';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $slug = 'company-setup';
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                // Hidden field for ID (not editable or included in form submission)
                Forms\Components\Hidden::make('id')
                    ->disabled()
                    ->dehydrated(false),

                // Number of minutes allowed before/after a shift for attendance matching
                Forms\Components\TextInput::make('attendance_flexibility_minutes')
                    ->numeric()
                    ->default(30)
                    ->required()
                    ->label('Attendance Flexibility (Minutes)'),

                // Defines the level of logging in the system (None, Error, Warning, Info, Debug)
                Forms\Components\Select::make('logging_level')
                    ->options([
                        'none' => 'None',      // No logging
                        'error' => 'Error',    // Only critical errors
                        'warning' => 'Warning',// Errors + potential issues
                        'info' => 'Info',      // General system events
                        'debug' => 'Debug',    // Most detailed logs for troubleshooting
                    ])
                    ->default('error')
                    ->required()
                    ->label('Logging Level'),

                // Whether to automatically adjust punch types for incomplete records
                Forms\Components\Toggle::make('auto_adjust_punches')
                    ->default(false)
                    ->label('Auto Adjust Punches'),

                // Enable ML-based punch classification for better accuracy in punch assignments
                Forms\Components\Toggle::make('use_ml_for_punch_matching')
                    ->default(true)
                    ->label('Use ML for Punch Matching'),

                // If enabled, employees must adhere to assigned shift schedules
                Forms\Components\Toggle::make('enforce_shift_schedules')
                    ->default(true)
                    ->label('Enforce Shift Schedules'),

                // Allow admins to manually edit time records (If disabled, all changes must be system-generated)
                Forms\Components\Toggle::make('allow_manual_time_edits')
                    ->default(true)
                    ->label('Allow Manual Time Edits'),

                // Maximum shift length (in hours) before requiring admin approval
                Forms\Components\TextInput::make('max_shift_length')
                    ->numeric()
                    ->default(12)
                    ->required()
                    ->label('Max Shift Length (Hours)'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attendance_flexibility_minutes')->sortable()
                    ->label('Attendance Flexibility (Minutes)'),

                Tables\Columns\TextColumn::make('logging_level')->sortable()
                    ->label('Logging Level'),

                Tables\Columns\IconColumn::make('auto_adjust_punches')->boolean()
                    ->label('Auto Adjust Punches'),

                Tables\Columns\IconColumn::make('use_ml_for_punch_matching')->boolean()
                    ->label('Use ML for Punch Matching'),

                Tables\Columns\IconColumn::make('enforce_shift_schedules')->boolean()
                    ->label('Enforce Shift Schedules'),

                Tables\Columns\IconColumn::make('allow_manual_time_edits')->boolean()
                    ->label('Allow Manual Time Edits'),

                Tables\Columns\TextColumn::make('max_shift_length')->sortable()
                    ->label('Max Shift Length (Hours)'),

                Tables\Columns\TextColumn::make('created_at')->dateTime()
                    ->label('Created At'),

                Tables\Columns\TextColumn::make('updated_at')->dateTime()
                    ->label('Updated At'),
            ])
            ->filters([
                TrashedFilter::make(), // Allows viewing soft-deleted records if enabled
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]); // No bulk actions added yet
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanySetups::route('/'),
            'edit' => Pages\EditCompanySetup::route('/{record}/edit'),
        ];
    }
}
