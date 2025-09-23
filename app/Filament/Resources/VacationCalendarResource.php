<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationCalendarResource\Pages;
use App\Models\VacationCalendar;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VacationCalendarResource extends Resource
{
    protected static ?string $model = VacationCalendar::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Vacation Calendars';
    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_any_vacation::calendar') ?? false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_any_vacation::calendar') ?? false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_vacation::calendar') ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('create_vacation::calendar') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('update_vacation::calendar') ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('delete_vacation::calendar') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Admins and super admins can see all records
        if ($user?->hasRole(['admin', 'super_admin'])) {
            return $query;
        }

        // Managers can only see vacation records for employees in their departments
        if ($user?->hasRole('manager')) {
            // Get the employee record for the authenticated user
            $employee = Employee::where('email', $user->email)->first();

            if ($employee) {
                // Get departments where this employee is the manager
                $managedDepartmentIds = Department::where('manager_id', $employee->id)->pluck('id');

                if ($managedDepartmentIds->isNotEmpty()) {
                    // Filter vacation calendars to only show employees in managed departments
                    return $query->whereHas('employee', function (Builder $employeeQuery) use ($managedDepartmentIds) {
                        $employeeQuery->whereIn('department_id', $managedDepartmentIds);
                    });
                }
            }

            // If manager has no departments or employee record not found, show no records
            return $query->whereRaw('1 = 0');
        }

        // For other roles (viewer, etc.), show only their own records if they have an employee record
        if ($user) {
            $employee = Employee::where('email', $user->email)->first();
            if ($employee) {
                return $query->where('employee_id', $employee->id);
            }
        }

        // If no specific role or employee record, show no records
        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Vacation Request')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->relationship('employee', 'first_name', function (Builder $query) {
                            $user = auth()->user();

                            // Admins and super admins can select any employee
                            if ($user?->hasRole(['admin', 'super_admin'])) {
                                return $query;
                            }

                            // Managers can only select employees from their departments
                            if ($user?->hasRole('manager')) {
                                $employee = Employee::where('email', $user->email)->first();

                                if ($employee) {
                                    $managedDepartmentIds = Department::where('manager_id', $employee->id)->pluck('id');

                                    if ($managedDepartmentIds->isNotEmpty()) {
                                        return $query->whereIn('department_id', $managedDepartmentIds);
                                    }
                                }

                                // If manager has no departments, show no employees
                                return $query->whereRaw('1 = 0');
                            }

                            // For other roles, show only themselves if they have an employee record
                            if ($user) {
                                $employee = Employee::where('email', $user->email)->first();
                                if ($employee) {
                                    return $query->where('id', $employee->id);
                                }
                            }

                            // If no specific role or employee record, show no employees
                            return $query->whereRaw('1 = 0');
                        })
                        ->label('Employee')
                        ->required()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_names),

                    Forms\Components\DatePicker::make('vacation_date')
                        ->label('Vacation Date')
                        ->required(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Toggle::make('is_half_day')
                                ->label('Half Day')
                                ->default(false),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('employee.full_names')
                ->label('Employee')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('vacation_date')
                ->label('Vacation Date')
                ->date()
                ->sortable(),

            Tables\Columns\IconColumn::make('is_half_day')
                ->label('Half Day')
                ->boolean(),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->defaultSort('vacation_date', 'desc')
        ->filters([
            Tables\Filters\SelectFilter::make('employee_id')
                ->relationship('employee', 'first_name')
                ->searchable()
                ->preload(),

            Tables\Filters\TernaryFilter::make('is_half_day')
                ->label('Half Day')
                ->placeholder('All entries')
                ->trueLabel('Half days only')
                ->falseLabel('Full days only'),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active Status')
                ->placeholder('All entries')
                ->trueLabel('Active only')
                ->falseLabel('Inactive only'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVacationCalendars::route('/'),
            'create' => Pages\CreateVacationCalendar::route('/create'),
            'edit' => Pages\EditVacationCalendar::route('/{record}/edit'),
        ];
    }
}
