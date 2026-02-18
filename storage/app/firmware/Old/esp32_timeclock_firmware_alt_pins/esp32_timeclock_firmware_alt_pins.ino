/*
 * ESP32 Time Clock Firmware - ALTERNATIVE PIN CONFIGURATION
 *
 * This version uses different GPIO pins to avoid conflicts
 * Use this if the default pin configuration fails
 *
 * ALTERNATIVE PIN MAPPING:
 * - SS (Slave Select): GPIO 15 (instead of GPIO 5)
 * - RST (Reset): GPIO 4 (instead of GPIO 2)
 * - SCK: GPIO 18 (same - hardware fixed)
 * - MISO: GPIO 19 (same - hardware fixed)
 * - MOSI: GPIO 23 (same - hardware fixed)
 *
 * Hardware Requirements:
 * - ESP32 DevKit
 * - MFRC522 NFC/RFID Module
 * - Optional: RGB LED for status indication
 * - Optional: Buzzer for audio feedback
 *
 * Libraries Required:
 * - WiFi
 * - WebServer
 * - ArduinoJson
 * - MFRC522
 * - NTPClient
 * - SPIFFS or LittleFS
 */

#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <SPIFFS.h>
#include <NTPClient.h>
#include <WiFiUdp.h>
#include <SPI.h>
#include <MFRC522.h>

// ALTERNATIVE Pin Definitions - Use if default pins fail
#define RFID_SS_PIN     15   // Alternative SS (was 5)
#define RFID_RST_PIN    4    // Alternative RST (was 2)

// Status LED pins (avoiding conflicts)
#define LED_RED_PIN     25
#define LED_GREEN_PIN   26
#define LED_BLUE_PIN    27
#define BUZZER_PIN      14

// ESP32 SPI pins (these are hardware fixed for VSPI)
// SCK (Clock) = GPIO 18
// MISO (Master In, Slave Out) = GPIO 19
// MOSI (Master Out, Slave In) = GPIO 23
// SS (Slave Select) = GPIO 15 (configurable)

// Network Configuration
const char* AP_SSID = "ESP32-TimeClock-Alt";
const char* AP_PASSWORD = "Configure123";

// Global Objects
WebServer server(80);
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
WiFiUDP ntpUDP;
NTPClient timeClient(ntpUDP);

// Configuration Structure
struct DeviceConfig {
    String deviceName;
    String deviceId;
    String macAddress;
    String wifiSSID;
    String wifiPassword;
    String serverHost;
    int serverPort;
    String apiToken;
    String ntpServer;
    String timezone;
    bool isConfigured;
    bool isRegistered;
    unsigned long lastSync;
};

DeviceConfig config;

// Status Variables
enum DeviceStatus {
    STATUS_NOT_CONFIGURED,
    STATUS_CONFIGURED,
    STATUS_REGISTERED,
    STATUS_APPROVED,
    STATUS_ERROR
};

DeviceStatus currentStatus = STATUS_NOT_CONFIGURED;
String lastError = "";
unsigned long lastHeartbeat = 0;
unsigned long lastCardRead = 0;

// NFC/RFID Status Variables
bool nfcInitialized = false;
String lastCardUID = "";
unsigned long cardReadCount = 0;
String nfcStatusMessage = "Not Initialized";

