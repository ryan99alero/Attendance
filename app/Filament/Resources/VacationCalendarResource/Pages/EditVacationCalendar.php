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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Fetch the range of vacation dates for the selected employee
        $vacationEntries = VacationCalendar::where('employee_id', $data['employee_id'])->get();

        if ($vacationEntries->isNotEmpty()) {
            $data['start_date'] = $vacationEntries->min('vacation_date');
            $data['end_date'] = $vacationEntries->max('vacation_date');
        } else {
            $data['start_date'] = null;
            $data['end_date'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        // Delete existing entries for the employee in the specified range
        VacationCalendar::where('employee_id', $data['employee_id'])
            ->whereBetween('vacation_date', [$startDate, $endDate])
            ->delete();

        // Recreate vacation entries for the updated range
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekday()) {
                VacationCalendar::create([
                    'employee_id' => $data['employee_id'],
                    'vacation_date' => $date->toDateString(),
                    'is_half_day' => $data['is_half_day'],
                    'is_active' => $data['is_active'],
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        }

        return $data;
    }
}
