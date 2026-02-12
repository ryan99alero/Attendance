<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'pay_period_id',
        'integration_connection_id',
        'format',
        'file_path',
        'file_name',
        'employee_count',
        'record_count',
        'status',
        'progress',
        'progress_message',
        'total_employees',
        'processed_employees',
        'error_message',
        'metadata',
        'exported_by',
        'exported_at',
    ];

    protected $casts = [
        'employee_count' => 'integer',
        'record_count' => 'integer',
        'progress' => 'integer',
        'total_employees' => 'integer',
        'processed_employees' => 'integer',
        'metadata' => 'array',
        'exported_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    // Relationships

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class);
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    // Scopes

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForPayPeriod($query, int $payPeriodId)
    {
        return $query->where('pay_period_id', $payPeriodId);
    }

    public function scopeForProvider($query, int $connectionId)
    {
        return $query->where('integration_connection_id', $connectionId);
    }

    // Helper methods

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markCompleted(int $employeeCount, int $recordCount): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'employee_count' => $employeeCount,
            'record_count' => $recordCount,
            'exported_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Update progress during export
     */
    public function updateProgress(int $progress, string $message, ?int $processed = null): void
    {
        $data = [
            'progress' => min(100, max(0, $progress)),
            'progress_message' => $message,
        ];

        if ($processed !== null) {
            $data['processed_employees'] = $processed;
        }

        $this->update($data);
    }

    /**
     * Get progress percentage for display
     */
    public function getProgressPercentage(): int
    {
        return $this->progress ?? 0;
    }

    /**
     * Get formatted progress text
     */
    public function getProgressText(): string
    {
        if ($this->isCompleted()) {
            return "Complete - {$this->employee_count} employees exported";
        }

        if ($this->isFailed()) {
            return "Failed: {$this->error_message}";
        }

        if ($this->total_employees && $this->processed_employees) {
            return "{$this->progress_message} ({$this->processed_employees}/{$this->total_employees})";
        }

        return $this->progress_message ?? 'Starting...';
    }

    /**
     * Generate the file name based on convention
     */
    public static function generateFileName(
        IntegrationConnection $provider,
        PayPeriod $payPeriod,
        string $format
    ): string {
        $providerName = preg_replace('/[^a-zA-Z0-9]/', '', $provider->name);
        $periodName = $payPeriod->name ?? 'Period'.$payPeriod->id;
        $endDate = $payPeriod->end_date->format('Y-m-d');

        return "{$providerName}_PayPeriod_{$periodName}_{$endDate}.{$format}";
    }

    /**
     * Check if the export file exists on disk
     */
    public function fileExists(): bool
    {
        return $this->file_path && file_exists($this->file_path);
    }

    /**
     * Get file size in human readable format
     */
    public function getFileSizeForHumans(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        $bytes = filesize($this->file_path);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Delete the export and its associated file
     */
    public function deleteWithFile(): bool
    {
        // Delete the file if it exists
        if ($this->fileExists()) {
            @unlink($this->file_path);
        }

        // Delete associated SystemTask if exists
        SystemTask::where('related_model', self::class)
            ->where('related_id', $this->id)
            ->delete();

        return $this->delete();
    }
}
