/*
 * ESP32-P4 Time Clock Firmware with 7" Display and NFC
 *
 * Supports: PN532 and PN5180 NFC modules (switchable via #define)
 * Hardware: ESP32-P4-Function-EV-Board v1.5.2 + 7" Display
 *
 * SWITCHING NFC MODULES:
 * 1. Edit line 34-35 below to select module
 * 2. Install library (see NFC_MODULE_SETUP.md)
 * 3. Compile and upload (wiring stays the same!)
 */

// TEMPORARILY DISABLED DISPLAY TO DEBUG I2C CONFLICT
// #include <esp_display_panel.hpp>
// #include <lvgl.h>
// #include "lvgl_v8_port.h"
// #define LV_CONF_INCLUDE_SIMPLE 1
// #include "lv_conf.h"
// using namespace esp_panel::drivers;
// using namespace esp_panel::board;

#include <Arduino.h>

// TESTING: Commenting out ALL libraries to find I2C conflict source
// #include <WiFi.h>
// #include <WebServer.h>
// #include <ArduinoJson.h>
// #include <HTTPClient.h>
// #include <Preferences.h>
// #include <NTPClient.h>
// #include <WiFiUdp.h>
// #include <SPI.h>

// ============================================================================
// NFC MODULE SELECTION - Choose ONE
// ============================================================================
// #define USE_PN532       // PN532 NFC Module (currently installed)
// #define USE_PN5180   // PN5180 NFC Module (backup option)
// ============================================================================

// Conditional NFC library includes
// #ifdef USE_PN532
//   #include <Adafruit_PN532.h>
// #elif defined(USE_PN5180)
//   #include <PN5180.h>
//   #include <PN5180ISO15693.h>
// #endif

// #include <esp_sleep.h>
// #include <esp_system.h>
// #include <driver/gpio.h>
// #include <driver/ledc.h>
// #include <freertos/FreeRTOS.h>
// #include <freertos/task.h>

// ============================================================================
// PIN DEFINITIONS - ESP32-P4-Function-EV-Board v1.5.2
// ============================================================================

// NFC Module Pins (SAME FOR BOTH PN532 AND PN5180)
// Avoid GPIO7/8 (I2C touch), GPIO18/19 (SDIO), GPIO24/25 (USB-JTAG)
#define NFC_SS_PIN      33    // SPI Chip Select
#define NFC_RST_PIN     46    // Reset
#define NFC_IRQ_PIN     47    // Interrupt (optional)
#define NFC_MOSI        3     // SPI MOSI
#define NFC_MISO        4     // SPI MISO
#define NFC_SCK         5     // SPI Clock

// Display
#define BACKLIGHT_PWM_PIN    26
#define BACKLIGHT_PWM_FREQ   5000
#define BACKLIGHT_PWM_CHANNEL 0
#define BACKLIGHT_PWM_BITS   8

// Status LED (RGB)
#define LED_RED_PIN     48
#define LED_GREEN_PIN   53
#define LED_BLUE_PIN    54
#define BUZZER_PIN      32

// LED PWM Configuration
#define STATUS_LED_FREQ      5000
#define LEDC_CH_R            LEDC_CHANNEL_1
#define LEDC_CH_G            LEDC_CHANNEL_2
#define LEDC_CH_B            LEDC_CHANNEL_3
#define LEDC_TM_LED          LEDC_TIMER_1

// ============================================================================
// GLOBAL OBJECTS
// ============================================================================

const char* AP_SSID = "ESP32P4-TimeClock-V3";
const char* AP_PASSWORD = "Configure123";

WebServer *server = nullptr;
WiFiUDP *ntpUDP = nullptr;
NTPClient *timeClient = nullptr;
Preferences preferences;

// NFC object (conditional based on module type)
#ifdef USE_PN532
  Adafruit_PN532 *nfc = nullptr;
#elif defined(USE_PN5180)
  PN5180ISO15693 *nfc = nullptr;
#endif

// Display objects - TEMPORARILY DISABLED
// Board *board = nullptr;
// static bool display_initialized = false;
// static lv_obj_t *main_screen = nullptr;
// static lv_obj_t *status_label = nullptr;
// static lv_obj_t *time_label = nullptr;
// static lv_obj_t *card_label = nullptr;
// static lv_obj_t *wifi_label = nullptr;

