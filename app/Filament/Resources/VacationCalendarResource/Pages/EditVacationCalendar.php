<?php

namespace App\Filament\Resources\VacationCalendarResource\Pages;

use App\Filament\Resources\VacationCalendarResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVacationCalendar extends EditRecord
{
    protected static string $resource = VacationCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
