<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\ShiftSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignEmployeeShiftSchedules extends Command
{
    protected $signature = 'app:assign-employee-shift-schedules
                            {--dry-run : Show what would be changed without making changes}
                            {--force : Update even if employee already has a shift_schedule_id}';

    protected $description = 'Assign shift schedules to employees based on their department and shift';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Shift ID to schedule suffix mapping
        // shift_id 1 = "2nd Shift" → suffix "2"
        // shift_id 2 = "1st Shift" → suffix "1"
        $shiftSuffixMap = [
            1 => '2',
            2 => '1',
            3 => '2', // Extended hours maps to 2nd shift
        ];

        // Pre-load all shift schedules for matching
        // Create multiple lookup keys for each schedule to handle naming variations
        $shiftSchedules = ShiftSchedule::all();
        $scheduleLookup = collect();

        foreach ($shiftSchedules as $schedule) {
            $name = $schedule->schedule_name;
            // Add exact lowercase match
            $scheduleLookup->put(strtolower($name), $schedule);
            // Add normalized version (no spaces, hyphens, underscores)
            $normalized = strtolower(preg_replace('/[\s\-_]/', '', $name));
            $scheduleLookup->put($normalized, $schedule);
        }

        // Get employees that need updating
        $query = Employee::query()
            ->with(['department', 'shift'])
            ->whereNotNull('department_id')
            ->whereNotNull('shift_id')
            ->where('is_active', true);

        if (! $force) {
            // Only process employees whose current schedule doesn't match
            $query->where(function ($q) {
                $q->whereNull('shift_schedule_id')
                    ->orWhereHas('shiftSchedule', function ($sq) {
                        // Will filter in PHP since we need department name comparison
                    });
            });
        }

        $employees = $query->get();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $alreadyCorrect = 0;

        $this->info("Processing {$employees->count()} employees...\n");

        $changes = [];

        foreach ($employees as $employee) {
            $departmentName = $employee->department?->name;
            $shiftId = $employee->shift_id;

            if (! $departmentName || ! isset($shiftSuffixMap[$shiftId])) {
                $skipped++;

                continue;
            }

            $suffix = $shiftSuffixMap[$shiftId];

            // Build expected schedule name: {DepartmentName}{ShiftNumber}
            $expectedName = $departmentName.$suffix;
            $normalizedExpected = strtolower(preg_replace('/[\s\-_]/', '', $expectedName));

            // Try to find matching schedule (exact lowercase first, then normalized)
            $matchedSchedule = $scheduleLookup->get(strtolower($expectedName))
                ?? $scheduleLookup->get($normalizedExpected);

            // If not found, try with T suffix for temps
            if (! $matchedSchedule) {
                $matchedSchedule = $scheduleLookup->get(strtolower($expectedName.'T'))
                    ?? $scheduleLookup->get($normalizedExpected.'t');
            }

            if (! $matchedSchedule) {
                $notFound++;
                $this->warn("  No schedule found for: {$employee->full_names} ({$departmentName} + Shift {$suffix})");

                continue;
            }

            // Check if already correctly assigned
            if ($employee->shift_schedule_id === $matchedSchedule->id) {
                $alreadyCorrect++;

                continue;
            }

            $currentScheduleName = $employee->shiftSchedule?->schedule_name ?? 'None';

            $changes[] = [
                'id' => $employee->id,
                'name' => $employee->full_names,
                'department' => $departmentName,
                'shift' => $employee->shift?->shift_name,
                'from' => $currentScheduleName,
                'to' => $matchedSchedule->schedule_name,
                'new_schedule_id' => $matchedSchedule->id,
            ];

            $updated++;
        }

        // Display changes table
        if (count($changes) > 0) {
            $this->table(
                ['ID', 'Name', 'Department', 'Shift', 'From Schedule', 'To Schedule'],
                collect($changes)->map(fn ($c) => [
                    $c['id'],
                    $c['name'],
                    $c['department'],
                    $c['shift'],
                    $c['from'],
                    $c['to'],
                ])->toArray()
            );
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Will update: {$updated}");
        $this->info("  Already correct: {$alreadyCorrect}");
        $this->info("  No matching schedule: {$notFound}");
        $this->info("  Skipped (missing data): {$skipped}");

        if (! $dryRun && count($changes) > 0) {
            if ($this->confirm('Proceed with updating these employees?', true)) {
                DB::transaction(function () use ($changes) {
                    foreach ($changes as $change) {
                        Employee::where('id', $change['id'])
                            ->update(['shift_schedule_id' => $change['new_schedule_id']]);
                    }
                });

                $this->info("\nUpdated {$updated} employees successfully!");
            } else {
                $this->info("\nNo changes made.");
            }
        }

        return self::SUCCESS;
    }
}
