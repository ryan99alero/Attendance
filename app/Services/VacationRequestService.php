<?php

namespace App\Services;

use App\Mail\Templates\VacationRequestApproved;
use App\Mail\Templates\VacationRequestDenied;
use App\Mail\Templates\VacationRequestSubmitted;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationCalendar;
use App\Models\VacationRequest;
use App\Models\VacationTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VacationRequestService
{
    public function __construct(
        protected EmailTemplateService $emailTemplateService
    ) {}

    /**
     * Create a new vacation request for an employee.
     *
     * @param  array{start_date: string, end_date: string, is_half_day?: bool, notes?: string|null}  $data
     */
    public function createRequest(Employee $employee, array $data): VacationRequest
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $isHalfDay = $data['is_half_day'] ?? false;

        $hoursRequested = VacationRequest::calculateHoursRequested($startDate, $endDate, $isHalfDay);

        $request = VacationRequest::create([
            'employee_id' => $employee->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_half_day' => $isHalfDay,
            'hours_requested' => $hoursRequested,
            'notes' => $data['notes'] ?? null,
            'status' => VacationRequest::STATUS_PENDING,
            'created_by' => auth()->id(),
        ]);

        $this->notifyManagerOfRequest($request);

        return $request;
    }

    /**
     * Approve a vacation request.
     */
    public function approveRequest(VacationRequest $request, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($request, $reviewer, $notes) {
            $request->update([
                'status' => VacationRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->createVacationCalendarEntries($request);
            $this->updateVacationBalance($request);
            $this->createUsageTransaction($request);
        });

        $this->notifyEmployeeOfApproval($request);
    }

    /**
     * Deny a vacation request.
     */
    public function denyRequest(VacationRequest $request, User $reviewer, string $reason): void
    {
        $request->update([
            'status' => VacationRequest::STATUS_DENIED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        $this->notifyEmployeeOfDenial($request);
    }

    /**
     * Get pending requests for a manager's departments.
     */
    public function getManagerPendingRequests(Employee $manager): Collection
    {
        return VacationRequest::pending()
            ->forManager($manager->id)
            ->with(['employee.department'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all pending requests (admin only).
     */
    public function getAllPendingRequests(): Collection
    {
        return VacationRequest::pending()
            ->with(['employee.department'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if employee has sufficient vacation balance.
     */
    public function hasAvailableBalance(Employee $employee, float $hoursRequested): bool
    {
        $balance = VacationBalance::where('employee_id', $employee->id)->first();

        if (! $balance) {
            return false;
        }

        $available = ($balance->accrued_hours ?? 0) + ($balance->carry_over_hours ?? 0) - ($balance->used_hours ?? 0);

        return $available >= $hoursRequested;
    }

    /**
     * Create VacationCalendar entries for each business day in the request.
     */
    protected function createVacationCalendarEntries(VacationRequest $request): void
    {
        $current = $request->start_date->copy();

        while ($current <= $request->end_date) {
            if (! $current->isWeekend()) {
                VacationCalendar::create([
                    'employee_id' => $request->employee_id,
                    'vacation_date' => $current->toDateString(),
                    'is_half_day' => $request->is_half_day,
                    'is_active' => true,
                    'is_recorded' => false,
                    'created_by' => auth()->id(),
                ]);
            }
            $current->addDay();
        }
    }

    /**
     * Update vacation balance with used hours.
     */
    protected function updateVacationBalance(VacationRequest $request): void
    {
        $balance = VacationBalance::where('employee_id', $request->employee_id)->first();

        if ($balance) {
            $balance->increment('used_hours', $request->hours_requested);
        }
    }

    /**
     * Create a vacation usage transaction for tracking.
     */
    protected function createUsageTransaction(VacationRequest $request): void
    {
        VacationTransaction::createUsageTransaction(
            $request->employee_id,
            null,
            $request->hours_requested,
            $request->start_date,
            "Vacation request #{$request->id} - {$request->date_range}"
        );
    }

    /**
     * Notify the manager about a new vacation request.
     */
    protected function notifyManagerOfRequest(VacationRequest $request): void
    {
        $employee = $request->employee;
        $department = $employee->department;

        if (! $department || ! $department->manager) {
            return;
        }

        $manager = $department->manager;
        $managerUser = $manager->user;

        if (! $managerUser || ! $managerUser->email) {
            return;
        }

        try {
            $definition = new VacationRequestSubmitted($request);
            $this->emailTemplateService->sendFromDefinition($definition, $managerUser->email);
        } catch (\Exception $e) {
            Log::warning('Failed to send vacation request email: '.$e->getMessage());
        }
    }

    /**
     * Notify employee that their request was approved.
     */
    protected function notifyEmployeeOfApproval(VacationRequest $request): void
    {
        $employee = $request->employee;
        $employeeUser = $employee->user;

        if (! $employeeUser || ! $employeeUser->email) {
            return;
        }

        try {
            $definition = new VacationRequestApproved($request);
            $this->emailTemplateService->sendFromDefinition($definition, $employeeUser->email);
        } catch (\Exception $e) {
            Log::warning('Failed to send approval email: '.$e->getMessage());
        }
    }

    /**
     * Notify employee that their request was denied.
     */
    protected function notifyEmployeeOfDenial(VacationRequest $request): void
    {
        $employee = $request->employee;
        $employeeUser = $employee->user;

        if (! $employeeUser || ! $employeeUser->email) {
            return;
        }

        try {
            $definition = new VacationRequestDenied($request);
            $this->emailTemplateService->sendFromDefinition($definition, $employeeUser->email);
        } catch (\Exception $e) {
            Log::warning('Failed to send denial email: '.$e->getMessage());
        }
    }
}
