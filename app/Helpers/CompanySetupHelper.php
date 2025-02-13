<?php

namespace App\Helpers;

use App\Models\CompanySetup;
use Illuminate\Support\Facades\Cache;

class CompanySetupHelper
{
    /**
     * Get a setting from company_setup, with caching for performance.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("company_setup_{$key}", now()->addMinutes(60), function () use ($key, $default) {
            return CompanySetup::query()->value($key) ?? $default;
        });
    }

    /**
     * Invalidate cache when settings are updated.
     */
    public static function clearCache()
    {
        Cache::forget('company_setup_attendance_flexibility_minutes');
        Cache::forget('company_setup_logging_level');
        Cache::forget('company_setup_auto_adjust_punches');
        Cache::forget('company_setup_use_ml_for_punch_matching');
        Cache::forget('company_setup_enforce_shift_schedules');
        Cache::forget('company_setup_allow_manual_time_edits');
        Cache::forget('company_setup_max_shift_length');
    }
}