// Function declarations
void setStatus(DeviceStatus status, String error = "");
void setStatusLED(int red, int green, int blue);

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("ESP32 Time Clock Starting (ALTERNATIVE PIN VERSION)...");
    Serial.println("Using ALTERNATIVE pin configuration:");
    Serial.printf("  SS (Slave Select): GPIO %d\n", RFID_SS_PIN);
    Serial.printf("  RST (Reset): GPIO %d\n", RFID_RST_PIN);
    Serial.println("  SCK: GPIO 18, MISO: GPIO 19, MOSI: GPIO 23");

    // Initialize hardware
    initializeHardware();

    // Initialize file system with auto-format
    Serial.println("Initializing SPIFFS...");
    if (!SPIFFS.begin(true)) {
        Serial.println("Failed to mount SPIFFS, formatting...");
        if (SPIFFS.format()) {
            Serial.println("SPIFFS formatted successfully");
            if (SPIFFS.begin()) {
                Serial.println("SPIFFS mounted after format");
            } else {
                Serial.println("Failed to mount SPIFFS after format");
            }
        } else {
            Serial.println("SPIFFS format failed");
        }
    } else {
        Serial.println("SPIFFS mounted successfully");
    }

    // Load configuration
    loadConfiguration();

    // Initialize WiFi (AP mode initially)
    initializeWiFi();

    // Initialize web server
    initializeWebServer();

    // Initialize NFC reader with ALTERNATIVE pins
    Serial.println("=== NFC Reader Initialization (ALTERNATIVE PINS) ===");

    // Check GPIO pin states before initialization
    Serial.println("📋 Pre-initialization pin status:");
    Serial.printf("   SS (GPIO %d): %s\n", RFID_SS_PIN, digitalRead(RFID_SS_PIN) ? "HIGH" : "LOW");
    Serial.printf("   RST (GPIO %d): %s\n", RFID_RST_PIN, digitalRead(RFID_RST_PIN) ? "HIGH" : "LOW");

    // Initialize SPI with explicit parameters
    Serial.println("🔧 Initializing SPI...");
    SPI.begin();  // Default: SCK=18, MISO=19, MOSI=23
    SPI.setDataMode(SPI_MODE0);
    SPI.setBitOrder(MSBFIRST);
    SPI.setClockDivider(SPI_CLOCK_DIV4); // Slower clock for debugging

    delay(100); // Allow SPI to stabilize

    // Initialize MFRC522
    Serial.println("🔧 Initializing MFRC522...");
    rfid.PCD_Init();
    delay(100);

    // Test communication with multiple attempts
    Serial.println("🔍 Testing NFC Reader communication...");
    byte version = 0xFF;
    bool communicationSuccess = false;

    for (int attempt = 1; attempt <= 3; attempt++) {
        Serial.printf("   Attempt %d/3: ", attempt);
        version = rfid.PCD_ReadRegister(rfid.VersionReg);
        Serial.printf("Version = 0x%02X", version);

        if (version != 0x00 && version != 0xFF) {
            Serial.println(" ✅ SUCCESS!");
            communicationSuccess = true;
            break;
        } else {
            Serial.println(" ❌ Failed");
            delay(200);
        }
    }

    if (communicationSuccess) {
        Serial.println("✅ NFC Reader initialized successfully with ALTERNATIVE pins!");
        Serial.printf("   Chip Version: 0x%02X\n", version);

        // Additional chip verification
        if (version == 0x91 || version == 0x92) {
            Serial.println("   Detected: MFRC522 v1.0/v2.0");
        } else {
            Serial.printf("   Detected: Unknown MFRC522 variant (0x%02X)\n", version);
        }

        nfcStatusMessage = "Ready (v" + String(version, HEX) + ")";
        nfcInitialized = true;

        // Perform self-test
        Serial.println("🧪 Running self-test...");
        bool selfTestResult = rfid.PCD_PerformSelfTest();
        Serial.println(selfTestResult ? "   Self-test: PASSED" : "   Self-test: FAILED");

        // Re-initialize after self-test
        rfid.PCD_Init();

    } else {
        Serial.println("❌ NFC Reader communication STILL failed with alternative pins!");
        Serial.println("📋 This may indicate:");
        Serial.println("   1. Hardware issue with the PN532 module");
        Serial.println("   2. Power supply problems (check 3.3V)");
        Serial.println("   3. Faulty connections or wiring");
        Serial.println("   4. Incompatible/damaged module");

        nfcStatusMessage = "Failed Alt Pins (0x" + String(version, HEX) + ")";
        nfcInitialized = false;
    }

    Serial.println("=== NFC Initialization Complete ===");

    Serial.println("ESP32 Time Clock initialized successfully");
    setStatusLED(0, 0, 255); // Blue - ready
}

void loop() {
    // Handle web server requests
    server.handleClient();

    // Check WiFi connection
    if (WiFi.status() != WL_CONNECTED && config.isConfigured) {
        reconnectWiFi();
    }

    // Handle NFC card reading
    if (config.isRegistered && currentStatus == STATUS_APPROVED) {
        handleCardReading();
    }

    // Send heartbeat to server
    if (config.isRegistered && millis() - lastHeartbeat > 60000) { // Every minute
        sendHeartbeat();
        lastHeartbeat = millis();
    }

    delay(100);
}

void initializeHardware() {
    // Initialize LED pins
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_BLUE_PIN, OUTPUT);
    pinMode(BUZZER_PIN, OUTPUT);

    // Initialize NFC pins
    pinMode(RFID_SS_PIN, OUTPUT);
    pinMode(RFID_RST_PIN, OUTPUT);

    // Set initial LED state
    setStatusLED(255, 0, 0); // Red - initializing

    Serial.println("Hardware initialized with ALTERNATIVE pin configuration");
}

// Add all the remaining functions from the original firmware here...
// (This is a template showing the key changes for alternative pins)

void setStatus(DeviceStatus status, String error) {
    currentStatus = status;
    lastError = error;

    switch (status) {
        case STATUS_NOT_CONFIGURED:
            setStatusLED(255, 165, 0); // Orange
            break;
        case STATUS_CONFIGURED:
            setStatusLED(0, 0, 255); // Blue
            break;
        case STATUS_REGISTERED:
            setStatusLED(255, 255, 0); // Yellow
            break;
        case STATUS_APPROVED:
            setStatusLED(0, 255, 0); // Green
            break;
        case STATUS_ERROR:
            setStatusLED(255, 0, 0); // Red
            break;
    }
}

void setStatusLED(int red, int green, int blue) {
    analogWrite(LED_RED_PIN, red);
    analogWrite(LED_GREEN_PIN, green);
    analogWrite(LED_BLUE_PIN, blue);
}

// NOTE: This is a partial template. You would need to copy all the remaining
// functions from the original firmware file to make this complete.
// The key difference is the pin definitions at the top.