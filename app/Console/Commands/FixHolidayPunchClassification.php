<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Punch;
use App\Models\PunchType;
use App\Models\Classification;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class FixHolidayPunchClassification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'punch:fix-holiday-classification {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix punch records that need Holiday classification and correct Unknown punch types';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $holidayClassificationId = Classification::where('name', 'Holiday')->value('id');
        $unknownPunchTypeId = PunchType::where('name', 'Unknown')->value('id');

        if (!$holidayClassificationId) {
            $this->error('Holiday classification not found in database');
            return 1;
        }

        $this->info("Starting punch classification fix (Holiday Classification ID: {$holidayClassificationId})");
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Fix punch records with Unknown punch type
        $this->fixUnknownPunchTypes($unknownPunchTypeId, $isDryRun);

        // Fix holiday punch records missing classification
        $this->fixHolidayClassification($holidayClassificationId, $isDryRun);

        $this->info('Punch classification fix completed');
        return 0;
    }

    private function fixUnknownPunchTypes(?int $unknownPunchTypeId, bool $isDryRun): void
    {
        if (!$unknownPunchTypeId) {
            $this->info('No Unknown punch type found - skipping punch type fixes');
            return;
        }

        $unknownPunches = Punch::where('punch_type_id', $unknownPunchTypeId)
            ->whereNotNull('attendance_id')
            ->get();

        $this->info("Found {$unknownPunches->count()} punch records with Unknown punch type");

        $fixedCount = 0;
        foreach ($unknownPunches as $punch) {
            $attendance = $punch->attendance;
            if ($attendance && $attendance->punch_type_id && $attendance->punch_type_id != $unknownPunchTypeId) {
                $correctPunchType = $attendance->punchType;
                $this->line("Punch ID {$punch->id}: Unknown -> {$correctPunchType->name}");

                if (!$isDryRun) {
                    $punch->update(['punch_type_id' => $attendance->punch_type_id]);
                }
                $fixedCount++;
            }
        }

        $action = $isDryRun ? 'Would fix' : 'Fixed';
        $this->info("{$action} {$fixedCount} punch records with Unknown punch type");
    }

    private function fixHolidayClassification(int $holidayClassificationId, bool $isDryRun): void
    {
        // Find punch records that might be holiday-related but missing classification
        $punchesNeedingClassification = Punch::whereNull('classification_id')
            ->whereNotNull('attendance_id')
            ->get();

        $this->info("Found {$punchesNeedingClassification->count()} punch records with NULL classification");

        $holidayCount = 0;
        $this->info('Checking for holiday-related punch records...');

        foreach ($punchesNeedingClassification as $punch) {
            $attendance = $punch->attendance;
            if ($attendance) {
                $notes = $attendance->issue_notes ?? '';
                $isHoliday = str_contains($notes, 'Holiday') ||
                           str_contains($notes, 'Generated from Holiday') ||
                           !is_null($attendance->holiday_id);

                if ($isHoliday) {
                    $this->line("Punch ID {$punch->id}: Setting Holiday classification (Employee: {$punch->employee_id}, Time: {$punch->punch_time})");

                    if (!$isDryRun) {
                        $punch->update(['classification_id' => $holidayClassificationId]);
                    }
                    $holidayCount++;
                }
            }
        }

        $action = $isDryRun ? 'Would update' : 'Updated';
        $this->info("{$action} {$holidayCount} holiday punch records with proper classification");
    }
}
