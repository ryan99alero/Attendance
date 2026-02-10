# Payroll Export Implementation Status

## Overview

This document tracks the implementation progress of the multi-provider payroll export system.

**Started**: 2026-02-09
**Target Completion**: TBD
**Architecture Doc**: [PAYROLL_EXPORT_ARCHITECTURE.md](./PAYROLL_EXPORT_ARCHITECTURE.md)

---

## Phase 1: Database Schema Changes

### Migrations

| Task | Status | File | Notes |
|------|--------|------|-------|
| Add `name` to pay_periods | NOT STARTED | - | Human-readable period name (e.g., "Week12") |
| Add `payroll_provider_id` to employees | NOT STARTED | - | FK to integration_connections |
| Add payroll fields to integration_connections | NOT STARTED | - | is_payroll_provider, export_formats, export_destination, export_path |
| Create pay_period_employee_summaries table | NOT STARTED | - | Aggregated time data per employee/classification |
| Create payroll_exports table | NOT STARTED | - | Export history tracking |

### Models

| Task | Status | File | Notes |
|------|--------|------|-------|
| Create PayPeriodEmployeeSummary model | NOT STARTED | - | |
| Create PayrollExport model | NOT STARTED | - | |
| Update PayPeriod model | NOT STARTED | - | Add name field, relationship to summaries |
| Update Employee model | NOT STARTED | - | Add payrollProvider relationship |
| Update IntegrationConnection model | NOT STARTED | - | Add payroll provider fields, casts |

---

## Phase 2: Payroll Aggregation Engine

### Service Classes

| Task | Status | File | Notes |
|------|--------|------|-------|
| Create OvertimeCalculationService | NOT STARTED | app/Services/Payroll/OvertimeCalculationService.php | Uses existing overtime_rules table |
| Implement calculateOvertime() | NOT STARTED | - | Split regular/OT based on threshold |
| Implement getApplicableRule() | NOT STARTED | - | Match by shift or use default |
| Create PayrollAggregationService | NOT STARTED | app/Services/Payroll/PayrollAggregationService.php | Core aggregation logic |
| Implement aggregatePayPeriod() | NOT STARTED | - | Main entry point |
| Implement calculateEmployeeSummary() | NOT STARTED | - | Per-employee calculation |
| Add recalculation support | NOT STARTED | - | For manual adjustments |

### Testing

| Task | Status | Notes |
|------|--------|-------|
| Unit tests for overtime calculation | NOT STARTED | Test weekly threshold logic |
| Unit tests for aggregation | NOT STARTED | |
| Test overtime edge cases | NOT STARTED | 40.5 hrs, exempt employees, no rule defined |
| Test classification grouping | NOT STARTED | Vacation, Holiday, Regular |

---

## Phase 3: Payroll Export Engine

### Service Classes

| Task | Status | File | Notes |
|------|--------|------|-------|
| Create PayrollExportService | NOT STARTED | - | Export generation |
| Create PayrollFieldMapper | NOT STARTED | - | Field mapping logic |
| Implement CSV export | NOT STARTED | - | |
| Implement XLSX export | NOT STARTED | - | |
| Implement JSON export | NOT STARTED | - | |
| Implement XML export | NOT STARTED | - | |
| Implement file naming convention | NOT STARTED | - | {Provider}_PayPeriod_{Name}_{Date}.{ext} |
| Implement path-based saving | NOT STARTED | - | |

### Testing

| Task | Status | Notes |
|------|--------|-------|
| Unit tests for exports | NOT STARTED | |
| Test field mapping | NOT STARTED | |
| Test file generation | NOT STARTED | |

---

## Phase 4: UI Changes

### Integration Connection Form

| Task | Status | File | Notes |
|------|--------|------|-------|
| Add is_payroll_provider toggle | NOT STARTED | IntegrationConnectionResource.php | |
| Add export_formats checkboxes | NOT STARTED | - | Visible when is_payroll_provider=true |
| Add export_destination radio | NOT STARTED | - | Download / Path |
| Add export_path input | NOT STARTED | - | Visible when destination=path |

### Employee Form

| Task | Status | File | Notes |
|------|--------|------|-------|
| Add payroll_provider_id dropdown | NOT STARTED | EmployeeResource.php | Filter by is_payroll_provider=true |

### Pay Period Resource

| Task | Status | File | Notes |
|------|--------|------|-------|
| Add name field to form | NOT STARTED | PayPeriodResource.php | |
| Add "Aggregate Time Data" action | NOT STARTED | - | |
| Add "Export Payroll" action | NOT STARTED | - | |
| Add "View Summaries" action/tab | NOT STARTED | - | |
| Enhance "Post Time" action | NOT STARTED | - | Auto-aggregate after posting |

### New Resources

| Task | Status | File | Notes |
|------|--------|------|-------|
| Create PayrollExportResource | NOT STARTED | - | Export history view |

---

## Phase 5: Integration & Testing

| Task | Status | Notes |
|------|--------|-------|
| End-to-end test: full workflow | NOT STARTED | |
| Performance testing with large datasets | NOT STARTED | |
| Deprecate/refactor ADPExportReport | NOT STARTED | |
| Update documentation | NOT STARTED | |

---

## Related Work Completed (Prior to This Feature)

These items were completed during the current session but are prerequisites:

| Task | Status | Notes |
|------|--------|-------|
| FK Lookup UI for IntegrationFieldMapping | COMPLETED | Added lookup_model, lookup_match_column, lookup_return_column fields |
| Fix Force Sync to sync all objects | COMPLETED | Was hardcoded to only sync employees |

