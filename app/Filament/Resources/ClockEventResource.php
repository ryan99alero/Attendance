<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClockEventResource\Pages;
use App\Models\ClockEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClockEventResource extends Resource
{
    protected static ?string $model = ClockEvent::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Time Tracking';
    protected static ?string $navigationLabel = 'Clock Events';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = -95;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names')
                    ->searchable()
                    ->nullable(),

                Forms\Components\Select::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('credential_id')
                    ->label('Credential')
                    ->relationship('credential', 'id')
                    ->searchable()
                    ->required(),


                Forms\Components\DateTimePicker::make('event_time')
                    ->label('Event Time')
                    ->required(),

                Forms\Components\DatePicker::make('shift_date')
                    ->label('Shift Date')
                    ->nullable(),

                Forms\Components\TextInput::make('event_source')
                    ->label('Event Source')
                    ->maxLength(50),

                Forms\Components\TextInput::make('location')
                    ->label('Location')
                    ->maxLength(255),

                Forms\Components\TextInput::make('confidence')
                    ->label('Confidence')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Unknown'),

                Tables\Columns\TextColumn::make('device.device_name')
                    ->label('Device')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('credential.id')
                    ->label('Credential ID')
                    ->sortable(),


                Tables\Columns\TextColumn::make('event_time')
                    ->label('Event Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('shift_date')
                    ->label('Shift Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_source')
                    ->label('Source')
                    ->sortable(),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_id')
                    ->label('Device')
                    ->relationship('device', 'device_name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names'),


                Tables\Filters\Filter::make('event_time')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<ClockEvent> $query */
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('event_time', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('event_time', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('event_time', 'desc');
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
            'index' => Pages\ListClockEvents::route('/'),
            'create' => Pages\CreateClockEvent::route('/create'),
            'edit' => Pages\EditClockEvent::route('/{record}/edit'),
        ];
    }
}
