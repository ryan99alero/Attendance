# ESP32 Time Clock API Documentation

## Overview
RESTful API for ESP32 time clock devices to handle employee punch recording and information retrieval via RFID/magnetic cards.

## Base URL
```
https://your-domain.com/api/v1/timeclock
```

## API Endpoints

### 1. Health Check
**GET** `/health`

Check if the API is operational.

**Response:**
```json
{
    "success": true,
    "message": "Time Clock API is healthy",
    "server_time": "2025-09-14T15:30:00.000000Z",
    "api_version": "1.0"
}
```

---

### 2. Device Authentication
**POST** `/auth`

Authenticate and register ESP32 time clock device.

**Request Body:**
```json
{
    "device_id": "ESP32-001",
    "device_name": "Front Entrance Clock",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "ip_address": "192.168.1.100",
    "firmware_version": "1.2.3"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Device authenticated successfully",
    "data": {
        "device_id": "ESP32-001",
        "device_name": "Front Entrance Clock",
        "server_time": "2025-09-14T15:30:00.000000Z",
        "timezone": "America/New_York",
        "api_version": "1.0"
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "message": "Invalid device data",
    "errors": {
        "device_id": ["The device id field is required."]
    }
}
```

---

### 3. Record Punch
**POST** `/punch`

Record employee time punch from credential presentation.

**Request Body:**
```json
{
    "device_id": "ESP32-001",
    "credential_kind": "rfid",
    "credential_value": "12345678",
    "event_time": "2025-09-14T15:30:00Z",
    "event_type": "unknown",
    "location": "Front Entrance",
    "confidence": 95,
    "meta": {
        "rssi": -62,
        "firmware": "1.2.3",
        "battery": 85
    }
}
```

**Field Descriptions:**
- `credential_kind`: Type of credential (rfid, nfc, magstripe, qrcode, barcode, ble, biometric, pin, mobile)
- `credential_value`: The raw credential identifier (card number, QR code, etc.)
- `event_type`: Optional event classification (in, out, break_in, break_out, unknown)
- `confidence`: Optional quality score 0-100 for credential read quality
- `meta`: Optional device metadata (RSSI, battery level, firmware version, etc.)

**Response (Success):**
```json
{
    "success": true,
    "message": "Event recorded successfully",
    "data": {
        "clock_event_id": 12345,
        "employee_id": 123,
        "employee_name": "John Smith",
        "event_time": "2025-09-14 15:30:00",
        "status": "recorded"
    },
    "display_message": "Hello John Smith! Time recorded at 3:30 PM"
}
```

**Response (Credential Not Found):**
```json
{
    "success": false,
    "message": "Credential not recognized",
    "display_message": "Card not recognized. Please contact HR."
}
```

**Response (Employee Inactive):**
```json
{
    "success": false,
    "message": "Employee is inactive",
    "display_message": "Access denied. Please contact HR."
}
```

---

### 4. Get Employee Information
**GET** `/employee/{credential_value}?kind={credential_kind}`

Retrieve employee information and hours summary.

**Parameters:**
- `credential_value`: The credential identifier (card number, etc.)
- `kind`: Optional credential type (defaults to "rfid" for backward compatibility)

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "employee": {
            "id": 123,
            "name": "John Smith",
            "external_id": "EMP001",
            "department": "Engineering",
            "is_active": true
        },
        "hours": {
            "today": {
                "regular": 8.0,
                "overtime": 0.0,
                "total": 8.0
            },
            "week": {
                "regular": 40.0,
                "overtime": 2.5,
                "total": 42.5
            },
            "month": {
                "regular": 160.0,
                "overtime": 8.0,
                "total": 168.0
            },
            "pay_period": {
                "regular": 80.0,
                "overtime": 4.0,
                "total": 84.0
            }
        },
        "current_pay_period": {
            "id": 42,
            "start_date": "2025-09-01",
            "end_date": "2025-09-15"
        },
        "server_time": "2025-09-14T15:30:00.000000Z"
    }
}
```

## ESP32 Implementation Example

### Basic Setup
```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

