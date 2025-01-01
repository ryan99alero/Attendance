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
        \Log::info("Starting VacationCalendar creation process with data: ", $data);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $isHalfDay = $data['is_half_day'] ?? false;
        $isActive = $data['is_active'] ?? true;

        // Validation: Check if the selected date range includes only weekends
        $currentDate = $startDate->copy();
        $onlyWeekends = true;

        while ($currentDate->lte($endDate)) {
            if ($currentDate->isWeekday()) {
                $onlyWeekends = false;
                break;
            }
            $currentDate->addDay();
        }

        if ($onlyWeekends) {
            \Log::warning("Vacation dates selected are only weekends.");
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_date' => 'Vacation dates cannot include only weekends.',
                'end_date' => 'Please select a date range that includes at least one weekday.',
            ]);
        }

        // Determine employees to process
        $employees = $data['vacation_pay']
            ? Employee::where('vacation_pay', true)->get()
            : Employee::where('id', $data['employee_id'])->get();

        if ($employees->isEmpty()) {
            \Log::error("No employees found matching the criteria.");
            throw new \Exception('No employees found matching the criteria.');
        }

        \Log::info("Number of employees to process: {$employees->count()}");

        $lastCreated = null;

        foreach ($employees as $employee) {
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                if ($currentDate->isWeekday()) {
                    \Log::info("Creating vacation entry for Employee ID: {$employee->id} on {$currentDate->toDateString()}");

                    $vacationCalendar = VacationCalendar::create([
                        'employee_id' => $employee->id,
                        'vacation_date' => $currentDate->toDateString(),
                        'is_half_day' => $isHalfDay,
                        'is_active' => $isActive,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                    $lastCreated = $vacationCalendar;
                    \Log::info("VacationCalendar record created: ", $vacationCalendar->toArray());
                }

                $currentDate->addDay();
            }
        }

        // Ensure at least one record was created
        if (!$lastCreated) {
            \Log::error("No VacationCalendar records were created. Process failed.");
            throw new \Exception('No valid vacation dates were found within the specified range.');
        }

        \Log::info("VacationCalendar creation process completed successfully. Returning the last created record.");

        return $lastCreated;
    }
}
