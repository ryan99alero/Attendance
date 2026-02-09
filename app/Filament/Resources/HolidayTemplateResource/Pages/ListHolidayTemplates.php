<?php

namespace App\Filament\Resources\HolidayTemplateResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\HolidayTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHolidayTemplates extends ListRecords
{
    protected static string $resource = HolidayTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
