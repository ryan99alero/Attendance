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
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $isHalfDay = $data['is_half_day'];
        $isActive = $data['is_active'];

        // Fetch all employees with vacation_pay = true
        if ($data['vacation_pay']) {
            $employees = Employee::where('vacation_pay', true)->get();
        } else {
            $employees = Employee::where('id', $data['employee_id'])->get();
        }
        \Log::info($employees);
        // Debugging: Check the retrieved employees
        if ($employees->isEmpty()) {
            throw new \Exception('No employees found matching the criteria.');
        }

        // Loop through all retrieved employees
        foreach ($employees as $employee) {
            \Log::info("Processing employee: {$employee->id}, {$employee->first_name} {$employee->last_name}");

            // Clone the start date for each employee to avoid mutation
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                if ($currentDate->isWeekday()) {
                    \Log::info("Creating vacation entry for {$employee->id} on {$currentDate->toDateString()}");

                    VacationCalendar::create([
                        'employee_id' => $employee->id,
                        'vacation_date' => $currentDate->toDateString(),
                        'is_half_day' => $isHalfDay,
                        'is_active' => $isActive,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }

                $currentDate->addDay();
            }
        }

        // Return the last created vacation calendar record
        return VacationCalendar::latest()->first();
    }
}
