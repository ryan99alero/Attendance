<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Exports\DataExport;
use App\Filament\Resources\AttendanceResource;
use App\Jobs\ProcessDataImportJob;
use App\Models\Attendance;
use App\Models\PayPeriod;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    #[Url]
    public ?string $payPeriodId = null;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        // Apply department filtering for managers
        $user = auth()->user();

        if ($user && $user->hasRole('manager') && ! $user->hasRole('super_admin')) {
            $managedEmployeeIds = $user->getManagedEmployeeIds();

            if (! empty($managedEmployeeIds)) {
                $query->whereIn('employee_id', $managedEmployeeIds);
            } else {
                // If manager has no employees, show no records
                $query->whereRaw('1 = 0');
            }
        }

        // Filter by selected pay period
        if ($this->payPeriodId) {
            $payPeriod = PayPeriod::find($this->payPeriodId);
            if ($payPeriod) {
                $query->whereBetween('punch_time', [
                    $payPeriod->start_date->startOfDay(),
                    $payPeriod->end_date->endOfDay(),
                ]);
            }
        } else {
            // Show nothing until a pay period is selected
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Pay Period Selection
            Action::make('select_pay_period')
                ->label('Pay Period')
                ->color('gray')
                ->icon('heroicon-o-calendar')
                ->form([
                    Select::make('pay_period_id')
                        ->label('Select Pay Period')
                        ->options(
                            PayPeriod::orderBy('start_date', 'desc')
                                ->get()
                                ->mapWithKeys(fn ($pp) => [
                                    $pp->id => $pp->name ?? "{$pp->start_date->format('M j')} - {$pp->end_date->format('M j, Y')}",
                                ])
                        )
                        ->placeholder('Select a Pay Period')
                        ->searchable()
                        ->required()
                        ->default($this->payPeriodId),
                ])
                ->action(function (array $data) {
                    $this->payPeriodId = $data['pay_period_id'];
                })
                ->modalSubmitActionLabel('View Attendances')
                ->modalWidth('md'),

            // Add a "Create" action button
            Action::make('New Attendance')
                ->label('New Attendance')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(AttendanceResource::getUrl('create')),

            // Add an "Import" action button
            Action::make('Import Attendances')
                ->label('Import')
                ->color('primary')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file'];
                    $fileName = basename($filePath);

                    Log::info("Queuing attendance import job for file: {$filePath}");

                    // Create import record and dispatch job
                    $import = ProcessDataImportJob::createAndDispatch(
                        $filePath,
                        Attendance::class,
                        ProcessDataImportJob::PROCESSOR_ATTENDANCE,
                        auth()->id(),
                        $fileName
                    );

                    $rowInfo = $import->total_rows ? " ({$import->total_rows} rows)" : '';

                    Notification::make()
                        ->title('Import Queued')
                        ->body("Your attendance import{$rowInfo} has been queued. You will be notified when complete.")
                        ->info()
                        ->send();
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            // Add an "Export" action button
            Action::make('Export Attendances')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    try {
                        return Excel::download(new DataExport(AttendanceResource::getModel()), 'attendances.xlsx');
                    } catch (Exception $e) {
                        Log::error("Export failed: {$e->getMessage()}");

                        Notification::make()
                            ->title('Export Failed')
                            ->body("An error occurred during the export: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                })
                ->icon('heroicon-o-arrow-down-on-square'),
        ];
    }
}
