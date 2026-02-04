# Data Model Documentation

## Overview

This document describes the database schema for the Attend time and attendance system.

## Entity Relationship Diagram (Simplified)

```
                    ┌─────────────┐
                    │  Company    │
                    │   Setup     │
                    └─────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         │                 │                 │
         ▼                 ▼                 ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ PayrollFreq │    │  Devices    │    │ Departments │
└─────────────┘    └──────┬──────┘    └──────┬──────┘
         │                │                  │
         │                │                  │
         ▼                ▼                  ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ PayPeriods  │    │ ClockEvents │◀───│  Employees  │
└─────────────┘    └─────────────┘    └──────┬──────┘
                                             │
                          ┌──────────────────┼──────────────────┐
                          │                  │                  │
                          ▼                  ▼                  ▼
                   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
                   │ Credentials │    │ Attendance  │    │  Vacation   │
                   └─────────────┘    └──────┬──────┘    │  Balances   │
                                             │          └─────────────┘
                                             ▼
                                      ┌─────────────┐
                                      │   Punches   │
                                      └─────────────┘
```

---

## Core Tables

### employees

Primary employee records.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| first_name | varchar(100) | First name |
| last_name | varchar(100) | Last name |
| email | varchar(255) | Email address (nullable) |
| phone | varchar(20) | Phone number (nullable) |
| external_id | varchar(50) | External system ID (for integrations) |
| department_id | bigint | FK to departments |
| shift_schedule_id | bigint | FK to shift_schedules |
| is_active | boolean | Active status |
| full_time | boolean | Full-time flag |
| date_of_hire | date | Original hire date |
| seniority_date | date | Seniority calculation date |
| termination_date | date | Termination date (null if active) |
| pay_type | enum | hourly, salary, contract |
| pay_rate | decimal(10,2) | Hourly rate or salary |
| overtime_exempt | boolean | Exempt from overtime |
| overtime_rate | decimal(5,3) | OT multiplier (e.g., 1.5) |
| round_group_id | bigint | FK to round_groups |

### departments

Organizational departments.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(100) | Department name |
| code | varchar(20) | Short code |
| manager_email | varchar(255) | Manager email for alerts |
| external_group_id | varchar(100) | External system ID |

### devices

Time clock devices.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| device_id | varchar(50) | Unique device identifier |
| device_name | varchar(100) | Internal name |
| display_name | varchar(100) | Human-friendly name |
| device_type | enum | esp32_timeclock, mobile_app, etc. |
| mac_address | varchar(17) | Hardware MAC address |
| ip_address | varchar(45) | Current IP address |
| timezone | varchar(50) | Device timezone |
| registration_status | enum | pending, approved, rejected, suspended |
| is_active | boolean | Active status |
| last_seen_at | timestamp | Last heartbeat |
| offline_alerted_at | timestamp | When offline alert was sent |
| firmware_version | varchar(20) | Current firmware |
| reboot_requested | boolean | Pending reboot flag |

---

## Time Tracking Tables

### clock_events

Raw punch data from devices (before processing).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| device_id | bigint | FK to devices |
| employee_id | bigint | FK to employees |
| credential_id | bigint | FK to credentials |
| event_time | timestamp | When punch occurred |
| event_type | enum | clock_in, clock_out, break_start, break_end |
| status | enum | new, processing, processed, failed |
| processed_at | timestamp | When processed |
| processing_notes | text | Processing details/errors |

### attendances

Processed daily attendance records.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| employee_id | bigint | FK to employees |
| attendance_date | date | The work date |
| shift_id | bigint | FK to shifts |
| pay_period_id | bigint | FK to pay_periods |
| status | enum | present, absent, partial, holiday |
| regular_hours | decimal(5,2) | Regular hours worked |
| overtime_hours | decimal(5,2) | Overtime hours |
| double_time_hours | decimal(5,2) | Double time hours |
| notes | text | Manager notes |

### punches

Individual punch records (processed from clock_events).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| attendance_id | bigint | FK to attendances |
| punch_type_id | bigint | FK to punch_types |
| punch_time | timestamp | Actual punch time |
| rounded_time | timestamp | After rounding rules applied |
| source | enum | device, manual, import |
| is_adjusted | boolean | Was manually adjusted |
| adjustment_reason | text | Why it was adjusted |

### punch_types

Types of punches.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| type_name | varchar(50) | clock_in, clock_out, break_start, break_end |
| display_name | varchar(50) | Human-friendly name |
| affects_hours | boolean | Counts toward worked hours |

---

## Payroll Tables

### payroll_frequencies

Pay period frequency definitions.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| frequency_name | varchar(50) | Weekly, Bi-weekly, Monthly, etc. |
| days_in_period | integer | Days per pay period |
| start_of_week | integer | 0=Sunday, 1=Monday, etc. |

### pay_periods

