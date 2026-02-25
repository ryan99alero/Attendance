<?php

namespace App\Mail\Templates;

use App\Contracts\EmailTemplateDefinition;
use App\Models\VacationRequest;

class VacationRequestSubmitted implements EmailTemplateDefinition
{
    public function __construct(
        public VacationRequest $request
    ) {}

    public static function getKey(): string
    {
        return 'vacation.request.submitted';
    }

    public static function getName(): string
    {
        return 'Vacation Request Submitted';
    }

    public static function getDescription(): string
    {
        return 'Sent to department manager when an employee submits a vacation request for review.';
    }

    public static function getAvailableVariables(): array
    {
        return [
            'employees.first_name' => ['label' => 'Employee First Name', 'example' => 'John'],
            'employees.last_name' => ['label' => 'Employee Last Name', 'example' => 'Smith'],
            'employees.email' => ['label' => 'Employee Email', 'example' => 'john@company.com'],
            'vacation_requests.start_date' => ['label' => 'Start Date', 'example' => 'Mar 15, 2026'],
            'vacation_requests.end_date' => ['label' => 'End Date', 'example' => 'Mar 20, 2026'],
            'vacation_requests.hours_requested' => ['label' => 'Hours Requested', 'example' => '40'],
            'vacation_requests.notes' => ['label' => 'Employee Notes', 'example' => 'Family vacation'],
            'vacation_requests.is_half_day' => ['label' => 'Is Half Day', 'example' => 'No'],
            'departments.name' => ['label' => 'Department Name', 'example' => 'Engineering'],
            'managers.first_name' => ['label' => 'Manager First Name', 'example' => 'Jane'],
            'managers.last_name' => ['label' => 'Manager Last Name', 'example' => 'Doe'],
            'url' => ['label' => 'Review URL', 'example' => 'https://attend.test/admin/vacation-management'],
        ];
    }

    public static function getDefaultSubject(): string
    {
        return 'Time Off Request from {{employees.first_name}} {{employees.last_name}}';
    }

    public static function getDefaultBody(): string
    {
        return <<<'BODY'
Hello {{managers.first_name}},

**{{employees.first_name}} {{employees.last_name}}** has submitted a time off request that requires your review.

**Request Details:**
- **Dates:** {{vacation_requests.start_date}} - {{vacation_requests.end_date}}
- **Hours:** {{vacation_requests.hours_requested}}
- **Department:** {{departments.name}}

{{#vacation_requests.notes}}
**Employee Notes:** {{vacation_requests.notes}}
{{/vacation_requests.notes}}

Please review this request at your earliest convenience.

[Review Request]({{url}})

Thank you,
Time & Attendance System
BODY;
    }

    public function getContextualUrl(): string
    {
        return route('filament.admin.pages.vacation-management');
    }

    public static function getSampleData(): array
    {
        return [
            'employees.first_name' => 'John',
            'employees.last_name' => 'Smith',
            'employees.email' => 'john@company.com',
            'vacation_requests.start_date' => 'Mar 15, 2026',
            'vacation_requests.end_date' => 'Mar 20, 2026',
            'vacation_requests.hours_requested' => '40',
            'vacation_requests.notes' => 'Family vacation',
            'vacation_requests.is_half_day' => 'No',
            'departments.name' => 'Engineering',
            'managers.first_name' => 'Jane',
            'managers.last_name' => 'Doe',
        ];
    }

    public function buildData(): array
    {
        $employee = $this->request->employee;
        $department = $employee->department;
        $manager = $department?->manager;

        return [
            'employees.first_name' => $employee->first_name,
            'employees.last_name' => $employee->last_name,
            'employees.email' => $employee->user?->email ?? '',
            'vacation_requests.start_date' => $this->request->start_date->format('M j, Y'),
            'vacation_requests.end_date' => $this->request->end_date->format('M j, Y'),
            'vacation_requests.hours_requested' => (string) $this->request->hours_requested,
            'vacation_requests.notes' => $this->request->notes ?? '',
            'vacation_requests.is_half_day' => $this->request->is_half_day ? 'Yes' : 'No',
            'departments.name' => $department?->name ?? 'N/A',
            'managers.first_name' => $manager?->first_name ?? '',
            'managers.last_name' => $manager?->last_name ?? '',
        ];
    }
}
