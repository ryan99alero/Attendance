<?php

namespace App\Console\Commands;

use App\Services\EmailTemplateService;
use Illuminate\Console\Command;

class SyncEmailTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:sync-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and sync email template definitions to the database';

    /**
     * Execute the console command.
     */
    public function handle(EmailTemplateService $service): int
    {
        $this->info('Discovering email template definitions...');

        $templates = $service->discoverTemplates();

        if ($templates->isEmpty()) {
            $this->warn('No email template definitions found in app/Mail/Templates/');

            return self::SUCCESS;
        }

        $this->info("Found {$templates->count()} template definition(s):");

        foreach ($templates as $class) {
            $this->line("  - {$class::getKey()}: {$class::getName()}");
        }

        $this->newLine();
        $this->info('Syncing templates to database...');

        $result = $service->syncTemplates();

        if ($result['created'] > 0) {
            $this->info("Created {$result['created']} new template(s)");
        }

        if ($result['skipped'] > 0) {
            $this->info("Skipped {$result['skipped']} existing template(s)");
        }

        $this->newLine();
        $this->info('Email template sync complete!');

        return self::SUCCESS;
    }
}