// ============================================================================
// CONFIGURATION
// ============================================================================

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
    int backlightBrightness;
    int lastValidTimezoneOffset;
    unsigned long lastTimezoneUpdate;
    bool timezoneValidated;
};

DeviceConfig config;

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

// NFC Status
bool nfcInitialized = false;
String lastCardUID = "";
unsigned long cardReadCount = 0;

#ifdef USE_PN532
  String nfcStatusMessage = "PN532 Not Initialized";
#elif defined(USE_PN5180)
  String nfcStatusMessage = "PN5180 Not Initialized";
#else
  String nfcStatusMessage = "No NFC Module Selected";
#endif

// ============================================================================
// FORWARD DECLARATIONS
// ============================================================================

void loadConfiguration();
void saveConfiguration();
void connectToWiFi();
void startAccessPoint();
void initializeNTP();
void initializeWebServer();
void initializeBacklight();
void initializeStatusLedPWM();
void initializeHardware();
void initializeNFC();
void handleCardReading();
void setStatusLED(uint8_t red, uint8_t green, uint8_t blue);
void setBacklightBrightness(uint8_t brightness);
void updateDisplayStatus();
String getStatusText();
String getISODateTime();
int getTimezoneOffset(String timezone);
void sendPunchData(String cardUID, String credentialKind);
void sendHeartbeat();
void pollConfigurationUpdates();

// Web handlers
void handleRoot();
void handleConfigSubmit();
void handleStatus();
void handleRegister();
void handleBrightness();
void handleRestart();
void handleAPIStatus();
void handleAPIGetConfig();
void handleAPISetConfig();
void handleTestAPI();
void handleNotFound();

// ============================================================================
// SETUP
// ============================================================================

void setup() {
    delay(2000); // USB Serial/JTAG initialization time

    printf("\n\n========================================\n");
    printf("ESP32-P4 MINIMAL TEST - Finding I2C Conflict\n");
    printf("========================================\n");
    printf("If you see this, Arduino.h is OK\n");
    printf("Heap: %d bytes\n", ESP.getFreeHeap());

    printf("✅ Test passed - no I2C conflict!\n");
    printf("========================================\n");
}

// ============================================================================
// MAIN LOOP
// ============================================================================

void loop() {
    static unsigned long lastLog = 0;
    if (millis() - lastLog > 5000) {
        printf("Running... Heap: %d\n", ESP.getFreeHeap());
        lastLog = millis();
    }
    delay(1000);
}

// ============================================================================
// HARDWARE INITIALIZATION
// ============================================================================

void initializeHardware() {
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_BLUE_PIN, OUTPUT);
    pinMode(BUZZER_PIN, OUTPUT);

    digitalWrite(LED_RED_PIN, LOW);
    digitalWrite(LED_GREEN_PIN, LOW);
    digitalWrite(LED_BLUE_PIN, LOW);
    digitalWrite(BUZZER_PIN, LOW);

    printf("[HW] Status LED and buzzer initialized\n");
}

void initializeBacklight() {
    ledcAttach(BACKLIGHT_PWM_PIN, BACKLIGHT_PWM_FREQ, BACKLIGHT_PWM_BITS);

    if (config.backlightBrightness <= 0) {
        config.backlightBrightness = 128;
    }
    setBacklightBrightness(config.backlightBrightness);

    printf("[HW] Backlight initialized at %d brightness\n", config.backlightBrightness);
}

void initializeStatusLedPWM() {
    ledcAttach(LED_RED_PIN, STATUS_LED_FREQ, BACKLIGHT_PWM_BITS);
    ledcAttach(LED_GREEN_PIN, STATUS_LED_FREQ, BACKLIGHT_PWM_BITS);
    ledcAttach(LED_BLUE_PIN, STATUS_LED_FREQ, BACKLIGHT_PWM_BITS);

    printf("[HW] Status LED PWM initialized\n");
}

void setBacklightBrightness(uint8_t brightness) {
    ledcWrite(BACKLIGHT_PWM_PIN, brightness);
    config.backlightBrightness = brightness;
}

