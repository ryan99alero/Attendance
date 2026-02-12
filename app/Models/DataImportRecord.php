<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportRecord extends Model
{
    protected $table = 'data_imports';

    protected $fillable = [
        'model_type',
        'original_file_name',
        'file_path',
        'status',
        'progress',
        'progress_message',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_file_path',
        'error_message',
        'imported_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'progress_message' => 'Import complete',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $progress, string $message, ?int $processed = null): void
    {
        $data = [
            'progress' => min(100, max(0, $progress)),
            'progress_message' => $message,
        ];

        if ($processed !== null) {
            $data['processed_rows'] = $processed;
        }

        $this->update($data);
    }

    public function incrementProcessed(bool $success = true): void
    {
        $this->increment('processed_rows');

        if ($success) {
            $this->increment('successful_rows');
        } else {
            $this->increment('failed_rows');
        }

        // Update progress percentage
        if ($this->total_rows) {
            $progress = (int) (($this->processed_rows / $this->total_rows) * 100);
            $this->update(['progress' => min(100, $progress)]);
        }
    }

    public function getProgressText(): string
    {
        if ($this->isCompleted()) {
            $failed = $this->failed_rows > 0 ? " ({$this->failed_rows} failed)" : '';

            return "Imported {$this->successful_rows} rows{$failed}";
        }

        if ($this->isFailed()) {
            return "Failed: {$this->error_message}";
        }

        if ($this->total_rows && $this->processed_rows) {
            return "{$this->progress_message} ({$this->processed_rows}/{$this->total_rows})";
        }

        return $this->progress_message ?? 'Starting...';
    }

    public function getModelDisplayName(): string
    {
        $className = class_basename($this->model_type);

        return preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }
}
