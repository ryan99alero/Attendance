<?php

namespace App\Filament\Resources\VacationCalendarResource\Pages;

use App\Filament\Resources\VacationCalendarResource;
use App\Models\VacationCalendar;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateVacationCalendar extends CreateRecord
{
    protected static string $resource = VacationCalendarResource::class;

    protected function handleRecordCreation(array $data): VacationCalendar
    {
        \Log::info("Creating VacationCalendar with data: ", $data);

        // Simple creation for single employee, single date
        $vacationCalendar = VacationCalendar::create([
            'employee_id' => $data['employee_id'],
            'vacation_date' => $data['vacation_date'],
            'is_half_day' => $data['is_half_day'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        \Log::info("VacationCalendar record created successfully: ", $vacationCalendar->toArray());

        return $vacationCalendar;
    }
}