const char* API_BASE = "https://your-domain.com/api/v1/timeclock";
String deviceId = "ESP32-001";

// Device Authentication
bool authenticateDevice() {
    HTTPClient http;
    http.begin(String(API_BASE) + "/auth");
    http.addHeader("Content-Type", "application/json");

    DynamicJsonDocument doc(1024);
    doc["device_id"] = deviceId;
    doc["device_name"] = "ESP32 Time Clock";
    doc["mac_address"] = WiFi.macAddress();
    doc["ip_address"] = WiFi.localIP().toString();

    String payload;
    serializeJson(doc, payload);

    int httpCode = http.POST(payload);

    if (httpCode == 200) {
        String response = http.getString();
        DynamicJsonDocument responseDoc(1024);
        deserializeJson(responseDoc, response);

        bool success = responseDoc["success"];
        if (success) {
            Serial.println("Device authenticated successfully");
            return true;
        }
    }

    Serial.println("Authentication failed");
    return false;
}

// Record Punch
bool recordPunch(String credentialValue, String credentialKind = "rfid") {
    HTTPClient http;
    http.begin(String(API_BASE) + "/punch");
    http.addHeader("Content-Type", "application/json");

    DynamicJsonDocument doc(1024);
    doc["device_id"] = deviceId;
    doc["credential_kind"] = credentialKind;
    doc["credential_value"] = credentialValue;
    doc["event_type"] = "unknown";
    // event_time will default to server time if not provided

    // Optional: Add device metadata
    DynamicJsonDocument meta(256);
    meta["rssi"] = WiFi.RSSI();
    meta["firmware"] = "1.2.3";
    doc["meta"] = meta;

    String payload;
    serializeJson(doc, payload);

    int httpCode = http.POST(payload);
    String response = http.getString();

    DynamicJsonDocument responseDoc(1024);
    deserializeJson(responseDoc, response);

    bool success = responseDoc["success"];

    if (success) {
        String displayMessage = responseDoc["display_message"];
        // Show success message on LCD/display
        Serial.println("SUCCESS: " + displayMessage);
        return true;
    } else {
        String displayMessage = responseDoc["display_message"];
        // Show error message on LCD/display
        Serial.println("ERROR: " + displayMessage);
        return false;
    }
}

// Get Employee Info
void getEmployeeInfo(String credentialValue, String credentialKind = "rfid") {
    HTTPClient http;
    http.begin(String(API_BASE) + "/employee/" + credentialValue + "?kind=" + credentialKind);

    int httpCode = http.GET();

    if (httpCode == 200) {
        String response = http.getString();
        DynamicJsonDocument responseDoc(2048);
        deserializeJson(responseDoc, response);

        if (responseDoc["success"]) {
            String name = responseDoc["data"]["employee"]["name"];
            float todayHours = responseDoc["data"]["hours"]["today"]["total"];
            float weekHours = responseDoc["data"]["hours"]["week"]["total"];

            // Display employee info
            Serial.println("Employee: " + name);
            Serial.println("Today: " + String(todayHours) + " hours");
            Serial.println("Week: " + String(weekHours) + " hours");
        }
    }
}
```

## Error Codes

| HTTP Code | Description |
|-----------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid data |
| 403 | Forbidden - Employee inactive |
| 404 | Not Found - Device/Card/Employee not found |
| 500 | Internal Server Error |

## Security Notes

- All API calls are logged with device and employee information
- Inactive employees are automatically rejected
- Device authentication tracks IP addresses and MAC addresses
- All timestamps are in UTC format

## Expandable Features

The API is designed to be easily expandable for:
- Additional card types (NFC, QR codes, biometric)
- Custom overtime rules per employee
- Shift scheduling integration
- Real-time notifications
- Break time tracking
- Location-based punching
- Photo capture on punch
- Multi-tenant support