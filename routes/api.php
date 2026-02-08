<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TimeClockController;
use App\Http\Controllers\Api\WebhookSyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Keep-Alive Endpoints
|--------------------------------------------------------------------------
|
| These endpoints are used to maintain connection during long-running
| operations and prevent timeout issues.
|
*/

/**
 * Keep-Alive (Public)
 *
 * Maintains connection during long-running operations. No authentication required.
 *
 * @method POST
 * @url    /api/keep-alive
 *
 * @example curl -X POST http://attend.test/api/keep-alive \
 *              -H "Content-Type: application/json" \
 *              -d '{"processing": true}'
 *
 * @response {
 *     "status": "alive",
 *     "timestamp": "2026-01-30T12:00:00.000000Z",
 *     "processing": true
 * }
 */
Route::post('/keep-alive', function (Request $request) {
    return response()->json([
        'status' => 'alive',
        'timestamp' => now(),
        'processing' => $request->input('processing', false)
    ]);
})->name('api.keep-alive');

/**
 * Keep-Alive (Web Session)
 *
 * Maintains authenticated web session during long-running operations.
 * Requires web middleware for session handling.
 *
 * @method POST
 * @url    /api/web-keep-alive
 *
 * @example curl -X POST http://attend.test/api/web-keep-alive \
 *              -H "Content-Type: application/json" \
 *              -H "Cookie: laravel_session=xxx" \
 *              -d '{"processing": true}'
 *
 * @response {
 *     "status": "alive",
 *     "timestamp": "2026-01-30T12:00:00.000000Z",
 *     "session_id": "abc123...",
 *     "processing": true
 * }
 */
Route::middleware('web')->post('/web-keep-alive', function (Request $request) {
    return response()->json([
        'status' => 'alive',
        'timestamp' => now(),
        'session_id' => session()->getId(),
        'processing' => $request->input('processing', false)
    ]);
})->name('web.keep-alive');

/*
|--------------------------------------------------------------------------
| ESP32 Time Clock API Routes (v1)
|--------------------------------------------------------------------------
|
| API endpoints for ESP32-based time clock devices. These endpoints handle:
| - Device registration and authentication
| - Time synchronization
| - Punch recording (clock in/out)
| - Employee information lookup
| - Device configuration management
|
| Base URL: /api/v1/timeclock
|
*/

/*
|--------------------------------------------------------------------------
| Integration Webhook Routes
|--------------------------------------------------------------------------
|
| Token-authenticated webhook endpoint for triggering integration syncs.
| No auth middleware — the token in the URL serves as authentication.
|
*/

Route::post('/webhooks/sync/{token}/{object?}', [WebhookSyncController::class, 'trigger'])
    ->name('webhooks.sync');

