<?php

namespace App\Filament\Resources\PunchResource\Pages;

use App\Exports\DataExport;
use App\Filament\Resources\PunchResource;
use App\Imports\DataImport;
use App\Models\PayPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;

class ListPunches extends ListRecords
{
    protected static string $resource = PunchResource::class;

    #[Url]
    public ?string $payPeriodId = null;

    /**
     * Filter table query by selected pay period.
     */
    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($this->payPeriodId) {
            $query->where('pay_period_id', $this->payPeriodId);
        } else {
            // Show nothing until a pay period is selected
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Define the header actions for the resource.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('select_pay_period')
                ->label('Pay Period')
                ->color('gray')
                ->icon('heroicon-o-calendar')
                ->form([
                    Select::make('pay_period_id')
                        ->label('Select Pay Period')
                        ->options(
                            PayPeriod::where('is_posted', true)
                                ->orderBy('start_date', 'desc')
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
                ->modalSubmitActionLabel('View Punches')
                ->modalWidth('md'),

            Action::make('New Punch')
                ->label('New Punch')
                ->color('success')
                ->icon('heroicon-o-plus')
                ->url(PunchResource::getUrl('create')),

            Action::make('Import Punchs')
                ->label('Import')
                ->color('primary')
                ->schema([
                    FileUpload::make('file')
                        ->label('Import File')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    Excel::import(new DataImport(PunchResource::getModel()), $data['file']);
                    $this->notify('success', 'Punchs imported successfully!');
                })
                ->icon('heroicon-o-arrow-up-on-square-stack'),

            Action::make('Export Punchs')
                ->label('Export')
                ->color('warning')
                ->action(function () {
                    return Excel::download(new DataExport(PunchResource::getModel()), 'punch.xlsx');
                })
                ->icon('heroicon-o-arrow-down-on-square'),
        ];
    }

    /**
     * Get the selected pay period name for display.
     */
    public function getSelectedPayPeriodName(): ?string
    {
        if (! $this->payPeriodId) {
            return null;
        }

        $payPeriod = PayPeriod::find($this->payPeriodId);

        return $payPeriod?->name ?? "{$payPeriod?->start_date->format('M j')} - {$payPeriod?->end_date->format('M j, Y')}";
    }
}
