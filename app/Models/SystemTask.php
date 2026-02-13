<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SystemTask extends Model
{
    // Task types
    public const TYPE_IMPORT = 'import';

    public const TYPE_EXPORT = 'export';

    public const TYPE_PROCESSING = 'processing';

    public const TYPE_SYNC = 'sync';

    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'type',
        'name',
        'description',
        'status',
        'progress',
        'progress_message',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'related_model',
        'related_id',
        'file_path',
        'output_file_path',
        'error_message',
        'metadata',
        'created_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'successful_records' => 'integer',
            'failed_records' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

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

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Status checks

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

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    // Status transitions

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(?string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'progress_message' => $message ?? 'Completed successfully',
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

    public function markCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'progress_message' => 'Cancelled by user',
            'completed_at' => now(),
        ]);

        // Reset related PayPeriod if this was a processing task
        $this->resetRelatedModel();
    }

    /**
     * Reset the related model's processing status if applicable
     */
    public function resetRelatedModel(): void
    {
        if ($this->related_model === PayPeriod::class && $this->related_id) {
            $payPeriod = PayPeriod::find($this->related_id);
            if ($payPeriod) {
                $payPeriod->update([
                    'processing_status' => 'pending',
                    'processing_progress' => 0,
                    'processing_message' => null,
                    'processing_started_at' => null,
                    'processing_completed_at' => null,
                    'processing_error' => null,
                ]);
            }
        }
    }

    // Progress updates

    public function updateProgress(int $progress, ?string $message = null, ?int $processed = null): void
    {
        $data = [
            'progress' => min(100, max(0, $progress)),
        ];

        if ($message !== null) {
            $data['progress_message'] = $message;
        }

        if ($processed !== null) {
            $data['processed_records'] = $processed;
        }

        $this->update($data);
    }

    public function incrementProcessed(bool $success = true): void
    {
        $this->increment('processed_records');

        if ($success) {
            $this->increment('successful_records');
        } else {
            $this->increment('failed_records');
        }

        // Update progress percentage
        if ($this->total_records) {
            $progress = (int) (($this->processed_records / $this->total_records) * 100);
            $this->update(['progress' => min(100, $progress)]);
        }
    }

    // Helper methods

    public function getProgressText(): string
    {
        if ($this->isCompleted()) {
            $failed = $this->failed_records > 0 ? " ({$this->failed_records} failed)" : '';

            return "Processed {$this->successful_records} records{$failed}";
        }

        if ($this->isFailed()) {
            return 'Failed: '.($this->error_message ?? 'Unknown error');
        }

        if ($this->total_records && $this->processed_records) {
            return "{$this->processed_records} / {$this->total_records} records";
        }

        return $this->progress_message ?? 'Starting...';
    }

    public function getDuration(): ?string
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $diff = $this->started_at->diff($end);

        if ($diff->h > 0) {
            return $diff->format('%hh %im %ss');
        }

        if ($diff->i > 0) {
            return $diff->format('%im %ss');
        }

        return $diff->format('%ss');
    }

    public function hasOutputFile(): bool
    {
        return $this->output_file_path && Storage::exists($this->output_file_path);
    }

    public function getOutputFileUrl(): ?string
    {
        if (! $this->hasOutputFile()) {
            return null;
        }

        return Storage::url($this->output_file_path);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IMPORT => 'Import',
            self::TYPE_EXPORT => 'Export',
            self::TYPE_PROCESSING => 'Processing',
            self::TYPE_SYNC => 'Sync',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_IMPORT => 'info',
            self::TYPE_EXPORT => 'success',
            self::TYPE_PROCESSING => 'warning',
            self::TYPE_SYNC => 'primary',
            default => 'gray',
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'heroicon-o-clock',
            self::STATUS_PROCESSING => 'heroicon-o-arrow-path',
            self::STATUS_COMPLETED => 'heroicon-o-check-circle',
            self::STATUS_FAILED => 'heroicon-o-x-circle',
            self::STATUS_CANCELLED => 'heroicon-o-no-symbol',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    // Factory methods for creating tasks

    public static function createImport(
        string $name,
        ?string $description = null,
        ?string $filePath = null,
        ?int $totalRecords = null,
        ?int $userId = null
    ): self {
        return self::create([
            'type' => self::TYPE_IMPORT,
            'name' => $name,
            'description' => $description,
            'status' => self::STATUS_PENDING,
            'file_path' => $filePath,
            'total_records' => $totalRecords,
            'progress_message' => 'Queued for processing...',
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function createExport(
        string $name,
        ?string $description = null,
        ?string $relatedModel = null,
        ?int $relatedId = null,
        ?int $userId = null
    ): self {
        return self::create([
            'type' => self::TYPE_EXPORT,
            'name' => $name,
            'description' => $description,
            'status' => self::STATUS_PENDING,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'progress_message' => 'Queued for processing...',
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function createProcessing(
        string $name,
        ?string $description = null,
        ?string $relatedModel = null,
        ?int $relatedId = null,
        ?int $totalRecords = null,
        ?int $userId = null
    ): self {
        return self::create([
            'type' => self::TYPE_PROCESSING,
            'name' => $name,
            'description' => $description,
            'status' => self::STATUS_PENDING,
            'related_model' => $relatedModel,
            'related_id' => $relatedId,
            'total_records' => $totalRecords,
            'progress_message' => 'Queued for processing...',
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    public static function createSync(
        string $name,
        ?string $description = null,
        ?int $userId = null
    ): self {
        return self::create([
            'type' => self::TYPE_SYNC,
            'name' => $name,
            'description' => $description,
            'status' => self::STATUS_PENDING,
            'progress_message' => 'Queued for processing...',
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
