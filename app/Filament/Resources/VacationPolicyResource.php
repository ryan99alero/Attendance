<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VacationPolicyResource\Pages\ListVacationPolicies;
use App\Filament\Resources\VacationPolicyResource\Pages\CreateVacationPolicy;
use App\Filament\Resources\VacationPolicyResource\Pages\EditVacationPolicy;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\VacationPolicyResource\Pages;
use App\Filament\Resources\VacationPolicyResource\RelationManagers;
use App\Models\VacationPolicy;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VacationPolicyResource extends Resource
{
    protected static ?string $model = VacationPolicy::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Time Off Management';
    protected static ?string $navigationLabel = 'Vacation Policies';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_any_vacation::policy') ?? false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_any_vacation::policy') ?? false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('view_vacation::policy') ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('create_vacation::policy') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('update_vacation::policy') ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('delete_vacation::policy') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Policy Information')
                    ->schema([
                        TextInput::make('policy_name')
                            ->label('Policy Name')
                            ->required()
                            ->placeholder('e.g., Years 1-5, Year 6, etc.')
                            ->helperText('Descriptive name for this vacation tier'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this policy is currently active'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order for displaying policies (lower numbers first)'),
                    ])
                    ->columns(3),

                Section::make('Tenure Requirements')
                    ->schema([
                        TextInput::make('min_tenure_years')
                            ->label('Minimum Years of Service')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->helperText('Minimum years of service to qualify for this policy'),

                        TextInput::make('max_tenure_years')
                            ->label('Maximum Years of Service')
                            ->numeric()
                            ->nullable()
                            ->helperText('Maximum years for this policy (leave empty for no upper limit)'),
                    ])
                    ->columns(2),

                Section::make('Vacation Entitlement')
                    ->schema([
                        TextInput::make('vacation_days_per_year')
                            ->label('Vacation Days Per Year')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->helperText('Number of vacation days earned annually'),

                        TextInput::make('vacation_hours_per_year')
                            ->label('Vacation Hours Per Year')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->helperText('Number of vacation hours earned annually (typically days × 8)'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy_name')
                    ->label('Policy Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('min_tenure_years')
                    ->label('Min Years')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('max_tenure_years')
                    ->label('Max Years')
                    ->placeholder('No limit')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('vacation_days_per_year')
                    ->label('Days/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('vacation_hours_per_year')
                    ->label('Hours/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All policies')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacationPolicies::route('/'),
            'create' => CreateVacationPolicy::route('/create'),
            'edit' => EditVacationPolicy::route('/{record}/edit'),
        ];
    }
}