void setStatusLED(uint8_t red, uint8_t green, uint8_t blue) {
    ledcWrite(LED_RED_PIN, red);
    ledcWrite(LED_GREEN_PIN, green);
    ledcWrite(LED_BLUE_PIN, blue);
}

// ============================================================================
// NFC INITIALIZATION AND CARD READING
// ============================================================================

void initializeNFC() {
#ifdef USE_PN532
    printf("[NFC] Initializing PN532...\n");

    SPI.begin(NFC_SCK, NFC_MISO, NFC_MOSI, NFC_SS_PIN);

    nfc = new Adafruit_PN532(NFC_SS_PIN, &SPI);
    nfc->begin();

    uint32_t versiondata = nfc->getFirmwareVersion();
    if (!versiondata) {
        printf("❌ PN532 not found - check wiring\n");
        nfcStatusMessage = "PN532 Not Found";
        nfcInitialized = false;
        setStatusLED(255, 0, 0); // Red - error
        return;
    }

    printf("✅ PN532 found - chip v%d.%d\n",
           (versiondata >> 24) & 0xFF,
           (versiondata >> 16) & 0xFF);

    nfc->SAMConfig();
    nfc->setPassiveActivationRetries(0xFF);

    nfcStatusMessage = "PN532 Ready";
    nfcInitialized = true;

#elif defined(USE_PN5180)
    printf("[NFC] Initializing PN5180...\n");

    SPI.begin(NFC_SCK, NFC_MISO, NFC_MOSI, NFC_SS_PIN);

    nfc = new PN5180ISO15693(NFC_SS_PIN, NFC_IRQ_PIN, NFC_RST_PIN, &SPI);
    nfc->begin();
    nfc->reset();

    uint8_t productVersion[2];
    if (!nfc->readEEprom(PRODUCT_VERSION, productVersion, sizeof(productVersion))) {
        printf("❌ PN5180 not found - check wiring\n");
        nfcStatusMessage = "PN5180 Not Found";
        nfcInitialized = false;
        setStatusLED(255, 0, 0); // Red - error
        return;
    }

    printf("✅ PN5180 found - product v%d.%d\n",
           productVersion[1], productVersion[0]);

    nfc->setupRF();

    nfcStatusMessage = "PN5180 Ready";
    nfcInitialized = true;

#else
    printf("❌ No NFC module selected in firmware!\n");
    nfcStatusMessage = "No Module Selected";
    nfcInitialized = false;
#endif
}

void handleCardReading() {
    if (!nfcInitialized) {
        return;
    }

    // Debounce - don't read cards too frequently
    if (millis() - lastCardRead < 2000) {
        return;
    }

#ifdef USE_PN532
    uint8_t uid[] = { 0, 0, 0, 0, 0, 0, 0 };
    uint8_t uidLength;

    // Check for ISO14443A card (MIFARE, NTAG, etc.)
    if (nfc->readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 50)) {
        String cardUID = "";
        for (uint8_t i = 0; i < uidLength; i++) {
            if (cardUID.length() > 0) cardUID += ":";
            if (uid[i] < 0x10) cardUID += "0";
            cardUID += String(uid[i], HEX);
        }
        cardUID.toUpperCase();

        // Only process if it's a different card or enough time has passed
        if (cardUID != lastCardUID || millis() - lastCardRead > 5000) {
            printf("[NFC] PN532 Card detected: %s\n", cardUID.c_str());

            lastCardUID = cardUID;
            lastCardRead = millis();
            cardReadCount++;

            // Visual/audio feedback
            setStatusLED(0, 0, 255); // Blue
            digitalWrite(BUZZER_PIN, HIGH);
            delay(100);
            digitalWrite(BUZZER_PIN, LOW);
            setStatusLED(0, 255, 0); // Green

            // Send to server
            sendPunchData(cardUID, "nfc_iso14443a");

            // Update display - DISABLED
            // if (lvgl_port_lock(50)) {
            //     lv_label_set_text(card_label, ("Card: " + cardUID).c_str());
            //     lvgl_port_unlock();
            // }
        }
    }

