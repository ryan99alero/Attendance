<?php

namespace App\Filament\Resources\CompanySetupResource\Pages;

use App\Filament\Resources\CompanySetupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanySetups extends ListRecords
{
    protected static string $resource = CompanySetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
