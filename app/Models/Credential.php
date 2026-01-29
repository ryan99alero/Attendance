<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use HasFactory;

    /**
     * Boot the model - auto-recalculate hash when identifier changes
     */
    protected static function boot()
    {
        parent::boot();

        // Recalculate hash whenever identifier is created or updated
        static::saving(function ($credential) {
            if ($credential->isDirty('identifier') && $credential->identifier) {
                $normalized = static::normalizeIdentifier($credential->identifier);
                $credential->identifier_hash = hash('sha256', $normalized);
                // Note: hash_algo ENUM doesn't include 'sha256', leave it null for identifier hashes
            }
        });
    }

    protected $fillable = [
        'employee_id',
        'kind',
        'identifier',
        'identifier_hash',
        'hash_algo',
        'template_ref',
        'template_hash',
        'label',
        'is_active',
        'issued_at',
        'revoked_at',
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only active credentials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->whereNull('revoked_at');
    }

    /**
     * Find credential by kind and normalized value
     */
    public static function findByKindAndValue(string $kind, string $normalizedValue): ?self
    {
        $hash = hash('sha256', $normalizedValue);

        return static::where('kind', $kind)
                    ->where('identifier_hash', $hash)
                    ->active()
                    ->first();
    }

    /**
     * Create a new credential with proper hashing
     */
    public static function createWithHash(array $attributes): self
    {
        if (isset($attributes['identifier']) && !isset($attributes['identifier_hash'])) {
            $normalized = static::normalizeIdentifier($attributes['identifier']);
            $attributes['identifier_hash'] = hash('sha256', $normalized);
            // Note: hash_algo ENUM doesn't include 'sha256', leave it unset for identifier hashes
        }

        return static::create($attributes);
    }

    /**
     * Normalize credential identifier
     */
    public static function normalizeIdentifier(string $value): string
    {
        $normalized = trim($value);
        $normalized = strtoupper($normalized);
        // Remove common separators for card UIDs, MAC-like strings, barcodes with dashes
        $normalized = preg_replace('/[\s:\-_]/', '', $normalized);

        return $normalized;
    }
}