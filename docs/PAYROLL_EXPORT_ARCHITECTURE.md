# Payroll Export Architecture

## Overview

This document describes the architecture for the multi-provider payroll export system. The system allows exporting finalized pay period data to multiple payroll providers (ADP, temp agencies, etc.) in various formats (CSV, XLSX, JSON, XML, API).

## Business Requirements

1. **Multiple Payroll Providers**: Support ADP for full-time/part-time employees and multiple temp agencies for contract workers
2. **Employee Assignment**: Each employee is assigned to exactly one payroll provider
3. **Multiple Output Formats**: CSV, XLSX, JSON, XML, and direct API integration
4. **Output Destinations**: Download on demand or auto-save to configured file path
5. **Aggregated Data**: Export compiled time data (regular hours, OT, vacation, etc.) not raw punches
6. **Classification Support**: Handle Regular, Vacation, Holiday, Jury Duty, and other pay classifications
7. **Pay Period Triggered**: Export initiated when posting/finalizing a pay period

## Data Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              PAY PERIOD POSTING                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PAYROLL AGGREGATION ENGINE                           │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ For each employee in pay period:                                     │    │
│  │   1. Get all Attendance/Punch records                                │    │
│  │   2. Group by Classification (Regular, OT, Vacation, Holiday, etc.) │    │
│  │   3. Calculate hours per classification                              │    │
│  │   4. Store in PayPeriodEmployeeSummary                               │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PAY PERIOD EMPLOYEE SUMMARY                           │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ employee_id | pay_period_id | classification_id | hours | ...       │    │
│  │ 101         | 52            | 1 (Regular)       | 36.00 |           │    │
│  │ 101         | 52            | 2 (Overtime)      | 12.375|           │    │
│  │ 101         | 52            | 3 (Vacation)      | 4.00  |           │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PAYROLL EXPORT SERVICE                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │ For each active Payroll Provider:                                    │    │
│  │   1. Filter employees by payroll_provider_id                         │    │
│  │   2. Get their PayPeriodEmployeeSummary records                      │    │
│  │   3. Apply IntegrationObject field mappings                          │    │
│  │   4. Generate output in configured format(s)                         │    │
│  │   5. Save to path or return for download                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    ▼                 ▼                 ▼
              ┌──────────┐      ┌──────────┐      ┌──────────┐
              │   ADP    │      │ Temp     │      │ Temp     │
              │ FlatFile │      │ Agency A │      │ Agency B │
              └──────────┘      └──────────┘      └──────────┘
```

## Database Schema Changes

### 1. New Table: `pay_period_employee_summaries`

Stores aggregated time data per employee per classification per pay period.

```sql
CREATE TABLE pay_period_employee_summaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pay_period_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    classification_id BIGINT UNSIGNED NOT NULL,
    hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    is_finalized BOOLEAN NOT NULL DEFAULT FALSE,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (classification_id) REFERENCES classifications(id) ON DELETE RESTRICT,

    UNIQUE KEY unique_summary (pay_period_id, employee_id, classification_id)
);
```

### 2. Modify Table: `pay_periods`

Add name field for human-readable period identification.

```sql
ALTER TABLE pay_periods ADD COLUMN name VARCHAR(50) NULL AFTER end_date;
```

### 3. Modify Table: `employees`

Add payroll provider assignment.

```sql
ALTER TABLE employees ADD COLUMN payroll_provider_id BIGINT UNSIGNED NULL;
ALTER TABLE employees ADD FOREIGN KEY (payroll_provider_id)
    REFERENCES integration_connections(id) ON DELETE SET NULL;
```

### 4. Modify Table: `integration_connections`

Add payroll provider configuration fields.

```sql
ALTER TABLE integration_connections
    ADD COLUMN is_payroll_provider BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN export_formats JSON NULL,
    ADD COLUMN export_destination ENUM('download', 'path') NULL DEFAULT 'download',
    ADD COLUMN export_path VARCHAR(500) NULL;
