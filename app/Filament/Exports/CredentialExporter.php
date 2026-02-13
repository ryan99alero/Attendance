<?php

namespace App\Filament\Exports;

use App\Models\Credential;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class CredentialExporter extends Exporter
{
    protected static ?string $model = Credential::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('employee.full_names')
                ->label('Employee'),

            ExportColumn::make('employee.external_id')
                ->label('Employee ID'),

            ExportColumn::make('kind')
                ->label('Type'),

            ExportColumn::make('identifier')
                ->label('Credential Value'),

            ExportColumn::make('label')
                ->label('Label'),

            ExportColumn::make('is_active')
                ->label('Active'),

            ExportColumn::make('issued_at')
                ->label('Issued At'),

            ExportColumn::make('revoked_at')
                ->label('Revoked At'),

            ExportColumn::make('last_used_at')
                ->label('Last Used'),

            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your credential export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
