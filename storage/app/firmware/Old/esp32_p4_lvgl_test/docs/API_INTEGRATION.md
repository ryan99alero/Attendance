# API Integration Guide

Complete guide for ESP32-P4 Time Clock communication with Laravel backend

## Table of Contents
- [Overview](#overview)
- [API Architecture](#api-architecture)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Request/Response Format](#requestresponse-format)
- [Error Handling](#error-handling)
- [ESP32 Implementation](#esp32-implementation)
- [Testing](#testing)

## Overview

The ESP32 time clock communicates with a Laravel-based web application over HTTP/HTTPS. The API provides endpoints for:

- Device registration and authentication
- Time synchronization
- Clock event recording (punch in/out)
- Employee information retrieval
- Configuration management

### Base URL

```
Development: http://attend.test/api/v1/timeclock
Production:  https://your-domain.com/api/v1/timeclock
```

## API Architecture

### Communication Flow

```
┌─────────────┐                    ┌─────────────┐
│  ESP32-P4   │                    │   Laravel   │
│  Time Clock │                    │   Backend   │
└──────┬──────┘                    └──────┬──────┘
       │                                  │
       │  1. POST /auth                   │
       │  (MAC address, device info)      │
       ├─────────────────────────────────>│
       │                                  │
       │  2. 200 OK                       │
       │  { api_token, device_id, ... }   │
       │<─────────────────────────────────┤
       │                                  │
       │  3. POST /punch                  │
       │  Authorization: Bearer {token}   │
       │  { card_uid, timestamp, ... }    │
       ├─────────────────────────────────>│
       │                                  │
       │  4. 200 OK                       │
       │  { employee_name, hours, ... }   │
       │<─────────────────────────────────┤
       │                                  │
```

### Device Identification

Each ESP32 device is uniquely identified by its **MAC address** (primary) and assigned a **device_id** by the server.

## Authentication

### Initial Registration

**Endpoint**: `POST /register`

Register a new device with the system.

**Request**:
```json
{
  "device_name": "ESP32-P4-NFC-Clock-01",
  "mac_address": "30:ED:A0:E2:20:73",
  "firmware_version": "1.0.0",
  "device_config": {
    "ntp_server": "pool.ntp.org",
    "nfc_enabled": true,
    "buzzer_enabled": true,
    "led_enabled": true
  }
}
```

**Response (201 Created)**:
```json
{
  "success": true,
  "message": "Device registered successfully",
  "data": {
    "device_id": "ESP32_A1B2C3D4",
    "device_name": "ESP32-P4-NFC-Clock-01",
    "api_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_expires_at": "2026-02-26T12:00:00.000000Z",
    "registration_status": "registered",
    "device_config": {...},
    "server_time": "2026-01-26T12:00:00.000000Z",
    "api_version": "1.0"
  },
  "instructions": [
    "Store the api_token securely on your device",
    "Include Authorization: Bearer {api_token} in future API calls",
    "Check registration status with GET /status"
  ]
}
```

### Device Authentication

**Endpoint**: `POST /auth`

Authenticate an existing device (after registration or on restart).

**Request**:
```json
{
  "mac_address": "30:ED:A0:E2:20:73",
  "device_name": "ESP32-P4-NFC-Clock-01",
  "ip_address": "192.168.1.100",
  "firmware_version": "1.0.0"
}
```

**Response (200 OK)**:
```json
{
  "success": true,
  "message": "Device authenticated successfully",
  "data": {
    "device_id": "ESP32_A1B2C3D4",
    "device_name": "ESP32-P4-NFC-Clock-01",
    "api_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "registration_status": "approved",
    "server_time": "2026-01-26T12:00:00.000000Z",
    "timezone": "UTC",
    "api_version": "1.0",
    "device_timezone": {
      "timezone_name": "America/Chicago",
      "current_offset": -6,
      "is_dst": false,
      "timezone_abbr": "CST",
      "device_time": "2026-01-26 06:00:00"
    }
  }
}
```

**Important**: Store the `api_token` in NVS. Include it in all subsequent API calls:
```
Authorization: Bearer {api_token}
```

## API Endpoints

### 1. Health Check

**Endpoint**: `GET /health`

Check if the API is reachable.

**Request**: No parameters

**Response**:
```json
{
  "success": true,
  "message": "Time Clock API is healthy",
  "server_time": "2026-01-26T12:00:00.000000Z",
  "api_version": "1.0"
}
```

**Use Case**: Network connectivity test, API availability check

---

### 2. Get Server Time

**Endpoint**: `GET /time?mac_address={mac}`

Lightweight time synchronization endpoint.

**Request**:
```
GET /time?mac_address=30:ED:A0:E2:20:73
```

**Response**:
```json
{
  "success": true,
  "server_time": "2026-01-26T12:00:00.000000Z",
  "unix_timestamp": 1737892800,
  "formatted_time": "2026-01-26 12:00:00",
  "server_timezone": "UTC",
  "device_timezone": {
    "timezone_name": "America/Chicago",
    "current_offset": -6,
    "is_dst": false,
    "timezone_abbr": "CST",
    "device_time": "2026-01-26 06:00:00"
  },
  "device_registered": true
}
```

**Use Case**: Periodic time sync (every 5-10 minutes)

---

### 3. Record Punch

**Endpoint**: `POST /punch`

Record a clock-in/out event from NFC card or other credential.

**Authentication**: Required (`Authorization: Bearer {token}`)

**Request**:
```json
{
  "device_id": "ESP32_A1B2C3D4",
  "credential_kind": "nfc",
  "credential_value": "04A1B2C3D4E5F6",
  "event_time": "2026-01-26 08:30:00",
  "event_type": "in",
  "location": "Main Office",
  "confidence": 100,
  "meta": {
    "card_type": "Mifare Classic",
    "reader_type": "PN532"
  },
  "device_timezone": "-6"
}
```

**Field Descriptions**:
- `device_id`: Your device's ID (from auth response)
- `credential_kind`: `nfc`, `rfid`, `qrcode`, `barcode`, `pin`, `mobile`, etc.
- `credential_value`: Card UID, QR code data, PIN, etc.
- `event_time`: Local time when card was presented (format: `Y-m-d H:i:s`)
- `event_type`: Optional - `in`, `out`, `break_in`, `break_out`, `unknown`
- `location`: Optional - physical location name
- `confidence`: Optional - 0-100 confidence score
- `meta`: Optional - additional metadata
- `device_timezone`: Optional - timezone offset (e.g., "-6" for CST)

**Response (200 OK - Success)**:
```json
{
  "success": true,
  "message": "Event recorded successfully",
  "data": {
    "clock_event_id": 12345,
    "employee_id": 42,
    "employee_name": "John Doe",
    "event_time": "2026-01-26 08:30:00",
    "status": "recorded"
  },
  "display_message": "Hello John Doe! Time recorded at 8:30 AM"
}
```

**Response (404 Not Found - Unrecognized Card)**:
```json
{
  "success": false,
  "message": "Credential not recognized",
  "data": {
    "clock_event_id": 12346,
    "status": "unmatched"
  },
  "display_message": "Credential not recognized. Please contact HR."
}
```

**Response (403 Forbidden - Inactive Employee)**:
```json
{
  "success": false,
  "message": "Employee is inactive",
  "display_message": "Access denied. Please contact HR."
}
```

**Use Case**: When NFC card is scanned, send immediately to record attendance

---

### 4. Get Employee Information

**Endpoint**: `GET /employee/{card_id}?kind={credential_kind}`

Retrieve employee details and hours summary by credential.

**Request**:
```
GET /employee/04A1B2C3D4E5F6?kind=nfc
```

**Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "employee": {
      "id": 42,
      "name": "John Doe",
      "external_id": "EMP001",
      "department": "Engineering",
      "is_active": true
    },
    "hours": {
      "today": {
        "regular": 5.5,
        "overtime": 0,
        "total": 5.5
      },
      "week": {
        "regular": 32.0,
        "overtime": 0,
        "total": 32.0
      },
      "month": {
        "regular": 120.0,
        "overtime": 5.5,
        "total": 125.5
      },
      "pay_period": {
        "regular": 78.0,
        "overtime": 2.0,
        "total": 80.0
      }
    },
    "current_pay_period": {
      "id": 5,
      "start_date": "2026-01-16",
      "end_date": "2026-01-31"
    },
    "server_time": "2026-01-26T17:30:00.000000Z"
  }
}
```

**Response (404 Not Found)**:
```json
{
  "success": false,
  "message": "Employee not found"
}
```

**Use Case**: Display employee hours on screen after successful punch

---

### 5. Get Device Configuration

**Endpoint**: `GET /config?mac_address={mac}&current_config_version={version}`

Check for updated device configuration from server.

**Request**:
```
GET /config?mac_address=30:ED:A0:E2:20:73&current_config_version=1
```

**Response (200 OK - Updates Available)**:
```json
{
  "success": true,
  "message": "Configuration updates available",
  "has_updates": true,
  "config_version": 2,
  "config": {
    "device_name": "ESP32-P4-NFC-Clock-01",
    "display_name": "Front Desk Time Clock",
    "timezone": "America/Chicago",
    "ntp_server": "pool.ntp.org",
    "registration_status": "approved",
    "config_updated_at": "2026-01-26T12:00:00.000000Z",
    "device_timezone": {
      "timezone_name": "America/Chicago",
      "current_offset": -6,
      "is_dst": false,
      "timezone_abbr": "CST",
      "device_time": "2026-01-26 06:00:00"
    }
  }
}
```

**Response (200 OK - Up to Date)**:
```json
{
  "success": true,
  "message": "Configuration is up to date",
  "config_version": 1,
  "has_updates": false
}
```

**Use Case**: Periodic check for config updates (every 10-15 minutes)

---

### 6. Check Device Status

**Endpoint**: `GET /status`

Verify device registration and API token validity.

**Authentication**: Required

**Headers**:
```
Authorization: Bearer {token}
X-Device-ID: ESP32_A1B2C3D4
```

**Response**:
```json
{
  "success": true,
  "data": {
    "device_id": "ESP32_A1B2C3D4",
    "device_name": "ESP32-P4-NFC-Clock-01",
    "registration_status": "approved",
    "is_active": true,
    "is_approved": true,
    "device_config": {...},
    "last_seen_at": "2026-01-26T12:00:00.000000Z",
    "server_time": "2026-01-26T12:00:00.000000Z",
    "token_expires_at": "2026-02-26T12:00:00.000000Z"
  }
}
```

**Use Case**: Verify connectivity and token validity on startup

## Request/Response Format

### Request Headers

All API requests should include:

```http
Content-Type: application/json
Accept: application/json
User-Agent: ESP32-TimeClock/1.0.0
```

Authenticated requests also need:
```http
Authorization: Bearer {api_token}
```

### Response Format

All responses follow this structure:

```json
{
  "success": true | false,
  "message": "Human-readable message",
  "data": { /* Response data */ },
  "display_message": "Message to show on device screen",
  "errors": { /* Validation errors if any */ }
}
```

### HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created (e.g., device registered)
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid API token
- `403 Forbidden` - Authenticated but not authorized
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Error Handling

### Network Errors

When network is unavailable:

```c
// Store punch locally
store_offline_punch(card_uid, timestamp);

// Show message to user
ui_manager_show_message("Offline - data saved", true);

// Retry when network returns
sync_offline_punches();
```

### API Errors

Handle different error types:

```c
void handle_api_response(http_response_t *response)
{
    if(response->status_code >= 200 && response->status_code < 300) {
        // Success
        cJSON *data = cJSON_Parse(response->body);
        const char *display_msg = cJSON_GetStringValue(
            cJSON_GetObjectItem(data, "display_message"));
        ui_manager_show_message(display_msg, true);
        cJSON_Delete(data);

    } else if(response->status_code == 401) {
        // Unauthorized - token expired
        ESP_LOGW(TAG, "Token expired, re-authenticating");
        api_client_authenticate();

    } else if(response->status_code == 404) {
        // Not found - unrecognized card
        cJSON *data = cJSON_Parse(response->body);
        const char *display_msg = cJSON_GetStringValue(
            cJSON_GetObjectItem(data, "display_message"));
        ui_manager_show_message(display_msg, false);
        cJSON_Delete(data);

    } else if(response->status_code >= 500) {
        // Server error
        ESP_LOGE(TAG, "Server error: %d", response->status_code);
        ui_manager_show_message("Server error - try again", false);

    } else {
        // Other error
        ESP_LOGE(TAG, "API error: %d", response->status_code);
        ui_manager_show_message("Error - please try again", false);
    }
}
```

### Retry Strategy

Implement exponential backoff for retries:

```c
int retry_count = 0;
int retry_delays[] = {1000, 2000, 5000, 10000, 30000}; // milliseconds

bool send_with_retry(const char *endpoint, cJSON *payload)
{
    while(retry_count < 5) {
        esp_err_t ret = http_client_post(endpoint, payload, on_response);

        if(ret == ESP_OK) {
            retry_count = 0;
            return true;
        }

        ESP_LOGW(TAG, "Request failed, retry %d/5 in %dms",
            retry_count + 1, retry_delays[retry_count]);

        vTaskDelay(pdMS_TO_TICKS(retry_delays[retry_count]));
        retry_count++;
    }

    ESP_LOGE(TAG, "Request failed after 5 retries");
    return false;
}
```

## ESP32 Implementation

### 1. HTTP Client Setup

```c
// api_client.h
void api_client_init(const char *server_host, uint16_t server_port);
void api_client_authenticate(void);
void api_client_send_punch(const char *credential_kind, const char *credential_value);

// api_client.c
#include "esp_http_client.h"
#include "cJSON.h"

static char g_api_token[256] = {0};
static char g_device_id[32] = {0};
static char g_server_url[128] = {0};

void api_client_init(const char *server_host, uint16_t server_port)
{
    snprintf(g_server_url, sizeof(g_server_url),
        "http://%s:%d/api/v1/timeclock", server_host, server_port);

    ESP_LOGI(TAG, "API client initialized: %s", g_server_url);

    // Load saved token from NVS if available
    load_token_from_nvs();
}

void api_client_authenticate(void)
{
    char url[256];
    snprintf(url, sizeof(url), "%s/auth", g_server_url);

    // Get MAC address
    uint8_t mac[6];
    esp_read_mac(mac, ESP_MAC_WIFI_STA);
    char mac_str[18];
    snprintf(mac_str, sizeof(mac_str), "%02X:%02X:%02X:%02X:%02X:%02X",
        mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

    // Build JSON payload
    cJSON *json = cJSON_CreateObject();
    cJSON_AddStringToObject(json, "mac_address", mac_str);
    cJSON_AddStringToObject(json, "device_name", DEVICE_NAME);
    cJSON_AddStringToObject(json, "firmware_version", FIRMWARE_VERSION);

    char *json_str = cJSON_PrintUnformatted(json);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Set headers
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_post_field(client, json_str, strlen(json_str));

    // Send request
    esp_err_t err = esp_http_client_perform(client);

    if(err == ESP_OK) {
        int status = esp_http_client_get_status_code(client);
        int content_length = esp_http_client_get_content_length(client);

        if(status == 200) {
            // Read response
            char response_buffer[1024];
            int read_len = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
            response_buffer[read_len] = '\0';

            // Parse JSON response
            cJSON *response = cJSON_Parse(response_buffer);
            cJSON *data = cJSON_GetObjectItem(response, "data");

            // Extract token and device_id
            const char *token = cJSON_GetStringValue(cJSON_GetObjectItem(data, "api_token"));
            const char *device_id = cJSON_GetStringValue(cJSON_GetObjectItem(data, "device_id"));

            strncpy(g_api_token, token, sizeof(g_api_token));
            strncpy(g_device_id, device_id, sizeof(g_device_id));

            // Save to NVS
            save_token_to_nvs(g_api_token, g_device_id);

            ESP_LOGI(TAG, "Authentication successful, device_id: %s", g_device_id);

            cJSON_Delete(response);
        } else {
            ESP_LOGE(TAG, "Authentication failed, status: %d", status);
        }
    } else {
        ESP_LOGE(TAG, "HTTP request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(json);
    free(json_str);
}
```

### 2. Sending Punch Events

```c
void api_client_send_punch(const char *credential_kind, const char *credential_value)
{
    if(strlen(g_api_token) == 0) {
        ESP_LOGW(TAG, "Not authenticated");
        return;
    }

    char url[256];
    snprintf(url, sizeof(url), "%s/punch", g_server_url);

    // Get current time
    time_t now;
    time(&now);
    struct tm timeinfo;
    localtime_r(&now, &timeinfo);
    char time_str[32];
    strftime(time_str, sizeof(time_str), "%Y-%m-%d %H:%M:%S", &timeinfo);

    // Build JSON payload
    cJSON *json = cJSON_CreateObject();
    cJSON_AddStringToObject(json, "device_id", g_device_id);
    cJSON_AddStringToObject(json, "credential_kind", credential_kind);
    cJSON_AddStringToObject(json, "credential_value", credential_value);
    cJSON_AddStringToObject(json, "event_time", time_str);

    char *json_str = cJSON_PrintUnformatted(json);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Set headers including Authorization
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");

    char auth_header[512];
    snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_token);
    esp_http_client_set_header(client, "Authorization", auth_header);

    esp_http_client_set_post_field(client, json_str, strlen(json_str));

    // Send request
    esp_err_t err = esp_http_client_perform(client);

    if(err == ESP_OK) {
        int status = esp_http_client_get_status_code(client);

        // Read response
        char response_buffer[2048];
        int read_len = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
        response_buffer[read_len] = '\0';

        // Parse and show message
        cJSON *response = cJSON_Parse(response_buffer);
        const char *display_msg = cJSON_GetStringValue(
            cJSON_GetObjectItem(response, "display_message"));

        if(status == 200) {
            ESP_LOGI(TAG, "Punch recorded successfully");
            ui_manager_show_message(display_msg, true);
        } else {
            ESP_LOGW(TAG, "Punch failed: %d", status);
            ui_manager_show_message(display_msg ? display_msg : "Error", false);
        }

        cJSON_Delete(response);
    } else {
        ESP_LOGE(TAG, "HTTP request failed: %s", esp_err_to_name(err));
        ui_manager_show_message("Network error", false);
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(json);
    free(json_str);
}
```

### 3. Storing Token in NVS

```c
#include "nvs_flash.h"
#include "nvs.h"

#define NVS_NAMESPACE "timeclock"

void save_token_to_nvs(const char *token, const char *device_id)
{
    nvs_handle_t handle;
    esp_err_t err = nvs_open(NVS_NAMESPACE, NVS_READWRITE, &handle);

    if(err == ESP_OK) {
        nvs_set_str(handle, "api_token", token);
        nvs_set_str(handle, "device_id", device_id);
        nvs_commit(handle);
        nvs_close(handle);

        ESP_LOGI(TAG, "Token saved to NVS");
    } else {
        ESP_LOGE(TAG, "Failed to open NVS: %s", esp_err_to_name(err));
    }
}

void load_token_from_nvs(void)
{
    nvs_handle_t handle;
    esp_err_t err = nvs_open(NVS_NAMESPACE, NVS_READONLY, &handle);

    if(err == ESP_OK) {
        size_t token_len = sizeof(g_api_token);
        size_t device_id_len = sizeof(g_device_id);

        nvs_get_str(handle, "api_token", g_api_token, &token_len);
        nvs_get_str(handle, "device_id", g_device_id, &device_id_len);

        nvs_close(handle);

        if(strlen(g_api_token) > 0) {
            ESP_LOGI(TAG, "Loaded token from NVS");
        }
    }
}
```

## Testing

### Using cURL (Command Line)

**1. Test Health Check**:
```bash
curl http://attend.test/api/v1/timeclock/health
```

**2. Test Authentication**:
```bash
curl -X POST http://attend.test/api/v1/timeclock/auth \
  -H "Content-Type: application/json" \
  -d '{
    "mac_address": "30:ED:A0:E2:20:73",
    "device_name": "Test-Device",
    "firmware_version": "1.0.0"
  }'
```

**3. Test Punch (replace TOKEN)**:
```bash
curl -X POST http://attend.test/api/v1/timeclock/punch \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "device_id": "ESP32_A1B2C3D4",
    "credential_kind": "nfc",
    "credential_value": "04A1B2C3D4E5F6",
    "event_time": "2026-01-26 08:30:00"
  }'
```

### Using Postman

1. Import collection from `docs/postman_collection.json` (create this)
2. Set environment variable `base_url` = `http://attend.test/api/v1/timeclock`
3. Run authentication request first
4. Copy token to environment
5. Test other endpoints

### ESP32 Serial Monitor

Monitor API calls:
```bash
idf.py monitor
```

Look for log messages:
```
I (12345) API: Authenticating with server...
I (12456) API: Authentication successful, device_id: ESP32_A1B2C3D4
I (15678) API: Sending punch for card: 04A1B2C3D4E5F6
I (15789) API: Punch recorded successfully
```

## Best Practices

1. **Always authenticate first** before making other API calls
2. **Store token securely** in NVS (not in flash)
3. **Handle token expiration** - re-authenticate when you get 401
4. **Implement offline mode** - queue punches when network is down
5. **Use exponential backoff** for retries
6. **Log all API interactions** for debugging
7. **Validate responses** - check `success` field before using data
8. **Show user feedback** - display API messages on screen
9. **Sync time regularly** - call `/time` endpoint every 5-10 minutes
10. **Handle all error codes** - don't assume success

## Troubleshooting

### Common Issues

**Problem**: Authentication fails with "Device not found"
- **Solution**: Register device first using `/register` endpoint

**Problem**: Punch returns 401 Unauthorized
- **Solution**: Token expired, call `/auth` again

**Problem**: "Credential not recognized" on punch
- **Solution**: Card UID not registered in backend - add via admin panel

**Problem**: Network timeout errors
- **Solution**: Check WiFi connection, increase timeout, implement retry

**Problem**: JSON parsing errors
- **Solution**: Verify Content-Type header, check response format

## Next Steps

- Review [FIRMWARE_INTEGRATION.md](FIRMWARE_INTEGRATION.md) for UI integration
- Review [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for building firmware
- See Laravel API controller: `app/Http/Controllers/Api/TimeClockController.php`

## Resources

- Laravel API Routes: `routes/api.php`
- Postman Collection: `docs/postman_collection.json`
- ESP HTTP Client: https://docs.espressif.com/projects/esp-idf/en/latest/esp32/api-reference/protocols/esp_http_client.html
- cJSON Library: https://github.com/DaveGamble/cJSON

---

Last Updated: 2026-01-26
API Version: 1.0