#elif defined(USE_PN5180)
    uint8_t uid[8];

    // Inventory command for ISO15693
    ISO15693ErrorCode rc = nfc->getInventory(uid);

    if (rc == ISO15693_EC_OK) {
        String cardUID = "";
        for (int i = 0; i < 8; i++) {
            if (cardUID.length() > 0) cardUID += ":";
            if (uid[i] < 0x10) cardUID += "0";
            cardUID += String(uid[i], HEX);
        }
        cardUID.toUpperCase();

        // Only process if it's a different card or enough time has passed
        if (cardUID != lastCardUID || millis() - lastCardRead > 5000) {
            printf("[NFC] PN5180 Card detected: %s\n", cardUID.c_str());

            lastCardUID = cardUID;
            lastCardRead = millis();
            cardReadCount++;

            // Visual/audio feedback
            setStatusLED(0, 0, 255); // Blue
            digitalWrite(BUZZER_PIN, HIGH);
            delay(100);
            digitalWrite(BUZZER_PIN, LOW);
            setStatusLED(0, 255, 0); // Green

            // Send to server
            sendPunchData(cardUID, "nfc_iso15693");

            // Update display - DISABLED
            // if (lvgl_port_lock(50)) {
            //     lv_label_set_text(card_label, ("Card: " + cardUID).c_str());
            //     lvgl_port_unlock();
            // }
        }
    }

    // Reset RF field for next read
    nfc->reset();
    nfc->setupRF();
#endif
}

// ============================================================================
// CONFIGURATION MANAGEMENT
// ============================================================================

void loadConfiguration() {
    if (!preferences.begin("timeclock", true)) {
        printf("[CONFIG] Failed to open preferences\n");
        config.isConfigured = false;
        return;
    }

    config.deviceName = preferences.getString("deviceName", "ESP32P4-TimeClock-V3");
    config.deviceId = preferences.getString("deviceId", "");
    config.wifiSSID = preferences.getString("wifiSSID", "");
    config.wifiPassword = preferences.getString("wifiPassword", "");
    config.serverHost = preferences.getString("serverHost", "");
    config.serverPort = preferences.getInt("serverPort", 443);
    config.apiToken = preferences.getString("apiToken", "");
    config.ntpServer = preferences.getString("ntpServer", "pool.ntp.org");
    config.timezone = preferences.getString("timezone", "America/New_York");
    config.isConfigured = preferences.getBool("isConfigured", false);
    config.isRegistered = preferences.getBool("isRegistered", false);
    config.lastSync = preferences.getULong("lastSync", 0);
    config.lastConfigVersion = preferences.getInt("lastConfigVer", 0);
    config.backlightBrightness = preferences.getInt("backlight", 128);
    config.lastValidTimezoneOffset = preferences.getInt("tzOffset", -18000);
    config.lastTimezoneUpdate = preferences.getULong("tzUpdate", 0);
    config.timezoneValidated = preferences.getBool("tzValid", false);

    config.macAddress = WiFi.macAddress();

    preferences.end();

    printf("[CONFIG] Loaded: %s | WiFi: %s | Server: %s:%d | Registered: %s\n",
           config.deviceName.c_str(),
           config.wifiSSID.c_str(),
           config.serverHost.c_str(),
           config.serverPort,
           config.isRegistered ? "Yes" : "No");
}

void saveConfiguration() {
    if (!preferences.begin("timeclock", false)) {
        printf("[CONFIG] Failed to open preferences for writing\n");
        return;
    }

    preferences.putString("deviceName", config.deviceName);
    preferences.putString("deviceId", config.deviceId);
    preferences.putString("wifiSSID", config.wifiSSID);
    preferences.putString("wifiPassword", config.wifiPassword);
    preferences.putString("serverHost", config.serverHost);
    preferences.putInt("serverPort", config.serverPort);
    preferences.putString("apiToken", config.apiToken);
    preferences.putString("ntpServer", config.ntpServer);
    preferences.putString("timezone", config.timezone);
    preferences.putBool("isConfigured", config.isConfigured);
    preferences.putBool("isRegistered", config.isRegistered);
    preferences.putULong("lastSync", config.lastSync);
    preferences.putInt("lastConfigVer", config.lastConfigVersion);
    preferences.putInt("backlight", config.backlightBrightness);
    preferences.putInt("tzOffset", config.lastValidTimezoneOffset);
    preferences.putULong("tzUpdate", config.lastTimezoneUpdate);
    preferences.putBool("tzValid", config.timezoneValidated);

    preferences.end();

    printf("[CONFIG] Saved successfully\n");
}

