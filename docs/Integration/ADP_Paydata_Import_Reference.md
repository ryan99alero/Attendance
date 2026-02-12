# ADP Workforce Now — Paydata Import CSV Reference Guide
## For Integration with Time Attendance Solution (Laravel/Filament)

---

## File Naming Convention

**CRITICAL**: ADP requires a specific filename format or the import will fail.

```
PRcccEPI.csv
```

- `PR` — Fixed prefix (Payroll)
- `ccc` — Your 3-character ADP Company Code (e.g., `ADP`, `RGI`, `XYZ`)
- `EPI` — Fixed suffix (Employee Payroll Input)

For 2-digit company codes, pad with underscore: `PRcc_EPI.csv`

**Example**: If your company code is `RGI` → filename is `PRRGIEPI.csv`

> ⚠️ Your ADP rep assigned this company code during onboarding. Find it in:
> ADP WFN → Setup → Company Setup → Company Information

---

## Column Definitions

| # | Column | Required | Format | Description |
|---|--------|----------|--------|-------------|
| 1 | **Co Code** | ✅ Yes | 3 chars | ADP Company Code (same as filename) |
| 2 | **Batch ID** | ✅ Yes | 6 digits | Batch identifier — use `YYMMDD` format (e.g., `250211` for Feb 11, 2025) or sequential `000001`, `000002`, etc. |
| 3 | **File #** | ✅ Yes | Up to 6 digits | ADP Employee File Number (unique per employee, assigned by ADP) |
| 4 | **Temp Dept** | ❌ No | String | Temporary department override — only use if employee worked in a different dept this period |
| 5 | **Temp Rate** | ❌ No | Decimal | Temporary pay rate override — leave blank to use employee's default rate in ADP |
| 6 | **Reg Hours** | ✅ Yes | Decimal (2) | Regular hours worked (e.g., `80.00` for biweekly, `40.00` for weekly) |
| 7 | **O/T Hours** | ✅ Yes | Decimal (2) | Overtime hours at 1.5x (ADP calculates the pay based on employee's rate) |
| 8 | **Hours 3 Code** | ❌ No | 1 char | Additional hours type code (see Hours Codes below) |
| 9 | **Hours 3 Amount** | ❌ No | Decimal (2) | Hours for the corresponding Hours 3 Code |
| 10-13 | **Hours 3 Code/Amount** | ❌ No | Repeating | Additional hours code/amount pairs (up to 4 total) |
| 14 | **Earnings 3 Code** | ❌ No | 1 char | Additional earnings code (see Earnings Codes below) |
| 15 | **Earnings 3 Amount** | ❌ No | Decimal (2) | Dollar amount for the corresponding Earnings 3 Code |
| 16-19 | **Earnings 3 Code/Amount** | ❌ No | Repeating | Additional earnings code/amount pairs |
| 20 | **Memo Code** | ❌ No | 1 digit | Memo code (e.g., `5` for reported tips) |
| 21 | **Memo Amount** | ❌ No | Decimal (2) | Amount for the memo code |

---

## Standard Hours & Earnings Codes

### Hours Codes (Hours 3 Code)
These are configured in ADP under: **Setup → Tools → Validation Tables → Payroll → Paydata → Hours & Earnings Codes**

| Code | Typical Meaning | Notes |
|------|----------------|-------|
| `R` | Regular | **Reserved** — use the Reg Hours column instead |
| `O` | Overtime | **Reserved** — use the O/T Hours column instead |
| `H` | Holiday | Holiday hours |
| `V` | Vacation / PTO | Paid time off hours |
| `S` | Sick | Sick leave hours |
| `P` | Personal | Personal day hours |
| `D` | Double Time | Double-time overtime (2x rate) |

> ⚠️ These codes are **company-specific**. Your ADP admin may have different codes configured.
> Verify yours at: **Setup → Tools → Validation Tables → Paydata → Hours & Earnings Codes**

### Earnings Codes (Earnings 3 Code)
| Code | Typical Meaning | Notes |
|------|----------------|-------|
| `T` | Tips | Reported tips |
| `B` | Bonus | One-time bonus payment |
| `C` | Commission | Commission earnings |

### Memo Codes
| Code | Typical Meaning |
|------|----------------|
| `5` | Reported Tips |

---

## Sample Records Explained

### Basic — Employee with regular hours + overtime
```csv
ADP,000001,100,,, 80.00, 4.50,,,,,,,,,,,,,,
```
Employee #100 worked 80 regular hours + 4.5 OT hours. No dept override, no rate override.

### With Holiday Hours
```csv
ADP,000001,102,,, 80.00, 0.00,H, 8.00,,,,,,,,,,,,
```
Employee #102 worked 80 reg hours, no OT, plus 8 holiday hours.

### With Temp Department Override
```csv
ADP,000001,103,SHIP,, 40.00, 0.00,V, 8.00,,,,,,,,,,,,
```
Employee #103 worked 40 hours in SHIP department (not their home dept), plus 8 hours PTO.

### With Temp Rate Override
```csv
ADP,000001,105,PROD, 22.50, 72.00, 0.00,,,,,,,,,,,,,,
```
Employee #105 worked 72 hours in PROD dept at a temporary rate of $22.50/hr.

---

## Import Path in ADP Workforce Now

1. Start or open a payroll cycle
2. Navigate to: **Process → Payroll → Import Paydata**
   - *OR*: **Payroll Dashboard → Import File**
3. Drag/upload your `PRcccEPI.csv` file
4. Review and confirm the batch
5. ADP maps File # to employees and populates the Payroll Worksheet

---

## Integration Notes for Laravel Time Attendance Solution

### Mapping Your Data to ADP Fields

| Your App Field | ADP Column | Notes |
|---------------|------------|-------|
| `employee.adp_file_number` | File # | Store this in your employees table |
| `employee.department_code` | Temp Dept | Only if overriding home dept |
| `timesheet.regular_hours` | Reg Hours | Sum of regular hours for pay period |
| `timesheet.overtime_hours` | O/T Hours | Hours exceeding 40/week |
| `timesheet.holiday_hours` | Hours 3 (H) | Holiday hours if tracked |
| `timesheet.pto_hours` | Hours 3 (V) | PTO/Vacation hours |
| `timesheet.sick_hours` | Hours 3 (S) | Sick leave hours |
| Company code (config) | Co Code | Store in app config/env |
| Auto-generated | Batch ID | YYMMDD or sequential counter |

### Suggested Database Column
Add to your `employees` migration:
```php
$table->string('adp_file_number', 6)->nullable()->index();
$table->string('adp_department_code', 10)->nullable();
```

### Laravel Export Example (Conceptual)
```php
// In your ADP Export Service
public function generatePaydataCSV(PayPeriod $payPeriod): string
{
    $companyCode = config('adp.company_code'); // e.g., 'RGI'
    $batchId = $payPeriod->end_date->format('ymd'); // YYMMDD

    $rows = [];
    $rows[] = 'Co Code,Batch ID,File #,Temp Dept,Temp Rate,Reg Hours,O/T Hours,Hours 3 Code,Hours 3 Amount';

    foreach ($payPeriod->approvedTimesheets as $timesheet) {
        $employee = $timesheet->employee;

        $row = implode(',', [
            $companyCode,
            $batchId,
            $employee->adp_file_number,
            '', // Temp Dept
            '', // Temp Rate
            number_format($timesheet->regular_hours, 2, '.', ''),
            number_format($timesheet->overtime_hours, 2, '.', ''),
            $timesheet->holiday_hours > 0 ? 'H' : '',
            $timesheet->holiday_hours > 0 ? number_format($timesheet->holiday_hours, 2, '.', '') : '',
        ]);

        $rows[] = $row;
    }

    $filename = "PR{$companyCode}EPI.csv";
    return implode("\n", $rows);
}
```

---

## Important Warnings

1. **Do NOT open/resave the CSV in Excel** before importing — Excel can corrupt the flat file format
2. **One pay frequency per file** — don't mix weekly and biweekly employees
3. **Only active employees** — terminated or LOA employees will be rejected
4. **Decimal precision** — always use 2 decimal places for hours (e.g., `8.00` not `8`)
5. **Batch ID must be unique** per payroll cycle — reusing a batch ID can cause duplicates
6. **File # must match ADP exactly** — this is how ADP identifies which employee gets the hours

---

## Getting Your Company-Specific Values

Contact your ADP rep or check these locations in ADP WFN:

| Value | Where to Find It |
|-------|-----------------|
| Company Code | Setup → Company Setup → Company Information |
| Employee File Numbers | People → View Employees → Payroll tab |
| Department Codes | Setup → Tools → Validation Tables → Organization → Department |
| Hours & Earnings Codes | Setup → Tools → Validation Tables → Payroll → Paydata |
| Pay Frequencies | Setup → Payroll Setup → Pay Cycles |
