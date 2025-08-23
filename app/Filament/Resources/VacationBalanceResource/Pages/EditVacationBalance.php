<?php

namespace App\Filament\Resources\VacationBalanceResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\VacationBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVacationBalance extends EditRecord
{
    protected static string $resource = VacationBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
