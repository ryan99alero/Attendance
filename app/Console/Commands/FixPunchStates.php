<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Punch;
use Illuminate\Console\Command;

class FixPunchStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'punch:fix-states {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix punch records and attendance records with unknown or missing punch_state';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Starting punch state fix');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Fix punch records with unknown punch_state
        $this->fixPunchRecords($isDryRun);

        // Fix attendance records with unknown punch_state
        $this->fixAttendanceRecords($isDryRun);

        $this->info('Punch state fix completed');

        return 0;
    }

    private function fixPunchRecords(bool $isDryRun): void
    {
        $unknownPunches = Punch::where(function ($query) {
            $query->where('punch_state', 'unknown')
                ->orWhereNull('punch_state');
        })
            ->whereNotNull('punch_type_id')
            ->get();

        $this->info("Found {$unknownPunches->count()} punch records with unknown/null punch_state");

        $fixedCount = 0;
        foreach ($unknownPunches as $punch) {
            if ($punch->punchType) {
                $correctState = $this->determinePunchState($punch->punchType);

                if ($correctState) {
                    $this->line("Punch ID {$punch->id}: {$punch->punchType->name} -> {$correctState}");

                    if (! $isDryRun) {
                        $punch->update(['punch_state' => $correctState]);
                    }
                    $fixedCount++;
                }
            }
        }

        $action = $isDryRun ? 'Would fix' : 'Fixed';
        $this->info("{$action} {$fixedCount} punch records");
    }

    private function fixAttendanceRecords(bool $isDryRun): void
    {
        $unknownAttendance = Attendance::where(function ($query) {
            $query->where('punch_state', 'unknown')
                ->orWhereNull('punch_state');
        })
            ->whereNotNull('punch_type_id')
            ->get();

        $this->info("Found {$unknownAttendance->count()} attendance records with unknown/null punch_state");

        $fixedCount = 0;
        foreach ($unknownAttendance as $attendance) {
            if ($attendance->punchType) {
                $correctState = $this->determinePunchState($attendance->punchType);

                if ($correctState) {
                    $this->line("Attendance ID {$attendance->id}: {$attendance->punchType->name} -> {$correctState}");

                    if (! $isDryRun) {
                        $attendance->update(['punch_state' => $correctState]);
                    }
                    $fixedCount++;
                }
            }
        }

        $action = $isDryRun ? 'Would fix' : 'Fixed';
        $this->info("{$action} {$fixedCount} attendance records");
    }

    /**
     * Get the punch state from the PunchType's punch_direction field.
     * Uses database-driven values instead of hardcoded mappings.
     */
    private function determinePunchState(\App\Models\PunchType $punchType): ?string
    {
        return $punchType->punch_direction;
    }
}
