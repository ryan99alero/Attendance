<?php

namespace App\Providers;

use App\Models\CompanySetup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class DebugToolsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Disable Debugbar by default in register phase
        // It will be enabled in boot() if configured
        if (class_exists(\Barryvdh\Debugbar\Facade::class)) {
            config(['debugbar.enabled' => false]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Skip if running in console (migrations, etc.) or if table doesn't exist
        if ($this->app->runningInConsole()) {
            return;
        }

        // Check if the table exists to avoid errors during migrations
        if (! Schema::hasTable('company_setup')) {
            return;
        }

        try {
            $companySetup = CompanySetup::first();

            if (! $companySetup) {
                return;
            }

            // Configure Debugbar
            if (class_exists(\Barryvdh\Debugbar\Facade::class)) {
                $debugbarEnabled = $companySetup->debugbar_enabled ?? false;
                config(['debugbar.enabled' => $debugbarEnabled]);

                if ($debugbarEnabled) {
                    \Barryvdh\Debugbar\Facade::enable();
                } else {
                    \Barryvdh\Debugbar\Facade::disable();
                }
            }

            // Configure Telescope
            if (class_exists(\Laravel\Telescope\Telescope::class)) {
                $telescopeEnabled = $companySetup->telescope_enabled ?? true;
                config(['telescope.enabled' => $telescopeEnabled]);
            }
        } catch (\Exception $e) {
            // Silently fail if database isn't ready
            // This prevents errors during initial setup or migrations
        }
    }
}
