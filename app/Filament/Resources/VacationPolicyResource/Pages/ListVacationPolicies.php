<?php

namespace App\Filament\Resources\VacationPolicyResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VacationPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVacationPolicies extends ListRecords
{
    protected static string $resource = VacationPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
