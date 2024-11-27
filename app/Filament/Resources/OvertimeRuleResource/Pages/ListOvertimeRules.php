<?php

namespace App\Filament\Resources\OvertimeRuleResource\Pages;

use App\Filament\Resources\OvertimeRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeRules extends ListRecords
{
    protected static string $resource = OvertimeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
