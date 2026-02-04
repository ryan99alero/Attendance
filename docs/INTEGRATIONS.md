# Integrations Documentation

## Overview

The Attend system supports integrations with external systems for data synchronization:

| System | Direction | Purpose |
|--------|-----------|---------|
| Pace/ePace ERP | Pull/Push | Employee data, job costing |
| ADP Payroll | Push | Timecard export |
| QuickBooks | Push | Payroll export |

## Integration Framework

### Database Tables

```
integration_connections     - API connection credentials
integration_objects         - Object types available from API
integration_query_templates - Reusable query definitions
integration_field_mappings  - Field-to-field mappings
integration_sync_logs       - Sync operation history
```

### Models

- `IntegrationConnection` - Stores encrypted credentials
- `IntegrationObject` - Defines available object types
- `IntegrationQueryTemplate` - Reusable loadValueObjects queries
- `IntegrationFieldMapping` - Maps external fields to local columns
- `IntegrationSyncLog` - Tracks sync operations

---

## Pace/ePace Integration

### Connection Setup

1. Navigate to **Integrations > Integrations** in Filament
2. Click **New Integration Connection**
3. Fill in:
   - **Name**: "Pace Production" (human-friendly name)
   - **Type**: Pace / ePace ERP
   - **Base URL**: Your Pace API endpoint (e.g., `https://pace.yourcompany.com/api`)
   - **Auth Type**: Basic Authentication
   - **Username/Password**: Pace API credentials

### API Overview

Pace uses a REST API with the primary endpoint `loadValueObjects` for data retrieval.

#### Endpoint
```
POST /FindObjects/loadValueObjects
```

#### Request Structure
```json
{
  "objectName": "Job",
  "offset": 0,
  "limit": 100,
  "fields": [
    {"name": "job", "xpath": "@job"},
    {"name": "csrName", "xpath": "csr/@name"}
  ],
  "children": [
    {
      "objectName": "JobPart",
      "fields": [
        {"name": "jobPart", "xpath": "@jobPart"}
      ]
    }
  ],
  "filter": {...},
  "sort": {...}
}
```

#### Response Structure
```json
{
  "valueObjects": [
    {
      "primaryKey": "12345",
      "objectName": "Job",
      "fields": [
        {"name": "job", "value": "12345", "type": "String", "xpath": "@job"},
        {"name": "csrName", "value": "JUDY", "type": "String", "xpath": "csr/@name"}
      ],
      "children": [
        {
          "objectName": "JobPart",
          "totalRecords": 2,
          "valueObjects": [...]
        }
      ]
    }
  ],
  "totalRecords": 150
}
```

### Field Types

| Type | Description | Example Value |
|------|-------------|---------------|
| String | Text value | "JUDY" |
| Integer | Whole number | 42 |
| Date | Milliseconds since epoch | 1541134800000 |
| Identity | Reference ID | 1 |
| Email | Email address | "judy@example.com" |

### XPath Selectors

XPath is used to select fields from the object graph:

| XPath | Description |
|-------|-------------|
| `@job` | Direct attribute on current object |
| `csr/@name` | Attribute on related object |
| `customer/@U_customField` | Custom user field (prefixed with U_) |
| `customer/customerGroup/@name` | Nested relationship |

### Using PaceApiClient

```php
use App\Services\Integrations\PaceApiClient;
use App\Models\IntegrationConnection;

// Get connection
$connection = IntegrationConnection::where('driver', 'pace')->first();
$client = new PaceApiClient($connection);

// Test connection
$result = $client->testConnection();

// Load jobs with parts
$response = $client->loadValueObjects(
    objectName: 'Job',
    fields: [
        ['name' => 'job', 'xpath' => '@job'],
        ['name' => 'dateSetup', 'xpath' => '@dateSetup'],
        ['name' => 'csrName', 'xpath' => 'csr/@name'],
    ],
    children: [
        [
            'objectName' => 'JobPart',
            'fields' => [
                ['name' => 'jobPart', 'xpath' => '@jobPart'],
                ['name' => 'description', 'xpath' => '@description'],
            ]
        ]
    ],
    limit: 100
);

// Parse response into arrays
$jobs = $client->parseValueObjects($response['valueObjects']);

foreach ($jobs as $job) {
    echo $job['job'] . ': ' . $job['csrName'] . "\n";

    // Access children
    foreach ($job['_children']['JobPart'] ?? [] as $part) {
        echo "  Part: " . $part['jobPart'] . "\n";
    }
}
```

### Query Templates

Save common queries as templates for reuse:

```php
use App\Models\IntegrationQueryTemplate;

$template = IntegrationQueryTemplate::create([
    'connection_id' => $connection->id,
    'name' => 'Active Jobs with CSR',
    'object_name' => 'Job',
    'fields' => [
        ['name' => 'job', 'xpath' => '@job'],
        ['name' => 'csrName', 'xpath' => 'csr/@name'],
        ['name' => 'salesName', 'xpath' => 'salesPerson/@name'],
    ],
    'children' => [
        [
            'objectName' => 'JobPart',
            'fields' => [
                ['name' => 'jobPart', 'xpath' => '@jobPart'],
            ]
        ]
    ],
    'default_limit' => 100,
]);

// Use template
$response = $client->loadFromTemplate($template, offset: 0, limit: 50);
```

