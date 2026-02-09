<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CredentialResource\Pages\ListCredentials;
use App\Filament\Resources\CredentialResource\Pages\CreateCredential;
use App\Filament\Resources\CredentialResource\Pages\EditCredential;
use UnitEnum;
use BackedEnum;

use App\Filament\Resources\CredentialResource\Pages;
use App\Models\Credential;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CredentialResource extends Resource
{
    protected static ?string $model = Credential::class;

    // Navigation Configuration
    protected static string | \UnitEnum | null $navigationGroup = 'Employee Management';
    protected static ?string $navigationLabel = 'Employee Credentials';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_names')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('kind')
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

                TextInput::make('identifier')
                    ->label('Credential Value')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The actual credential value (card number, PIN, etc.)'),

                TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255)
                    ->helperText('Optional descriptive label for this credential'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                DateTimePicker::make('issued_at')
                    ->label('Issued At')
                    ->default(now()),

                DateTimePicker::make('revoked_at')
                    ->label('Revoked At')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('kind')
                    ->label('Type')
                    ->colors([
                        'primary' => 'rfid',
                        'success' => 'nfc',
                        'warning' => 'magstripe',
                        'danger' => 'pin',
                        'secondary' => 'mobile',
                    ]),

                TextColumn::make('identifier')
                    ->label('Credential Value')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Credential copied to clipboard'),

                TextColumn::make('label')
                    ->label('Label')
                    ->searchable(),

                BooleanColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')
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

                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListCredentials::route('/'),
            'create' => CreateCredential::route('/create'),
            'edit' => EditCredential::route('/{record}/edit'),
        ];
    }
}
