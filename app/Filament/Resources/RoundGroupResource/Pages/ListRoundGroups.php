<?php

namespace App\Filament\Resources\RoundGroupResource\Pages;

use App\Filament\Resources\RoundGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoundGroups extends ListRecords
{
    protected static string $resource = RoundGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