// ============================================================================
// WIFI AND NETWORK
// ============================================================================

void connectToWiFi() {
    printf("[WIFI] Connecting to %s...\n", config.wifiSSID.c_str());

    WiFi.mode(WIFI_STA);
    WiFi.begin(config.wifiSSID.c_str(), config.wifiPassword.c_str());

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        printf(".");
        attempts++;
    }
    printf("\n");

    if (WiFi.status() == WL_CONNECTED) {
        printf("✅ Connected: %s\n", WiFi.localIP().toString().c_str());
        currentStatus = config.isRegistered ? STATUS_REGISTERED : STATUS_CONFIGURED;
    } else {
        printf("❌ Connection failed\n");
        startAccessPoint();
    }
}

void startAccessPoint() {
    printf("[WIFI] Starting AP: %s\n", AP_SSID);

    WiFi.mode(WIFI_AP);
    WiFi.softAP(AP_SSID, AP_PASSWORD);

    printf("✅ AP started: %s\n", WiFi.softAPIP().toString().c_str());
    currentStatus = STATUS_NOT_CONFIGURED;
}

void initializeNTP() {
    if (!timeClient) return;

    printf("[NTP] Starting NTP client...\n");
    timeClient->begin();
    timeClient->setUpdateInterval(3600000); // 1 hour

    if (config.timezoneValidated && config.lastValidTimezoneOffset != 0) {
        timeClient->setTimeOffset(config.lastValidTimezoneOffset);
        printf("[NTP] Using cached timezone offset: %d seconds\n", config.lastValidTimezoneOffset);
    }

    timeClient->update();
    printf("✅ NTP synchronized\n");
}

// ============================================================================
// WEB SERVER
// ============================================================================

void initializeWebServer() {
    server->on("/", HTTP_GET, handleRoot);
    server->on("/config", HTTP_POST, handleConfigSubmit);
    server->on("/status", HTTP_GET, handleStatus);
    server->on("/register", HTTP_POST, handleRegister);
    server->on("/brightness", HTTP_POST, handleBrightness);
    server->on("/restart", HTTP_POST, handleRestart);
    server->on("/api/status", HTTP_GET, handleAPIStatus);
    server->on("/api/config", HTTP_GET, handleAPIGetConfig);
    server->on("/api/config", HTTP_POST, handleAPISetConfig);
    server->on("/test-api", HTTP_POST, handleTestAPI);
    server->onNotFound(handleNotFound);

    server->begin();
    printf("✅ Web server started on port 80\n");
}

// ============================================================================
// WEB HANDLERS (Inline HTML)
// ============================================================================

