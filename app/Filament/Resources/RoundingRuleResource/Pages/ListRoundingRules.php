<?php

namespace App\Filament\Resources\RoundingRuleResource\Pages;

use App\Filament\Resources\RoundingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoundingRules extends ListRecords
{
    protected static string $resource = RoundingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
