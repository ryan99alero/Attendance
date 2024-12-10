<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ShiftScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ShiftScheduleResource::class;
}
