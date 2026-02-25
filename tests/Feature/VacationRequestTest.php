<?php

use App\Models\Employee;
use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationCalendar;
use App\Models\VacationRequest;
use App\Services\VacationRequestService;
use Carbon\Carbon;

beforeEach(function () {
    $this->employee = Employee::factory()->create();
    $this->user = User::factory()->create(['employee_id' => $this->employee->id]);

    VacationBalance::create([
        'employee_id' => $this->employee->id,
        'accrued_hours' => 80,
        'used_hours' => 0,
        'carry_over_hours' => 0,
        'cap_hours' => 160,
    ]);
});

describe('VacationRequest Model', function () {
    it('calculates hours requested for a single day', function () {
        $startDate = Carbon::parse('2026-03-02'); // Monday
        $endDate = Carbon::parse('2026-03-02'); // Same day

        $hours = VacationRequest::calculateHoursRequested($startDate, $endDate, false);

        expect($hours)->toBe(8.0);
    });

    it('calculates hours requested for a half day', function () {
        $startDate = Carbon::parse('2026-03-02'); // Monday
        $endDate = Carbon::parse('2026-03-02');

        $hours = VacationRequest::calculateHoursRequested($startDate, $endDate, true);

        expect($hours)->toBe(4.0);
    });

    it('calculates hours requested for multiple days excluding weekends', function () {
        $startDate = Carbon::parse('2026-03-02'); // Monday
        $endDate = Carbon::parse('2026-03-06'); // Friday

        $hours = VacationRequest::calculateHoursRequested($startDate, $endDate, false);

        expect($hours)->toBe(40.0); // 5 business days
    });

    it('calculates hours for a week spanning weekend correctly', function () {
        $startDate = Carbon::parse('2026-03-05'); // Thursday
        $endDate = Carbon::parse('2026-03-09'); // Monday

        $hours = VacationRequest::calculateHoursRequested($startDate, $endDate, false);

        expect($hours)->toBe(24.0); // Thu, Fri, Mon = 3 business days
    });

    it('has pending scope', function () {
        VacationRequest::factory()->pending()->forEmployee($this->employee)->create();
        VacationRequest::factory()->approved()->forEmployee($this->employee)->create();

        $pending = VacationRequest::pending()->where('employee_id', $this->employee->id)->count();

        expect($pending)->toBe(1);
    });

    it('has approved scope', function () {
        $pendingRequest = VacationRequest::factory()->pending()->forEmployee($this->employee)->create();
        $approvedRequest = VacationRequest::factory()->approved()->forEmployee($this->employee)->create();

        $approved = VacationRequest::approved()->where('employee_id', $this->employee->id)->count();

        expect($approved)->toBe(1);
    });

    it('returns correct date range attribute for single day', function () {
        $request = VacationRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
        ]);

        expect($request->date_range)->toBe('Mar 2, 2026');
    });

    it('returns correct date range attribute for multiple days', function () {
        $request = VacationRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
        ]);

        expect($request->date_range)->toBe('Mar 2 - Mar 6, 2026');
    });
});

describe('VacationRequestService', function () {
    it('creates a vacation request with correct hours', function () {
        $service = app(VacationRequestService::class);

        $this->actingAs($this->user);

        $request = $service->createRequest($this->employee, [
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-04',
            'is_half_day' => false,
            'notes' => 'Family vacation',
        ]);

        expect($request->status)->toBe(VacationRequest::STATUS_PENDING)
            ->and($request->hours_requested)->toBe('24.00')
            ->and($request->notes)->toBe('Family vacation');
    });

    it('approves a request and creates calendar entries', function () {
        $service = app(VacationRequestService::class);

        $this->actingAs($this->user);

        $request = VacationRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-02', // Monday
            'end_date' => '2026-03-04', // Wednesday
            'hours_requested' => 24,
            'is_half_day' => false,
        ]);

        $service->approveRequest($request, $this->user, 'Enjoy your time off!');

        $request->refresh();

        expect($request->status)->toBe(VacationRequest::STATUS_APPROVED)
            ->and($request->reviewed_by)->toBe($this->user->id)
            ->and($request->review_notes)->toBe('Enjoy your time off!');

        $calendarEntries = VacationCalendar::where('employee_id', $this->employee->id)->count();
        expect($calendarEntries)->toBe(3); // Mon, Tue, Wed
    });

    it('updates vacation balance on approval', function () {
        $service = app(VacationRequestService::class);

        $this->actingAs($this->user);

        $request = VacationRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'hours_requested' => 8,
            'is_half_day' => false,
        ]);

        $service->approveRequest($request, $this->user);

        $balance = VacationBalance::where('employee_id', $this->employee->id)->first();

        expect($balance->used_hours)->toBe('8.00');
    });

    it('denies a request with reason', function () {
        $service = app(VacationRequestService::class);

        $this->actingAs($this->user);

        $request = VacationRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
        ]);

        $service->denyRequest($request, $this->user, 'Insufficient coverage during this period');

        $request->refresh();

        expect($request->status)->toBe(VacationRequest::STATUS_DENIED)
            ->and($request->reviewed_by)->toBe($this->user->id)
            ->and($request->review_notes)->toBe('Insufficient coverage during this period');
    });

    it('does not create calendar entries when request is denied', function () {
        $service = app(VacationRequestService::class);

        $this->actingAs($this->user);

        $request = VacationRequest::factory()->pending()->create([
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-04',
        ]);

        $service->denyRequest($request, $this->user, 'Denied');

        $calendarEntries = VacationCalendar::where('employee_id', $this->employee->id)->count();
        expect($calendarEntries)->toBe(0);
    });

    it('checks available balance correctly', function () {
        $service = app(VacationRequestService::class);

        expect($service->hasAvailableBalance($this->employee, 40))->toBeTrue()
            ->and($service->hasAvailableBalance($this->employee, 80))->toBeTrue()
            ->and($service->hasAvailableBalance($this->employee, 81))->toBeFalse();
    });
});
