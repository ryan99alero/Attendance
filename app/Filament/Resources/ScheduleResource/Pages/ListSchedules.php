<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ShiftScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchedules extends ListRecords
{
    protected static string $resource = ShiftScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
