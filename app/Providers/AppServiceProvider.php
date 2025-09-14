<?php

namespace App\Providers;

use App\Services\Shift\ShiftScheduleService;
use App\Services\Logging\AutoLogger;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Correct namespace for ShiftScheduleService
        $this->app->singleton(ShiftScheduleService::class, function ($app) {
            return new ShiftScheduleService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define a custom Carbon macro for formatting
        Carbon::macro('toCustomFormat', function () {
            return $this->format('Y-m-d H:i:s');
        });

        // Intelligent logging for views (Only logs if `company_setup.logging_level` is `debug` or `info`)
        View::composer('*', function ($view) {
            AutoLogger::log('info', "View Loaded: " . $view->getName(), $view->getData());
        });

        // Intelligent SQL Query Logging (Only logs if `company_setup.logging_level` is `debug`)
        DB::listen(function ($query) {
            AutoLogger::logDatabaseQuery($query->sql, $query->bindings, $query->time);
        });

        // Processing indicator will be added via individual pages as needed
    }
}
