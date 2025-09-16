<?php

namespace App\Filament\Resources\ClockEventResource\Pages;

use App\Filament\Resources\ClockEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClockEvents extends ListRecords
{
    protected static string $resource = ClockEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
