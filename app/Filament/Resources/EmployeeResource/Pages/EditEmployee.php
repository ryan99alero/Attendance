<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Allow for mutation of form data before filling the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data; // Ensures the data is passed to the form unaltered
    }
}
