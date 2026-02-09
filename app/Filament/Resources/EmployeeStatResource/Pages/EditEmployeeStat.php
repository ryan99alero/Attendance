<?php

namespace App\Filament\Resources\EmployeeStatResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\EmployeeStatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeStat extends EditRecord
{
    protected static string $resource = EmployeeStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
