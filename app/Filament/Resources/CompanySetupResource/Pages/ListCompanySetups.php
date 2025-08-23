<?php

namespace App\Filament\Resources\CompanySetupResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CompanySetupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanySetups extends ListRecords
{
    protected static string $resource = CompanySetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
