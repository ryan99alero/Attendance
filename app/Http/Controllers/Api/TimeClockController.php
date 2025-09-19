<?php

namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Device;
use App\Models\PayPeriod;
use App\Models\Credential;
use App\Models\ClockEvent;
use App\Models\PunchType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TimeClockController extends Controller
{
    /**
     * Authenticate time clock device and establish handshake
     * POST /api/v1/timeclock/auth
     */
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:100',
            'mac_address' => 'nullable|string|max:17',
            'ip_address' => 'nullable|ip',
            'firmware_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Find or create device
            $device = Device::updateOrCreate(
                ['device_id' => $request->device_id],
                [
                    'device_name' => $request->device_name ?? 'ESP32-TimeClock',
                    'mac_address' => $request->mac_address,
                    'ip_address' => $request->ip_address ?? $request->ip(),
                    'last_seen_at' => now(),
                    'last_ip' => $request->ip(),
                    'last_mac' => $request->mac_address,
                    'firmware_version' => $request->firmware_version,
                    'is_active' => true
                ]
            );

            Log::info("[TimeClockAPI] Device authenticated", [
                'device_id' => $request->device_id,
                'ip' => $request->ip_address
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device authenticated successfully',
                'data' => [
                    'device_id' => $device->device_id,
                    'device_name' => $device->device_name,
                    'server_time' => now()->toISOString(),
                    'timezone' => config('app.timezone'),
                    'api_version' => '1.0'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[TimeClockAPI] Authentication failed", [
                'device_id' => $request->device_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record employee punch from credential presentation
     * POST /api/v1/timeclock/punch
     */
    public function recordPunch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'credential_kind' => 'required|string|in:rfid,nfc,magstripe,qrcode,barcode,ble,biometric,pin,mobile',
            'credential_value' => 'required|string',
            'event_time' => 'nullable|date',
            'event_type' => 'nullable|string|in:in,out,break_in,break_out,unknown',
            'location' => 'nullable|string|max:191',
            'confidence' => 'nullable|integer|min:0|max:100',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid punch data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // 1. Resolve/update device (same logic as auth endpoint)
            $device = Device::updateOrCreate(
                ['device_id' => $request->device_id],
                [
                    'device_name' => $request->device_id,
                    'ip_address' => $request->ip(),
                    'last_seen_at' => now(),
                    'last_ip' => $request->ip(),
                    'is_active' => true
                ]
            );

            // 2. Normalize and hash credential
            $normalizedValue = Credential::normalizeIdentifier($request->credential_value);
            $credentialHash = hash('sha256', $normalizedValue);

            // 3. Find credential and employee
            $credential = Credential::where('kind', $request->credential_kind)
                                  ->where('identifier_hash', $credentialHash)
                                  ->active()
                                  ->first();

            $employee = null;
            $status = 'unmatched';

            if ($credential && $credential->employee) {
                if ($credential->employee->is_active) {
                    $employee = $credential->employee;
                    $status = 'recorded';

                    // Update credential last used
                    $credential->update(['last_used_at' => now()]);
                } else {
                    $status = 'rejected'; // inactive employee
                }
            }

            // 4. Event time and duplicate check
            $eventTime = $request->event_time ?
                Carbon::parse($request->event_time) :
                now();

            // Check for duplicates within 10 seconds (only if we have a valid credential)
            if ($credential && ClockEvent::hasDuplicateWithin(
                $device->id,
                $credential->id,
                $eventTime,
                10
            )) {
                return response()->json([
                    'success' => true,
                    'message' => 'Duplicate event ignored',
                    'display_message' => 'Already recorded.'
                ], 200);
            }

            // 5. Create clock event (always record, even unmatched)
            $clockEvent = ClockEvent::create([
                'employee_id' => $employee?->id,
                'device_id' => $device->id,
                'credential_id' => $credential?->id,
                'event_time' => $eventTime,
                'shift_date' => $eventTime->toDateString(),
                'event_source' => 'device',
                'location' => $request->location,
                'confidence' => $request->confidence,
                'raw_payload' => array_merge(
                    $request->only(['device_id', 'credential_kind', 'credential_value']),
                    $request->meta ?? []
                ),
            ]);

            Log::info("[TimeClockAPI] Clock event recorded", [
                'clock_event_id' => $clockEvent->id,
                'employee_id' => $employee?->id,
                'employee_name' => $employee?->full_names,
                'device_id' => $request->device_id,
                'credential_kind' => $request->credential_kind,
                'event_time' => $eventTime->toISOString(),
                'status' => $status
            ]);

            // 6. Return appropriate response
            if ($status === 'recorded') {
                return response()->json([
                    'success' => true,
                    'message' => 'Event recorded successfully',
                    'data' => [
                        'clock_event_id' => $clockEvent->id,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_names,
                        'event_time' => $eventTime->format('Y-m-d H:i:s'),
                        'status' => $status
                    ],
                    'display_message' => "Hello {$employee->full_names}! Time recorded at " . $eventTime->format('g:i A')
                ]);
            }

            if ($status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee is inactive',
                    'display_message' => 'Access denied. Please contact HR.'
                ], 403);
            }

            // Unmatched credential - but we still recorded the event for audit purposes
            return response()->json([
                'success' => false,
                'message' => 'Credential not recognized',
                'data' => [
                    'clock_event_id' => $clockEvent->id,
                    'status' => 'unmatched'
                ],
                'display_message' => 'Credential not recognized. Please contact HR.'
            ], 404);

        } catch (\Exception $e) {
            Log::error("[TimeClockAPI] Clock event recording failed", [
                'device_id' => $request->device_id,
                'credential_kind' => $request->credential_kind,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record event',
                'display_message' => 'System error. Please try again.'
            ], 500);
        }
    }

    /**
     * Get employee information and hours summary
     * GET /api/v1/timeclock/employee/{credential_value}?kind={credential_kind}
     */
    public function getEmployeeInfo(Request $request, $credentialValue)
    {
        try {
            $credentialKind = $request->query('kind', 'rfid'); // Default to rfid for backward compatibility

            // Normalize and hash the credential
            $normalizedValue = Credential::normalizeIdentifier($credentialValue);
            $credentialHash = hash('sha256', $normalizedValue);

            // Find employee by credential
            $credential = Credential::where('kind', $credentialKind)
                                  ->where('identifier_hash', $credentialHash)
                                  ->active()
                                  ->first();

            if (!$credential || !$credential->employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $employee = $credential->employee;

            if (!$employee->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee is inactive'
                ], 403);
            }

            // Get current pay period
            $currentPayPeriod = PayPeriod::current();
            $today = Carbon::today();
            $weekStart = $today->copy()->startOfWeek();
            $monthStart = $today->copy()->startOfMonth();

            // Calculate hours for different periods
            $hoursData = [
                'today' => $this->calculateHours($employee->id, $today, $today),
                'week' => $this->calculateHours($employee->id, $weekStart, $today),
                'month' => $this->calculateHours($employee->id, $monthStart, $today),
                'pay_period' => $currentPayPeriod ?
                    $this->calculateHours($employee->id, $currentPayPeriod->start_date, $currentPayPeriod->end_date) :
                    ['regular' => 0, 'overtime' => 0, 'total' => 0]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->full_names,
                        'external_id' => $employee->external_id,
                        'department' => $employee->department?->name,
                        'is_active' => $employee->is_active
                    ],
                    'hours' => $hoursData,
                    'current_pay_period' => $currentPayPeriod ? [
                        'id' => $currentPayPeriod->id,
                        'start_date' => $currentPayPeriod->start_date->toDateString(),
                        'end_date' => $currentPayPeriod->end_date->toDateString()
                    ] : null,
                    'server_time' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("[TimeClockAPI] Employee info fetch failed", [
                'credential_value' => $credentialValue,
                'credential_kind' => $credentialKind ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate hours worked for an employee in a date range
     */
    private function calculateHours($employeeId, $startDate, $endDate)
    {
        // Get completed attendance records (migrated to punches)
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereBetween('punch_time', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->where('status', 'Posted') // Only count finalized records
            ->get();

        // Basic calculation - you may want to implement more sophisticated logic
        $totalMinutes = 0;
        $punchPairs = $attendances->groupBy('shift_date');

        foreach ($punchPairs as $date => $punches) {
            // Simple punch pairing logic (Clock In -> Clock Out)
            $clockIns = $punches->where('punch_state', 'start');
            $clockOuts = $punches->where('punch_state', 'stop');

            $minCount = min($clockIns->count(), $clockOuts->count());

            for ($i = 0; $i < $minCount; $i++) {
                $clockIn = $clockIns->skip($i)->first();
                $clockOut = $clockOuts->skip($i)->first();

                if ($clockIn && $clockOut) {
                    $minutes = Carbon::parse($clockOut->punch_time)
                        ->diffInMinutes(Carbon::parse($clockIn->punch_time));
                    $totalMinutes += $minutes;
                }
            }
        }

        $totalHours = round($totalMinutes / 60, 2);
        $regularHours = min($totalHours, 40); // Assuming 40 hour work week
        $overtimeHours = max(0, $totalHours - 40);

        return [
            'regular' => $regularHours,
            'overtime' => $overtimeHours,
            'total' => $totalHours
        ];
    }

    /**
     * Health check endpoint for time clock devices
     * GET /api/v1/timeclock/health
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'Time Clock API is healthy',
            'server_time' => now()->toISOString(),
            'api_version' => '1.0'
        ]);
    }
}