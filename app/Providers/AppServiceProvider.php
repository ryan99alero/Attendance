<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
