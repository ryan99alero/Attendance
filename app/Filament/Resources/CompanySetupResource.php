<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use App\Filament\Resources\CompanySetupResource\Pages\ListCompanySetups;
use App\Filament\Resources\CompanySetupResource\Pages\EditCompanySetup;
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
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $slug = 'company-setup';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Hidden field for ID (not editable or included in form submission)
                Hidden::make('id')
                    ->disabled()
                    ->dehydrated(false),

                // Number of minutes allowed before/after a shift for attendance matching
                TextInput::make('attendance_flexibility_minutes')
                    ->numeric()
                    ->default(30)
                    ->required()
                    ->label('Attendance Flexibility (Minutes)'),

                // Defines the level of logging in the system (None, Error, Warning, Info, Debug)
                Select::make('logging_level')
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
                            // Temporary Debug Mode in  for Development (None, Error, Warning, Info, Debug)
                Select::make('debug_punch_assignment_mode')
                    ->options([
                        'shift_schedule' => 'Shift Schedule',      // No logging
                        'heuristic' => 'Heuristic',    // Only critical errors
                        'ml' => 'Machine Learning',// Errors + potential issues
                        'full' => 'All',      // General system events
                    ])
                    ->default('error')
                    ->required()
                    ->label('PunchType Debug Mode'),

                // Whether to automatically adjust punch types for incomplete records
                Toggle::make('auto_adjust_punches')
                    ->default(false)
                    ->label('Auto Adjust Punches'),

                // Defines the minimum hours required between two punches for auto-assigning Clock In/Out instead of Needs Review.
                TextInput::make('heuristic_min_punch_gap')
                    ->numeric()
                    ->default(6)
                    ->required()
                    ->label('heuristic Min Punch Gap'),

                // Enable ML-based punch classification for better accuracy in punch assignments
                Toggle::make('use_ml_for_punch_matching')
                    ->default(true)
                    ->label('Use ML for Punch Matching'),

                // If enabled, employees must adhere to assigned shift schedules
                Toggle::make('enforce_shift_schedules')
                    ->default(true)
                    ->label('Enforce Shift Schedules'),

                // Allow admins to manually edit time records (If disabled, all changes must be system-generated)
                Toggle::make('allow_manual_time_edits')
                    ->default(true)
                    ->label('Allow Manual Time Edits'),

                // Maximum shift length (in hours) before requiring admin approval
                TextInput::make('max_shift_length')
                    ->numeric()
                    ->default(12)
                    ->required()
                    ->label('Max Shift Length (Hours)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_flexibility_minutes')->sortable()
                    ->label('Attendance Flexibility (Minutes)'),

                TextColumn::make('logging_level')->sortable()
                    ->label('Logging Level'),

                TextColumn::make('debug_punch_assignment_mode')->sortable()
                    ->label('debug punch assignment mode'),

                IconColumn::make('auto_adjust_punches')->boolean()
                    ->label('Auto Adjust Punches'),

                TextColumn::make('heuristic_min_punch_gap')->sortable()
                    ->label('Heuristic Min Punch Gap'),

                IconColumn::make('use_ml_for_punch_matching')->boolean()
                    ->label('Use ML for Punch Matching'),

                IconColumn::make('enforce_shift_schedules')->boolean()
                    ->label('Enforce Shift Schedules'),

                IconColumn::make('allow_manual_time_edits')->boolean()
                    ->label('Allow Manual Time Edits'),

                TextColumn::make('max_shift_length')->sortable()
                    ->label('Max Shift Length (Hours)'),

                TextColumn::make('created_at')->dateTime()
                    ->label('Created At'),

                TextColumn::make('updated_at')->dateTime()
                    ->label('Updated At'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]); // No bulk actions added yet
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanySetups::route('/'),
            'edit' => EditCompanySetup::route('/{record}/edit'),
        ];
    }
}
