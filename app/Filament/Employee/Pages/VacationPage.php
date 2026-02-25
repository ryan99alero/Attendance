<?php

namespace App\Filament\Employee\Pages;

use App\Models\VacationBalance;
use App\Models\VacationCalendar;
use App\Models\VacationRequest;
use App\Services\VacationRequestService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class VacationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?string $navigationLabel = 'Vacation';

    protected static ?string $title = 'Vacation & Time Off';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.employee.pages.vacation-page';

    public ?array $requestData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getVacationBalance(): ?VacationBalance
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return null;
        }

        return VacationBalance::where('employee_id', $employee->id)->first();
    }

    public function getAvailableHours(): float
    {
        $balance = $this->getVacationBalance();

        if (! $balance) {
            return 0;
        }

        return max(0, ($balance->accrued_hours ?? 0) + ($balance->carry_over_hours ?? 0) - ($balance->used_hours ?? 0));
    }

    public function getScheduledVacations()
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return collect();
        }

        return VacationCalendar::where('employee_id', $employee->id)
            ->where('vacation_date', '>=', Carbon::today())
            ->where('is_active', true)
            ->orderBy('vacation_date')
            ->get();
    }

    public function getPastVacations()
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return collect();
        }

        return VacationCalendar::where('employee_id', $employee->id)
            ->where('vacation_date', '<', Carbon::today())
            ->where('vacation_date', '>=', Carbon::now()->startOfYear())
            ->where('is_active', true)
            ->orderBy('vacation_date', 'desc')
            ->limit(10)
            ->get();
    }

    public function getRecentRequests()
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return collect();
        }

        return VacationRequest::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function getPendingRequests()
    {
        $employee = Auth::user()->employee;

        if (! $employee) {
            return collect();
        }

        return VacationRequest::where('employee_id', $employee->id)
            ->pending()
            ->orderBy('start_date')
            ->get();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->minDate(now()->addDay())
                    ->native(false),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->minDate(now()->addDay())
                    ->afterOrEqual('start_date')
                    ->native(false),

                Toggle::make('is_half_day')
                    ->label('Half Day Only')
                    ->helperText('Check if requesting only a half day'),

                Textarea::make('notes')
                    ->label('Notes (Optional)')
                    ->placeholder('Any additional information for your manager...')
                    ->rows(3),
            ])
            ->statePath('requestData');
    }

    public function submitRequest(): void
    {
        $this->validate();
        $data = $this->requestData;
        $employee = Auth::user()->employee;

        if (! $employee) {
            Notification::make()
                ->title('Error')
                ->body('Employee record not found.')
                ->danger()
                ->send();

            return;
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $isHalfDay = $data['is_half_day'] ?? false;

        $hoursRequested = VacationRequest::calculateHoursRequested($startDate, $endDate, $isHalfDay);

        $available = $this->getAvailableHours();
        if ($hoursRequested > $available) {
            Notification::make()
                ->title('Insufficient Balance')
                ->body("You requested {$hoursRequested} hours but only have {$available} hours available.")
                ->danger()
                ->send();

            return;
        }

        $service = app(VacationRequestService::class);
        $request = $service->createRequest($employee, $data);

        $daysRequested = $request->business_days;

        Notification::make()
            ->title('Request Submitted')
            ->body("Your time off request for {$daysRequested} day(s) has been submitted and is pending approval.")
            ->success()
            ->send();

        $this->reset('requestData');
    }
}
