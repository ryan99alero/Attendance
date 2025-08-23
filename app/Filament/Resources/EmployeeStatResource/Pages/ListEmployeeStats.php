<?php

namespace App\Filament\Resources\EmployeeStatResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\EmployeeStatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeStats extends ListRecords
{
    protected static string $resource = EmployeeStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
