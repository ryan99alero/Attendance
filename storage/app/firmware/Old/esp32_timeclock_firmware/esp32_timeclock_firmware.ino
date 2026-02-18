/*
 * ESP32 Time Clock Firmware
 *
 * This firmware creates a WiFi access point for initial configuration,
 * serves a web interface for device setup, and communicates with the
 * Laravel attendance system for employee time tracking.
 *
 * Hardware Requirements:
 * - ESP32 DevKit
 * - MFRC522 RFID Module (WORKING CONFIGURATION)
 * - GPIO Wiring: SS=21, RST=22, SCK=18, MISO=19, MOSI=23
 * - Optional: RGB LED for status indication
 * - Optional: Buzzer for audio feedback
 * - Optional: OLED Display
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
#include <esp_sleep.h>

// Pin Definitions - Using WORKING MFRC522 Configuration
// MFRC522 RFID Module Configuration (TESTED AND WORKING)
#define RFID_SS_PIN     21   // SS/SDA pin (tested working)
#define RFID_RST_PIN    22   // RST pin (tested working)

// Status LED pins (avoiding SPI conflicts)
#define LED_RED_PIN     25
#define LED_GREEN_PIN   26
#define LED_BLUE_PIN    27
#define BUZZER_PIN      14

// ESP32 SPI pins (these are fixed for VSPI)
// SCK (Clock) = GPIO 18
// MISO (Master In, Slave Out) = GPIO 19
// MOSI (Master Out, Slave In) = GPIO 23
// SS (Slave Select) = GPIO 21 (WORKING CONFIGURATION)

// Network Configuration
const char* AP_SSID = "ESP32-TimeClock";
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
    int lastConfigVersion;

    // Timezone drift protection
    int lastValidTimezoneOffset;
    unsigned long lastTimezoneUpdate;
    bool timezoneValidated;
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
unsigned long lastConfigPoll = 0;

// NFC/RFID Status Variables
bool nfcInitialized = false;
String lastCardUID = "";
unsigned long cardReadCount = 0;
String nfcStatusMessage = "Not Initialized";

// Function declarations
void setStatus(DeviceStatus status, String error = "");
void setStatusLED(int red, int green, int blue);
void attemptAutoRegistration();

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("ESP32 Time Clock Starting...");

    // ⚡ DISABLE ALL POWER MANAGEMENT - Critical for MFRC522 reliability
    Serial.println("🔋 Configuring power management for MFRC522 compatibility...");

    // Disable CPU frequency scaling (keep at maximum speed)
    setCpuFrequencyMhz(240); // Max speed: 240MHz

    // Disable WiFi power saving (prevents deep sleep)
    WiFi.setSleep(false);

    // Disable automatic light sleep
    esp_sleep_disable_wakeup_source(ESP_SLEEP_WAKEUP_ALL);

    Serial.println("✅ Power management disabled - ESP32 will stay fully awake");
    Serial.printf("   CPU Frequency: %d MHz\n", getCpuFrequencyMhz());
    Serial.println("   WiFi Sleep: DISABLED");
    Serial.println("   Auto Sleep: DISABLED");

    // Initialize hardware
    initializeHardware();

    // Initialize file system with auto-format
    Serial.println("Initializing SPIFFS...");
    if (!SPIFFS.begin()) {
        Serial.println("SPIFFS mount failed, attempting to format...");

        if (SPIFFS.format()) {
            Serial.println("SPIFFS format successful!");

            if (SPIFFS.begin()) {
                Serial.println("SPIFFS mount successful after format!");
            } else {
                Serial.println("SPIFFS mount failed even after format!");
                setStatus(STATUS_ERROR, "SPIFFS initialization failed");
                return;
            }
        } else {
            Serial.println("SPIFFS format failed!");
            setStatus(STATUS_ERROR, "SPIFFS format failed");
            return;
        }
    } else {
        Serial.println("SPIFFS mounted successfully!");
    }

    // Show SPIFFS info
    Serial.printf("SPIFFS - Total: %u bytes, Used: %u bytes, Free: %u bytes\n",
                  SPIFFS.totalBytes(), SPIFFS.usedBytes(),
                  SPIFFS.totalBytes() - SPIFFS.usedBytes());

    // List files in SPIFFS for debugging
    Serial.println("📁 SPIFFS file listing:");
    File root = SPIFFS.open("/");
    File file = root.openNextFile();
    int fileCount = 0;
    while(file){
        Serial.println("   " + String(file.name()) + " (" + String(file.size()) + " bytes)");
        file = root.openNextFile();
        fileCount++;
    }
    if (fileCount == 0) {
        Serial.println("   No files found in SPIFFS");
    }
    Serial.println("📁 End of file listing");

    // Load configuration
    loadConfiguration();

    // Initialize WiFi
    initializeWiFi();

    // Initialize web server
    initializeWebServer();

    // Initialize MFRC522 RFID reader (TESTED WORKING CONFIGURATION)
    initializeMFRC522();

    // Check GPIO pin states before initialization
    Serial.println("📋 Pre-initialization pin status:");
    Serial.printf("   SS (GPIO %d): %s\n", RFID_SS_PIN, digitalRead(RFID_SS_PIN) ? "HIGH" : "LOW");
    Serial.printf("   RST (GPIO %d): %s\n", RFID_RST_PIN, digitalRead(RFID_RST_PIN) ? "HIGH" : "LOW");

    // Initialize SPI with proven working parameters
    Serial.println("🔧 Initializing SPI...");
    SPI.begin();  // SCK=18, MISO=19, MOSI=23
    SPI.setDataMode(SPI_MODE0);
    SPI.setBitOrder(MSBFIRST);
    SPI.setClockDivider(SPI_CLOCK_DIV4); // Conservative clock speed
    delay(100);
    Serial.println("✅ SPI initialized");

    // Initialize MFRC522
    Serial.println("🔧 Initializing MFRC522...");
    rfid.PCD_Init();
    delay(200);
    Serial.println("✅ MFRC522 initialization called");

    // Test communication with multiple attempts
    Serial.println("🔍 Testing MFRC522 communication...");
    byte version = 0xFF;
    bool communicationSuccess = false;

    for (int attempt = 1; attempt <= 5; attempt++) {
        Serial.printf("   Attempt %d/5: ", attempt);
        version = rfid.PCD_ReadRegister(rfid.VersionReg);
        Serial.printf("Version = 0x%02X ", version);

        // Check for valid MFRC522 versions
        if (version == 0x91) {
            Serial.println("✅ SUCCESS! MFRC522 v1.0 detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x92) {
            Serial.println("✅ SUCCESS! MFRC522 v2.0 detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x88) {
            Serial.println("✅ SUCCESS! MFRC522 clone detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x00) {
            Serial.println("❌ No response (0x00)");
        } else if (version == 0xFF) {
            Serial.println("❌ No communication (0xFF)");
        } else {
            Serial.printf("⚠️  Unknown version (0x%02X) - might still work\n", version);
            communicationSuccess = true;
            break;
        }

        delay(500);
    }

    if (communicationSuccess) {
        Serial.println("✅ MFRC522 initialized successfully!");
        Serial.printf("   Chip Version: 0x%02X\n", version);

        // Set status message
        if (version == 0x91) {
            nfcStatusMessage = "MFRC522 v1.0 Ready";
        } else if (version == 0x92) {
            nfcStatusMessage = "MFRC522 v2.0 Ready";
        } else if (version == 0x88) {
            nfcStatusMessage = "MFRC522 Clone Ready";
        } else {
            nfcStatusMessage = "MFRC522 Ready (v" + String(version, HEX) + ")";
        }

        nfcInitialized = true;

        // Perform self-test
        Serial.println("🧪 Running MFRC522 self-test...");
        bool selfTestResult = rfid.PCD_PerformSelfTest();
        Serial.println(selfTestResult ? "   Self-test: PASSED" : "   Self-test: FAILED");

        // Re-initialize after self-test
        rfid.PCD_Init();

        Serial.println("✅ MFRC522 ready for card reading!");

    } else {
        Serial.println("❌ MFRC522 communication failed!");
        Serial.println("📋 This should not happen with tested configuration!");
        Serial.println("   1. Double-check wiring:");
        Serial.printf("      SS/SDA → GPIO %d\n", RFID_SS_PIN);
        Serial.printf("      RST → GPIO %d\n", RFID_RST_PIN);
        Serial.println("      SCK → GPIO 18, MISO → GPIO 19, MOSI → GPIO 23");
        Serial.println("      VCC → 3.3V, GND → GND");
        Serial.println("   2. Power supply issues");
        Serial.println("   3. Hardware failure");

        nfcStatusMessage = "MFRC522 Failed (0x" + String(version, HEX) + ")";
        nfcInitialized = false;
    }

    Serial.println("=== MFRC522 Initialization Complete ===");

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

    // Debug status output - always show every 10 seconds
    static unsigned long lastDebugPrint = 0;
    if (millis() - lastDebugPrint > 10000) { // Every 10 seconds
        Serial.println("🔍 === DEVICE STATUS DEBUG ===");
        Serial.println("   config.isRegistered: " + String(config.isRegistered ? "true" : "false"));
        Serial.println("   config.isConfigured: " + String(config.isConfigured ? "true" : "false"));
        Serial.println("   currentStatus: " + String(currentStatus));
        Serial.println("   STATUS_REGISTERED: " + String(STATUS_REGISTERED));
        Serial.println("   STATUS_APPROVED: " + String(STATUS_APPROVED));
        Serial.println("   WiFi Status: " + String(WiFi.status() == WL_CONNECTED ? "Connected" : "Disconnected"));
        Serial.println("   API Token length: " + String(config.apiToken.length()));
        if (config.apiToken.length() > 0) {
            Serial.println("   API Token: " + config.apiToken.substring(0, min(8, (int)config.apiToken.length())) + "...");
        } else {
            Serial.println("   API Token: Not set");
        }
        if (config.deviceId.length() > 0) {
            Serial.println("   Device ID: " + config.deviceId);
        } else {
            Serial.println("   Device ID: Not set");
        }
        lastDebugPrint = millis();
    }

    // Handle NFC card reading
    if (config.isRegistered && (currentStatus == STATUS_REGISTERED || currentStatus == STATUS_APPROVED)) {
        handleCardReading();
    } else {
        // Additional debug for why card reading is disabled
        static unsigned long lastCardDebugPrint = 0;
        if (millis() - lastCardDebugPrint > 15000) { // Every 15 seconds
            Serial.println("⚠️  Card reading DISABLED:");
            if (!config.isRegistered) {
                Serial.println("   - Device not registered");
            }
            if (currentStatus != STATUS_REGISTERED && currentStatus != STATUS_APPROVED) {
                Serial.println("   - Status not registered/approved (current: " + String(currentStatus) + ")");
            }
            lastCardDebugPrint = millis();
        }
    }

    // Send heartbeat to server
    if (config.isRegistered && millis() - lastHeartbeat > 60000) { // Every minute
        sendHeartbeat();
        lastHeartbeat = millis();
    }

    // Poll for configuration updates
    if (config.isRegistered && millis() - lastConfigPoll > 300000) { // Every 5 minutes (configurable)
        pollConfigurationUpdates();
        lastConfigPoll = millis();
    }

    // Update time from NTP
    if (config.isConfigured && millis() - config.lastSync > 3600000) { // Every hour
        syncTime();
    }

    delay(100);
}

void initializeMFRC522() {
    Serial.println("=== MFRC522 RFID Reader Initialization ===");

    // Check GPIO pin states before initialization
    Serial.println("📋 Pre-initialization pin status:");
    Serial.printf("   SS (GPIO %d): %s\n", RFID_SS_PIN, digitalRead(RFID_SS_PIN) ? "HIGH" : "LOW");
    Serial.printf("   RST (GPIO %d): %s\n", RFID_RST_PIN, digitalRead(RFID_RST_PIN) ? "HIGH" : "LOW");

    // Initialize SPI with proven working parameters
    Serial.println("🔧 Initializing SPI...");
    SPI.begin();  // SCK=18, MISO=19, MOSI=23
    SPI.setDataMode(SPI_MODE0);
    SPI.setBitOrder(MSBFIRST);
    SPI.setClockDivider(SPI_CLOCK_DIV4); // Conservative clock speed
    delay(100);
    Serial.println("✅ SPI initialized");

    // Initialize MFRC522
    Serial.println("🔧 Initializing MFRC522...");
    rfid.PCD_Init();
    delay(200);
    Serial.println("✅ MFRC522 initialization called");

    // Test communication with multiple attempts
    Serial.println("🔍 Testing MFRC522 communication...");
    byte version = 0xFF;
    bool communicationSuccess = false;

    for (int attempt = 1; attempt <= 5; attempt++) {
        Serial.printf("   Attempt %d/5: ", attempt);
        version = rfid.PCD_ReadRegister(rfid.VersionReg);
        Serial.printf("Version = 0x%02X ", version);

        // Check for valid MFRC522 versions
        if (version == 0x91) {
            Serial.println("✅ SUCCESS! MFRC522 v1.0 detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x92) {
            Serial.println("✅ SUCCESS! MFRC522 v2.0 detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x88) {
            Serial.println("✅ SUCCESS! MFRC522 clone detected");
            communicationSuccess = true;
            break;
        } else if (version == 0x00) {
            Serial.println("❌ No response (0x00)");
        } else if (version == 0xFF) {
            Serial.println("❌ No communication (0xFF)");
        } else {
            Serial.printf("⚠️  Unknown version (0x%02X) - might still work\n", version);
            communicationSuccess = true;
            break;
        }

        delay(500);
    }

    if (communicationSuccess) {
        Serial.println("✅ MFRC522 initialized successfully!");
        Serial.printf("   Chip Version: 0x%02X\n", version);

        // Set status message
        if (version == 0x91) {
            nfcStatusMessage = "MFRC522 v1.0 Ready";
        } else if (version == 0x92) {
            nfcStatusMessage = "MFRC522 v2.0 Ready";
        } else if (version == 0x88) {
            nfcStatusMessage = "MFRC522 Clone Ready";
        } else {
            nfcStatusMessage = "MFRC522 Ready (v" + String(version, HEX) + ")";
        }

        nfcInitialized = true;
    } else {
        Serial.println("❌ MFRC522 initialization FAILED!");
        Serial.println("   Check wiring connections:");
        Serial.println("   ESP32 -> MFRC522");
        Serial.println("   3V3  -> 3.3V");
        Serial.println("   GND  -> GND");
        Serial.println("   D18  -> SCK");
        Serial.println("   D19  -> MISO");
        Serial.println("   D23  -> MOSI");
        Serial.println("   D21  -> SDA/SS");
        Serial.println("   D22  -> RST");

        nfcStatusMessage = "MFRC522 Failed - Check Connections";
        nfcInitialized = false;
    }

    Serial.println("=== MFRC522 Initialization Complete ===");
}

void initializeHardware() {
    // Initialize LED pins
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_BLUE_PIN, OUTPUT);

    // Initialize buzzer
    pinMode(BUZZER_PIN, OUTPUT);

    // Test hardware
    setStatusLED(255, 0, 0); // Red
    delay(500);
    setStatusLED(0, 255, 0); // Green
    delay(500);
    setStatusLED(0, 0, 255); // Blue
    delay(500);
    setStatusLED(0, 0, 0);   // Off

    // Test buzzer
    tone(BUZZER_PIN, 1000, 200);
    delay(300);
    tone(BUZZER_PIN, 1500, 200);
    delay(300);
}

void loadConfiguration() {
    Serial.println("🔍 === LOADING CONFIGURATION DEBUG ===");

    File configFile = SPIFFS.open("/config.json", "r");
    if (!configFile) {
        Serial.println("❌ No configuration file found, using defaults");
        config.isConfigured = false;
        config.isRegistered = false;
        config.macAddress = WiFi.macAddress();
        Serial.println("   Setting isConfigured = false");
        Serial.println("   Setting isRegistered = false");
        return;
    }

    Serial.println("✅ Configuration file found, reading...");
    String configData = configFile.readString();
    configFile.close();

    Serial.println("📄 Raw config file contents:");
    Serial.println(configData);
    Serial.println("📄 End of config file");

    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, configData);

    if (error) {
        Serial.println("❌ JSON parsing failed: " + String(error.c_str()));
        config.isConfigured = false;
        config.isRegistered = false;
        config.macAddress = WiFi.macAddress();
        return;
    }

    Serial.println("✅ JSON parsed successfully, extracting fields...");

    config.deviceName = doc["deviceName"].as<String>();
    config.deviceId = doc["deviceId"].as<String>();
    config.wifiSSID = doc["wifiSSID"].as<String>();
    config.wifiPassword = doc["wifiPassword"].as<String>();
    config.serverHost = doc["serverHost"].as<String>();
    config.serverPort = doc["serverPort"];
    config.apiToken = doc["apiToken"].as<String>();
    config.ntpServer = doc["ntpServer"].as<String>();
    config.timezone = doc["timezone"].as<String>();
    config.isConfigured = doc["isConfigured"];
    config.isRegistered = doc["isRegistered"];
    config.macAddress = WiFi.macAddress();

    Serial.println("📋 Loaded configuration values:");
    Serial.println("   deviceName: " + config.deviceName);
    Serial.println("   deviceId: " + (config.deviceId.length() > 0 ? config.deviceId : "EMPTY"));
    Serial.println("   wifiSSID: " + (config.wifiSSID.length() > 0 ? config.wifiSSID : "EMPTY"));
    Serial.println("   serverHost: " + (config.serverHost.length() > 0 ? config.serverHost : "EMPTY"));
    Serial.println("   serverPort: " + String(config.serverPort));
    Serial.println("   apiToken: " + (config.apiToken.length() > 0 ? config.apiToken.substring(0, 8) + "..." : "EMPTY"));
    Serial.println("   ntpServer: " + config.ntpServer);
    Serial.println("   timezone: " + config.timezone);
    Serial.println("   isConfigured: " + String(config.isConfigured ? "true" : "false"));
    Serial.println("   isRegistered: " + String(config.isRegistered ? "true" : "false"));
    Serial.println("   macAddress: " + config.macAddress);

    // CRITICAL FIX: Restore currentStatus based on loaded configuration
    // Check for valid credentials (not just non-empty strings)
    bool hasValidCredentials = config.isRegistered &&
                              config.apiToken.length() > 0 &&
                              config.deviceId.length() > 0 &&
                              config.apiToken != "null" &&
                              config.deviceId != "null";

    if (hasValidCredentials) {
        currentStatus = STATUS_REGISTERED;
        Serial.println("✅ Configuration loaded - device status restored to REGISTERED");
        Serial.println("   Device ID: " + config.deviceId);
        Serial.println("   API Token: " + config.apiToken.substring(0, min(8, (int)config.apiToken.length())) + "...");
    } else if (config.isConfigured) {
        // Check if we have corrupted credentials
        if (config.apiToken == "null" || config.deviceId == "null") {
            Serial.println("⚠️  Configuration loaded - found corrupted credentials (null strings)");
            config.isRegistered = false; // Reset corrupted registration
            currentStatus = STATUS_CONFIGURED;
        } else {
            currentStatus = STATUS_CONFIGURED;
            Serial.println("⚠️  Configuration loaded - device configured but not registered");
        }
    } else {
        currentStatus = STATUS_NOT_CONFIGURED;
        Serial.println("ℹ️  Configuration loaded - device not configured");
    }

    Serial.println("Configuration loaded successfully");
}

void saveConfiguration() {
    Serial.println("🔍 === SAVING CONFIGURATION DEBUG ===");
    Serial.println("📋 Current config values to save:");
    Serial.println("   deviceName: " + config.deviceName);
    Serial.println("   deviceId: " + (config.deviceId.length() > 0 ? config.deviceId : "EMPTY"));
    Serial.println("   wifiSSID: " + (config.wifiSSID.length() > 0 ? config.wifiSSID : "EMPTY"));
    Serial.println("   serverHost: " + (config.serverHost.length() > 0 ? config.serverHost : "EMPTY"));
    Serial.println("   serverPort: " + String(config.serverPort));
    Serial.println("   apiToken: " + (config.apiToken.length() > 0 ? config.apiToken.substring(0, 8) + "..." : "EMPTY"));
    Serial.println("   ntpServer: " + config.ntpServer);
    Serial.println("   timezone: " + config.timezone);
    Serial.println("   isConfigured: " + String(config.isConfigured ? "true" : "false"));
    Serial.println("   isRegistered: " + String(config.isRegistered ? "true" : "false"));

    DynamicJsonDocument doc(1024);

    doc["deviceName"] = config.deviceName;
    doc["deviceId"] = config.deviceId;
    doc["wifiSSID"] = config.wifiSSID;
    doc["wifiPassword"] = config.wifiPassword;
    doc["serverHost"] = config.serverHost;
    doc["serverPort"] = config.serverPort;
    doc["apiToken"] = config.apiToken;
    doc["ntpServer"] = config.ntpServer;
    doc["timezone"] = config.timezone;
    doc["isConfigured"] = config.isConfigured;
    doc["isRegistered"] = config.isRegistered;

    String jsonString;
    serializeJson(doc, jsonString);
    Serial.println("📄 JSON to be saved:");
    Serial.println(jsonString);

    File configFile = SPIFFS.open("/config.json", "w");
    if (configFile) {
        serializeJson(doc, configFile);
        configFile.close();
        Serial.println("✅ Configuration saved successfully");

        // Verify save by reading it back
        File verifyFile = SPIFFS.open("/config.json", "r");
        if (verifyFile) {
            String savedData = verifyFile.readString();
            verifyFile.close();
            Serial.println("📄 Verification - file now contains:");
            Serial.println(savedData);
        }
    } else {
        Serial.println("❌ Failed to save configuration - could not open file");
    }
}

void initializeWiFi() {
    if (config.isConfigured && config.wifiSSID.length() > 0) {
        // Connect to configured WiFi
        Serial.println("Connecting to WiFi: " + config.wifiSSID);
        WiFi.begin(config.wifiSSID.c_str(), config.wifiPassword.c_str());

        int attempts = 0;
        while (WiFi.status() != WL_CONNECTED && attempts < 20) {
            delay(1000);
            Serial.print(".");
            attempts++;
        }

        if (WiFi.status() == WL_CONNECTED) {
            Serial.println("\nWiFi connected!");
            Serial.println("IP address: " + WiFi.localIP().toString());
            setStatusLED(0, 255, 0); // Green - connected

            // Initialize NTP client
            if (config.ntpServer.length() > 0) {
                timeClient.setPoolServerName(config.ntpServer.c_str());
                syncTime();
            }

            // Auto-register if we have stored credentials but aren't registered
            attemptAutoRegistration();
        } else {
            Serial.println("\nFailed to connect to WiFi, starting AP mode");
            startAPMode();
        }
    } else {
        startAPMode();
    }
}

void startAPMode() {
    WiFi.softAP(AP_SSID, AP_PASSWORD);
    Serial.println("AP mode started");
    Serial.println("SSID: " + String(AP_SSID));
    Serial.println("Password: " + String(AP_PASSWORD));
    Serial.println("IP address: " + WiFi.softAPIP().toString());
    setStatusLED(255, 255, 0); // Yellow - AP mode
}

void reconnectWiFi() {
    static unsigned long lastReconnectAttempt = 0;

    if (millis() - lastReconnectAttempt > 30000) { // Try every 30 seconds
        Serial.println("Attempting WiFi reconnection...");
        WiFi.disconnect();
        WiFi.begin(config.wifiSSID.c_str(), config.wifiPassword.c_str());
        lastReconnectAttempt = millis();
    }
}

void attemptAutoRegistration() {
    Serial.println("=== CHECKING AUTO-REGISTRATION ===");

    // Check if we have the required configuration
    if (!config.isConfigured || config.serverHost.length() == 0 || config.deviceName.length() == 0) {
        Serial.println("⚠️  Device not configured for auto-registration");
        return;
    }

    // Check if we already have VALID credentials and are registered
    bool hasValidCredentials = config.isRegistered &&
                              config.apiToken.length() > 0 &&
                              config.deviceId.length() > 0 &&
                              config.apiToken != "null" &&
                              config.deviceId != "null";

    if (hasValidCredentials) {
        Serial.println("✅ Device already has registration credentials");
        Serial.println("   Device ID: " + config.deviceId);
        Serial.println("   API Token: " + config.apiToken.substring(0, min(8, (int)config.apiToken.length())) + "...");
        currentStatus = STATUS_REGISTERED;
        setStatusLED(0, 255, 255); // Cyan - registered
        Serial.println("🎯 Auto-registration successful - device ready for card scanning");
        return;
    }

    // If we get here, credentials are invalid (null strings) - force re-registration
    if (config.apiToken == "null" || config.deviceId == "null") {
        Serial.println("⚠️  Found corrupted credentials (null strings) - forcing re-registration");
        config.isRegistered = false; // Reset registration status
    }

    // If we have partial credentials or lost registration status, attempt re-registration
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("❌ WiFi not connected - cannot auto-register");
        return;
    }

    Serial.println("🚀 Attempting auto-registration with server...");

    // Prepare registration data
    DynamicJsonDocument registrationData(1024);
    registrationData["device_name"] = config.deviceName;
    registrationData["mac_address"] = WiFi.macAddress();
    registrationData["firmware_version"] = "1.0.0";

    // Add device config
    DynamicJsonDocument deviceConfig(512);
    deviceConfig["ntp_server"] = config.ntpServer;
    deviceConfig["timezone"] = config.timezone;
    registrationData["device_config"] = deviceConfig;

    String jsonString;
    serializeJson(registrationData, jsonString);

    Serial.println("   URL: http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/register");
    Serial.println("   Data: " + jsonString);

    // Send registration request
    HTTPClient http;
    String url = "http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/register";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    int httpResponseCode = http.POST(jsonString);
    String serverResponse = http.getString();

    Serial.println("📡 Auto-registration response code: " + String(httpResponseCode));
    Serial.println("📡 Auto-registration response: " + serverResponse);

    if (httpResponseCode == 200 || httpResponseCode == 201) {
        Serial.println("✅ Auto-registration successful!");

        DynamicJsonDocument responseDoc(1024);
        DeserializationError parseError = deserializeJson(responseDoc, serverResponse);

        if (!parseError) {
            // Detailed JSON parsing debugging
            Serial.println("🔍 === JSON RESPONSE PARSING DEBUG ===");
            Serial.println("Raw server response: " + serverResponse);
            Serial.println("Response contains 'data' key: " + String(responseDoc.containsKey("data") ? "YES" : "NO"));

            // Extract API token and device ID
            if (responseDoc.containsKey("data")) {
                JsonObject dataObj = responseDoc["data"];
                Serial.println("Data object found, extracting fields...");
                Serial.println("data.api_token exists: " + String(dataObj.containsKey("api_token") ? "YES" : "NO"));
                Serial.println("data.device_id exists: " + String(dataObj.containsKey("device_id") ? "YES" : "NO"));

                config.apiToken = dataObj["api_token"].as<String>();
                config.deviceId = dataObj["device_id"].as<String>();

                Serial.println("Extracted api_token: " + (config.apiToken.length() > 0 ? config.apiToken.substring(0, 8) + "..." : "EMPTY"));
                Serial.println("Extracted device_id: " + config.deviceId);
            } else {
                // Legacy format
                Serial.println("Using legacy format, extracting from root...");
                Serial.println("root.api_token exists: " + String(responseDoc.containsKey("api_token") ? "YES" : "NO"));
                Serial.println("root.device_id exists: " + String(responseDoc.containsKey("device_id") ? "YES" : "NO"));

                config.apiToken = responseDoc["api_token"].as<String>();
                config.deviceId = responseDoc["device_id"].as<String>();

                Serial.println("Extracted api_token: " + (config.apiToken.length() > 0 ? config.apiToken.substring(0, 8) + "..." : "EMPTY"));
                Serial.println("Extracted device_id: " + config.deviceId);
            }

            config.isRegistered = true;
            saveConfiguration();
            currentStatus = STATUS_REGISTERED;
            setStatusLED(0, 255, 255); // Cyan - registered

            Serial.println("💾 Auto-saved API token: " + (config.apiToken.length() > 0 ? config.apiToken.substring(0, 8) + "..." : "NULL/EMPTY"));
            Serial.println("💾 Auto-saved device ID: " + config.deviceId);
            Serial.println("🎯 Auto-registration complete - device ready for card scanning");
        } else {
            Serial.println("❌ Failed to parse auto-registration response");
            currentStatus = STATUS_ERROR;
            lastError = "Auto-registration parse error";
        }
    } else {
        Serial.println("❌ Auto-registration failed with code: " + String(httpResponseCode));
        Serial.println("   Response: " + serverResponse);
        currentStatus = STATUS_ERROR;
        lastError = "Auto-registration failed: " + String(httpResponseCode);
    }

    http.end();
}

void initializeWebServer() {
    // Serve the main configuration page - embedded HTML
    server.on("/", HTTP_GET, []() {
        Serial.println("=== ROOT PAGE REQUESTED ===");
        Serial.println("🚀 Serving embedded HTML interface...");
        server.send(200, "text/html", getConfigurationHTML());
    });

    // Test endpoint with simple content
    server.on("/test", HTTP_GET, []() {
        Serial.println("=== TEST PAGE REQUESTED ===");
        String testHTML = "<!DOCTYPE html><html><head><title>Test Page</title></head>";
        testHTML += "<body><h1>ESP32 Test Page Working!</h1>";
        testHTML += "<p>If you see this, the web server is working fine.</p>";
        testHTML += "<p>Problem is with SPIFFS file upload.</p>";
        testHTML += "<a href='/'>Back to Main</a> | <a href='/debug/files'>Debug Files</a>";
        testHTML += "</body></html>";

        server.send(200, "text/html", testHTML);
        Serial.println("✅ Test page served successfully");
    });

    // Debug endpoint to list SPIFFS files with detailed info
    server.on("/debug/files", HTTP_GET, []() {
        Serial.println("=== DEBUG FILES REQUESTED ===");
        String output = "=== SPIFFS DEBUG INFO ===\n\n";
        output += "Total SPIFFS: " + String(SPIFFS.totalBytes()) + " bytes\n";
        output += "Used SPIFFS: " + String(SPIFFS.usedBytes()) + " bytes\n";
        output += "Free SPIFFS: " + String(SPIFFS.totalBytes() - SPIFFS.usedBytes()) + " bytes\n\n";
        output += "FILES:\n";

        File root = SPIFFS.open("/");
        if (!root) {
            output += "ERROR: Could not open root directory\n";
        } else {
            File file = root.openNextFile();
            int fileCount = 0;
            while(file) {
                fileCount++;
                output += "File #" + String(fileCount) + ": " + String(file.name()) + " (" + String(file.size()) + " bytes)\n";

                // Show first 50 chars of small files
                if (file.size() > 0 && file.size() < 100) {
                    String content = file.readString();
                    output += "  Content preview: " + content.substring(0, 50) + "...\n";
                }

                file = root.openNextFile();
            }

            if (fileCount == 0) {
                output += "NO FILES FOUND - SPIFFS is empty!\n";
                output += "You need to upload files using 'ESP32 Sketch Data Upload'\n";
            }
        }

        server.send(200, "text/plain", output);
        Serial.println("✅ Debug info served");
    });

    // Debug endpoint to show current sketch path
    server.on("/debug/info", HTTP_GET, []() {
        String output = "=== ESP32 DEBUG INFO ===\n\n";
        output += "Firmware: ESP32 Time Clock v1.0\n";
        output += "Free Heap: " + String(ESP.getFreeHeap()) + " bytes\n";
        output += "Chip ID: " + String(ESP.getEfuseMac()) + "\n";
        output += "Flash Size: " + String(ESP.getFlashChipSize()) + " bytes\n";
        output += "SPIFFS Initialized: " + String(SPIFFS.totalBytes() > 0 ? "YES" : "NO") + "\n";
        output += "\nTo upload HTML file:\n";
        output += "1. Make sure 'data' folder exists in sketch directory\n";
        output += "2. Put index.html in the 'data' folder\n";
        output += "3. Use Tools -> ESP32 Sketch Data Upload\n";

        server.send(200, "text/plain", output);
    });

    // NFC test endpoint
    server.on("/api/nfc/test", HTTP_POST, []() {
        Serial.println("=== NFC TEST REQUESTED ===");

        DynamicJsonDocument response(512);

        if (!nfcInitialized) {
            response["success"] = false;
            response["message"] = "NFC reader not initialized";
            response["status"] = nfcStatusMessage;
        } else {
            // Test communication
            byte version = rfid.PCD_ReadRegister(rfid.VersionReg);
            Serial.println("🔍 NFC Reader Test - Version: 0x" + String(version, HEX));

            response["success"] = true;
            response["message"] = "NFC reader working - try presenting a card";
            response["version"] = "0x" + String(version, HEX);
            response["status"] = nfcStatusMessage;
            response["card_count"] = cardReadCount;
            response["last_card"] = lastCardUID.length() > 0 ? lastCardUID : "None";

            // Check for card right now
            if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
                String cardUID = "";
                for (byte i = 0; i < rfid.uid.size; i++) {
                    cardUID += String(rfid.uid.uidByte[i] < 0x10 ? "0" : "");
                    cardUID += String(rfid.uid.uidByte[i], HEX);
                }
                cardUID.toUpperCase();

                Serial.println("🎫 Card detected during test: " + cardUID);
                response["card_detected"] = true;
                response["card_id"] = cardUID;

                // Update tracking
                lastCardUID = cardUID;
                cardReadCount++;

                // Halt PICC
                rfid.PICC_HaltA();
                rfid.PCD_StopCrypto1();

                // Flash LED
                setStatusLED(0, 255, 0); // Green
                delay(200);
                setStatusLED(0, 0, 255); // Blue
            } else {
                response["card_detected"] = false;
                response["message"] = "NFC reader working - try presenting a card";
            }
        }

        String jsonResponse;
        serializeJson(response, jsonResponse);
        server.send(200, "application/json", jsonResponse);
    });

    // API endpoints
    server.on("/api/device/info", HTTP_GET, handleDeviceInfo);
    server.on("/api/config", HTTP_GET, handleGetConfig);
    server.on("/api/config", HTTP_POST, handleSaveConfig);
    server.on("/api/status", HTTP_GET, handleGetStatus);
    server.on("/api/register", HTTP_POST, handleRegister);
    server.on("/api/token", HTTP_POST, handleSaveToken);
    server.on("/api/test-connection", HTTP_POST, handleTestConnection);
    server.on("/api/restart", HTTP_POST, handleRestart);

    // NFC Recovery endpoint - force MFRC522 reconnection
    server.on("/api/nfc/recover", HTTP_POST, []() {
        Serial.println("=== NFC RECOVERY REQUESTED ===");

        // Force reinitialize MFRC522
        nfcInitialized = false;
        nfcStatusMessage = "Manual Recovery Initiated...";

        initializeMFRC522();

        String response = "{";
        response += "\"success\": " + String(nfcInitialized ? "true" : "false") + ",";
        response += "\"message\": \"" + nfcStatusMessage + "\",";
        response += "\"timestamp\": \"" + String(millis()) + "\"";
        response += "}";

        server.send(200, "application/json", response);

        Serial.println("🔧 NFC Recovery Result: " + String(nfcInitialized ? "SUCCESS" : "FAILED"));
    });

    server.begin();
    Serial.println("Web server started");
}

void handleDeviceInfo() {
    DynamicJsonDocument doc(256);
    doc["ip"] = WiFi.localIP().toString();
    doc["mac"] = WiFi.macAddress();
    doc["firmware"] = "1.0.0";
    doc["uptime"] = millis();

    String response;
    serializeJson(doc, response);
    server.send(200, "application/json", response);
}

void handleGetConfig() {
    DynamicJsonDocument doc(512);
    doc["deviceName"] = config.deviceName;
    doc["serverHost"] = config.serverHost;
    doc["serverPort"] = config.serverPort;
    doc["wifiSSID"] = config.wifiSSID;
    doc["ntpServer"] = config.ntpServer;
    doc["timezone"] = config.timezone;
    doc["macAddress"] = config.macAddress;

    String response;
    serializeJson(doc, response);
    server.send(200, "application/json", response);
}

void handleSaveConfig() {
    Serial.println("=== SAVE CONFIG REQUESTED ===");

    if (server.hasArg("plain")) {
        String payload = server.arg("plain");
        Serial.println("📄 Received payload: " + payload);

        DynamicJsonDocument doc(1024);
        DeserializationError error = deserializeJson(doc, payload);

        if (error) {
            Serial.println("❌ JSON parse error: " + String(error.c_str()));
            server.send(400, "application/json", "{\"error\":\"Invalid JSON\"}");
            return;
        }

        // Extract and log configuration
        config.deviceName = doc["deviceName"].as<String>();
        config.serverHost = doc["serverHost"].as<String>();
        config.serverPort = doc["serverPort"];
        config.wifiSSID = doc["wifiSSID"].as<String>();
        config.wifiPassword = doc["wifiPassword"].as<String>();
        config.ntpServer = doc["ntpServer"].as<String>();
        config.timezone = doc["timezone"].as<String>();
        config.isConfigured = true;

        Serial.println("💾 Saving config:");
        Serial.println("   Device Name: " + config.deviceName);
        Serial.println("   Server Host: " + config.serverHost);
        Serial.println("   Server Port: " + String(config.serverPort));
        Serial.println("   WiFi SSID: " + config.wifiSSID);
        Serial.println("   WiFi Password: [" + String(config.wifiPassword.length()) + " chars]");
        Serial.println("   NTP Server: " + config.ntpServer);
        Serial.println("   Timezone: " + config.timezone);

        saveConfiguration();
        Serial.println("✅ Configuration saved successfully!");

        server.send(200, "application/json", "{\"success\":true,\"message\":\"Configuration saved\"}");
    } else {
        Serial.println("❌ No data received in request");
        server.send(400, "application/json", "{\"error\":\"No data received\"}");
    }
}

void handleGetStatus() {
    Serial.println("=== STATUS REQUEST ===");

    DynamicJsonDocument doc(512);

    String statusText;
    switch (currentStatus) {
        case STATUS_NOT_CONFIGURED: statusText = "Not Configured"; break;
        case STATUS_CONFIGURED: statusText = "Configured"; break;
        case STATUS_REGISTERED: statusText = "Registered"; break;
        case STATUS_APPROVED: statusText = "Approved"; break;
        case STATUS_ERROR: statusText = "Error: " + lastError; break;
    }

    // Check WiFi status more thoroughly
    String wifiStatusText;
    if (WiFi.status() == WL_CONNECTED) {
        wifiStatusText = "Connected (" + WiFi.localIP().toString() + ")";
        Serial.println("📶 WiFi: Connected to " + WiFi.SSID() + " with IP " + WiFi.localIP().toString());
    } else {
        // Check if we're in AP mode
        if (WiFi.softAPgetStationNum() >= 0) {
            wifiStatusText = "AP Mode";
            Serial.println("📶 WiFi: AP Mode - " + WiFi.softAPIP().toString());
        } else {
            wifiStatusText = "Disconnected";
            Serial.println("📶 WiFi: Disconnected");
        }
    }

    doc["registration"] = statusText;
    doc["wifi"] = wifiStatusText;
    doc["server"] = config.isRegistered ? "Connected" : "Not Connected";
    doc["time"] = timeClient.isTimeSet() ? "Synced" : "Not Synced";
    doc["nfc_status"] = nfcStatusMessage;
    doc["last_card"] = lastCardUID.length() > 0 ? lastCardUID : "None";
    doc["card_count"] = cardReadCount;

    Serial.println("📊 Status Response: " + String(wifiStatusText));

    String response;
    serializeJson(doc, response);
    server.send(200, "application/json", response);
}

void handleRegister() {
    Serial.println("=== DEVICE REGISTRATION REQUEST ===");

    if (!server.hasArg("plain")) {
        Serial.println("❌ No data received in registration request");
        server.send(400, "application/json", "{\"error\":\"No data received\"}");
        return;
    }

    String payload = server.arg("plain");
    Serial.println("📄 Registration payload: " + payload);

    DynamicJsonDocument requestDoc(1024);
    DeserializationError error = deserializeJson(requestDoc, payload);

    if (error) {
        Serial.println("❌ JSON parse error: " + String(error.c_str()));
        server.send(400, "application/json", "{\"error\":\"Invalid JSON\"}");
        return;
    }

    // Prepare registration data
    DynamicJsonDocument registrationData(1024);
    registrationData["device_name"] = requestDoc["device_name"];
    registrationData["mac_address"] = WiFi.macAddress();
    registrationData["firmware_version"] = "1.0.0";
    registrationData["device_config"] = requestDoc["device_config"];

    String jsonString;
    serializeJson(registrationData, jsonString);

    Serial.println("🚀 Sending registration to server...");
    Serial.println("   URL: http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/register");
    Serial.println("   Data: " + jsonString);

    // Send registration request to Laravel server
    HTTPClient http;
    String url = "http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/register";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    int httpResponseCode = http.POST(jsonString);
    String serverResponse = http.getString();

    Serial.println("📡 Server response code: " + String(httpResponseCode));
    Serial.println("📡 Server response body: " + serverResponse);

    if (httpResponseCode == 200 || httpResponseCode == 201) {
        Serial.println("✅ Registration successful!");

        DynamicJsonDocument responseDoc(1024);
        DeserializationError parseError = deserializeJson(responseDoc, serverResponse);

        if (!parseError) {
            // Save the API token and device ID
            // Check if response has nested data structure
            if (responseDoc.containsKey("data")) {
                JsonObject dataObj = responseDoc["data"];
                config.apiToken = dataObj["api_token"].as<String>();
                config.deviceId = dataObj["device_id"].as<String>();
            } else {
                // Legacy format
                config.apiToken = responseDoc["api_token"].as<String>();
                config.deviceId = responseDoc["device_id"].as<String>();
            }

            config.isRegistered = true;
            saveConfiguration();

            currentStatus = STATUS_REGISTERED;
            setStatusLED(0, 255, 255); // Cyan - registered

            Serial.println("💾 Saved API token: " + config.apiToken.substring(0, 8) + "...");
            Serial.println("💾 Saved device ID: " + config.deviceId);

            server.send(200, "application/json", serverResponse);
        } else {
            Serial.println("❌ Failed to parse server response JSON");
            server.send(200, "application/json", "{\"success\":true,\"message\":\"Registered but failed to parse response\"}");
        }
    } else {
        Serial.println("❌ Registration failed with code: " + String(httpResponseCode));
        Serial.println("❌ Server error response: " + serverResponse);

        // Try to parse error message from server response
        DynamicJsonDocument errorDoc(512);
        String errorMessage = "Registration failed";

        if (!deserializeJson(errorDoc, serverResponse)) {
            if (errorDoc.containsKey("message")) {
                errorMessage = errorDoc["message"].as<String>();
            } else if (errorDoc.containsKey("error")) {
                errorMessage = errorDoc["error"].as<String>();
            }
        }

        // Create proper JSON error response without nested JSON strings
        DynamicJsonDocument responseDoc(1024);
        responseDoc["error"] = errorMessage;
        responseDoc["code"] = httpResponseCode;
        responseDoc["raw_response"] = serverResponse.substring(0, 200); // Truncate for safety

        String errorResponse;
        serializeJson(responseDoc, errorResponse);
        server.send(httpResponseCode >= 400 && httpResponseCode < 500 ? httpResponseCode : 500, "application/json", errorResponse);
    }

    http.end();
}

void handleSaveToken() {
    if (server.hasArg("plain")) {
        DynamicJsonDocument doc(256);
        deserializeJson(doc, server.arg("plain"));

        config.apiToken = doc["api_token"].as<String>();
        config.deviceId = doc["device_id"].as<String>();
        config.isRegistered = true;
        saveConfiguration();

        server.send(200, "application/json", "{\"success\":true}");
    } else {
        server.send(400, "application/json", "{\"error\":\"No data received\"}");
    }
}

void handleTestConnection() {
    if (!server.hasArg("plain")) {
        server.send(400, "application/json", "{\"error\":\"No data received\"}");
        return;
    }

    DynamicJsonDocument doc(256);
    deserializeJson(doc, server.arg("plain"));

    String host = doc["host"];
    int port = doc["port"];

    HTTPClient http;
    String url = "http://" + host + ":" + String(port) + "/api/v1/timeclock/health";
    http.begin(url);
    http.setTimeout(5000);

    int httpResponseCode = http.GET();

    if (httpResponseCode == 200) {
        server.send(200, "application/json", "{\"success\":true}");
    } else {
        String error = "{\"success\":false,\"message\":\"Connection failed\",\"code\":" + String(httpResponseCode) + "}";
        server.send(200, "application/json", error);
    }

    http.end();
}

void handleRestart() {
    server.send(200, "application/json", "{\"success\":true}");
    delay(1000);
    ESP.restart();
}

void handleCardReading() {
    // Auto-recovery: Check MFRC522 connection every 60 seconds and reinitialize if needed
    static unsigned long lastConnectionCheck = 0;
    if (millis() - lastConnectionCheck > 60000) { // Every 60 seconds
        Serial.println("🔧 === MFRC522 CONNECTION CHECK ===");
        byte version = rfid.PCD_ReadRegister(rfid.VersionReg);

        if (version == 0x00 || version == 0xFF) {
            Serial.println("⚠️  MFRC522 connection lost! Attempting to recover...");
            Serial.printf("   Read version: 0x%02X (should be 0x91, 0x92, or 0x88)\n", version);

            // Mark as uninitialized and attempt reinit
            nfcInitialized = false;
            nfcStatusMessage = "Connection Lost - Recovering...";

            // Attempt to reinitialize
            delay(500); // Give it a moment
            initializeMFRC522();

            if (nfcInitialized) {
                Serial.println("✅ MFRC522 connection recovered successfully!");
            } else {
                Serial.println("❌ MFRC522 recovery failed - check physical connections");
            }
        } else {
            Serial.printf("✅ MFRC522 connection healthy (version: 0x%02X)\n", version);
        }
        lastConnectionCheck = millis();
    }

    // Debug output every 20 seconds when actively scanning for cards
    static unsigned long lastCardScanDebug = 0;
    if (millis() - lastCardScanDebug > 20000) { // Every 20 seconds
        Serial.println("🎫 === CARD READING STATUS ===");
        Serial.println("   nfcInitialized: " + String(nfcInitialized ? "true" : "false"));
        Serial.println("   NFC Status: " + nfcStatusMessage);
        Serial.println("   Card read count: " + String(cardReadCount));
        Serial.println("   Last card UID: " + lastCardUID);
        Serial.println("   Actively scanning for cards...");
        lastCardScanDebug = millis();
    }

    if (!nfcInitialized) {
        static unsigned long lastNfcWarning = 0;
        if (millis() - lastNfcWarning > 30000) { // Every 30 seconds
            Serial.println("❌ Card reading blocked - NFC not initialized!");
            Serial.println("   nfcStatusMessage: " + nfcStatusMessage);
            Serial.println("   💡 TIP: Check physical wire connections to MFRC522");
            lastNfcWarning = millis();
        }
        return; // Don't try to read if NFC isn't working
    }

    // Debug the card detection process with connection validation
    static unsigned long lastScanDebug = 0;
    if (millis() - lastScanDebug > 5000) { // Every 5 seconds
        Serial.println("🔍 Scanning for cards...");

        // Test communication before checking for cards
        byte testVersion = rfid.PCD_ReadRegister(rfid.VersionReg);
        if (testVersion == 0x00 || testVersion == 0xFF) {
            Serial.printf("⚠️  Communication issue detected during scan (version: 0x%02X)\n", testVersion);
            Serial.println("   Will attempt recovery on next cycle...");
        } else {
            Serial.println("   PICC_IsNewCardPresent(): " + String(rfid.PICC_IsNewCardPresent() ? "true" : "false"));
        }
        lastScanDebug = millis();
    }

    if (!rfid.PICC_IsNewCardPresent()) {
        return;
    }

    Serial.println("📱 New card detected! Attempting to read...");
    if (!rfid.PICC_ReadCardSerial()) {
        Serial.println("❌ Failed to read card serial");
        return;
    }

    Serial.println("✅ Card serial read successfully!");

    // Prevent duplicate reads (reduced from 2000ms to 500ms for better throughput)
    if (millis() - lastCardRead < 500) {
        return;
    }
    lastCardRead = millis();

    // Read card UID
    String cardUID = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
        cardUID += String(rfid.uid.uidByte[i] < 0x10 ? "0" : "");
        cardUID += String(rfid.uid.uidByte[i], HEX);
    }
    cardUID.toUpperCase();

    // Identify card type using MFRC522 library
    MFRC522::PICC_Type piccType = rfid.PICC_GetType(rfid.uid.sak);
    String cardTypeName = rfid.PICC_GetTypeName(piccType);
    String credentialKind = getCredentialKind(piccType);

    // Update tracking variables
    lastCardUID = cardUID;
    cardReadCount++;

    Serial.println("🎫 Card #" + String(cardReadCount) + " detected:");
    Serial.println("   UID: " + cardUID);
    Serial.println("   Type: " + cardTypeName);
    Serial.println("   Protocol: " + credentialKind);

    // Send punch data to server if registered
    if (config.isRegistered && (currentStatus == STATUS_REGISTERED || currentStatus == STATUS_APPROVED)) {
        sendPunchData(cardUID, credentialKind);
    } else {
        Serial.println("📝 Device not registered or not approved - card logged only");
        Serial.println("📋 Current status: " + String(currentStatus) + ", Registered: " + String(config.isRegistered));
        // Flash LED to show card was read
        setStatusLED(0, 255, 0); // Green
        delay(500);
        setStatusLED(0, 0, 255); // Back to blue
    }

    // Halt PICC
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
}

bool validateTimezoneChange(int newOffset) {
    // First time setup - accept any reasonable timezone
    if (!config.timezoneValidated) {
        if (newOffset >= -12 && newOffset <= 12) {
            config.lastValidTimezoneOffset = newOffset;
            config.lastTimezoneUpdate = millis();
            config.timezoneValidated = true;
            Serial.println("✅ Initial timezone set: " + String(newOffset));
            return true;
        }
        Serial.println("❌ Invalid initial timezone: " + String(newOffset));
        return false;
    }

    // Calculate change from last valid offset
    int offsetChange = abs(newOffset - config.lastValidTimezoneOffset);
    unsigned long timeSinceLastUpdate = millis() - config.lastTimezoneUpdate;

    // Allow changes <= 1 hour (normal DST change)
    if (offsetChange <= 1) {
        config.lastValidTimezoneOffset = newOffset;
        config.lastTimezoneUpdate = millis();
        if (offsetChange == 1) {
            Serial.println("🔄 DST timezone change detected: " + String(config.lastValidTimezoneOffset) + " -> " + String(newOffset));
        }
        return true;
    }

    // Allow changes > 1 hour only if enough time has passed (manual reconfiguration)
    if (timeSinceLastUpdate > 3600000) { // 1 hour in milliseconds
        Serial.println("⚠️ Major timezone change after 1+ hour: " + String(config.lastValidTimezoneOffset) + " -> " + String(newOffset));
        config.lastValidTimezoneOffset = newOffset;
        config.lastTimezoneUpdate = millis();
        return true;
    }

    // Reject suspicious rapid changes > 1 hour
    Serial.println("🚫 Suspicious timezone change rejected: " + String(config.lastValidTimezoneOffset) + " -> " + String(newOffset));
    Serial.println("   Change: " + String(offsetChange) + " hours, Time since last: " + String(timeSinceLastUpdate / 1000) + "s");
    return false;
}

int getTimezoneOffset(String timezone) {
    // Convert timezone names to numeric offsets (hours)
    // Using daylight saving time offsets (current for September)
    if (timezone == "America/Chicago" || timezone == "US/Central") return -5; // CDT (Central Daylight Time)
    if (timezone == "America/New_York" || timezone == "US/Eastern") return -4; // EDT (Eastern Daylight Time)
    if (timezone == "America/Denver" || timezone == "US/Mountain") return -6; // MDT (Mountain Daylight Time)
    if (timezone == "America/Los_Angeles" || timezone == "US/Pacific") return -7; // PDT (Pacific Daylight Time)
    if (timezone == "UTC" || timezone == "GMT") return 0;

    // If it's already numeric (e.g., "-5", "+7"), parse it
    if (timezone.startsWith("+") || timezone.startsWith("-") ||
        (timezone.length() > 0 && (timezone.charAt(0) >= '0' && timezone.charAt(0) <= '9'))) {
        return timezone.toInt();
    }

    // Default to Central Daylight Time if unknown
    return -5;
}

String getISODateTime() {
    // Get current Unix timestamp from NTP client
    unsigned long epochTime = timeClient.getEpochTime();

    // Get timezone offset (handle both names and numeric formats)
    int requestedTimezoneOffset = 0;
    if (config.timezone.length() > 0) {
        requestedTimezoneOffset = getTimezoneOffset(config.timezone);
    } else {
        requestedTimezoneOffset = -5; // Default to CDT
    }

    // Validate timezone change before applying
    int actualTimezoneOffset;
    if (validateTimezoneChange(requestedTimezoneOffset)) {
        actualTimezoneOffset = requestedTimezoneOffset;
    } else {
        // Use last known good timezone on validation failure
        actualTimezoneOffset = config.lastValidTimezoneOffset;
        Serial.println("⚠️ Using last valid timezone: " + String(actualTimezoneOffset));
    }

    int timezoneOffsetSeconds = actualTimezoneOffset * 3600;
    epochTime += timezoneOffsetSeconds;

    // Convert to broken-down time structure
    time_t rawTime = (time_t)epochTime;
    struct tm *timeInfo = gmtime(&rawTime);

    // Format as ISO 8601 datetime string (YYYY-MM-DD HH:MM:SS)
    char isoBuffer[25];
    sprintf(isoBuffer, "%04d-%02d-%02d %02d:%02d:%02d",
            timeInfo->tm_year + 1900,
            timeInfo->tm_mon + 1,
            timeInfo->tm_mday,
            timeInfo->tm_hour,
            timeInfo->tm_min,
            timeInfo->tm_sec);

    return String(isoBuffer);
}

String getCredentialKind(MFRC522::PICC_Type piccType) {
    // Map MFRC522 card types to credential kinds used by Laravel API
    switch (piccType) {
        case MFRC522::PICC_TYPE_MIFARE_MINI:
        case MFRC522::PICC_TYPE_MIFARE_1K:
        case MFRC522::PICC_TYPE_MIFARE_4K:
            return "rfid";  // Classic RFID/Mifare cards

        case MFRC522::PICC_TYPE_MIFARE_UL:
            return "nfc";   // NFC cards (Ultralight)

        case MFRC522::PICC_TYPE_MIFARE_PLUS:
        case MFRC522::PICC_TYPE_MIFARE_DESFIRE:
            return "nfc";   // Advanced NFC cards

        case MFRC522::PICC_TYPE_TNP3XXX:
            return "rfid";  // Topaz cards (treated as RFID)

        case MFRC522::PICC_TYPE_ISO_14443_4:
            return "nfc";   // ISO14443-4 compliant (typically NFC)

        case MFRC522::PICC_TYPE_ISO_18092:
            return "nfc";   // ISO18092 (NFC)

        case MFRC522::PICC_TYPE_NOT_COMPLETE:
        case MFRC522::PICC_TYPE_UNKNOWN:
        default:
            return "rfid";  // Default to RFID for unknown types
    }
}

void sendPunchData(String cardUID, String credentialKind) {
    Serial.println("🚀 === SENDING PUNCH DATA ===");

    if (!config.isRegistered || config.apiToken.length() == 0) {
        Serial.println("❌ Cannot send punch - device not registered or no API token");
        setStatusLED(255, 0, 0); // Red - error
        tone(BUZZER_PIN, 800, 1000); // Error beep
        return;
    }

    // Prepare punch data with correct field names for Laravel API
    DynamicJsonDocument punchData(512);
    punchData["device_id"] = config.deviceId;
    punchData["credential_kind"] = credentialKind;  // Detected card type
    punchData["credential_value"] = cardUID;  // Correct field name
    punchData["event_time"] = getISODateTime(); // Local datetime
    punchData["event_type"] = "unknown";  // Let server determine
    punchData["confidence"] = 100;  // High confidence for RFID

    // Send device timezone as numeric offset (not timezone name)
    int timezoneOffset = config.timezone.length() > 0 ? getTimezoneOffset(config.timezone) : -5;
    punchData["device_timezone"] = String(timezoneOffset);  // e.g., "-6" for CST

    String jsonString;
    serializeJson(punchData, jsonString);

    Serial.println("📤 Punch API Request:");
    Serial.println("   URL: http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/punch");
    Serial.println("   JSON: " + jsonString);
    Serial.println("   Auth: Bearer " + config.apiToken.substring(0, 8) + "...");

    HTTPClient http;
    String url = "http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/punch";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    int httpResponseCode = http.POST(jsonString);
    String serverResponse = http.getString();

    Serial.println("📥 Punch API Response:");
    Serial.println("   Status Code: " + String(httpResponseCode));
    Serial.println("   Response: " + serverResponse);

    if (httpResponseCode == 200) {
        // Success
        setStatusLED(0, 255, 0); // Green
        tone(BUZZER_PIN, 1500, 200);
        delay(100);
        tone(BUZZER_PIN, 1500, 200);
        Serial.println("✅ Punch recorded successfully!");
    } else {
        // Error
        setStatusLED(255, 0, 0); // Red
        tone(BUZZER_PIN, 800, 1000);
        Serial.println("❌ Failed to record punch - Status: " + String(httpResponseCode));
        Serial.println("   Server said: " + serverResponse);
    }

    http.end();

    // Reset LED after 2 seconds
    delay(2000);
    setStatusLED(0, 0, 255); // Back to blue (ready)
}

void sendHeartbeat() {
    if (!config.isRegistered || config.apiToken.length() == 0) {
        return;
    }

    HTTPClient http;
    String url = "http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/status";
    http.begin(url);
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    int httpResponseCode = http.GET();

    if (httpResponseCode == 200) {
        String response = http.getString();
        DynamicJsonDocument doc(512);
        deserializeJson(doc, response);

        String status = doc["status"];
        if (status == "approved") {
            currentStatus = STATUS_APPROVED;
        } else {
            currentStatus = STATUS_REGISTERED;
        }
    }

    http.end();
}

void pollConfigurationUpdates() {
    if (!config.isRegistered || config.apiToken.length() == 0 || WiFi.status() != WL_CONNECTED) {
        return;
    }

    Serial.println("🔄 Polling for configuration updates...");

    HTTPClient http;
    String url = "http://" + config.serverHost + ":" + String(config.serverPort) + "/api/v1/timeclock/config";
    url += "?mac_address=" + WiFi.macAddress();
    url += "&current_config_version=" + String(config.lastConfigVersion);

    http.begin(url);
    http.addHeader("Authorization", "Bearer " + config.apiToken);
    http.addHeader("X-Device-MAC", WiFi.macAddress());

    int httpResponseCode = http.GET();
    String response = http.getString();

    if (httpResponseCode == 200) {
        Serial.println("📡 Configuration response: " + response);

        DynamicJsonDocument doc(1024);
        DeserializationError error = deserializeJson(doc, response);

        if (!error) {
            // Check if this is a "no updates" response
            if (doc.containsKey("has_updates") && !doc["has_updates"].as<bool>()) {
                Serial.println("✅ Configuration is up to date");
                return;
            }

            // Parse configuration updates
            if (doc.containsKey("config")) {
                JsonObject configObj = doc["config"];
                bool configChanged = false;

                // Check for device name updates
                if (configObj.containsKey("device_name") && configObj["device_name"].as<String>() != config.deviceName) {
                    String newDeviceName = configObj["device_name"].as<String>();
                    Serial.println("🔄 Device name update: " + config.deviceName + " -> " + newDeviceName);
                    config.deviceName = newDeviceName;
                    configChanged = true;
                }

                // Check for timezone updates
                if (configObj.containsKey("timezone") && configObj["timezone"].as<String>() != config.timezone) {
                    String newTimezone = configObj["timezone"].as<String>();
                    Serial.println("🔄 Timezone update: " + config.timezone + " -> " + newTimezone);
                    config.timezone = newTimezone;
                    configChanged = true;
                }

                // Check for NTP server updates
                if (configObj.containsKey("ntp_server") && configObj["ntp_server"].as<String>() != config.ntpServer) {
                    String newNtpServer = configObj["ntp_server"].as<String>();
                    Serial.println("🔄 NTP server update: " + config.ntpServer + " -> " + newNtpServer);
                    config.ntpServer = newNtpServer;
                    timeClient.setPoolServerName(config.ntpServer.c_str());
                    configChanged = true;
                }

                // Update config version
                if (doc.containsKey("config_version")) {
                    config.lastConfigVersion = doc["config_version"].as<int>();
                    Serial.println("🔄 Config version updated to: " + String(config.lastConfigVersion));
                }

                if (configChanged) {
                    Serial.println("💾 Saving updated configuration...");
                    saveConfiguration();
                    Serial.println("✅ Configuration updated successfully");
                } else {
                    Serial.println("✅ Configuration received but no changes detected");
                }
            } else {
                Serial.println("⚠️ Configuration response missing 'config' object");
            }
        } else {
            Serial.println("❌ Failed to parse configuration response: " + String(error.c_str()));
        }
    } else if (httpResponseCode == 304) {
        Serial.println("✅ Configuration unchanged (304)");
    } else {
        Serial.println("❌ Configuration poll failed: " + String(httpResponseCode) + " - " + response);
    }

    http.end();
}

void syncTime() {
    if (config.ntpServer.length() > 0 && WiFi.status() == WL_CONNECTED) {
        timeClient.begin();
        timeClient.update();
        config.lastSync = millis();
        Serial.println("Time synchronized: " + timeClient.getFormattedTime());
    }
}

void setStatusLED(int red, int green, int blue) {
    analogWrite(LED_RED_PIN, red);
    analogWrite(LED_GREEN_PIN, green);
    analogWrite(LED_BLUE_PIN, blue);
}

void setStatus(DeviceStatus status, String error) {
    currentStatus = status;
    lastError = error;

    switch (status) {
        case STATUS_NOT_CONFIGURED:
            setStatusLED(255, 255, 0); // Yellow
            break;
        case STATUS_CONFIGURED:
            setStatusLED(0, 255, 255); // Cyan
            break;
        case STATUS_REGISTERED:
            setStatusLED(255, 0, 255); // Magenta
            break;
        case STATUS_APPROVED:
            setStatusLED(0, 0, 255); // Blue
            break;
        case STATUS_ERROR:
            setStatusLED(255, 0, 0); // Red
            break;
    }
}

String getConfigurationHTML() {
    return R"rawliteral(
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 Time Clock Configuration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .form-container {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }

        .status-display {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .status-item:last-child {
            margin-bottom: 0;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .hidden {
            display: none;
        }

        .loading {
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ip-display {
            background: #2c3e50;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
            font-family: 'Courier New', monospace;
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ESP32 Time Clock</h1>
            <p>Device Configuration & Registration</p>
        </div>

        <div class="form-container">
            <!-- Current IP Display -->
            <div class="ip-display">
                Device IP: <span id="deviceIP">)rawliteral" + WiFi.localIP().toString() + R"rawliteral(</span>
            </div>

            <!-- Status Display -->
            <div class="status-display">
                <div class="status-item">
                    <span>Status:</span>
                    <span id="deviceStatus">Not Configured</span>
                </div>
                <div class="status-item">
                    <span>WiFi:</span>
                    <span id="wifiStatus">AP Mode</span>
                </div>
                <div class="status-item">
                    <span>MAC Address:</span>
                    <span id="macAddress">)rawliteral" + WiFi.macAddress() + R"rawliteral(</span>
                </div>
                <div class="status-item">
                    <span>Server:</span>
                    <span id="serverStatus">Not Connected</span>
                </div>
                <div class="status-item">
                    <span>NFC Reader:</span>
                    <span id="nfcStatus">Unknown</span>
                </div>
                <div class="status-item">
                    <span>Last Card:</span>
                    <span id="lastCard">None</span>
                </div>
                <div class="status-item">
                    <span>Card Count:</span>
                    <span id="cardCount">0</span>
                </div>
                <div class="status-item">
                    <span>Last Update:</span>
                    <span id="lastUpdate">Just Now</span>
                </div>
            </div>

            <!-- Setup Instructions -->
            <div class="alert alert-info">
                <strong>Setup Steps:</strong><br>
                1. Fill in configuration below<br>
                2. Click "Save Configuration"<br>
                3. Click "Restart & Connect to WiFi"<br>
                4. Reconnect to device (find new IP)<br>
                5. Test connection and register device
            </div>

            <!-- Alert Area -->
            <div id="alertArea"></div>

            <!-- Configuration Form -->
            <form id="configForm">
                <div class="form-group">
                    <label for="deviceName">Device Name</label>
                    <input type="text" id="deviceName" name="deviceName" placeholder="Enter device name (e.g., Front Door Clock)" required>
                </div>

                <div class="form-group">
                    <label for="serverHost">Attendance System IP/FQDN</label>
                    <input type="text" id="serverHost" name="serverHost" placeholder="192.168.1.100 or attend.company.com" required>
                </div>

                <div class="form-group">
                    <label for="serverPort">Server Port</label>
                    <input type="number" id="serverPort" name="serverPort" value="80" min="1" max="65535" required>
                </div>

                <div class="form-group">
                    <label for="wifiSSID">WiFi Network (SSID)</label>
                    <input type="text" id="wifiSSID" name="wifiSSID" placeholder="Company-WiFi" required>
                </div>

                <div class="form-group">
                    <label for="wifiPassword">WiFi Password</label>
                    <input type="password" id="wifiPassword" name="wifiPassword" placeholder="Enter WiFi password" required>
                </div>

                <div class="form-group">
                    <label for="ntpServer">NTP Server (Optional)</label>
                    <input type="text" id="ntpServer" name="ntpServer" placeholder="pool.ntp.org" value="pool.ntp.org">
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" required>
                        <option value="">Select Timezone</option>
                        <option value="America/New_York">Eastern Time (EST/EDT)</option>
                        <option value="America/Chicago">Central Time (CST/CDT)</option>
                        <option value="America/Denver">Mountain Time (MST/MDT)</option>
                        <option value="America/Los_Angeles">Pacific Time (PST/PDT)</option>
                        <option value="America/Phoenix">Arizona Time (MST)</option>
                        <option value="America/Anchorage">Alaska Time (AKST/AKDT)</option>
                        <option value="Pacific/Honolulu">Hawaii Time (HST)</option>
                    </select>
                </div>

                <button type="button" class="btn btn-primary" id="saveConfigBtn">
                    Save Configuration
                </button>

                <button type="button" class="btn btn-secondary" id="testConnectionBtn">
                    Test Connection
                </button>

                <button type="button" class="btn btn-secondary" id="registerBtn">
                    Register Device
                </button>

                <button type="button" class="btn btn-secondary" id="debugRegisterBtn">
                    Debug Register (Skip WiFi Check)
                </button>

                <button type="button" class="btn btn-secondary" id="testNfcBtn">
                    Test NFC Reader
                </button>

                <button type="button" class="btn btn-warning" id="recoverNfcBtn">
                    🔧 Recover NFC Connection
                </button>

                <button type="button" class="btn btn-secondary" id="restartBtn">
                    Restart & Connect to WiFi
                </button>
            </form>

            <!-- Loading Area -->
            <div id="loadingArea" class="loading hidden">
                <div class="spinner"></div>
                <p id="loadingText">Processing...</p>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let deviceConfig = {
            macAddress: ')rawliteral" + WiFi.macAddress() + R"rawliteral('
        };
        let registrationStatus = 'not_configured';

        // Initialize the interface
        document.addEventListener('DOMContentLoaded', function() {
            loadSavedConfig();
            updateStatus();

            // Set up event listeners
            document.getElementById('saveConfigBtn').addEventListener('click', saveConfiguration);
            document.getElementById('testConnectionBtn').addEventListener('click', testConnection);
            document.getElementById('registerBtn').addEventListener('click', registerDevice);
            document.getElementById('debugRegisterBtn').addEventListener('click', debugRegisterDevice);
            document.getElementById('testNfcBtn').addEventListener('click', testNfcReader);
            document.getElementById('recoverNfcBtn').addEventListener('click', recoverNfcConnection);
            document.getElementById('restartBtn').addEventListener('click', restartDevice);

            // Auto-refresh status every 30 seconds
            setInterval(updateStatus, 30000);
        });

        // Load saved configuration
        async function loadSavedConfig() {
            try {
                const response = await fetch('/api/config');
                const config = await response.json();

                if (config) {
                    document.getElementById('deviceName').value = config.deviceName || '';
                    document.getElementById('serverHost').value = config.serverHost || '';
                    document.getElementById('serverPort').value = config.serverPort || '80';
                    document.getElementById('wifiSSID').value = config.wifiSSID || '';
                    document.getElementById('ntpServer').value = config.ntpServer || 'pool.ntp.org';
                    document.getElementById('timezone').value = config.timezone || '';

                    deviceConfig = {...deviceConfig, ...config};
                }
            } catch (error) {
                console.error('Failed to load saved config:', error);
            }
        }

        // Update status display
        async function updateStatus() {
            try {
                const response = await fetch('/api/status');
                const status = await response.json();

                console.log('Status Response:', status);

                document.getElementById('deviceStatus').textContent = status.registration || 'Not Configured';
                document.getElementById('wifiStatus').textContent = status.wifi || 'Unknown';
                document.getElementById('serverStatus').textContent = status.server || 'Not Connected';
                document.getElementById('nfcStatus').textContent = status.nfc_status || 'Unknown';
                document.getElementById('lastCard').textContent = status.last_card || 'None';
                document.getElementById('cardCount').textContent = status.card_count || '0';
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();

                registrationStatus = status.registration || 'not_configured';
                updateButtonStates();

                console.log('WiFi Status Updated To:', status.wifi);

            } catch (error) {
                console.error('Failed to update status:', error);
                showAlert('Failed to update device status', 'error');
            }
        }

        // Update button states
        function updateButtonStates() {
            const registerBtn = document.getElementById('registerBtn');

            if (registrationStatus === 'registered') {
                registerBtn.textContent = 'Re-register Device';
                registerBtn.className = 'btn btn-secondary';
            } else {
                registerBtn.textContent = 'Register Device';
                registerBtn.className = 'btn btn-primary';
            }
        }

        // Save configuration locally
        async function saveConfiguration() {
            const formData = new FormData(document.getElementById('configForm'));
            const config = Object.fromEntries(formData.entries());

            // Validate required fields
            if (!config.deviceName || !config.wifiSSID || !config.wifiPassword) {
                showAlert('Please fill in at least Device Name, WiFi SSID, and WiFi Password', 'error');
                return;
            }

            showLoading('Saving configuration...');

            try {
                const response = await fetch('/api/config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(config)
                });

                if (response.ok) {
                    showAlert('Configuration saved successfully! Click "Restart & Connect to WiFi" to apply changes.', 'success');
                    deviceConfig = {...deviceConfig, ...config};
                } else {
                    showAlert('Failed to save configuration', 'error');
                }

            } catch (error) {
                console.error('Save configuration error:', error);
                showAlert('Failed to save configuration', 'error');
            } finally {
                hideLoading();
            }
        }

        // Register device with attendance system
        async function registerDevice() {
            // Check if device is connected to WiFi first
            const wifiStatus = document.getElementById('wifiStatus').textContent;
            if (wifiStatus.includes('AP Mode') || wifiStatus === 'Disconnected') {
                showAlert('Please save configuration and restart to connect to WiFi first', 'error');
                return;
            }

            console.log('WiFi Status Check Passed:', wifiStatus);

            const formData = new FormData(document.getElementById('configForm'));
            const config = Object.fromEntries(formData.entries());

            // Validate required fields
            if (!config.deviceName || !config.serverHost) {
                showAlert('Please fill in Device Name and Server Host', 'error');
                return;
            }

            showLoading('Registering device...');

            try {
                // Attempt registration with the attendance system
                const registrationData = {
                    device_name: config.deviceName,
                    mac_address: deviceConfig.macAddress,
                    firmware_version: '1.0.0',
                    device_config: {
                        wifi_ssid: config.wifiSSID,
                        ntp_server: config.ntpServer,
                        timezone: config.timezone,
                        server_host: config.serverHost,
                        server_port: config.serverPort
                    }
                };

                const registerResponse = await fetch('/api/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(registrationData)
                });

                const result = await registerResponse.json();

                if (registerResponse.ok) {
                    // Show the appropriate message from the server
                    const message = result.message || 'Device registered successfully!';
                    showAlert(message, 'success');
                    deviceConfig.apiToken = result.api_token;
                    deviceConfig.deviceId = result.device_id;

                    // Save the API token locally
                    await fetch('/api/token', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            api_token: result.api_token,
                            device_id: result.device_id
                        })
                    });

                    updateStatus();
                } else {
                    showAlert('Registration failed: ' + (result.message || 'Unknown error'), 'error');
                }

            } catch (error) {
                console.error('Registration error:', error);
                showAlert('Failed to register device. Please check your settings and try again.', 'error');
            } finally {
                hideLoading();
            }
        }

        // Debug register device (skip WiFi check)
        async function debugRegisterDevice() {
            console.log('DEBUG: Skipping WiFi check for registration');

            const formData = new FormData(document.getElementById('configForm'));
            const config = Object.fromEntries(formData.entries());

            // Validate required fields
            if (!config.deviceName || !config.serverHost) {
                showAlert('Please fill in Device Name and Server Host', 'error');
                return;
            }

            showLoading('Debug Registering device...');

            try {
                // Attempt registration with the attendance system
                const registrationData = {
                    device_name: config.deviceName,
                    mac_address: deviceConfig.macAddress,
                    firmware_version: '1.0.0',
                    device_config: {
                        wifi_ssid: config.wifiSSID,
                        ntp_server: config.ntpServer,
                        timezone: config.timezone,
                        server_host: config.serverHost,
                        server_port: config.serverPort
                    }
                };

                console.log('Debug Registration Data:', registrationData);

                const registerResponse = await fetch('/api/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(registrationData)
                });

                let result;
                const responseText = await registerResponse.text();
                console.log('Raw Server Response:', responseText);

                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.log('Response was not valid JSON:', responseText.substring(0, 200));
                    showAlert('Server returned invalid response. Check Serial Monitor for details.', 'error');
                    return;
                }

                console.log('Parsed Registration Response:', result);

                if (registerResponse.ok) {
                    // Show the appropriate message from the server
                    const message = result.message || 'Device registered successfully!';
                    showAlert(message, 'success');

                    if (result.data && result.data.api_token) {
                        deviceConfig.apiToken = result.data.api_token;
                        deviceConfig.deviceId = result.data.device_id;

                        // Save the API token locally
                        await fetch('/api/token', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                api_token: result.data.api_token,
                                device_id: result.data.device_id
                            })
                        });
                    }

                    updateStatus();
                } else {
                    console.error('Registration failed with status:', registerResponse.status);
                    const errorMsg = result.message || result.error || 'Unknown error';
                    showAlert('Registration failed: ' + errorMsg, 'error');

                    if (result.errors) {
                        console.log('Validation errors:', result.errors);
                    }
                }

            } catch (error) {
                console.error('Debug Registration error:', error);
                showAlert('Failed to register device: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }

        // Test NFC Reader
        async function testNfcReader() {
            showLoading('Testing NFC Reader...');

            try {
                const response = await fetch('/api/nfc/test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                const result = await response.json();

                if (response.ok) {
                    if (result.success) {
                        showAlert(`NFC Test: ${result.message}`, 'success');
                        if (result.card_detected) {
                            showAlert(`Card Detected: ${result.card_id}`, 'info');
                        }
                    } else {
                        showAlert(`NFC Test Failed: ${result.message}`, 'error');
                    }
                } else {
                    showAlert('NFC test request failed', 'error');
                }

                // Update status after test
                updateStatus();

            } catch (error) {
                console.error('NFC test error:', error);
                showAlert('NFC test failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }

        // Test connection to attendance system
        async function testConnection() {
            const serverHost = document.getElementById('serverHost').value;
            const serverPort = document.getElementById('serverPort').value;

            if (!serverHost) {
                showAlert('Please enter server host/IP address', 'error');
                return;
            }

            showLoading('Testing connection...');

            try {
                const response = await fetch('/api/test-connection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        host: serverHost,
                        port: serverPort
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showAlert('Connection successful!', 'success');
                } else {
                    showAlert('Connection failed: ' + (result.message || 'Unable to reach server'), 'error');
                }

            } catch (error) {
                console.error('Connection test error:', error);
                showAlert('Connection test failed', 'error');
            } finally {
                hideLoading();
            }
        }

        // Restart device
        async function restartDevice() {
            if (confirm('Are you sure you want to restart the device?')) {
                showLoading('Restarting device...');

                try {
                    await fetch('/api/restart', { method: 'POST' });
                    showAlert('Device is restarting...', 'info');
                } catch (error) {
                    console.error('Restart error:', error);
                    showAlert('Failed to restart device', 'error');
                } finally {
                    hideLoading();
                }
            }
        }

        // Recover NFC Connection
        async function recoverNfcConnection() {
            if (confirm('This will attempt to reinitialize the MFRC522 card reader. Continue?')) {
                showLoading('Recovering NFC connection...');

                try {
                    const response = await fetch('/api/nfc/recover', { method: 'POST' });
                    const result = await response.json();

                    if (result.success) {
                        showAlert('✅ NFC connection recovered successfully!', 'success');
                        // Refresh status to show updated NFC status
                        setTimeout(updateStatus, 1000);
                    } else {
                        showAlert('❌ NFC recovery failed: ' + result.message, 'error');
                    }
                } catch (error) {
                    console.error('NFC recovery error:', error);
                    showAlert('Failed to recover NFC connection', 'error');
                } finally {
                    hideLoading();
                }
            }
        }

        // Show alert message
        function showAlert(message, type = 'info') {
            const alertArea = document.getElementById('alertArea');
            alertArea.innerHTML = `<div class="alert alert-${type}">${message}</div>`;

            // Auto-hide after 5 seconds for success/info messages
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    alertArea.innerHTML = '';
                }, 5000);
            }
        }

        // Show loading indicator
        function showLoading(text = 'Processing...') {
            document.getElementById('loadingText').textContent = text;
            document.getElementById('loadingArea').classList.remove('hidden');
            document.getElementById('configForm').style.opacity = '0.5';

            // Disable all buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.disabled = true);
        }

        // Hide loading indicator
        function hideLoading() {
            document.getElementById('loadingArea').classList.add('hidden');
            document.getElementById('configForm').style.opacity = '1';

            // Re-enable all buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.disabled = false);
        }
    </script>
</body>
</html>
)rawliteral";
}