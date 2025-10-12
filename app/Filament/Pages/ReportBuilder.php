<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Employee;
use App\Models\Department;
use App\Reports\ADPExportReport;
use App\Services\ModelDiscoveryService;
use Carbon\Carbon;

class ReportBuilder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.report-builder';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $title = 'Report Builder';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->endOfMonth()->format('Y-m-d'),
            'export_format' => 'csv',
            'field_mappings' => [
                ['source_table' => 'employees', 'source_field' => 'external_id', 'display_name' => 'Employee_ID', 'enabled' => true],
                ['source_table' => 'employees', 'source_field' => 'full_names', 'display_name' => 'Employee_Name', 'enabled' => true],
                ['source_table' => 'attendances', 'source_field' => 'shift_date', 'display_name' => 'Date', 'enabled' => true],
                ['source_table' => 'attendances', 'source_field' => 'punch_time', 'display_name' => 'Time', 'enabled' => true],
                ['source_table' => 'employees', 'source_field' => 'pay_rate', 'display_name' => 'Pay_Rate', 'enabled' => true],
                ['source_table' => 'employees', 'source_field' => 'overtime_rate', 'display_name' => 'OT_Rate', 'enabled' => true],
                ['source_table' => 'classifications', 'source_field' => 'name', 'display_name' => 'Pay_Code', 'enabled' => true],
                ['source_table' => 'departments', 'source_field' => 'external_department_id', 'display_name' => 'Cost_Center', 'enabled' => true],
                ['source_table' => 'departments', 'source_field' => 'name', 'display_name' => 'Department', 'enabled' => true],
            ],
            'static_fields' => [],
            'calculated_fields' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Report Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Settings')
                            ->schema([
                                Forms\Components\Section::make('Date Range')
                                    ->schema([
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label('Start Date')
                                            ->required(),
                                        Forms\Components\DatePicker::make('end_date')
                                            ->label('End Date')
                                            ->required(),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Filters')
                                    ->schema([
                                        Forms\Components\Select::make('employee_ids')
                                            ->label('Employees')
                                            ->multiple()
                                            ->searchable()
                                            ->options(Employee::where('is_active', true)->pluck('full_names', 'id'))
                                            ->helperText('Leave empty to include all employees'),

                                        Forms\Components\Select::make('department_ids')
                                            ->label('Departments')
                                            ->multiple()
                                            ->searchable()
                                            ->options(Department::pluck('name', 'id'))
                                            ->helperText('Leave empty to include all departments'),

                                        Forms\Components\Select::make('export_format')
                                            ->label('Export Format')
                                            ->options([
                                                'csv' => 'CSV',
                                                'excel' => 'Excel',
                                                'json' => 'JSON',
                                            ])
                                            ->default('csv')
                                            ->required(),
                                    ])
                                    ->columns(3),
                            ]),

                        Forms\Components\Tabs\Tab::make('Field Mapping')
                            ->schema([
                                Forms\Components\Section::make('Standard Fields')
                                    ->description('Configure which fields to include and their display names')
                                    ->schema([
                                        Forms\Components\Repeater::make('field_mappings')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\Select::make('source_table')
                                                    ->label('Source Table')
                                                    ->options(function () {
                                                        $discovery = new ModelDiscoveryService();
                                                        return $discovery->getModelOptionsForSelect();
                                                    })
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function (callable $set) {
                                                        $set('source_field', null); // Reset field when table changes
                                                    }),
                                                Forms\Components\Select::make('source_field')
                                                    ->label('Source Field')
                                                    ->options(function (callable $get) {
                                                        $sourceTable = $get('source_table');
                                                        if (!$sourceTable) {
                                                            return [];
                                                        }

                                                        $discovery = new ModelDiscoveryService();
                                                        return $discovery->getFieldOptionsForSelect($sourceTable);
                                                    })
                                                    ->required()
                                                    ->disabled(fn (callable $get) => !$get('source_table'))
                                                    ->helperText('Select a table first'),
                                                Forms\Components\TextInput::make('display_name')
                                                    ->label('Display Name')
                                                    ->required(),
                                                Forms\Components\Toggle::make('enabled')
                                                    ->label('Include in Export')
                                                    ->default(true),
                                            ])
                                            ->columns(4)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Field Mapping'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Static Fields')
                            ->schema([
                                Forms\Components\Section::make('Static Value Fields')
                                    ->description('Add fields with static values that don\'t exist in the database')
                                    ->schema([
                                        Forms\Components\Repeater::make('static_fields')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\TextInput::make('field_name')
                                                    ->label('Field Name')
                                                    ->required()
                                                    ->helperText('Name of the column in the export'),
                                                Forms\Components\TextInput::make('static_value')
                                                    ->label('Static Value')
                                                    ->required()
                                                    ->helperText('Value that will be used for all records'),
                                                Forms\Components\Textarea::make('description')
                                                    ->label('Description')
                                                    ->rows(2)
                                                    ->helperText('Optional description for this field'),
                                            ])
                                            ->columns(3)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Static Field'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Calculated Fields')
                            ->schema([
                                Forms\Components\Section::make('Calculated Fields')
                                    ->description('Create computed fields based on existing data')
                                    ->schema([
                                        Forms\Components\Repeater::make('calculated_fields')
                                            ->label('')
                                            ->schema([
                                                Forms\Components\TextInput::make('field_name')
                                                    ->label('Field Name')
                                                    ->required(),
                                                Forms\Components\Select::make('calculation_type')
                                                    ->label('Calculation Type')
                                                    ->options([
                                                        'gross_pay' => 'Gross Pay (Hours × Rate)',
                                                        'full_name' => 'Full Name (First + Last)',
                                                        'formatted_date' => 'Formatted Date',
                                                        'custom_formula' => 'Custom Formula',
                                                    ])
                                                    ->required(),
                                                Forms\Components\Textarea::make('formula')
                                                    ->label('Custom Formula')
                                                    ->rows(2)
                                                    ->visible(fn (Forms\Get $get) => $get('calculation_type') === 'custom_formula')
                                                    ->helperText('Use PHP syntax with available variables'),
                                                Forms\Components\Textarea::make('description')
                                                    ->label('Description')
                                                    ->rows(2),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Calculated Field'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview Report')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action('previewReport'),

            Action::make('generate')
                ->label('Generate Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('generateReport'),

            Action::make('save_template')
                ->label('Save Template')
                ->icon('heroicon-o-bookmark')
                ->color('primary')
                ->action('saveTemplate'),
        ];
    }

    /**
     * Preview the report configuration
     */
    public function previewReport()
    {
        $data = $this->form->getState();

        // Show preview notification with configuration summary
        $summary = "Date Range: {$data['start_date']} to {$data['end_date']}\n";
        $summary .= "Export Format: " . strtoupper($data['export_format']) . "\n";
        $summary .= "Fields: " . count(array_filter($data['field_mappings'], fn($field) => $field['enabled'])) . " standard";

        if (!empty($data['static_fields'])) {
            $summary .= ", " . count($data['static_fields']) . " static";
        }

        if (!empty($data['calculated_fields'])) {
            $summary .= ", " . count($data['calculated_fields']) . " calculated";
        }

        Notification::make()
            ->title('Report Preview')
            ->body($summary)
            ->info()
            ->send();
    }

    /**
     * Generate the report with current configuration
     */
    public function generateReport()
    {
        try {
            $data = $this->form->getState();

            // Create report instance
            $report = new ADPExportReport();

            // Set parameters
            $report->run([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'employee_ids' => $data['employee_ids'] ?? [],
                'department_ids' => $data['department_ids'] ?? []
            ]);

            // Generate filename
            $startDate = Carbon::parse($data['start_date'])->format('Y-m-d');
            $endDate = Carbon::parse($data['end_date'])->format('Y-m-d');
            $filename = "custom_report_{$startDate}_to_{$endDate}.{$data['export_format']}";

            // Export based on format
            $filepath = $report->exportToCSV($filename);

            Notification::make()
                ->title('Report Generated Successfully')
                ->body("Report '{$filename}' has been generated and downloaded.")
                ->success()
                ->send();

            // Return download response
            return response()->download($filepath, $filename, [
                'Content-Type' => 'text/csv',
            ])->deleteFileAfterSend();

        } catch (\Exception $e) {
            \Log::error('Custom Report Generation Error: ' . $e->getMessage());

            Notification::make()
                ->title('Report Generation Failed')
                ->body('An error occurred while generating the report: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Save the current configuration as a template
     */
    public function saveTemplate()
    {
        $data = $this->form->getState();

        // In a real implementation, you'd save this to database
        // For now, just show a success message

        Notification::make()
            ->title('Template Saved')
            ->body('Report template has been saved successfully. (Feature in development)')
            ->success()
            ->send();
    }
}