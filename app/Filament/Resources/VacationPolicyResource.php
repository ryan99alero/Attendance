<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationPolicyResource\Pages;
use App\Filament\Resources\VacationPolicyResource\RelationManagers;
use App\Models\VacationPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VacationPolicyResource extends Resource
{
    protected static ?string $model = VacationPolicy::class;

    protected static ?string $navigationGroup = 'Time Off Management';
    protected static ?string $navigationLabel = 'Vacation Policies';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Policy Information')
                    ->schema([
                        Forms\Components\TextInput::make('policy_name')
                            ->label('Policy Name')
                            ->required()
                            ->placeholder('e.g., Years 1-5, Year 6, etc.')
                            ->helperText('Descriptive name for this vacation tier'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this policy is currently active'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Order for displaying policies (lower numbers first)'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Tenure Requirements')
                    ->schema([
                        Forms\Components\TextInput::make('min_tenure_years')
                            ->label('Minimum Years of Service')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->helperText('Minimum years of service to qualify for this policy'),

                        Forms\Components\TextInput::make('max_tenure_years')
                            ->label('Maximum Years of Service')
                            ->numeric()
                            ->nullable()
                            ->helperText('Maximum years for this policy (leave empty for no upper limit)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Vacation Entitlement')
                    ->schema([
                        Forms\Components\TextInput::make('vacation_days_per_year')
                            ->label('Vacation Days Per Year')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->helperText('Number of vacation days earned annually'),

                        Forms\Components\TextInput::make('vacation_hours_per_year')
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
                Tables\Columns\TextColumn::make('policy_name')
                    ->label('Policy Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('min_tenure_years')
                    ->label('Min Years')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('max_tenure_years')
                    ->label('Max Years')
                    ->placeholder('No limit')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('vacation_days_per_year')
                    ->label('Days/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('vacation_hours_per_year')
                    ->label('Hours/Year')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All policies')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListVacationPolicies::route('/'),
            'create' => Pages\CreateVacationPolicy::route('/create'),
            'edit' => Pages\EditVacationPolicy::route('/{record}/edit'),
        ];
    }
}
