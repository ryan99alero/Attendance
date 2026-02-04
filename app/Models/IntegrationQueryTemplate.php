<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationQueryTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'object_id',
        'name',
        'description',
        'object_name',
        'fields',
        'children',
        'filter',
        'sort',
        'default_limit',
        'max_limit',
        'usage_count',
        'last_used_at',
        'created_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'children' => 'array',
        'default_limit' => 'integer',
        'max_limit' => 'integer',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Build the loadValueObjects request payload
     */
    public function buildPayload(int $offset = 0, ?string $additionalFilter = null): array
    {
        // Merge xpath filters
        $xpathFilter = $this->filter;
        if ($additionalFilter) {
            $xpathFilter = $xpathFilter
                ? "({$xpathFilter}) and ({$additionalFilter})"
                : $additionalFilter;
        }

        return [
            'objectName' => $this->object_name,
            'fields' => $this->fields ?? [],
            'children' => $this->children ?? [],
            'xpathFilter' => $xpathFilter,
            'xpathSorts' => $this->sort,
            'offset' => $offset,
        ];
    }

    /**
     * Record usage of this template
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    // Relationships

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(IntegrationObject::class, 'object_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
