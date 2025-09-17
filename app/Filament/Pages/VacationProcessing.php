<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\VacationTransaction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class VacationProcessing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Employee Management';
    protected static ?string $navigationLabel = 'Vacation Processing';
    protected static ?int $navigationSort = 40;
    protected static string $view = 'filament.pages.vacation-processing';

    public $processDate;
    public $selectedEmployee;
    public $dryRun = true;
    public $force = false;

    public function mount()
    {
        $this->processDate = Carbon::now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('processAccruals')
                ->label('Process Vacation Accruals')
                ->icon('heroicon-o-play')
                ->color('success')
                ->form([
                    DatePicker::make('processDate')
                        ->label('Process Date')
                        ->default(Carbon::now())
                        ->required(),

                    Select::make('selectedEmployee')
                        ->label('Employee (Optional)')
                        ->placeholder('Process all eligible employees')
                        ->options(Employee::where('is_active', true)
                            ->whereNotNull('date_of_hire')
                            ->orderBy('first_name')
                            ->pluck('full_names', 'id'))
                        ->searchable(),

                    Toggle::make('dryRun')
                        ->label('Dry Run (Preview Only)')
                        ->default(true)
                        ->helperText('Enable to see what would be processed without making changes'),

                    Toggle::make('force')
                        ->label('Force Reprocessing')
                        ->default(false)
                        ->helperText('Reprocess even if already processed for this anniversary'),
                ])
                ->action(function (array $data) {
                    $this->processVacationAccruals($data);
                }),

            Action::make('viewLogs')
                ->label('View Processing Logs')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('filament.admin.pages.vacation-processing') . '?tab=logs'),
        ];
    }

    public function processVacationAccruals(array $data)
    {
        $command = 'vacation:process-accruals';
        $options = [];

        if ($data['processDate']) {
            $options[] = '--date=' . Carbon::parse($data['processDate'])->toDateString();
        }

        if ($data['selectedEmployee']) {
            $options[] = '--employee=' . $data['selectedEmployee'];
        }

        if ($data['dryRun']) {
            $options[] = '--dry-run';
        }

        if ($data['force']) {
            $options[] = '--force';
        }

        $fullCommand = $command . ' ' . implode(' ', $options);

        try {
            $exitCode = Artisan::call($command, array_reduce($options, function ($carry, $option) {
                if (str_starts_with($option, '--date=')) {
                    $carry['--date'] = substr($option, 7);
                } elseif (str_starts_with($option, '--employee=')) {
                    $carry['--employee'] = substr($option, 11);
                } elseif ($option === '--dry-run') {
                    $carry['--dry-run'] = true;
                } elseif ($option === '--force') {
                    $carry['--force'] = true;
                }
                return $carry;
            }, []));

            $output = Artisan::output();

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Vacation Processing Completed')
                    ->body($data['dryRun'] ? 'Dry run completed successfully' : 'Vacation accruals processed successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Processing Failed')
                    ->body('There were errors during processing. Check the logs for details.')
                    ->danger()
                    ->send();
            }

            // Store the output for display
            session(['vacation_processing_output' => $output]);

        } catch (\Exception $e) {
            Notification::make()
                ->title('Processing Error')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(VacationTransaction::query()
                ->where('transaction_type', 'accrual')
                ->with('employee')
                ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('employee.full_names')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('hours')
                    ->label('Hours Awarded')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' hrs')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Anniversary Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('transaction_date')
                    ->label('Processed Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('accrual_period')
                    ->label('Period')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Add filters if needed
            ])
            ->striped();
    }
}
