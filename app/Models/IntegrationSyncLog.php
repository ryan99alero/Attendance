<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'object_id',
        'template_id',
        'operation',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'records_fetched',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'request_payload',
        'response_summary',
        'error_message',
        'error_details',
        'failed_records',
        'triggered_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'records_fetched' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_skipped' => 'integer',
        'records_failed' => 'integer',
        'request_payload' => 'array',
        'response_summary' => 'array',
        'error_details' => 'array',
        'failed_records' => 'array',
    ];

    /**
     * Start a new sync log
     */
    public static function start(
        int $connectionId,
        string $operation,
        ?int $objectId = null,
        ?int $templateId = null,
        ?int $triggeredBy = null,
        ?array $requestPayload = null
    ): self {
        return self::create([
            'connection_id' => $connectionId,
            'object_id' => $objectId,
            'template_id' => $templateId,
            'operation' => $operation,
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => $triggeredBy,
            'request_payload' => $requestPayload,
        ]);
    }

    /**
     * Mark sync as successful
     */
    public function markSuccess(array $stats = [], ?array $responseSummary = null): void
    {
        $this->update([
            'status' => 'success',
            'completed_at' => now(),
            'duration_ms' => $this->started_at->diffInMilliseconds(now()),
            'records_fetched' => $stats['fetched'] ?? 0,
            'records_created' => $stats['created'] ?? 0,
            'records_updated' => $stats['updated'] ?? 0,
            'records_skipped' => $stats['skipped'] ?? 0,
            'response_summary' => $responseSummary,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $message, ?array $errorDetails = null, ?array $failedRecords = null): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_ms' => $this->started_at->diffInMilliseconds(now()),
            'error_message' => $message,
            'error_details' => $errorDetails,
            'failed_records' => $failedRecords,
        ]);
    }

    /**
     * Mark sync as partial (some records failed)
     */
    public function markPartial(array $stats, string $message, ?array $failedRecords = null): void
    {
        $this->update([
            'status' => 'partial',
            'completed_at' => now(),
            'duration_ms' => $this->started_at->diffInMilliseconds(now()),
            'records_fetched' => $stats['fetched'] ?? 0,
            'records_created' => $stats['created'] ?? 0,
            'records_updated' => $stats['updated'] ?? 0,
            'records_skipped' => $stats['skipped'] ?? 0,
            'records_failed' => $stats['failed'] ?? 0,
            'error_message' => $message,
            'failed_records' => $failedRecords,
        ]);
    }

    /**
     * Check if sync is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if sync completed successfully
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Get human-readable duration
     */
    public function getDurationForHumans(): string
    {
        if (!$this->duration_ms) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        $seconds = $this->duration_ms / 1000;
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return $minutes . 'm ' . round($remainingSeconds) . 's';
    }

    /**
     * Get total records processed
     */
    public function getTotalProcessed(): int
    {
        return $this->records_created + $this->records_updated + $this->records_skipped;
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(IntegrationQueryTemplate::class, 'template_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    // Scopes

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'partial']);
    }
}
