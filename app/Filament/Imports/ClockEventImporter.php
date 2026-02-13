<?php

namespace App\Filament\Imports;

use App\Models\ClockEvent;
use App\Models\Credential;
use App\Models\Employee;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class ClockEventImporter extends Importer
{
    protected static ?string $model = ClockEvent::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('employee')
                ->relationship(resolveUsing: function (string $state): ?Employee {
                    // Try to match by full_names or external_id
                    return Employee::where('full_names', $state)
                        ->orWhere('external_id', $state)
                        ->first();
                })
                ->label('Employee (Name or External ID)'),

            ImportColumn::make('device')
                ->relationship(resolveUsing: 'device_name')
                ->label('Device Name'),

            ImportColumn::make('credential')
                ->fillRecordUsing(function (ClockEvent $record, ?string $state): void {
                    if (empty($state)) {
                        $record->credential_id = null;

                        return;
                    }

                    // Try exact match first
                    $credential = Credential::where('identifier', $state)->first();

                    // Try with leading zero
                    if (! $credential) {
                        $credential = Credential::where('identifier', '0'.$state)->first();
                    }

                    // Try without leading zeros
                    if (! $credential) {
                        $credential = Credential::where('identifier', ltrim($state, '0'))->first();
                    }

                    // Set credential_id if found, otherwise leave null (don't fail)
                    $record->credential_id = $credential?->id;
                })
                ->label('Credential Identifier'),

            ImportColumn::make('event_time')
                ->requiredMapping()
                ->rules(['required', 'date']),

            ImportColumn::make('shift_date')
                ->rules(['date']),

            ImportColumn::make('event_source')
                ->rules(['max:50']),

            ImportColumn::make('location')
                ->rules(['max:255']),

            ImportColumn::make('confidence')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0', 'max:100']),

            ImportColumn::make('notes')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): ClockEvent
    {
        return new ClockEvent;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your clock event import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
