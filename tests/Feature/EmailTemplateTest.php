<?php

use App\Mail\TemplatedMail;
use App\Mail\Templates\VacationRequestSubmitted;
use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\VacationRequest;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Clear and re-sync templates to database for each test
    EmailTemplate::query()->delete();
    app(EmailTemplateService::class)->syncTemplates();
});

describe('EmailTemplate Model', function () {
    it('renders subject with variable replacement', function () {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{employees.first_name}} {{employees.last_name}}',
        ]);

        $rendered = $template->renderSubject([
            'employees.first_name' => 'John',
            'employees.last_name' => 'Smith',
        ]);

        expect($rendered)->toBe('Hello John Smith');
    });

    it('renders body with variable replacement', function () {
        $template = EmailTemplate::factory()->create([
            'body' => 'Dear {{employees.first_name}}, your request for {{vacation_requests.hours_requested}} hours is pending.',
        ]);

        $rendered = $template->renderBody([
            'employees.first_name' => 'Jane',
            'vacation_requests.hours_requested' => '40',
        ]);

        expect($rendered)->toBe('Dear Jane, your request for 40 hours is pending.');
    });

    it('handles conditional blocks in body', function () {
        $template = EmailTemplate::factory()->create([
            'body' => 'Request submitted.{{#vacation_requests.notes}} Notes: {{vacation_requests.notes}}{{/vacation_requests.notes}} End.',
        ]);

        // With notes
        $withNotes = $template->renderBody([
            'vacation_requests.notes' => 'Family vacation',
        ]);
        expect($withNotes)->toBe('Request submitted. Notes: Family vacation End.');

        // Without notes
        $withoutNotes = $template->renderBody([
            'vacation_requests.notes' => '',
        ]);
        expect($withoutNotes)->toBe('Request submitted. End.');
    });

    it('removes unreplaced variables', function () {
        $template = EmailTemplate::factory()->create([
            'body' => 'Hello {{missing.variable}}, your balance is {{vacation_balances.available}}.',
        ]);

        $rendered = $template->renderBody([]);

        expect($rendered)->toBe('Hello , your balance is .');
    });
});

describe('EmailTemplateService', function () {
    it('discovers template definitions from Mail/Templates folder', function () {
        $service = app(EmailTemplateService::class);

        $templates = $service->discoverTemplates();

        expect($templates)->toHaveCount(5)
            ->and($templates->contains('App\\Mail\\Templates\\VacationRequestSubmitted'))->toBeTrue()
            ->and($templates->contains('App\\Mail\\Templates\\VacationRequestApproved'))->toBeTrue()
            ->and($templates->contains('App\\Mail\\Templates\\VacationRequestDenied'))->toBeTrue()
            ->and($templates->contains('App\\Mail\\Templates\\DeviceOfflineAlert'))->toBeTrue()
            ->and($templates->contains('App\\Mail\\Templates\\DeviceBackOnlineAlert'))->toBeTrue();
    });

    it('syncs templates to database', function () {
        // Clear existing templates
        EmailTemplate::query()->delete();

        $service = app(EmailTemplateService::class);
        $result = $service->syncTemplates();

        expect($result['created'])->toBe(5)
            ->and($result['skipped'])->toBe(0)
            ->and(EmailTemplate::count())->toBe(5);
    });

    it('does not overwrite existing templates on sync', function () {
        $service = app(EmailTemplateService::class);

        // Modify a template
        $template = EmailTemplate::where('key', 'vacation.request.submitted')->first();
        $template->update(['subject' => 'Custom Subject']);

        // Re-sync
        $result = $service->syncTemplates();

        // Should skip existing templates
        expect($result['created'])->toBe(0)
            ->and($result['skipped'])->toBe(5);

        // Custom subject should remain
        $template->refresh();
        expect($template->subject)->toBe('Custom Subject');
    });

    it('renders template by key', function () {
        $service = app(EmailTemplateService::class);

        $rendered = $service->render('vacation.request.submitted', [
            'employees.first_name' => 'John',
            'employees.last_name' => 'Smith',
            'departments.name' => 'Engineering',
            'vacation_requests.start_date' => 'Mar 15, 2026',
            'vacation_requests.end_date' => 'Mar 20, 2026',
            'vacation_requests.hours_requested' => '40',
            'managers.first_name' => 'Jane',
            'url' => 'https://example.com/review',
        ]);

        expect($rendered['subject'])->toContain('John Smith')
            ->and($rendered['body'])->toContain('John Smith')
            ->and($rendered['body'])->toContain('Engineering')
            ->and($rendered['body'])->toContain('Mar 15, 2026');
    });

    it('throws exception for non-existent template', function () {
        $service = app(EmailTemplateService::class);

        $service->render('non.existent.template', []);
    })->throws(InvalidArgumentException::class);

    it('resets template to default', function () {
        $service = app(EmailTemplateService::class);

        $template = EmailTemplate::where('key', 'vacation.request.submitted')->first();
        $defaultSubject = VacationRequestSubmitted::getDefaultSubject();

        // Modify template
        $template->update(['subject' => 'Custom Subject', 'body' => 'Custom Body']);

        // Reset
        $result = $service->resetToDefault($template);

        expect($result)->toBeTrue();

        $template->refresh();
        expect($template->subject)->toBe($defaultSubject);
    });

    it('sends email using template', function () {
        Mail::fake();

        $service = app(EmailTemplateService::class);

        $service->send('vacation.request.submitted', 'test@example.com', [
            'employees.first_name' => 'John',
            'employees.last_name' => 'Smith',
            'departments.name' => 'Engineering',
            'vacation_requests.start_date' => 'Mar 15, 2026',
            'vacation_requests.end_date' => 'Mar 20, 2026',
            'vacation_requests.hours_requested' => '40',
            'managers.first_name' => 'Jane',
            'url' => 'https://example.com/review',
        ]);

        Mail::assertQueued(TemplatedMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    });

    it('does not send email when template is inactive', function () {
        Mail::fake();

        // Disable template
        $template = EmailTemplate::where('key', 'vacation.request.submitted')->first();
        $template->update(['is_active' => false]);

        $service = app(EmailTemplateService::class);
        $service->send('vacation.request.submitted', 'test@example.com', []);

        Mail::assertNothingQueued();
    });

    it('sends test email with sample data', function () {
        Mail::fake();

        $service = app(EmailTemplateService::class);
        $service->sendTest('vacation.request.submitted', 'test@example.com');

        Mail::assertQueued(TemplatedMail::class, function ($mail) {
            return $mail->hasTo('test@example.com')
                && str_contains($mail->renderedSubject, '[TEST]');
        });
    });
});

describe('VacationRequestSubmitted Definition', function () {
    it('returns correct key', function () {
        expect(VacationRequestSubmitted::getKey())->toBe('vacation.request.submitted');
    });

    it('returns available variables', function () {
        $variables = VacationRequestSubmitted::getAvailableVariables();

        expect($variables)->toHaveKey('employees.first_name')
            ->and($variables)->toHaveKey('vacation_requests.start_date')
            ->and($variables)->toHaveKey('url');
    });

    it('builds data from vacation request', function () {
        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $request = VacationRequest::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-20',
            'hours_requested' => 40,
        ]);

        $definition = new VacationRequestSubmitted($request);
        $data = $definition->buildData();

        expect($data['employees.first_name'])->toBe('John')
            ->and($data['employees.last_name'])->toBe('Smith')
            ->and($data['vacation_requests.hours_requested'])->toBe('40.00');
    });
});
