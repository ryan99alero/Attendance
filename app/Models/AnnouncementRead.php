<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $announcement_id
 * @property int $employee_id
 * @property \Carbon\Carbon $read_at
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property \Carbon\Carbon|null $dismissed_at
 * @property string $read_via
 */
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'announcement_id',
        'employee_id',
        'read_at',
        'acknowledged_at',
        'dismissed_at',
        'read_via',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Check if dismissed without acknowledgment.
     */
    public function isDismissedWithoutAcknowledgment(): bool
    {
        return $this->dismissed_at !== null && $this->acknowledged_at === null;
    }

    /**
     * Check if properly acknowledged.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
