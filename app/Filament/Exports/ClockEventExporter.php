<?php

namespace App\Filament\Exports;

use App\Models\ClockEvent;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ClockEventExporter extends Exporter
{
    protected static ?string $model = ClockEvent::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('employee.full_names')
                ->label('Employee'),

            ExportColumn::make('employee.external_id')
                ->label('Employee ID'),

            ExportColumn::make('device.device_name')
                ->label('Device'),

            ExportColumn::make('credential.identifier')
                ->label('Credential'),

            ExportColumn::make('event_time')
                ->label('Event Time'),

            ExportColumn::make('shift_date')
                ->label('Shift Date'),

            ExportColumn::make('event_source')
                ->label('Source'),

            ExportColumn::make('location')
                ->label('Location'),

            ExportColumn::make('confidence')
                ->label('Confidence %'),

            ExportColumn::make('processing_error')
                ->label('Error'),

            ExportColumn::make('notes')
                ->label('Notes'),

            ExportColumn::make('created_at')
                ->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your clock event export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
