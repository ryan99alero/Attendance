<?php

namespace App\Filament\Resources\PayrollFrequencyResource\Pages;

use App\Filament\Resources\PayrollFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayrollFrequencies extends ListRecords
{
    protected static string $resource = PayrollFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
