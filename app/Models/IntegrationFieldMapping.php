<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class IntegrationFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'object_id',
        'external_field',
        'external_xpath',
        'external_type',
        'local_field',
        'local_type',
        'transform',
        'transform_options',
        'sync_on_pull',
        'sync_on_push',
        'is_identifier',
    ];

    protected $casts = [
        'transform_options' => 'array',
        'sync_on_pull' => 'boolean',
        'sync_on_push' => 'boolean',
        'is_identifier' => 'boolean',
    ];

    /**
     * Transform a value from external format to local format
     */
    public function transformToLocal($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->transform) {
            'date_ms_to_carbon' => $this->dateMillisecondsToCarbon($value),
            'date_iso_to_carbon' => Carbon::parse($value),
            'cents_to_dollars' => $value / 100,
            'string_to_int' => (int) $value,
            'string_to_float' => (float) $value,
            'string_to_bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json_decode' => json_decode($value, true),
            'trim' => trim($value),
            'uppercase' => strtoupper($value),
            'lowercase' => strtolower($value),
            default => $value,
        };
    }

    /**
     * Transform a value from local format to external format
     */
    public function transformToExternal($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->transform) {
            'date_ms_to_carbon' => $this->carbonToDateMilliseconds($value),
            'date_iso_to_carbon' => $value instanceof Carbon ? $value->toIso8601String() : $value,
            'cents_to_dollars' => (int) ($value * 100),
            'string_to_int' => (string) $value,
            'string_to_float' => (string) $value,
            'string_to_bool' => $value ? 'true' : 'false',
            'json_decode' => json_encode($value),
            default => $value,
        };
    }

    /**
     * Convert milliseconds timestamp to Carbon
     */
    protected function dateMillisecondsToCarbon($milliseconds): Carbon
    {
        return Carbon::createFromTimestampMs($milliseconds);
    }

    /**
     * Convert Carbon to milliseconds timestamp
     */
    protected function carbonToDateMilliseconds($value): int
    {
        if ($value instanceof Carbon) {
            return $value->getTimestampMs();
        }
        return Carbon::parse($value)->getTimestampMs();
    }

    // Relationships

    public function object(): BelongsTo
    {
        return $this->belongsTo(IntegrationObject::class, 'object_id');
    }

    // Scopes

    public function scopeIdentifiers($query)
    {
        return $query->where('is_identifier', true);
    }

    public function scopePullEnabled($query)
    {
        return $query->where('sync_on_pull', true);
    }

    public function scopePushEnabled($query)
    {
        return $query->where('sync_on_push', true);
    }
}