Individual pay periods.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| payroll_frequency_id | bigint | FK to payroll_frequencies |
| period_start | date | Period start date |
| period_end | date | Period end date |
| status | enum | open, closed, exported |
| check_date | date | Paycheck date |

### round_groups / rounding_rules

Time rounding configuration.

**round_groups:**
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| group_name | varchar(50) | Group name |

**rounding_rules:**
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| round_group_id | bigint | FK to round_groups |
| rule_type | enum | clock_in, clock_out |
| round_to_minutes | integer | Round to nearest X minutes |
| direction | enum | nearest, up, down |

---

## Integration Tables

### integration_connections

External API connection credentials.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(100) | Connection name |
| driver | varchar(50) | pace, adp, quickbooks |
| base_url | varchar(255) | API endpoint |
| auth_type | varchar(50) | basic, oauth2, api_key |
| auth_credentials | text | Encrypted credentials (JSON) |
| is_active | boolean | Enabled status |
| timeout_seconds | integer | Request timeout |
| retry_attempts | integer | Retry count |
| last_connected_at | timestamp | Last successful connection |
| last_error_at | timestamp | Last error time |
| last_error_message | text | Last error details |

### integration_objects

Object types available from external API.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| connection_id | bigint | FK to integration_connections |
| object_name | varchar(100) | API object name (Job, Employee) |
| display_name | varchar(100) | Human-friendly name |
| primary_key_field | varchar(100) | XPath to PK |
| available_fields | json | Fields from API discovery |
| local_model | varchar(100) | Laravel model class |
| sync_enabled | boolean | Sync enabled |
| sync_direction | enum | pull, push, bidirectional |

### integration_query_templates

Reusable API query definitions.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| connection_id | bigint | FK to integration_connections |
| name | varchar(100) | Template name |
| object_name | varchar(100) | Root object to query |
| fields | json | Field definitions |
| children | json | Child objects to include |
| filter | json | Filter conditions |
| default_limit | integer | Records per page |

### integration_field_mappings

Maps external fields to local columns.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| object_id | bigint | FK to integration_objects |
| external_field | varchar(100) | External field name |
| external_xpath | varchar(255) | XPath to field |
| external_type | varchar(50) | API data type |
| local_field | varchar(100) | Local column name |
| local_type | varchar(50) | Local data type |
| transform | varchar(50) | Transformation function |
| sync_on_pull | boolean | Update on pull |
| sync_on_push | boolean | Update on push |
| is_identifier | boolean | Used for record matching |

### integration_sync_logs

Sync operation history.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| connection_id | bigint | FK to integration_connections |
| operation | varchar(50) | pull, push, test |
| status | varchar(50) | running, success, failed |
| started_at | timestamp | Start time |
| completed_at | timestamp | End time |
| duration_ms | integer | Duration in ms |
| records_fetched | integer | Records from API |
| records_created | integer | New local records |
| records_updated | integer | Updated records |
| records_failed | integer | Failed records |
| error_message | text | Error if failed |

---

## Configuration Tables

### company_setup

Single-row company configuration.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key (always 1) |
| payroll_frequency_id | bigint | Default payroll frequency |
| attendance_flexibility_minutes | integer | Grace period |
| max_shift_length | integer | Max shift hours |
| enforce_shift_schedules | boolean | Require schedules |
| vacation_accrual_method | enum | calendar_year, pay_period, anniversary |
| smtp_enabled | boolean | Use custom SMTP |
| smtp_host | varchar(255) | SMTP server |
| device_offline_threshold_minutes | integer | Alert threshold |
| device_alert_email | varchar(255) | Alert recipient |
| logging_level | enum | none, error, warning, info, debug |

---

## Vacation Tables

### vacation_policies

Vacation accrual policies.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(100) | Policy name |
| accrual_rate | decimal(8,4) | Hours per period |
| max_balance | decimal(8,2) | Maximum balance cap |
| carryover_limit | decimal(8,2) | Max carryover |

### vacation_balances

Employee vacation balances.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| employee_id | bigint | FK to employees |
| balance_hours | decimal(8,2) | Current balance |
| accrued_ytd | decimal(8,2) | Accrued this year |
| used_ytd | decimal(8,2) | Used this year |
| as_of_date | date | Balance date |

### vacation_transactions

Individual vacation transactions.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| employee_id | bigint | FK to employees |
| transaction_type | enum | accrual, use, adjustment, carryover |
| hours | decimal(8,2) | Hours (+ or -) |
| transaction_date | date | Effective date |
| notes | text | Description |

---

## Naming Conventions

- Table names: plural, snake_case (`employees`, `clock_events`)
- Column names: snake_case (`first_name`, `created_at`)
- Foreign keys: singular_tablename_id (`employee_id`, `department_id`)
- Timestamps: Laravel conventions (`created_at`, `updated_at`)
- Boolean flags: is_* or has_* prefix (`is_active`, `has_overtime`)
