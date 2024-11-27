<?php

namespace App\Filament\Resources\VacationBalanceResource\Pages;

use App\Filament\Resources\VacationBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVacationBalances extends ListRecords
{
    protected static string $resource = VacationBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
