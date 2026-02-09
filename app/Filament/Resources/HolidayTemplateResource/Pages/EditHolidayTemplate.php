<?php

namespace App\Filament\Resources\HolidayTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\HolidayTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHolidayTemplate extends EditRecord
{
    protected static string $resource = HolidayTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
