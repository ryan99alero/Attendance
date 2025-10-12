<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

/**
 * KoolReport Laravel 11 Compatibility Fix
 *
 * This class provides compatibility fixes for KoolReport with Laravel 11
 * by adding missing methods that KoolReport expects to exist.
 */
class KoolReportLaravelCompatibility
{
    /**
     * Initialize compatibility fixes
     */
    public static function initialize()
    {
        // Force register the clone macro early
        Collection::macro('clone', function () {
            return new Collection($this->all());
        });

        // Also add copy method for additional compatibility
        Collection::macro('copy', function () {
            return new Collection($this->all());
        });

        // Ensure toArray exists (it already does in Laravel 11, but ensure compatibility)
        if (!method_exists(Collection::class, 'toArray')) {
            Collection::macro('toArray', function () {
                return $this->all();
            });
        }

        // Debug: Log that our compatibility is loaded
        \Log::debug('KoolReport Laravel 11 compatibility macros loaded');
    }
}