```

### 5. New Table: `payroll_exports`

Tracks export history for audit purposes.

```sql
CREATE TABLE payroll_exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pay_period_id BIGINT UNSIGNED NOT NULL,
    integration_connection_id BIGINT UNSIGNED NOT NULL,
    format VARCHAR(20) NOT NULL,
    file_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NOT NULL,
    employee_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    exported_by BIGINT UNSIGNED NULL,
    exported_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (integration_connection_id) REFERENCES integration_connections(id) ON DELETE CASCADE,
    FOREIGN KEY (exported_by) REFERENCES users(id) ON DELETE SET NULL
);
```

## Service Classes

### 1. OvertimeCalculationService (NEW - Uses Existing overtime_rules Table)

Calculates overtime based on configured rules.

**Location**: `app/Services/Payroll/OvertimeCalculationService.php`

**Methods**:
- `calculateOvertime(Employee $employee, float $totalHours, PayPeriod $payPeriod): array` - Returns [regular_hours, overtime_hours]
- `getApplicableRule(Employee $employee): ?OvertimeRule` - Get rule for employee (by shift or default)
- `isOvertimeExempt(Employee $employee): bool` - Check if employee is exempt

**Logic**:
1. Check if employee is `overtime_exempt` → return all hours as regular
2. Find applicable OvertimeRule (by shift_id match or default rule)
3. Apply `hours_threshold` (default 40) → hours over threshold = overtime
4. Return split of regular vs overtime hours

**Uses Existing:**
- `overtime_rules` table (hours_threshold, multiplier, shift_id)
- `Employee.overtime_exempt` field
- `Employee.shift_id` for rule matching

### 2. PayrollAggregationService

Computes and stores employee time summaries for a pay period.

**Location**: `app/Services/Payroll/PayrollAggregationService.php`

**Methods**:
- `aggregatePayPeriod(PayPeriod $payPeriod): void` - Main entry point
- `calculateEmployeeSummary(Employee $employee, PayPeriod $payPeriod): Collection` - Returns classification breakdowns
- `recalculateEmployee(Employee $employee, PayPeriod $payPeriod): void` - Recalculate single employee

**Aggregation Logic**:
1. Query all Punch records for employee within pay period date range
2. Pair start/stop punches and calculate duration
3. Group by classification_id (Vacation, Holiday, Regular, etc.)
4. For Regular hours: call OvertimeCalculationService to split regular/OT
5. Upsert into pay_period_employee_summaries

### 2. PayrollExportService

Generates export files for payroll providers.

**Location**: `app/Services/Payroll/PayrollExportService.php`

**Methods**:
- `exportPayPeriod(PayPeriod $payPeriod): Collection` - Export to all active providers
- `exportToProvider(PayPeriod $payPeriod, IntegrationConnection $provider): PayrollExport` - Export to specific provider
- `generateFile(PayPeriod $payPeriod, IntegrationConnection $provider, string $format): string` - Generate single format
- `getExportData(PayPeriod $payPeriod, IntegrationConnection $provider): Collection` - Get mapped data rows

**File Naming Convention**:
```
{IntegrationName}_PayPeriod_{PeriodName}_{EndDate}.{format}
Example: ADP_PayPeriod_Week12_2026-02-07.csv
```

### 3. PayrollFieldMapper

Maps aggregated data to export format using IntegrationObject field mappings.

**Location**: `app/Services/Payroll/PayrollFieldMapper.php`

**Available Source Fields** (for field mapping):
- `employee.id` - Internal employee ID
- `employee.external_id` - External system ID
- `employee.first_name`, `employee.last_name`
- `employee.department.name`, `employee.department.external_department_id`
- `summary.regular_hours` - Regular hours
- `summary.overtime_hours` - Overtime hours
- `summary.vacation_hours` - Vacation hours
- `summary.holiday_hours` - Holiday hours
- `summary.{classification_code}_hours` - Any classification by code
- `summary.total_hours` - Sum of all hours
- `pay_period.name` - Period name
- `pay_period.start_date`, `pay_period.end_date`

## UI Changes

### 1. Integration Connection Form

When `is_payroll_provider = true`, show:
- Export Formats checkboxes (CSV, XLSX, JSON, XML)
- Export Destination radio (Download / File Path)
- Export Path input (visible when destination = 'path')

### 2. Employee Form

Add "Payroll Provider" dropdown:
- Shows only connections where `is_payroll_provider = true`
- Required for all employees (enforced at business logic level, not DB)

### 3. Pay Period Resource

Add actions:
- "Aggregate Time Data" - Runs PayrollAggregationService (before posting)
- "Export Payroll" - Opens modal to select which providers/formats to export
- "View Summaries" - Shows aggregated data per employee

Modify "Post Time" action:
- After posting, auto-run aggregation if not already done
- Prompt for payroll export

### 4. New: PayrollExportResource

View export history with:
- Pay Period, Provider, Format, File, Status
- Download action for completed exports
- Re-export action

## Workflow

### Standard Pay Period Close Process

1. **Process Attendance** (existing)
   - ClockEvents → Attendance records
   - Resolve discrepancies, assign classifications

2. **Review & Approve** (existing)
   - Fix issues flagged by consensus engine
   - Ensure all records are Migrated status

3. **Aggregate Time Data** (NEW)
   - Calculate hours by classification per employee
   - Store in pay_period_employee_summaries
   - Mark as not finalized (editable)

4. **Review Aggregated Data** (NEW)
   - View employee summaries
   - Make manual adjustments if needed
   - Mark as finalized

5. **Post Pay Period** (existing, enhanced)
   - Mark Attendance/Punch as Posted
   - Process vacation deductions
   - Lock aggregated summaries (is_finalized = true)

6. **Export Payroll** (NEW)
   - Auto-export to all active providers
   - Or manual selection of specific providers/formats
   - Track in payroll_exports table

## Configuration

### Overtime Rules (EXISTING TABLE: `overtime_rules`)

The system already has an `overtime_rules` table with:
- `rule_name` - Name of the rule
- `hours_threshold` - Weekly hours before OT kicks in (default: 40)
- `multiplier` - OT pay multiplier (default: 1.5)
- `shift_id` - Optional FK to apply rule to specific shift
- `consecutive_days_threshold` - For consecutive day OT rules
- `applies_on_weekends` - Boolean for weekend handling

**Note:** Table and Filament UI exist but no calculation service. Need to build `OvertimeCalculationService`.

Employee model has unused fields: `overtime_exempt`, `overtime_rate`, `double_time_threshold`

### Classification Mapping

Each classification should have a `payroll_code` that maps to the payroll provider's expected codes:
- Regular → REG
- Overtime → OT
- Vacation → VAC
- Holiday → HOL
- etc.

### Vacation System (EXISTING - FULLY IMPLEMENTED)

The vacation system is comprehensive and already integrated:

**Tables:**
- `vacation_policies` - Tenure-based tiers (1-5 yrs = 10 days, 6 yrs = 11 days, etc.)
- `vacation_balances` - Employee balances with anniversary tracking
- `vacation_calendars` - Scheduled vacation days
- `vacation_transactions` - Audit trail (accrual, usage, adjustment, carryover, expiration)
- `employee_vacation_assignments` - Links employees to policies

**Services:**
- `VacationAccrualService` - Anniversary-based accrual (Rand Graphics specific)
- `ConfigurableVacationAccrualService` - Supports 3 methods (calendar year, pay period, anniversary)
- `VacationTimeProcessAttendanceService` - Converts VacationCalendar → Attendance records

**Integration Points:**
- VacationCalendar entries become Attendance records with VACATION classification
- VacationTransaction has `pay_period_id` for linking to pay periods
- Vacation deductions already process during PayPeriod posting

**For Payroll Export:** Vacation hours can be pulled from:
1. Attendance records with `classification_id` = VACATION, OR
2. VacationTransaction records with `transaction_type` = 'usage' for the pay period

## Integration with Existing Code

### Existing ADPExportReport

The existing `app/Reports/ADPExportReport.php` can be deprecated or refactored to use the new PayrollExportService. The new system provides:
- Multi-provider support
- Pre-aggregated data (faster exports)
- Configurable field mappings
- Export history tracking

### Existing PayPeriod Posting

The posting logic in `PayPeriodResource.php` will be enhanced to:
1. Call PayrollAggregationService after marking records as Posted
2. Trigger PayrollExportService for auto-export (if configured)
3. Create VacationTransaction records (existing)

## Security & Audit

- All exports logged in payroll_exports table
- Export files include hash for integrity verification
- User who triggered export recorded
- File path exports require appropriate filesystem permissions

## Future Considerations

1. **API Export**: Direct API calls to ADP/payroll providers
2. **Scheduled Exports**: Auto-export on schedule after posting
3. **Approval Workflow**: Require manager approval before export
4. **Batch Corrections**: Handle adjustments after posting
5. **Multi-Company**: Support multiple companies with different providers
