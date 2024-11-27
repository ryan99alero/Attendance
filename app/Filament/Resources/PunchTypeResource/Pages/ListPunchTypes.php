<?php

namespace App\Filament\Resources\PunchTypeResource\Pages;

use App\Filament\Resources\PunchTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPunchTypes extends ListRecords
{
    protected static string $resource = PunchTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
