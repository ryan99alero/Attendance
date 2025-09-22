<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CredentialResource\Pages;
use App\Models\Credential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CredentialResource extends Resource
{
    protected static ?string $model = Credential::class;

    // Navigation Configuration
    protected static ?string $navigationGroup = 'Employee Management';
    protected static ?string $navigationLabel = 'Employee Credentials';
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('kind')
                    ->label('Credential Type')
                    ->options([
                        'rfid' => 'RFID',
                        'nfc' => 'NFC',
                        'magstripe' => 'Magnetic Stripe',
                        'qrcode' => 'QR Code',
                        'barcode' => 'Barcode',
                        'ble' => 'Bluetooth',
                        'biometric' => 'Biometric',
                        'pin' => 'PIN',
                        'mobile' => 'Mobile App',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('identifier')
                    ->label('Credential Value')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The actual credential value (card number, PIN, etc.)'),

                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255)
                    ->helperText('Optional descriptive label for this credential'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\DateTimePicker::make('issued_at')
                    ->label('Issued At')
                    ->default(now()),

                Forms\Components\DateTimePicker::make('revoked_at')
                    ->label('Revoked At')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('kind')
                    ->label('Type')
                    ->colors([
                        'primary' => 'rfid',
                        'success' => 'nfc',
                        'warning' => 'magstripe',
                        'danger' => 'pin',
                        'secondary' => 'mobile',
                    ]),

                Tables\Columns\TextColumn::make('identifier')
                    ->label('Credential Value')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Credential copied to clipboard'),

                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->searchable(),

                Tables\Columns\BooleanColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        'rfid' => 'RFID',
                        'nfc' => 'NFC',
                        'magstripe' => 'Magnetic Stripe',
                        'qrcode' => 'QR Code',
                        'barcode' => 'Barcode',
                        'ble' => 'Bluetooth',
                        'biometric' => 'Biometric',
                        'pin' => 'PIN',
                        'mobile' => 'Mobile App',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListCredentials::route('/'),
            'create' => Pages\CreateCredential::route('/create'),
            'edit' => Pages\EditCredential::route('/{record}/edit'),
        ];
    }
}