void handleRoot() {
    String html = "<!DOCTYPE html><html><head><title>ESP32-P4 TimeClock</title>";
    html += "<meta name='viewport' content='width=device-width,initial-scale=1'>";
    html += "<style>body{font-family:Arial;margin:20px;background:#f0f0f0}";
    html += ".container{max-width:600px;margin:0 auto;background:white;padding:20px;border-radius:10px;box-shadow:0 2px 4px rgba(0,0,0,0.1)}";
    html += "h1{color:#333;text-align:center}input,select{width:100%;padding:10px;margin:8px 0;box-sizing:border-box}";
    html += "button{background:#4CAF50;color:white;padding:12px;border:none;border-radius:4px;cursor:pointer;width:100%;margin-top:10px}";
    html += "button:hover{background:#45a049}.status{padding:10px;margin:10px 0;border-radius:4px;background:#e3f2fd}</style></head>";
    html += "<body><div class='container'><h1>ESP32-P4 TimeClock V3</h1>";
    html += "<div class='status'><strong>Status:</strong> " + getStatusText() + "</div>";
    html += "<div class='status'><strong>Device ID:</strong> " + config.deviceId + "</div>";
    html += "<div class='status'><strong>MAC:</strong> " + WiFi.macAddress() + "</div>";
    html += "<div class='status'><strong>NFC:</strong> " + nfcStatusMessage + "</div>";

    html += "<h2>WiFi Configuration</h2><form action='/config' method='POST'>";
    html += "<input name='deviceName' placeholder='Device Name' value='" + config.deviceName + "'>";
    html += "<input name='ssid' placeholder='WiFi SSID' value='" + config.wifiSSID + "'>";
    html += "<input name='password' type='password' placeholder='WiFi Password'>";
    html += "<input name='serverHost' placeholder='Server Host' value='" + config.serverHost + "'>";
    html += "<input name='serverPort' placeholder='Server Port' value='" + String(config.serverPort) + "'>";
    html += "<input name='apiToken' placeholder='API Token' value='" + config.apiToken + "'>";
    html += "<button type='submit'>Save Configuration</button></form>";

    if (config.isConfigured && !config.isRegistered) {
        html += "<h2>Register Device</h2><form action='/register' method='POST'>";
        html += "<button type='submit'>Register with Server</button></form>";
    }

    html += "<h2>Backlight</h2><form action='/brightness' method='POST'>";
    html += "<input type='range' name='brightness' min='0' max='255' value='" + String(config.backlightBrightness) + "' onchange='this.nextElementSibling.value=this.value'>";
    html += "<output>" + String(config.backlightBrightness) + "</output>";
    html += "<button type='submit'>Set Brightness</button></form>";

    html += "<form action='/restart' method='POST'><button style='background:#f44336'>Restart Device</button></form>";
    html += "</div></body></html>";

    server->send(200, "text/html", html);
}

void handleConfigSubmit() {
    if (server->hasArg("deviceName")) config.deviceName = server->arg("deviceName");
    if (server->hasArg("ssid")) config.wifiSSID = server->arg("ssid");
    if (server->hasArg("password") && server->arg("password").length() > 0) {
        config.wifiPassword = server->arg("password");
    }
    if (server->hasArg("serverHost")) config.serverHost = server->arg("serverHost");
    if (server->hasArg("serverPort")) config.serverPort = server->arg("serverPort").toInt();
    if (server->hasArg("apiToken")) config.apiToken = server->arg("apiToken");

    config.isConfigured = true;
    saveConfiguration();

    String response = "Configuration saved! Restarting in 3 seconds...";
    response += "<script>setTimeout(function(){window.location='/';},3000);</script>";
    server->send(200, "text/html", response);

    delay(3000);
    ESP.restart();
}

void handleStatus() {
    StaticJsonDocument<512> doc;
    doc["deviceId"] = config.deviceId;
    doc["deviceName"] = config.deviceName;
    doc["status"] = getStatusText();
    doc["isConfigured"] = config.isConfigured;
    doc["isRegistered"] = config.isRegistered;
    doc["wifiSSID"] = config.wifiSSID;
    doc["wifiConnected"] = (WiFi.status() == WL_CONNECTED);
    doc["ipAddress"] = WiFi.status() == WL_CONNECTED ? WiFi.localIP().toString() : WiFi.softAPIP().toString();
    doc["macAddress"] = WiFi.macAddress();
    doc["nfcStatus"] = nfcStatusMessage;
    doc["nfcInitialized"] = nfcInitialized;
    doc["cardReadCount"] = cardReadCount;
    doc["freeHeap"] = ESP.getFreeHeap();
    doc["uptime"] = millis() / 1000;

    String response;
    serializeJson(doc, response);
    server->send(200, "application/json", response);
}

void handleRegister() {
    if (!config.isConfigured) {
        server->send(400, "text/plain", "Device not configured");
        return;
    }

    HTTPClient http;
    String url = String("https://") + config.serverHost + ":" + config.serverPort + "/api/timeclocks/register";

    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    StaticJsonDocument<512> doc;
    doc["device_id"] = config.deviceId;
    doc["device_name"] = config.deviceName;
    doc["mac_address"] = WiFi.macAddress();
    doc["ip_address"] = WiFi.localIP().toString();
    doc["firmware_version"] = "V3.0";

    String requestBody;
    serializeJson(doc, requestBody);

    int httpCode = http.POST(requestBody);

    if (httpCode == 200 || httpCode == 201) {
        config.isRegistered = true;
        config.lastSync = millis();
        saveConfiguration();
        currentStatus = STATUS_REGISTERED;

        server->send(200, "text/html", "Registration successful!<script>setTimeout(function(){window.location='/';},2000);</script>");
        printf("[API] Device registered successfully\n");
    } else {
        String error = "Registration failed: " + String(httpCode);
        server->send(500, "text/plain", error);
        printf("[API] Registration failed: %d\n", httpCode);
    }

    http.end();
}

