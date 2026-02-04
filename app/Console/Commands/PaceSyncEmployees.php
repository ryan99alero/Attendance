<?php

namespace App\Console\Commands;

use App\Models\Credential;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncLog;
use App\Models\User;
use App\Services\Integrations\PaceApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PaceSyncEmployees extends Command
{
    protected $signature = 'pace:sync-employees
                            {--connection= : Integration connection ID (default: first active Pace connection)}
                            {--dry-run : Show what would change without saving}
                            {--employee= : Sync a single employee by Pace ID (external_id)}';

    protected $description = 'Sync active employees from Pace ERP into the local database. Employees not found in Pace are deactivated.';

    /**
     * Department lookup cache: external_department_id => local id
     */
    protected array $departmentMap = [];

    /**
     * Track which external_ids were seen from Pace
     */
    protected Collection $syncedExternalIds;

    /**
     * Counters for summary
     */
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;
    protected int $deactivated = 0;
    protected int $errors = 0;
    protected array $errorMessages = [];

    /**
     * Fields to request from Pace loadValueObjects
     */
    protected function getPaceFields(): array
    {
        return [
            ['name' => 'external_id',        'xpath' => '@id'],
            ['name' => 'first_name',          'xpath' => '@firstName'],
            ['name' => 'last_name',           'xpath' => '@lastName'],
            ['name' => 'email',               'xpath' => '@email'],
            ['name' => 'phone',               'xpath' => '@phoneNumber'],
            ['name' => 'address',             'xpath' => '@address1'],
            ['name' => 'address2',            'xpath' => '@address2'],
            ['name' => 'city',                'xpath' => '@city'],
            ['name' => 'state',               'xpath' => '@state'],
            ['name' => 'zip',                 'xpath' => '@zip'],
            ['name' => 'country',             'xpath' => '@country'],
            ['name' => 'department_code',     'xpath' => '@department'],
            ['name' => 'pay_rate',            'xpath' => '@payRate01'],
            ['name' => 'date_of_hire',        'xpath' => '@startDate'],
            ['name' => 'termination_date',    'xpath' => '@terminationDate'],
            ['name' => 'is_active',           'xpath' => '@status'],
            ['name' => 'birth_date',          'xpath' => '@birthDate'],
            ['name' => 'emergency_contact',   'xpath' => '@emergencyContact'],
            ['name' => 'emergency_phone',     'xpath' => '@emergencyPhone'],
            ['name' => 'notes',               'xpath' => '@notes'],
            ['name' => 'default_shift',       'xpath' => '@defaultShift'],
            ['name' => 'secure_id',           'xpath' => '@secureId'],
            ['name' => 'is_supervisor',       'xpath' => '@isSupervisor'],
        ];
    }

    public function handle(): int
    {
        $connection = $this->resolveConnection();
        if (!$connection) {
            return 1;
        }

        $client = new PaceApiClient($connection);
        $this->syncedExternalIds = collect();

        // Start sync log
        $syncLog = IntegrationSyncLog::start($connection->id, 'sync_employees');

        $this->info('Pace Employee Sync');
        $this->info('Connection: ' . $connection->name);

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - no changes will be saved');
        }

        $this->newLine();

        // Build department lookup cache
        $this->buildDepartmentMap();

        try {
            if ($this->option('employee')) {
                $this->syncSingleEmployee($client);
            } else {
                $this->syncActiveEmployees($client);
                $this->deactivateMissingEmployees();
            }
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            $syncLog->markFailed($e->getMessage());
            return 1;
        }

        // Show summary
        $this->newLine();
        $this->info('Sync Complete');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $this->created],
                ['Updated', $this->updated],
                ['Skipped (no changes)', $this->skipped],
                ['Deactivated', $this->deactivated],
                ['Errors', $this->errors],
            ]
        );

        if (!empty($this->errorMessages)) {
            $this->newLine();
            $this->warn('Errors:');
            foreach (array_slice($this->errorMessages, 0, 10) as $msg) {
                $this->line("  - {$msg}");
            }
            if (count($this->errorMessages) > 10) {
                $this->line('  ... and ' . (count($this->errorMessages) - 10) . ' more');
            }
        }

        // Update sync log
        $stats = [
            'fetched' => $this->created + $this->updated + $this->skipped + $this->errors,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->errors,
        ];

        if ($this->errors > 0 && ($this->created + $this->updated) > 0) {
            $syncLog->markPartial($stats, $this->errors . ' errors during sync');
        } elseif ($this->errors > 0) {
            $syncLog->markFailed('All records failed', ['errors' => $this->errorMessages]);
        } else {
            $syncLog->markSuccess($stats);
        }

        return $this->errors > 0 ? 1 : 0;
    }

    /**
     * Fetch all active employees from Pace and process them.
     * Makes a probe call first to get totalRecords, then fetches all in one request.
     */
    protected function syncActiveEmployees(PaceApiClient $client): void
    {
        $this->info('Fetching all active employees from Pace...');

        // Probe call: get totalRecords count (limit omitted = 1 record returned)
        $probe = $client->loadValueObjects(
            objectName: 'Employee',
            fields: [['name' => 'external_id', 'xpath' => '@id']],
            xpathFilter: "@status = 'A'",
        );

        $totalRecords = $probe['totalRecords'] ?? 0;
        $this->info("Active employees in Pace: {$totalRecords}");

        if ($totalRecords === 0) {
            $this->warn('No active employees found in Pace.');
            return;
        }

        // Fetch all records in one call using totalRecords as the limit
        $response = $client->loadValueObjects(
            objectName: 'Employee',
            fields: $this->getPaceFields(),
            xpathFilter: "@status = 'A'",
            limit: $totalRecords,
        );

        $valueObjects = $response['valueObjects'] ?? [];
        $this->info("Records returned: " . count($valueObjects));
        $this->newLine();

        $bar = $this->output->createProgressBar(count($valueObjects));
        $bar->start();

        foreach ($valueObjects as $vo) {
            $parsed = $client->parseValueObject($vo);
            $this->processEmployee($parsed);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Deactivate local employees that were not in the Pace active results
     */
    protected function deactivateMissingEmployees(): void
    {
        if ($this->syncedExternalIds->isEmpty()) {
            return;
        }

        // Find local employees with an external_id that are currently active
        // but were NOT in the Pace results
        $toDeactivate = Employee::where('is_active', true)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNotIn('external_id', $this->syncedExternalIds->toArray())
            ->get();

        if ($toDeactivate->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info("Deactivating {$toDeactivate->count()} employees not found in Pace active list:");

        foreach ($toDeactivate as $employee) {
            if ($this->option('dry-run')) {
                $this->line("  Would deactivate: {$employee->external_id} ({$employee->full_names})");
            } else {
                $employee->update(['is_active' => false]);
            }
            $this->deactivated++;
        }
    }

    /**
     * Sync a single employee by Pace ID (uses primaryKey, no filter)
     */
    protected function syncSingleEmployee(PaceApiClient $client): void
    {
        $paceId = $this->option('employee');
        $this->info("Fetching employee with Pace ID: {$paceId}");

        $response = $client->loadValueObjects(
            objectName: 'Employee',
            fields: $this->getPaceFields(),
            primaryKey: (string) $paceId,
        );

        $valueObjects = $response['valueObjects'] ?? [];

        if (empty($valueObjects)) {
            $this->error("Employee {$paceId} not found in Pace");
            $this->errors++;
            return;
        }

        $parsed = $client->parseValueObject($valueObjects[0]);

        if ($this->option('dry-run')) {
            $this->info('Pace data:');
            $this->table(
                ['Field', 'Value'],
                collect($parsed)->except(['_primaryKey', '_objectName'])->map(
                    fn($v, $k) => [$k, is_null($v) ? '(null)' : (string) $v]
                )->values()->toArray()
            );
        }

        $this->processEmployee($parsed);
    }

    /**
     * Process a single parsed employee record
     */
    protected function processEmployee(array $data): void
    {
        $externalId = $data['external_id'] ?? null;

        if (!$externalId) {
            $this->errors++;
            $this->errorMessages[] = 'Record missing external_id (Pace ID), skipping';
            return;
        }

        // Track this external_id as seen from Pace
        $this->syncedExternalIds->push((string) $externalId);

        try {
            // Build the employee attributes from Pace data
            $attributes = $this->mapEmployeeFields($data);

            if ($this->option('dry-run')) {
                $existing = Employee::where('external_id', $externalId)->first();
                if ($existing) {
                    $changes = array_diff_assoc(
                        array_map('strval', array_filter($attributes, fn($v) => $v !== null)),
                        array_map('strval', array_filter($existing->only(array_keys($attributes)), fn($v) => $v !== null))
                    );
                    if (empty($changes)) {
                        $this->skipped++;
                    } else {
                        $this->updated++;
                        if ($this->getOutput()->isVerbose()) {
                            $this->line("  Would update {$externalId} ({$data['first_name']} {$data['last_name']}): " . implode(', ', array_keys($changes)));
                        }
                    }
                } else {
                    $this->created++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("  Would create {$externalId} ({$data['first_name']} {$data['last_name']})");
                    }
                }
                return;
            }

            // Upsert: match on external_id
            $employee = Employee::where('external_id', $externalId)->first();

            if ($employee) {
                $employee->update($attributes);
                $this->updated++;
            } else {
                $attributes['external_id'] = $externalId;
                $employee = Employee::create($attributes);
                $this->created++;
            }

            // Handle related data
            $this->syncCredential($employee, $data['secure_id'] ?? null);
            $this->syncSupervisorFlag($employee, $data['is_supervisor'] ?? null);

        } catch (\Exception $e) {
            $this->errors++;
            $this->errorMessages[] = "Employee {$externalId}: " . $e->getMessage();
        }
    }

    /**
     * Map Pace fields to local Employee attributes
     */
    protected function mapEmployeeFields(array $data): array
    {
        $attributes = [];

        // Direct string fields
        $attributes['first_name'] = $data['first_name'] ?? null;
        $attributes['last_name'] = $data['last_name'] ?? null;
        $attributes['full_names'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $attributes['email'] = $data['email'] ?? null;
        $attributes['phone'] = $data['phone'] ?? null;
        $attributes['address'] = $data['address'] ?? null;
        $attributes['address2'] = $data['address2'] ?? null;
        $attributes['city'] = $data['city'] ?? null;
        $attributes['state'] = $data['state'] ?? null;
        $attributes['zip'] = $data['zip'] ?? null;
        $attributes['country'] = $data['country'] ?? null;
        $attributes['emergency_contact'] = $data['emergency_contact'] ?? null;
        $attributes['emergency_phone'] = $data['emergency_phone'] ?? null;
        $attributes['notes'] = $data['notes'] ?? null;

        // All records from this query are active (filtered by @status = 'A')
        $attributes['is_active'] = true;

        // Pay rate: Currency type, can be null
        $payRate = $data['pay_rate'] ?? null;
        if ($payRate !== null) {
            $attributes['pay_rate'] = (float) $payRate;
        }

        // Date fields: Pace returns millisecond timestamps, parseValueObject converts them to Carbon
        $attributes['date_of_hire'] = $this->parseDate($data['date_of_hire'] ?? null);
        $attributes['termination_date'] = $this->parseDate($data['termination_date'] ?? null);
        $attributes['birth_date'] = $this->parseDate($data['birth_date'] ?? null);

        // Department: lookup by external_department_id
        $deptCode = $data['department_code'] ?? null;
        if ($deptCode !== null) {
            $deptCode = (string) $deptCode;
            $attributes['department_id'] = $this->departmentMap[$deptCode] ?? null;

            if ($attributes['department_id'] === null && $deptCode !== '') {
                $this->errorMessages[] = "Unknown department code '{$deptCode}' for employee {$data['external_id']}";
            }
        }

        // Shift: defaultShift is an integer FK
        $shift = $data['default_shift'] ?? null;
        if ($shift !== null) {
            $attributes['shift_id'] = (int) $shift;
        }

        return $attributes;
    }

    /**
     * Parse a date value that may be a Carbon instance (from parseValueObject)
     * or a millisecond timestamp
     */
    protected function parseDate($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        // Raw millisecond timestamp
        if (is_numeric($value)) {
            return Carbon::createFromTimestampMs($value)->toDateString();
        }

        return null;
    }

    /**
     * Sync badge/card credential from Pace secureId
     */
    protected function syncCredential(Employee $employee, $secureId): void
    {
        if ($secureId === null || $secureId === '') {
            return;
        }

        $secureId = (string) $secureId;

        // Check if this credential already exists for this employee
        $existing = Credential::where('employee_id', $employee->id)
            ->where('kind', 'card_uid')
            ->where('identifier', $secureId)
            ->first();

        if (!$existing) {
            Credential::createWithHash([
                'employee_id' => $employee->id,
                'kind' => 'card_uid',
                'identifier' => $secureId,
                'label' => 'Pace Badge',
                'is_active' => true,
                'issued_at' => now(),
            ]);
        }
    }

    /**
     * Sync supervisor/manager flag to users table
     */
    protected function syncSupervisorFlag(Employee $employee, $isSupervisor): void
    {
        if ($isSupervisor === null) {
            return;
        }

        // Pace returns boolean or string
        $isManager = filter_var($isSupervisor, FILTER_VALIDATE_BOOLEAN);

        // Only update if the employee has a linked user account
        $user = User::where('employee_id', $employee->id)->first();
        if ($user && $user->is_manager !== $isManager) {
            $user->update(['is_manager' => $isManager]);
        }
    }

    /**
     * Build a lookup map of department external IDs to local IDs
     */
    protected function buildDepartmentMap(): void
    {
        $this->departmentMap = Department::whereNotNull('external_department_id')
            ->pluck('id', 'external_department_id')
            ->toArray();

        $this->info('Department map loaded: ' . count($this->departmentMap) . ' departments');
    }

    /**
     * Resolve the Pace integration connection to use
     */
    protected function resolveConnection(): ?IntegrationConnection
    {
        if ($this->option('connection')) {
            $connection = IntegrationConnection::find($this->option('connection'));
            if (!$connection) {
                $this->error('Connection ID ' . $this->option('connection') . ' not found');
                return null;
            }
        } else {
            $connection = IntegrationConnection::where('driver', 'pace')
                ->where('is_active', true)
                ->first();

            if (!$connection) {
                $this->error('No active Pace connection found. Create one in Integrations settings.');
                return null;
            }
        }

        if ($connection->driver !== 'pace') {
            $this->error("Connection '{$connection->name}' is not a Pace integration (driver: {$connection->driver})");
            return null;
        }

        return $connection;
    }
}