### Common Pace Objects

| Object | Description | Key Fields |
|--------|-------------|------------|
| Job | Work orders | @job, @dateSetup, csr/@name |
| JobPart | Job line items | @jobPart, @description |
| JobShipment | Shipment records | @shipment, @shipDate |
| Customer | Customer records | @customer, @name |
| Employee | Pace employees | @employee, @name |
| Contact | Customer contacts | @contact, @firstName |

### Syncing Employees

Example: Sync Pace employees to local Employee table

```php
use App\Services\Integrations\PaceApiClient;
use App\Models\Employee;
use App\Models\IntegrationSyncLog;

$client = PaceApiClient::fromConnection($connectionId);

// Start sync log
$log = IntegrationSyncLog::start(
    connectionId: $connectionId,
    operation: 'pull',
    triggeredBy: auth()->id()
);

try {
    $response = $client->loadValueObjects(
        objectName: 'Employee',
        fields: [
            ['name' => 'id', 'xpath' => '@id'],
            ['name' => 'firstName', 'xpath' => '@firstName'],
            ['name' => 'lastName', 'xpath' => '@lastName'],
            ['name' => 'email', 'xpath' => '@email'],
            ['name' => 'department', 'xpath' => 'department/@name'],
        ]
    );

    $stats = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

    foreach ($client->parseValueObjects($response['valueObjects']) as $paceEmployee) {
        $stats['fetched']++;

        $employee = Employee::updateOrCreate(
            ['external_id' => $paceEmployee['id']],
            [
                'first_name' => $paceEmployee['firstName'],
                'last_name' => $paceEmployee['lastName'],
                'email' => $paceEmployee['email'],
            ]
        );

        $employee->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
    }

    $log->markSuccess($stats);

} catch (\Exception $e) {
    $log->markFailed($e->getMessage());
}
```

---

## ADP Integration

*Coming soon*

ADP integration will support:
- Employee data sync
- Timecard export
- Pay rate import

---

## QuickBooks Integration

*Coming soon*

QuickBooks integration will support:
- Timecard export for payroll
- Employee sync

---

## Field Mappings

Field mappings define how external fields map to local database columns.

### Creating Mappings

```php
use App\Models\IntegrationFieldMapping;

IntegrationFieldMapping::create([
    'object_id' => $employeeObject->id,
    'external_field' => 'firstName',
    'external_xpath' => '@firstName',
    'external_type' => 'String',
    'local_field' => 'first_name',
    'local_type' => 'string',
    'transform' => null, // No transformation needed
    'sync_on_pull' => true,
    'sync_on_push' => false,
    'is_identifier' => false,
]);

// Date field with transformation
IntegrationFieldMapping::create([
    'object_id' => $employeeObject->id,
    'external_field' => 'hireDate',
    'external_xpath' => '@hireDate',
    'external_type' => 'Date',
    'local_field' => 'date_of_hire',
    'local_type' => 'datetime',
    'transform' => 'date_ms_to_carbon', // Converts milliseconds to Carbon
    'sync_on_pull' => true,
]);
```

### Available Transformations

| Transform | Description |
|-----------|-------------|
| `date_ms_to_carbon` | Milliseconds timestamp → Carbon |
| `date_iso_to_carbon` | ISO 8601 string → Carbon |
| `cents_to_dollars` | Divide by 100 |
| `string_to_int` | Cast to integer |
| `string_to_float` | Cast to float |
| `string_to_bool` | Convert to boolean |
| `trim` | Trim whitespace |
| `uppercase` | Convert to uppercase |
| `lowercase` | Convert to lowercase |

---

## Sync Logs

All sync operations are logged for debugging and auditing.

### Viewing Logs

```php
use App\Models\IntegrationSyncLog;

// Recent syncs for a connection
$logs = IntegrationSyncLog::where('connection_id', $connectionId)
    ->recent(7) // Last 7 days
    ->orderBy('started_at', 'desc')
    ->get();

// Failed syncs
$failed = IntegrationSyncLog::failed()->recent(30)->get();
```

### Log Fields

| Field | Description |
|-------|-------------|
| operation | pull, push, test, discover |
| status | pending, running, success, failed, partial |
| records_fetched | Records retrieved from API |
| records_created | New local records created |
| records_updated | Existing records updated |
| records_skipped | Unchanged records |
| records_failed | Records that failed to process |
| duration_ms | Operation duration in milliseconds |
| error_message | Error message if failed |

---

## Troubleshooting

### Connection Errors

**401 Unauthorized**
- Check username/password in connection settings
- Verify API user has correct permissions in Pace

**Timeout Errors**
- Increase timeout in connection settings
- Reduce batch size in queries
- Check network connectivity to Pace server

**Empty Response**
- Verify object name is correct (case-sensitive)
- Check filter conditions
- Ensure API user has access to requested data

### Debug Mode

Enable debug logging in CompanySetup:
1. Go to **System & Hardware > Company Setup**
2. **System** tab → Set logging level to **Debug**

Check logs in `storage/logs/laravel.log`
