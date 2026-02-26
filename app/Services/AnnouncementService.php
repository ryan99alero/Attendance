<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class AnnouncementService
{
    /**
     * Send an announcement to all targeted employees.
     */
    public function sendAnnouncement(Announcement $announcement): int
    {
        $users = $this->getTargetUsers($announcement);

        if ($users->isEmpty()) {
            return 0;
        }

        Notification::send($users, new AnnouncementNotification($announcement));

        return $users->count();
    }

    /**
     * Get all User accounts that should receive the announcement.
     *
     * @return Collection<int, User>
     */
    public function getTargetUsers(Announcement $announcement): Collection
    {
        $employees = $this->getTargetEmployees($announcement);

        // Get users linked to these employees
        return User::whereIn('employee_id', $employees->pluck('id'))
            ->whereNotNull('employee_id')
            ->get();
    }

    /**
     * Get all employees targeted by an announcement.
     *
     * @return Collection<int, Employee>
     */
    public function getTargetEmployees(Announcement $announcement): Collection
    {
        $query = Employee::where('is_active', true);

        return match ($announcement->target_type) {
            Announcement::TARGET_ALL => $query->get(),
            Announcement::TARGET_DEPARTMENT => $query->where('department_id', $announcement->department_id)->get(),
            Announcement::TARGET_EMPLOYEE => $query->where('id', $announcement->employee_id)->get(),
            default => collect(),
        };
    }

    /**
     * Get active announcements for an employee (for time clock display).
     *
     * @return Collection<int, Announcement>
     */
    public function getAnnouncementsForEmployee(Employee $employee): Collection
    {
        return Announcement::query()
            ->active()
            ->forEmployee($employee)
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread announcements for an employee.
     *
     * @return Collection<int, Announcement>
     */
    public function getUnreadAnnouncementsForEmployee(Employee $employee): Collection
    {
        return Announcement::query()
            ->active()
            ->forEmployee($employee)
            ->unreadBy($employee)
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get announcements for time clock display (unread or requiring acknowledgment).
     *
     * @return Collection<int, Announcement>
     */
    public function getTimeClockAnnouncements(Employee $employee): Collection
    {
        return Announcement::query()
            ->active()
            ->forEmployee($employee)
            ->where(function ($query) use ($employee) {
                // Unread announcements
                $query->whereDoesntHave('reads', function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                })
                    // OR requiring acknowledgment that hasn't been given
                    ->orWhere(function ($q) use ($employee) {
                        $q->where('require_acknowledgment', true)
                            ->whereDoesntHave('reads', function ($readQuery) use ($employee) {
                                $readQuery->where('employee_id', $employee->id)
                                    ->whereNotNull('acknowledged_at');
                            });
                    });
            })
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark an announcement as read by an employee.
     */
    public function markAsRead(Announcement $announcement, Employee $employee, string $via = 'portal'): void
    {
        $announcement->markAsReadBy($employee, $via);
    }

    /**
     * Acknowledge an announcement by an employee.
     */
    public function acknowledge(Announcement $announcement, Employee $employee, string $via = 'portal'): void
    {
        $announcement->acknowledgeBy($employee, $via);
    }

    /**
     * Get announcement count for employee (for badge display).
     */
    public function getUnreadCount(Employee $employee): int
    {
        return Announcement::query()
            ->active()
            ->forEmployee($employee)
            ->unreadBy($employee)
            ->count();
    }
}