Route::prefix('v1/timeclock')->group(function () {

    /**
     * Health Check
     *
     * Simple endpoint to verify API server is reachable. No authentication required.
     * Used by devices to test connectivity before other operations.
     *
     * @method GET
     * @url    /api/v1/timeclock/health
     *
     * @example curl http://attend.test/api/v1/timeclock/health
     *
     * @response {
     *     "status": "ok",
     *     "timestamp": "2026-01-30T12:00:00.000000Z",
     *     "version": "1.0"
     * }
     */
    Route::get('/health', [TimeClockController::class, 'health'])
        ->name('timeclock.health');

    /**
     * Time Synchronization
     *
     * Returns current server time for device clock synchronization.
     * Includes timezone information and optional NTP server recommendation.
     *
     * @method GET
     * @url    /api/v1/timeclock/time
     *
     * @example curl http://attend.test/api/v1/timeclock/time
     *
     * @response {
     *     "success": true,
     *     "server_time": "2026-01-30T12:00:00.000000Z",
     *     "unix_timestamp": 1769860800,
     *     "timezone": "America/Chicago",
     *     "timezone_offset": -21600,
     *     "ntp_server": "pool.ntp.org",
     *     "use_server_time": true
     * }
     */
    Route::get('/time', [TimeClockController::class, 'getTime'])
        ->name('timeclock.time');

    /**
     * Device Registration
     *
     * Registers a new time clock device with the server. No authentication required
     * for initial registration. Device receives an API token for subsequent requests.
     * Device must be approved by admin before it can record punches.
     *
     * @method POST
     * @url    /api/v1/timeclock/register
     *
     * @param  string  mac_address   Device MAC address (unique identifier)
     * @param  string  device_name   Human-readable device name
     * @param  string  firmware_version  (optional) Current firmware version
     * @param  string  model         (optional) Device model/type
     *
     * @example curl -X POST http://attend.test/api/v1/timeclock/register \
     *              -H "Content-Type: application/json" \
     *              -d '{
     *                  "mac_address": "30:ED:A0:E2:20:73",
     *                  "device_name": "Front Lobby Clock",
     *                  "firmware_version": "1.0.0",
     *                  "model": "ESP32-P4"
     *              }'
     *
     * @response {
     *     "success": true,
     *     "message": "Device registered successfully",
     *     "device_id": "tc_abc123",
     *     "api_token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ",
     *     "is_approved": false,
     *     "requires_approval": true
     * }
     */
    Route::post('/register', [TimeClockController::class, 'register'])
        ->name('timeclock.register');

    /**
     * Device Status Check
     *
     * Returns current device status including approval state and configuration.
     * Requires device authentication via API token.
     *
     * @method GET
     * @url    /api/v1/timeclock/status
     * @header Authorization: Bearer {api_token}
     *
     * @example curl http://attend.test/api/v1/timeclock/status \
     *              -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ"
     *
     * @response {
     *     "success": true,
     *     "device_id": "tc_abc123",
     *     "device_name": "Front Lobby Clock",
     *     "is_approved": true,
     *     "is_active": true,
     *     "last_seen": "2026-01-30T11:55:00.000000Z",
     *     "firmware_version": "1.0.0",
     *     "pending_config_update": false
     * }
     */
    Route::get('/status', [TimeClockController::class, 'status'])
        ->name('timeclock.status');

    /**
     * Device Authentication / Handshake
     *
     * Authenticates a previously registered device. Used to verify credentials
     * and refresh authentication state. Can also be used to re-authenticate
     * after token expiration.
     *
     * @method POST
     * @url    /api/v1/timeclock/auth
     *
     * @param  string  device_id     Device identifier from registration
     * @param  string  mac_address   Device MAC address for verification
     * @param  string  api_token     (optional) Existing token to refresh
     *
     * @example curl -X POST http://attend.test/api/v1/timeclock/auth \
     *              -H "Content-Type: application/json" \
     *              -d '{
     *                  "device_id": "tc_abc123",
     *                  "mac_address": "30:ED:A0:E2:20:73"
     *              }'
     *
     * @response {
     *     "success": true,
     *     "message": "Authentication successful",
     *     "device_id": "tc_abc123",
     *     "is_approved": true,
     *     "api_token": "2|newTokenIfRefreshed..."
     * }
     */
    Route::post('/auth', [TimeClockController::class, 'authenticate'])
        ->name('timeclock.auth');

    /**
     * Record Punch (Clock In/Out)
     *
     * Records a time punch from a card/RFID swipe. The punch is associated with
     * an employee based on the credential (card) value. Supports offline queuing -
     * devices can submit punches recorded while offline with their original timestamps.
     *
     * @method POST
     * @url    /api/v1/timeclock/punch
     * @header Authorization: Bearer {api_token}
     *
     * @param  string  device_id         Device identifier
     * @param  string  credential_value  Card UID/number (e.g., "04A3B2C1D4E5F6")
     * @param  string  credential_kind   Card type (e.g., "MIFARE Ultralight", "NFC")
     * @param  string  event_time        ISO 8601 timestamp when punch occurred
     * @param  string  event_type        (optional) "clock_in", "clock_out", or "unknown"
     * @param  int     timezone_offset   (optional) Hours offset from UTC (e.g., -6 for CST)
     * @param  int     confidence        (optional) Read confidence 0-100
     *
     * @example curl -X POST http://attend.test/api/v1/timeclock/punch \
     *              -H "Content-Type: application/json" \
     *              -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ" \
     *              -d '{
     *                  "device_id": "tc_abc123",
     *                  "credential_value": "04A3B2C1D4E5F6",
     *                  "credential_kind": "MIFARE Ultralight",
     *                  "event_time": "2026-01-30T08:00:00",
     *                  "event_type": "clock_in",
     *                  "timezone_offset": -6,
     *                  "confidence": 100
     *              }'
     *
     * @response {
     *     "success": true,
     *     "message": "Punch recorded successfully",
     *     "punch_id": 12345,
     *     "employee_name": "John Smith",
     *     "event_type": "clock_in",
     *     "recorded_time": "2026-01-30T08:00:00-06:00",
     *     "display_message": "Hello John Smith! Time recorded at 8:00 AM"
     * }
     *
     * @error 400 {
     *     "success": false,
     *     "message": "Employee not found for this credential"
     * }
     */
    Route::post('/punch', [TimeClockController::class, 'recordPunch'])
        ->name('timeclock.punch');

    /**
     * Get Device Configuration
     *
     * Retrieves current configuration settings for the device.
     * Includes display settings, network preferences, and operational parameters.
     *
     * @method GET
     * @url    /api/v1/timeclock/config
     * @header Authorization: Bearer {api_token}
     *
     * @example curl http://attend.test/api/v1/timeclock/config \
     *              -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ"
     *
     * @response {
     *     "success": true,
     *     "config": {
     *         "device_name": "Front Lobby Clock",
     *         "timezone": "America/Chicago",
     *         "time_format": "12h",
     *         "date_format": "MM/DD/YYYY",
     *         "display_brightness": 80,
     *         "sound_enabled": true,
     *         "sync_interval_seconds": 300,
     *         "offline_mode_enabled": true
     *     },
     *     "last_updated": "2026-01-30T10:00:00.000000Z"
     * }
     */
    Route::get('/config', [TimeClockController::class, 'getConfig'])
        ->name('timeclock.config.get');

    /**
     * Update Device Configuration
     *
     * Updates configuration settings for a specific device.
     * Only provided fields are updated; others remain unchanged.
     *
     * @method PUT
     * @url    /api/v1/timeclock/config/{deviceId}
     * @header Authorization: Bearer {api_token}
     *
     * @param  string  deviceId          Device identifier (URL parameter)
     * @param  string  device_name       (optional) New device name
     * @param  string  timezone          (optional) Timezone identifier
     * @param  int     display_brightness (optional) 0-100
     * @param  bool    sound_enabled     (optional) Enable/disable sounds
     *
     * @example curl -X PUT http://attend.test/api/v1/timeclock/config/tc_abc123 \
     *              -H "Content-Type: application/json" \
     *              -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ" \
     *              -d '{
     *                  "device_name": "Main Entrance Clock",
     *                  "display_brightness": 90,
     *                  "sound_enabled": false
     *              }'
     *
     * @response {
     *     "success": true,
     *     "message": "Configuration updated",
     *     "config": { ... updated config ... }
     * }
     */
    Route::put('/config/{deviceId}', [TimeClockController::class, 'updateConfig'])
        ->name('timeclock.config.update');

    /**
     * Get Employee Information
     *
     * Retrieves employee details and work hour statistics based on their
     * credential (card) value. Used to display employee info after card scan.
     *
     * @method GET
     * @url    /api/v1/timeclock/employee/{card_id}
     * @header Authorization: Bearer {api_token}
     *
     * @param  string  card_id   Card UID/credential value (URL parameter)
     *
     * @example curl http://attend.test/api/v1/timeclock/employee/04A3B2C1D4E5F6 \
     *              -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ"
     *
     * @response {
     *     "success": true,
     *     "data": {
     *         "employee": {
     *             "id": 42,
     *             "name": "John Smith",
     *             "external_id": "EMP001",
     *             "department": "Engineering",
     *             "is_active": true
     *         },
     *         "hours": {
     *             "today": {"regular": 4.5, "overtime": 0, "total": 4.5},
     *             "week": {"regular": 32.0, "overtime": 2.0, "total": 34.0},
     *             "month": {"regular": 140.0, "overtime": 8.0, "total": 148.0},
     *             "pay_period": {"regular": 72.0, "overtime": 4.0, "total": 76.0}
     *         },
     *         "current_pay_period": {
     *             "id": 5,
     *             "start_date": "2026-01-16",
     *             "end_date": "2026-01-31"
     *         },
     *         "server_time": "2026-01-30T12:00:00.000000Z"
     *     }
     * }
     *
     * @error 404 {
     *     "success": false,
     *     "message": "Employee not found for credential"
     * }
     */
    Route::get('/employee/{card_id}', [TimeClockController::class, 'getEmployeeInfo'])
        ->name('timeclock.employee.info');
});
