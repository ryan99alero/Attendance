<?php

namespace App\Mail\Templates;

use App\Contracts\EmailTemplateDefinition;
use App\Models\VacationRequest;

class VacationRequestDenied implements EmailTemplateDefinition
{
    public function __construct(
        public VacationRequest $request
    ) {}

    public static function getKey(): string
    {
        return 'vacation.request.denied';
    }

    public static function getName(): string
    {
        return 'Vacation Request Denied';
    }

    public static function getDescription(): string
    {
        return 'Sent to the employee when their vacation request has been denied by their manager.';
    }

    public static function getAvailableVariables(): array
    {
        return [
            'employees.first_name' => ['label' => 'Employee First Name', 'example' => 'John'],
            'employees.last_name' => ['label' => 'Employee Last Name', 'example' => 'Smith'],
            'vacation_requests.start_date' => ['label' => 'Start Date', 'example' => 'Mar 15, 2026'],
            'vacation_requests.end_date' => ['label' => 'End Date', 'example' => 'Mar 20, 2026'],
            'vacation_requests.hours_requested' => ['label' => 'Hours Requested', 'example' => '40'],
            'vacation_requests.review_notes' => ['label' => 'Denial Reason', 'example' => 'Insufficient coverage during this period'],
            'reviewers.first_name' => ['label' => 'Reviewer First Name', 'example' => 'Jane'],
            'reviewers.last_name' => ['label' => 'Reviewer Last Name', 'example' => 'Doe'],
            'url' => ['label' => 'View Request URL', 'example' => 'https://attend.test/employee/vacation'],
        ];
    }

    public static function getDefaultSubject(): string
    {
        return 'Your Time Off Request Has Been Denied';
    }

    public static function getDefaultBody(): string
    {
        return <<<'BODY'
Hello {{employees.first_name}},

Unfortunately, your time off request has been **denied**.

**Request Details:**
- **Dates:** {{vacation_requests.start_date}} - {{vacation_requests.end_date}}
- **Hours:** {{vacation_requests.hours_requested}}

**Reason:** {{vacation_requests.review_notes}}

If you have questions about this decision, please contact your manager.

[View My Vacation]({{url}})

Thank you,
Time & Attendance System
BODY;
    }

    public function getContextualUrl(): string
    {
        return route('filament.employee.pages.vacation-page');
    }

    public static function getSampleData(): array
    {
        return [
            'employees.first_name' => 'John',
            'employees.last_name' => 'Smith',
            'vacation_requests.start_date' => 'Mar 15, 2026',
            'vacation_requests.end_date' => 'Mar 20, 2026',
            'vacation_requests.hours_requested' => '40',
            'vacation_requests.review_notes' => 'Insufficient coverage during this period',
            'reviewers.first_name' => 'Jane',
            'reviewers.last_name' => 'Doe',
        ];
    }

    public function buildData(): array
    {
        $employee = $this->request->employee;
        $reviewer = $this->request->reviewer;

        return [
            'employees.first_name' => $employee->first_name,
            'employees.last_name' => $employee->last_name,
            'vacation_requests.start_date' => $this->request->start_date->format('M j, Y'),
            'vacation_requests.end_date' => $this->request->end_date->format('M j, Y'),
            'vacation_requests.hours_requested' => (string) $this->request->hours_requested,
            'vacation_requests.review_notes' => $this->request->review_notes ?? '',
            'reviewers.first_name' => $reviewer?->name ?? '',
            'reviewers.last_name' => '',
        ];
    }
}
