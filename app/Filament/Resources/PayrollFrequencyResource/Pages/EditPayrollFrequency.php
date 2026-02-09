<?php

namespace App\Filament\Resources\PayrollFrequencyResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PayrollFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayrollFrequency extends EditRecord
{
    protected static string $resource = PayrollFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