---

## Session Log

### 2026-02-09

**Work Completed:**
1. Investigated Department integration sync issue
   - Discovered sync was working, but manager_id FK constraint failing
   - manager_id needs FK lookup to convert external Pace ID to local employee ID

2. Added FK Lookup configuration UI to IntegrationFieldMapping
   - Transform dropdown: when "FK Lookup" selected, shows:
     - Lookup Model (e.g., Employee)
     - Match Column (e.g., external_id)
     - Return Column (e.g., id)
   - Auto-populates transform_options JSON on save

3. Fixed Force Sync button
   - Was hardcoded to only run `pace:sync-employees`
   - Now syncs all enabled IntegrationObjects on the connection
   - Shows summary of created/updated/skipped per object

4. Created payroll export architecture documentation
   - PAYROLL_EXPORT_ARCHITECTURE.md - full design
   - PAYROLL_EXPORT_STATUS.md - this file

5. Researched existing overtime and vacation infrastructure
   - Overtime: Table/model/UI exist, but no calculation service
   - Vacation: Fully implemented with 3 accrual methods
   - Updated architecture doc with findings

**Next Steps:**
- Start Phase 1: Database migrations
- Create pay_period_employee_summaries table
- Add payroll provider fields to integration_connections
- Add payroll_provider_id to employees
- Build OvertimeCalculationService using existing overtime_rules table

---

## Notes & Decisions

1. **Single Provider per Employee**: Each employee belongs to exactly one payroll provider (1:1 relationship). No multi-provider scenarios.

2. **Aggregation Storage**: Decided to store aggregated data in `pay_period_employee_summaries` table rather than computing on-the-fly. Benefits:
   - Faster exports
   - Audit trail
   - Ability to make manual adjustments
   - Data is "locked" after posting

3. **File Naming**: `{IntegrationName}_PayPeriod_{PeriodName}_{EndDate}.{format}`
   - Example: `ADP_PayPeriod_Week12_2026-02-07.csv`
   - End date included for uniqueness and sorting

4. **Classification Handling**: Using existing Classification model. Each classification can have a payroll_code that maps to provider-specific codes.

5. **Existing ADPExportReport**: Will be kept for backwards compatibility but new exports should use the PayrollExportService.

---

## Open Questions

1. ~~**Overtime Rules**: How should OT be calculated?~~ **ANSWERED**
   - Weekly only (>40 hrs) - confirmed by user
   - `overtime_rules` table already exists with `hours_threshold` field
   - Need to build `OvertimeCalculationService` to use these rules

2. **Approval Workflow**: Should there be manager approval before export?

3. **Corrections**: How to handle corrections after posting? New "adjustment" pay period?

4. **API Exports**: Timeline for direct API integration with ADP?

---

## Existing Infrastructure Research

### Overtime System (2026-02-09 Research)

**EXISTS - NOT INTEGRATED**

| Component | Status | Location |
|-----------|--------|----------|
| overtime_rules table | EXISTS | migration 2024_11_23 |
| OvertimeRule model | EXISTS | app/Models/OvertimeRule.php |
| Filament CRUD UI | EXISTS | app/Filament/Resources/OvertimeRuleResource.php |
| OvertimeCalculationService | MISSING | Need to build |
| Integration with payroll | MISSING | Need to build |

**Table Fields:**
- `rule_name` - Name of the rule
- `hours_threshold` - Weekly hours before OT (default: 40)
- `multiplier` - OT rate multiplier (default: 1.5)
- `shift_id` - Optional shift-specific rule
- `consecutive_days_threshold` - Consecutive day OT
- `applies_on_weekends` - Weekend handling

**Employee Fields (unused):**
- `overtime_exempt` (boolean)
- `overtime_rate` (decimal)
- `double_time_threshold` (decimal)

### Vacation System (2026-02-09 Research)

**FULLY IMPLEMENTED**

| Component | Status | Location |
|-----------|--------|----------|
| vacation_policies table | ACTIVE | migration 2025_09_17 |
| vacation_balances table | ACTIVE | migration 2024_11_23 |
| vacation_calendars table | ACTIVE | migration 2024_11_23 |
| vacation_transactions table | ACTIVE | migration 2025_09_17 |
| employee_vacation_assignments table | ACTIVE | migration 2025_09_17 |
| VacationPolicy model | ACTIVE | app/Models/VacationPolicy.php |
| VacationBalance model | ACTIVE | app/Models/VacationBalance.php |
| VacationCalendar model | ACTIVE | app/Models/VacationCalendar.php |
| VacationTransaction model | ACTIVE | app/Models/VacationTransaction.php |
| VacationAccrualService | ACTIVE | app/Services/VacationAccrualService.php |
| ConfigurableVacationAccrualService | ACTIVE | app/Services/ConfigurableVacationAccrualService.php |
| VacationTimeProcessAttendanceService | ACTIVE | app/Services/VacationProcessing/ |
| Filament Resources | ACTIVE | VacationPolicyResource, VacationBalanceResource, VacationCalendarResource |
| Console Command | ACTIVE | vacation:process-accruals |
| PayPeriod integration | ACTIVE | vacation_transactions.pay_period_id |

**3 Accrual Methods Supported:**
1. Calendar Year - front-loads on award date
2. Pay Period - accrues per period
3. Anniversary - awards on hire anniversary

**Key Integration:** VacationCalendar → Attendance (via VacationTimeProcessAttendanceService)
- Creates Clock In/Out Attendance records with VACATION classification
- Handles full and half days
- Already processes during PayPeriod posting
