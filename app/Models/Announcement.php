<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $audio_type
 * @property string $target_type
 * @property int|null $department_id
 * @property int|null $employee_id
 * @property string $priority
 * @property \Carbon\Carbon|null $starts_at
 * @property \Carbon\Carbon|null $expires_at
 * @property bool $is_active
 * @property bool $require_acknowledgment
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Announcement extends Model
{
    use HasFactory;

    public const AUDIO_NONE = 'none';

    public const AUDIO_BUZZ = 'buzz';

    public const AUDIO_TTS = 'tts';

    public const AUDIO_READ_ALOUD = 'read_aloud';

    public const TARGET_ALL = 'all';

    public const TARGET_DEPARTMENT = 'department';

    public const TARGET_EMPLOYEE = 'employee';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'title',
        'body',
        'audio_type',
        'target_type',
        'department_id',
        'employee_id',
        'priority',
        'starts_at',
        'expires_at',
        'is_active',
        'require_acknowledgment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'require_acknowledgment' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Scope to get only active announcements within their display window.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Scope to get announcements for a specific employee.
     */
    public function scopeForEmployee(Builder $query, Employee $employee): Builder
    {
        return $query->where(function (Builder $q) use ($employee) {
            // All employees
            $q->where('target_type', self::TARGET_ALL)
                // Or their department
                ->orWhere(function (Builder $deptQuery) use ($employee) {
                    $deptQuery->where('target_type', self::TARGET_DEPARTMENT)
                        ->where('department_id', $employee->department_id);
                })
                // Or specifically them
                ->orWhere(function (Builder $empQuery) use ($employee) {
                    $empQuery->where('target_type', self::TARGET_EMPLOYEE)
                        ->where('employee_id', $employee->id);
                });
        });
    }

    /**
     * Scope to get unread announcements for an employee.
     */
    public function scopeUnreadBy(Builder $query, Employee $employee): Builder
    {
        return $query->whereDoesntHave('reads', function (Builder $q) use ($employee) {
            $q->where('employee_id', $employee->id);
        });
    }

    /**
     * Check if this announcement has been read by an employee.
     */
    public function isReadBy(Employee $employee): bool
    {
        return $this->reads()->where('employee_id', $employee->id)->exists();
    }

    /**
     * Check if this announcement has been acknowledged by an employee.
     */
    public function isAcknowledgedBy(Employee $employee): bool
    {
        return $this->reads()
            ->where('employee_id', $employee->id)
            ->whereNotNull('acknowledged_at')
            ->exists();
    }

    /**
     * Mark as read by an employee.
     */
    public function markAsReadBy(Employee $employee, string $via = 'portal'): AnnouncementRead
    {
        return $this->reads()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['read_at' => now(), 'read_via' => $via]
        );
    }

    /**
     * Acknowledge the announcement.
     */
    public function acknowledgeBy(Employee $employee, string $via = 'portal'): AnnouncementRead
    {
        return $this->reads()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['read_at' => now(), 'acknowledged_at' => now(), 'dismissed_at' => null, 'read_via' => $via]
        );
    }

    /**
     * Dismiss the announcement without acknowledging.
     */
    public function dismissBy(Employee $employee, string $via = 'portal'): AnnouncementRead
    {
        $read = $this->reads()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['read_at' => now(), 'dismissed_at' => now(), 'read_via' => $via]
        );

        // Notify creator if acknowledgment was required
        if ($this->require_acknowledgment && $this->creator) {
            $this->notifyCreatorOfDismissal($employee);
        }

        return $read;
    }

    /**
     * Check if this announcement was dismissed without acknowledgment by an employee.
     */
    public function isDismissedWithoutAckBy(Employee $employee): bool
    {
        return $this->reads()
            ->where('employee_id', $employee->id)
            ->whereNotNull('dismissed_at')
            ->whereNull('acknowledged_at')
            ->exists();
    }

    /**
     * Get employees who dismissed without acknowledging.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AnnouncementRead>
     */
    public function getDismissedWithoutAck(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->reads()
            ->with('employee')
            ->whereNotNull('dismissed_at')
            ->whereNull('acknowledged_at')
            ->get();
    }

    /**
     * Get read/acknowledgment statistics for this announcement.
     *
     * @return array{total_recipients: int, read: int, acknowledged: int, dismissed_without_ack: int, pending: int}
     */
    public function getReadStats(): array
    {
        $reads = $this->reads()->get();
        $totalRecipients = $this->getTargetedEmployeeCount();

        return [
            'total_recipients' => $totalRecipients,
            'read' => $reads->count(),
            'acknowledged' => $reads->whereNotNull('acknowledged_at')->count(),
            'dismissed_without_ack' => $reads->whereNotNull('dismissed_at')->whereNull('acknowledged_at')->count(),
            'pending' => $totalRecipients - $reads->count(),
        ];
    }

    /**
     * Get count of targeted employees.
     */
    public function getTargetedEmployeeCount(): int
    {
        return match ($this->target_type) {
            self::TARGET_ALL => Employee::where('is_active', true)->count(),
            self::TARGET_DEPARTMENT => Employee::where('department_id', $this->department_id)->where('is_active', true)->count(),
            self::TARGET_EMPLOYEE => 1,
            default => 0,
        };
    }

    /**
     * Notify the announcement creator that someone dismissed without acknowledging.
     */
    protected function notifyCreatorOfDismissal(Employee $employee): void
    {
        if (! $this->creator) {
            return;
        }

        $this->creator->notify(new \App\Notifications\AnnouncementDismissedNotification($this, $employee));
    }

    /**
     * Get audio type options for forms.
     *
     * @return array<string, string>
     */
    public static function getAudioTypeOptions(): array
    {
        return [
            self::AUDIO_NONE => 'None',
            self::AUDIO_BUZZ => 'Buzz Alert',
            self::AUDIO_TTS => 'Text-to-Speech Alert',
            self::AUDIO_READ_ALOUD => 'Read Message Aloud',
        ];
    }

    /**
     * Get target type options for forms.
     *
     * @return array<string, string>
     */
    public static function getTargetTypeOptions(): array
    {
        return [
            self::TARGET_ALL => 'All Employees',
            self::TARGET_DEPARTMENT => 'Specific Department',
            self::TARGET_EMPLOYEE => 'Specific Employee',
        ];
    }

    /**
     * Get priority options for forms.
     *
     * @return array<string, string>
     */
    public static function getPriorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }
}