void handleBrightness() {
    if (server->hasArg("brightness")) {
        int brightness = server->arg("brightness").toInt();
        setBacklightBrightness(brightness);
        saveConfiguration();
    }
    server->sendHeader("Location", "/");
    server->send(302);
}

void handleRestart() {
    server->send(200, "text/html", "Restarting...<script>setTimeout(function(){window.location='/';},5000);</script>");
    delay(1000);
    ESP.restart();
}

void handleAPIStatus() {
    handleStatus();
}

void handleAPIGetConfig() {
    StaticJsonDocument<512> doc;
    doc["deviceId"] = config.deviceId;
    doc["deviceName"] = config.deviceName;
    doc["timezone"] = config.timezone;
    doc["ntpServer"] = config.ntpServer;
    doc["backlightBrightness"] = config.backlightBrightness;
    doc["configVersion"] = config.lastConfigVersion;

    String response;
    serializeJson(doc, response);
    server->send(200, "application/json", response);
}

void handleAPISetConfig() {
    if (server->hasArg("plain")) {
        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, server->arg("plain"));

        if (!error) {
            if (doc.containsKey("deviceName")) config.deviceName = doc["deviceName"].as<String>();
            if (doc.containsKey("timezone")) config.timezone = doc["timezone"].as<String>();
            if (doc.containsKey("ntpServer")) config.ntpServer = doc["ntpServer"].as<String>();
            if (doc.containsKey("backlightBrightness")) {
                setBacklightBrightness(doc["backlightBrightness"]);
            }
            if (doc.containsKey("configVersion")) {
                config.lastConfigVersion = doc["configVersion"];
            }

            saveConfiguration();
            server->send(200, "application/json", "{\"status\":\"ok\"}");
            printf("[API] Configuration updated via API\n");
        } else {
            server->send(400, "application/json", "{\"error\":\"Invalid JSON\"}");
        }
    } else {
        server->send(400, "application/json", "{\"error\":\"No data\"}");
    }
}

void handleTestAPI() {
    HTTPClient http;
    String url = String("https://") + config.serverHost + ":" + config.serverPort + "/api/test";

    http.begin(url);
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    int httpCode = http.GET();
    String payload = http.getString();

    StaticJsonDocument<256> doc;
    doc["httpCode"] = httpCode;
    doc["response"] = payload;

    String response;
    serializeJson(doc, response);
    server->send(200, "application/json", response);

    http.end();
}

void handleNotFound() {
    server->send(404, "text/plain", "Not Found");
}

// ============================================================================
// SERVER COMMUNICATION
// ============================================================================

void sendPunchData(String cardUID, String credentialKind) {
    if (!config.isRegistered || WiFi.status() != WL_CONNECTED) {
        printf("[API] Cannot send punch - not registered or no WiFi\n");
        return;
    }

    HTTPClient http;
    String url = String("https://") + config.serverHost + ":" + config.serverPort + "/api/timeclocks/punch";

    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    StaticJsonDocument<512> doc;
    doc["device_id"] = config.deviceId;
    doc["credential_uid"] = cardUID;
    doc["credential_kind"] = credentialKind;
    doc["timestamp"] = getISODateTime();
    doc["timezone"] = config.timezone;

    String requestBody;
    serializeJson(doc, requestBody);

    printf("[API] Sending punch: %s\n", cardUID.c_str());

    int httpCode = http.POST(requestBody);

    if (httpCode == 200 || httpCode == 201) {
        printf("✅ Punch sent successfully\n");
        currentStatus = STATUS_APPROVED;

        // Update display - DISABLED
        // if (lvgl_port_lock(50)) {
        //     lv_label_set_text(status_label, "Punch Recorded ✓");
        //     lvgl_port_unlock();
        // }
    } else {
        printf("❌ Punch failed: %d - %s\n", httpCode, http.getString().c_str());
        lastError = "Punch failed: " + String(httpCode);
        currentStatus = STATUS_ERROR;
    }

    http.end();
}

