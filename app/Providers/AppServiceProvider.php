<?php

namespace App\Providers;

use App\Services\AttendanceProcessing\ShiftScheduleService;
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
        // Define a custom Carbon macro for the desired format
        Carbon::macro('toCustomFormat', function () {
            return $this->format('Y-m-d H:i:s');
        });

        // Set the default serialization format to the custom format
        Carbon::serializeUsing(function ($carbon) {
            return $carbon->toCustomFormat();
        });

        // Log every view that is loaded
        View::composer('*', function ($view) {
            Log::channel('view_log')->info("View Loaded: " . $view->getName(), $view->getData());
        });

        // Log all SQL queries executed
        DB::listen(function ($query) {
            Log::channel('sql')->info('SQL Query Executed:', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time . ' ms',
            ]);
        });
    }
}
