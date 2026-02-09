<?php

namespace App\Filament\Resources\VacationCalendarResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\VacationCalendarResource;
use App\Models\VacationCalendar;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVacationCalendar extends EditRecord
{
    protected static string $resource = VacationCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Add audit fields
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
