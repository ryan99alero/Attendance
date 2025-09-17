<?php

namespace App\Filament\Resources\VacationPolicyResource\Pages;

use App\Filament\Resources\VacationPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVacationPolicies extends ListRecords
{
    protected static string $resource = VacationPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
