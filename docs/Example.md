Yes — you can generate an ADP Workforce Now Paydata import file without logging into ADP, but you need to understand one key constraint:

ADP’s import layouts and valid “codes” are configuration-dependent (company code, employee file IDs, earnings/hour codes, memo codes, departments, etc.). So I can give you a widely-used, real-world EPI CSV layout and the meaning of each field, but the file will only import cleanly if the target ADP tenant has the matching codes configured. (That’s why most official templates live behind the portal.)

The most common ADP WFN Paydata CSV layout (EPI)

This header is used by multiple third-party time systems exporting “ADP WFN Export” files:

Co Code,Batch ID,File #,Employee Name,Temp Dept,Temp Rate,Reg Hours,O/T Hours,Hours 3 Code,Hours 3 Amount,Hours 4 Code,Hours 4 Amount,Earnings 5 Code,Earnings 5 Amount

Field meanings (what each column is)
	•	Co Code
ADP company code (often 2–3 chars/digits). Required by ADP imports.
Source example listing Co Code as column 1: https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
	•	Batch ID
Identifier for the paydata batch in ADP. Often anything unique per import run.
	•	File #
Employee “File ID” / “File Number” ADP uses to identify the employee for paydata import. Third-party guides call this out explicitly.
https://www.redcort.com/files/pdfs/payroll-guides/ADP-Workforce-Now-Payroll-Integration-Guide.pdf
	•	Employee Name
Usually informational; some exports include it, ADP may ignore it depending on template.
	•	Temp Dept
Temporary department override for the imported row (optional). Often maps to ADP department code tables (must exist in ADP).
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
	•	Temp Rate
Temporary rate override (optional). Used when the source system wants to send an explicit rate.
	•	Reg Hours
Regular hours for the period.
	•	O/T Hours
Overtime hours for the period.
	•	Hours 3 Code / Hours 3 Amount
“Hours & Earnings Code” + amount (hours) for a third bucket (commonly Holiday, but could be anything configured).
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
	•	Hours 4 Code / Hours 4 Amount
Another “Hours & Earnings Code” + amount (often PTO / Sick / Unpaid depending on what you map).
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
	•	Earnings 5 Code / Earnings 5 Amount
“Earnings Code” + amount (money) bucket (often Tips, bonuses, etc., depending on mapping).
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export

Example rows

Co Code,Batch ID,File #,Employee Name,Temp Dept,Temp Rate,Reg Hours,O/T Hours,Hours 3 Code,Hours 3 Amount,Hours 4 Code,Hours 4 Amount,Earnings 5 Code,Earnings 5 Amount
ABC,2026-02-14-01,100012,Smith John,WAREHOUSE,,40.00,2.50,HOL,8.00,PTO,4.00,TIPS,125.37
ABC,2026-02-14-01,100045,Doe Jane,,,38.00,0.00,,,SPTO,6.00,,0.00

The “unique codes” you asked about (the ones that matter)

These are the ones that will cause import failures if they don’t match ADP configuration:
	1.	Co Code
Must match the ADP company code for that tenant.
	2.	File #
Must match the employee’s ADP File ID exactly (third-party guides are strict about this).
https://www.redcort.com/files/pdfs/payroll-guides/ADP-Workforce-Now-Payroll-Integration-Guide.pdf
	3.	Hours & Earnings Codes (HOL, PTO, SPTO, etc.)
These are ADP validation-table codes. If you send a code ADP doesn’t recognize, you’ll commonly see “Completed with errors” and the row won’t load.
A public example of configuring these codes: https://marketplace-cdn.adp.com/dev-portal/pdf/protected/Payroll_Data_Input_API__Guide_for_ADP_Workforce_Now
	4.	Tips-specific quirk (common real-world requirement)
Some integrations/documentation require a specific Earnings Code (often T) and Memo Code (often 5) to be created/active in ADP for tip workflows.
https://marketplace-cdn.adp.com/dev-portal/pdf/protected/Payroll_Data_Input_API__Guide_for_ADP_Workforce_Now
Also shown in a practical walkthrough: https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
	5.	Department codes (Temp Dept)
If you populate Temp Dept, that code must exist in ADP’s department tables (or whatever validation table is used in that tenant).
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export

File naming convention (often enforced)

Many ADP WFN imports enforce a filename like:

PRcccEPI.csv

Where ccc is the company code.
https://kb.7shifts.com/hc/en-us/articles/4417520074387-ADP-Workforce-Now-US-Payroll-Export
Another example discussing PR ccc EPI.csv: https://support.timeportal.io/hc/en-us/articles/23838801956887-ADP-Workforce-Now-Payroll-Export

If you tell me what you’re importing, I’ll tailor the exact template

There are multiple “imports” people mean by ADP WFN:
	•	Paydata (hours/earnings) via EPI CSV (what I showed)
	•	New hire / employee demographic import
	•	Deductions/benefits
	•	Department/cost-center validation tables

If you reply with just:
	•	which import you want (paydata vs employee),
	•	whether you need PTO/sick/tips,
	•	and whether you identify employees by “File #” or “Associate ID” in your data feed,

…I’ll output a finalized CSV header + column rules + code mapping plan that’s ready to implement in your exporter.