void sendHeartbeat() {
    if (!config.isRegistered || WiFi.status() != WL_CONNECTED) {
        return;
    }

    HTTPClient http;
    String url = String("https://") + config.serverHost + ":" + config.serverPort + "/api/timeclocks/heartbeat";

    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    StaticJsonDocument<512> doc;
    doc["device_id"] = config.deviceId;
    doc["timestamp"] = getISODateTime();
    doc["status"] = getStatusText();
    doc["nfc_status"] = nfcStatusMessage;
    doc["card_count"] = cardReadCount;
    doc["free_heap"] = ESP.getFreeHeap();
    doc["uptime"] = millis() / 1000;

    String requestBody;
    serializeJson(doc, requestBody);

    int httpCode = http.POST(requestBody);

    if (httpCode == 200) {
        printf("[API] Heartbeat sent\n");
    } else {
        printf("[API] Heartbeat failed: %d\n", httpCode);
    }

    http.end();
}

void pollConfigurationUpdates() {
    if (!config.isRegistered || WiFi.status() != WL_CONNECTED) {
        return;
    }

    HTTPClient http;
    String url = String("https://") + config.serverHost + ":" + config.serverPort +
                 "/api/timeclocks/config/" + config.deviceId;

    http.begin(url);
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    int httpCode = http.GET();

    if (httpCode == 200) {
        String payload = http.getString();
        StaticJsonDocument<512> doc;
        DeserializationError error = deserializeJson(doc, payload);

        if (!error) {
            int serverConfigVersion = doc["configVersion"] | 0;

            if (serverConfigVersion > config.lastConfigVersion) {
                printf("[API] New configuration available (v%d -> v%d)\n",
                       config.lastConfigVersion, serverConfigVersion);

                if (doc.containsKey("deviceName")) config.deviceName = doc["deviceName"].as<String>();
                if (doc.containsKey("timezone")) config.timezone = doc["timezone"].as<String>();
                if (doc.containsKey("ntpServer")) config.ntpServer = doc["ntpServer"].as<String>();
                if (doc.containsKey("backlightBrightness")) {
                    setBacklightBrightness(doc["backlightBrightness"]);
                }

                config.lastConfigVersion = serverConfigVersion;
                saveConfiguration();

                printf("✅ Configuration updated from server\n");
            }
        }
    }

    http.end();
}

// ============================================================================
// DISPLAY UPDATES
// ============================================================================

void updateDisplayStatus() {
    // DISPLAY DISABLED FOR DEBUGGING
    // if (!display_initialized) return;
    // ... display code commented out ...
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

String getStatusText() {
    switch (currentStatus) {
        case STATUS_NOT_CONFIGURED: return "Not Configured";
        case STATUS_CONFIGURED: return "Configured";
        case STATUS_REGISTERED: return "Registered";
        case STATUS_APPROVED: return "Approved";
        case STATUS_ERROR: return "Error";
        default: return "Unknown";
    }
}

String getISODateTime() {
    if (!timeClient || WiFi.status() != WL_CONNECTED) {
        return "1970-01-01T00:00:00Z";
    }

    timeClient->update();
    unsigned long epochTime = timeClient->getEpochTime();

    time_t rawtime = epochTime;
    struct tm *ti = gmtime(&rawtime);

    char buffer[25];
    strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%SZ", ti);

    return String(buffer);
}

int getTimezoneOffset(String timezone) {
    // Simplified timezone lookup - expand as needed
    if (timezone == "America/New_York") return -18000;
    if (timezone == "America/Chicago") return -21600;
    if (timezone == "America/Denver") return -25200;
    if (timezone == "America/Los_Angeles") return -28800;
    if (timezone == "Europe/London") return 0;
    if (timezone == "Europe/Paris") return 3600;
    if (timezone == "Asia/Tokyo") return 32400;

    return config.lastValidTimezoneOffset; // Fallback to cached value
}
