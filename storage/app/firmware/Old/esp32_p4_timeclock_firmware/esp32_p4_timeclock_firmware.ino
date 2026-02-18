/*
 * ESP32-P4 Time Clock Firmware with 7" Display and PN5180 NFC
 *
 * This firmware creates a WiFi access point for initial configuration,
 * serves a web interface for device setup, communicates with the Laravel
 * attendance system, and provides a 7" touchscreen interface.
 *
 * Hardware Requirements:
 * - ESP32-P4-Function-EV-Board v1.5.2
 * - 7" Display connected via MIPI DSI port
 * - PN5180 NFC Module (ISO15693/ISO14443 support)
 * - PWM backlight control via GPIO26
 *
 * Display Wiring:
 * - 7" Display → MIPI DSI Port (J6)
 * - PWM wire → J6 header → GPIO26 (J1 header)
 *
 * PN5180 NFC Wiring (SPI):
 * - VCC → 3.3V
 * - GND → GND
 * - MOSI → GPIO11 (SPI2)
 * - MISO → GPIO13 (SPI2)
 * - SCK → GPIO12 (SPI2)
 * - NSS → GPIO10 (Chip Select)
 * - RST → GPIO14 (Reset)
 * - BUSY → GPIO21 (Status)
 * - IRQ → GPIO47 (Interrupt, optional)
 *
 * Libraries Required:
 * - WiFi
 * - WebServer
 * - ArduinoJson
 * - PN5180ISO15693 (for PN5180 NFC)
 * - PN5180ISO14443 (for PN5180 NFC)
 * - NTPClient
 * - SPIFFS or LittleFS
 * - esp_lcd (for display)
 * - lvgl (for GUI)
 */

#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <SPIFFS.h>
#include <NTPClient.h>
#include <WiFiUdp.h>
#include <SPI.h>
#include <PN5180.h>
#include <PN5180ISO15693.h>
#include <PN5180ISO14443.h>
#include <esp_sleep.h>
#include <esp_lcd_panel_ops.h>
#include <esp_lcd_mipi_dsi.h>
#include <driver/gpio.h>
#include <driver/ledc.h>
#include <lvgl.h>

// PN5180 NFC Module Pin Definitions for ESP32-P4-Function-EV-Board v1.5.2
#define PN5180_NSS      5     // SPI Chip Select (available on pin header)
#define PN5180_BUSY     21    // Busy status pin (available on pin header)
#define PN5180_RST      22    // Reset pin (available on pin header)
#define PN5180_REQ      23    // Request/Enable pin (controls RF field)
#define PN5180_IRQ      47    // Interrupt pin (optional, available on pin header)

// ESP32-P4 SPI pins - Using software SPI with available pins
#define PN5180_MOSI     6     // Data Out (available on pin header)
#define PN5180_MISO     7     // Data In (available on pin header)
#define PN5180_SCK      8     // Clock (available on pin header)

// Display backlight control
#define BACKLIGHT_PWM_PIN    26    // Connected from J6 PWM to GPIO26
#define BACKLIGHT_PWM_FREQ   5000  // 5kHz PWM frequency
#define BACKLIGHT_PWM_CHANNEL 0    // LEDC channel
#define BACKLIGHT_PWM_BITS   8     // 8-bit resolution (0-255)

// Status LED pins (using available pins from ESP32-P4-Function-EV-Board v1.5.2)
#define LED_RED_PIN     45    // Available on pin header
#define LED_GREEN_PIN   46    // Available on pin header
#define LED_BLUE_PIN    48    // Available on pin header
#define BUZZER_PIN      25    // Available on pin header

// Display configuration
#define LCD_PIXEL_CLOCK_HZ    (10 * 1000 * 1000)
#define LCD_BK_LIGHT_ON_LEVEL 1
#define LCD_BK_LIGHT_OFF_LEVEL !LCD_BK_LIGHT_ON_LEVEL

// LVGL display buffer
#define LVGL_BUFFER_SIZE (800 * 100)  // 100 lines buffer for 800px width
static lv_disp_draw_buf_t draw_buf;
static lv_color_t buf_1[LVGL_BUFFER_SIZE];
static lv_disp_drv_t disp_drv;

// Network Configuration
const char* AP_SSID = "ESP32P4-TimeClock";
const char* AP_PASSWORD = "Configure123";

// Global Objects
WebServer server(80);
PN5180ISO15693 nfc15693(PN5180_NSS, PN5180_BUSY, PN5180_RST);
PN5180ISO14443 nfc14443(PN5180_NSS, PN5180_BUSY, PN5180_RST);
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
    int backlightBrightness; // 0-255

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

// NFC Status Variables
bool nfcInitialized = false;
String lastCardUID = "";
unsigned long cardReadCount = 0;
String nfcStatusMessage = "Not Initialized";

// Display handles
esp_lcd_panel_handle_t panel_handle = NULL;
static bool display_initialized = false;

// GUI Objects
static lv_obj_t *main_screen;
static lv_obj_t *status_label;
static lv_obj_t *time_label;
static lv_obj_t *card_label;
static lv_obj_t *wifi_label;

void setup() {
    Serial.begin(115200);
    Serial.println();
    Serial.println("========================================");
    Serial.println("ESP32-P4 Time Clock with 7\" Display");
    Serial.println("========================================");

    Serial.println("ESP32-P4 Time Clock Starting...");

    // ⚡ DISABLE ALL POWER MANAGEMENT - Critical for PN5180 reliability
    Serial.println("🔋 Configuring power management for PN5180 compatibility...");

    // Disable CPU frequency scaling (keep at maximum speed)
    setCpuFrequencyMhz(240); // Max speed: 240MHz

    // Disable automatic light sleep
    esp_sleep_disable_wakeup_source(ESP_SLEEP_WAKEUP_ALL);

    // Configure GPIO power domains to stay active (ESP32-P4 compatible)
    esp_sleep_pd_config(ESP_PD_DOMAIN_RTC_PERIPH, ESP_PD_OPTION_ON);
    // ESP32-P4 uses different power domain constants
    // esp_sleep_pd_config(ESP_PD_DOMAIN_RTC_SLOW_MEM, ESP_PD_OPTION_ON); // Not available on ESP32-P4
    // esp_sleep_pd_config(ESP_PD_DOMAIN_RTC_FAST_MEM, ESP_PD_OPTION_ON); // Not available on ESP32-P4

    Serial.println("✅ Power management configured");
    Serial.printf("💪 CPU Frequency: %d MHz\n", getCpuFrequencyMhz());

    // Initialize hardware
    Serial.println("🔧 Initializing hardware...");
    initializeHardware();

    // Load configuration
    Serial.println("📁 Loading configuration...");
    loadConfiguration();

    // Generate device ID if not present
    if (config.deviceId.isEmpty()) {
        config.deviceId = "ESP32P4-" + WiFi.macAddress();
        config.deviceId.replace(":", "");
        Serial.println("🆔 Generated Device ID: " + config.deviceId);
        saveConfiguration();
    }

    // Initialize backlight PWM
    initializeBacklight();

    // Initialize 7" display
    initializeDisplay();

    // Initialize LVGL GUI
    initializeLVGL();

    // Initialize WiFi
    if (config.isConfigured && !config.wifiSSID.isEmpty()) {
        Serial.println("🌐 Connecting to configured WiFi...");
        connectToWiFi();
    } else {
        Serial.println("📡 Starting WiFi Access Point for configuration...");
        startAccessPoint();
    }

    // Initialize NTP if WiFi is connected
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("⏰ Initializing NTP client...");
        initializeNTP();
    }

    // Initialize web server
    initializeWebServer();

    // Initialize PN5180 NFC reader
    initializePN5180();

    // Check GPIO pin states before initialization
    Serial.println("📋 Pre-initialization pin status:");
    Serial.printf("   NSS (GPIO %d): %s\n", PN5180_NSS, digitalRead(PN5180_NSS) ? "HIGH" : "LOW");
    Serial.printf("   RST (GPIO %d): %s\n", PN5180_RST, digitalRead(PN5180_RST) ? "HIGH" : "LOW");
    Serial.printf("   BUSY (GPIO %d): %s\n", PN5180_BUSY, digitalRead(PN5180_BUSY) ? "HIGH" : "LOW");

    Serial.println("ESP32-P4 Time Clock initialized successfully");
    setStatusLED(0, 255, 0); // Green - ready

    // Update display with initial status
    updateDisplayStatus();
}

void loop() {
    // Handle web server requests
    server.handleClient();

    // Handle LVGL tasks
    lv_timer_handler();

    // Regular status updates
    static unsigned long lastDebugPrint = 0;
    if (millis() - lastDebugPrint > 30000) { // Every 30 seconds
        Serial.println("📊 === STATUS UPDATE ===");
        Serial.println("   Device: " + config.deviceName);
        Serial.println("   Status: " + getStatusText());
        Serial.println("   WiFi: " + String(WiFi.status() == WL_CONNECTED ? "Connected" : "Disconnected"));
        Serial.println("   Server: " + String(config.isRegistered ? "Registered" : "Not Registered"));
        Serial.println("   NFC: " + nfcStatusMessage);
        Serial.println("   Cards Read: " + String(cardReadCount));
        Serial.println("   Free Heap: " + String(ESP.getFreeHeap()) + " bytes");
        lastDebugPrint = millis();
    }

    // Handle NFC card reading
    if (config.isRegistered && (currentStatus == STATUS_REGISTERED || currentStatus == STATUS_APPROVED)) {
        handleCardReading();
    } else {
        // Blink status LED to show we're waiting for configuration/registration
        static unsigned long lastBlink = 0;
        if (millis() - lastBlink > 1000) {
            static bool ledState = false;
            if (currentStatus == STATUS_NOT_CONFIGURED) {
                setStatusLED(255, ledState ? 0 : 255, 0); // Red/Yellow blink
            } else if (currentStatus == STATUS_CONFIGURED) {
                setStatusLED(0, 0, ledState ? 255 : 0); // Blue blink
            }
            ledState = !ledState;
            lastBlink = millis();
        }
    }

    // Poll for configuration updates every 5 minutes if registered
    if (config.isRegistered && WiFi.status() == WL_CONNECTED) {
        if (millis() - lastConfigPoll > 300000) { // 5 minutes
            Serial.println("🔄 Polling for configuration updates...");
            pollConfigurationUpdates();
            lastConfigPoll = millis();
        }
    }

    // Send heartbeat every 60 seconds if approved
    if (currentStatus == STATUS_APPROVED && WiFi.status() == WL_CONNECTED) {
        if (millis() - lastHeartbeat > 60000) { // 60 seconds
            sendHeartbeat();
            lastHeartbeat = millis();
        }
    }

    // Update display periodically
    static unsigned long lastDisplayUpdate = 0;
    if (millis() - lastDisplayUpdate > 1000) { // Every second
        updateDisplayStatus();
        lastDisplayUpdate = millis();
    }

    delay(10); // Small delay to prevent watchdog
}

void initializeBacklight() {
    Serial.println("=== Display Backlight Initialization ===");

    // Configure LEDC for PWM backlight control (ESP32-P4 compatible)
    ledc_timer_config_t ledc_timer = {};
    ledc_timer.speed_mode = LEDC_LOW_SPEED_MODE;
    ledc_timer.timer_num = LEDC_TIMER_0;
    ledc_timer.duty_resolution = LEDC_TIMER_8_BIT;
    ledc_timer.freq_hz = BACKLIGHT_PWM_FREQ;
    ledc_timer.clk_cfg = LEDC_AUTO_CLK;

    esp_err_t ret = ledc_timer_config(&ledc_timer);
    if (ret != ESP_OK) {
        Serial.printf("❌ LEDC timer config failed: %s\n", esp_err_to_name(ret));
        return;
    }

    ledc_channel_config_t ledc_channel = {};
    ledc_channel.gpio_num = BACKLIGHT_PWM_PIN;
    ledc_channel.speed_mode = LEDC_LOW_SPEED_MODE;
    ledc_channel.channel = LEDC_CHANNEL_0;
    ledc_channel.timer_sel = LEDC_TIMER_0;
    ledc_channel.duty = 0;
    ledc_channel.hpoint = 0;

    ret = ledc_channel_config(&ledc_channel);
    if (ret != ESP_OK) {
        Serial.printf("❌ LEDC channel config failed: %s\n", esp_err_to_name(ret));
        return;
    }

    // Set default brightness (80%)
    config.backlightBrightness = 204; // 80% of 255
    setBacklightBrightness(config.backlightBrightness);

    Serial.printf("✅ Backlight PWM initialized on GPIO%d at %dHz\n", BACKLIGHT_PWM_PIN, BACKLIGHT_PWM_FREQ);
    Serial.printf("   Default brightness: %d/255 (%.1f%%)\n", config.backlightBrightness, (config.backlightBrightness/255.0)*100);
}

void setBacklightBrightness(uint8_t brightness) {
    uint32_t duty = (brightness * 255) / 255; // Convert to LEDC duty cycle
    esp_err_t ret = ledc_set_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0, duty);
    if (ret == ESP_OK) {
        ledc_update_duty(LEDC_LOW_SPEED_MODE, LEDC_CHANNEL_0);
        Serial.printf("🔆 Backlight brightness set to %d/255 (%.1f%%)\n", brightness, (brightness/255.0)*100);
    } else {
        Serial.printf("❌ Failed to set backlight brightness: %s\n", esp_err_to_name(ret));
    }
}

void initializeDisplay() {
    Serial.println("=== 7\" Display Initialization ===");

    // TODO: Implement MIPI DSI display initialization
    // This will require ESP32-P4 specific MIPI DSI configuration
    // For now, marking as initialized for PWM backlight control

    display_initialized = true;
    Serial.println("✅ Display initialization placeholder - implement MIPI DSI setup");
}

void initializeLVGL() {
    Serial.println("=== LVGL GUI Initialization ===");

    if (!display_initialized) {
        Serial.println("❌ Cannot initialize LVGL - display not initialized");
        return;
    }

    lv_init();

    // Initialize display buffer
    lv_disp_draw_buf_init(&draw_buf, buf_1, NULL, LVGL_BUFFER_SIZE);

    // Initialize display driver
    lv_disp_drv_init(&disp_drv);
    disp_drv.hor_res = 800;
    disp_drv.ver_res = 480;
    disp_drv.flush_cb = display_flush_cb;
    disp_drv.draw_buf = &draw_buf;
    lv_disp_drv_register(&disp_drv);

    createGUI();

    Serial.println("✅ LVGL GUI initialized with 800x480 resolution");
}

void display_flush_cb(lv_disp_drv_t * disp_drv, const lv_area_t * area, lv_color_t * color_p) {
    // TODO: Implement actual display flushing to MIPI DSI display
    // For now, just mark as ready
    lv_disp_flush_ready(disp_drv);
}

void createGUI() {
    // Create main screen
    main_screen = lv_obj_create(NULL);
    lv_scr_load(main_screen);

    // Set dark theme
    lv_obj_set_style_bg_color(main_screen, lv_color_hex(0x1E1E1E), 0);

    // Title
    lv_obj_t *title = lv_label_create(main_screen);
    lv_label_set_text(title, "ESP32-P4 Time Clock");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_24, 0);
    lv_obj_set_style_text_color(title, lv_color_white(), 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 20);

    // Status label
    status_label = lv_label_create(main_screen);
    lv_label_set_text(status_label, "Initializing...");
    lv_obj_set_style_text_font(status_label, &lv_font_montserrat_16, 0);
    lv_obj_set_style_text_color(status_label, lv_color_hex(0x00FF00), 0);
    lv_obj_align(status_label, LV_ALIGN_TOP_MID, 0, 70);

    // Time display
    time_label = lv_label_create(main_screen);
    lv_label_set_text(time_label, "--:--:--");
    lv_obj_set_style_text_font(time_label, &lv_font_montserrat_32, 0);
    lv_obj_set_style_text_color(time_label, lv_color_white(), 0);
    lv_obj_align(time_label, LV_ALIGN_CENTER, 0, -50);

    // Card reading area
    card_label = lv_label_create(main_screen);
    lv_label_set_text(card_label, "Present your card...");
    lv_obj_set_style_text_font(card_label, &lv_font_montserrat_18, 0);
    lv_obj_set_style_text_color(card_label, lv_color_hex(0x00CCFF), 0);
    lv_obj_align(card_label, LV_ALIGN_CENTER, 0, 50);

    // WiFi status
    wifi_label = lv_label_create(main_screen);
    lv_label_set_text(wifi_label, "WiFi: Disconnected");
    lv_obj_set_style_text_font(wifi_label, &lv_font_montserrat_12, 0);
    lv_obj_set_style_text_color(wifi_label, lv_color_hex(0xFF6600), 0);
    lv_obj_align(wifi_label, LV_ALIGN_BOTTOM_LEFT, 20, -20);
}

void updateDisplayStatus() {
    if (!display_initialized) return;

    // Update status
    lv_label_set_text(status_label, getStatusText().c_str());

    // Update time
    if (WiFi.status() == WL_CONNECTED) {
        timeClient.update();
        String timeStr = timeClient.getFormattedTime();
        lv_label_set_text(time_label, timeStr.c_str());
    }

    // Update WiFi status
    String wifiStatus = "WiFi: ";
    if (WiFi.status() == WL_CONNECTED) {
        wifiStatus += "Connected (" + WiFi.localIP().toString() + ")";
        lv_obj_set_style_text_color(wifi_label, lv_color_hex(0x00FF00), 0);
    } else {
        wifiStatus += "Disconnected";
        lv_obj_set_style_text_color(wifi_label, lv_color_hex(0xFF0000), 0);
    }
    lv_label_set_text(wifi_label, wifiStatus.c_str());

    // Update card reading status
    if (nfcInitialized && config.isRegistered) {
        lv_label_set_text(card_label, "Ready - Present your card");
        lv_obj_set_style_text_color(card_label, lv_color_hex(0x00FF00), 0);
    } else if (!nfcInitialized) {
        lv_label_set_text(card_label, "NFC Reader Error");
        lv_obj_set_style_text_color(card_label, lv_color_hex(0xFF0000), 0);
    } else {
        lv_label_set_text(card_label, "Device not registered");
        lv_obj_set_style_text_color(card_label, lv_color_hex(0xFF6600), 0);
    }
}

void initializePN5180() {
    Serial.println("=== PN5180 NFC Reader Initialization ===");

    // Hardware reset sequence for PN5180
    Serial.println("🔧 Performing PN5180 hardware reset...");
    digitalWrite(PN5180_REQ, LOW);   // Disable RF field during reset
    digitalWrite(PN5180_RST, LOW);   // Assert reset
    delay(10);                       // Hold reset for 10ms
    digitalWrite(PN5180_RST, HIGH);  // Release reset
    delay(100);                      // Wait for PN5180 to boot

    // Check GPIO pin states before initialization
    Serial.println("📋 Pre-initialization pin status:");
    Serial.printf("   NSS (GPIO %d): %s\n", PN5180_NSS, digitalRead(PN5180_NSS) ? "HIGH" : "LOW");
    Serial.printf("   RST (GPIO %d): %s\n", PN5180_RST, digitalRead(PN5180_RST) ? "HIGH" : "LOW");
    Serial.printf("   REQ (GPIO %d): %s\n", PN5180_REQ, digitalRead(PN5180_REQ) ? "HIGH" : "LOW");
    Serial.printf("   BUSY (GPIO %d): %s\n", PN5180_BUSY, digitalRead(PN5180_BUSY) ? "HIGH" : "LOW");

    // Initialize SPI for PN5180 using available GPIO pins
    Serial.println("🔧 Initializing SPI for PN5180...");
    SPI.begin(PN5180_SCK, PN5180_MISO, PN5180_MOSI, PN5180_NSS); // SCK, MISO, MOSI, SS
    SPI.setFrequency(2000000); // 2 MHz for PN5180
    delay(100);
    Serial.printf("✅ SPI initialized: SCK=%d, MISO=%d, MOSI=%d, NSS=%d\n",
                  PN5180_SCK, PN5180_MISO, PN5180_MOSI, PN5180_NSS);

    // Initialize PN5180 for ISO15693 (RFID)
    Serial.println("🔧 Initializing PN5180 for ISO15693...");
    nfc15693.begin();
    delay(200);
    Serial.println("✅ PN5180 ISO15693 initialization called");

    // Initialize PN5180 for ISO14443 (NFC)
    Serial.println("🔧 Initializing PN5180 for ISO14443...");
    nfc14443.begin();
    delay(200);
    Serial.println("✅ PN5180 ISO14443 initialization called");

    // Test communication
    Serial.println("🔍 Testing PN5180 communication...");
    bool communicationSuccess = false;

    // Try to read product version
    uint8_t productVersion[2];
    if (nfc15693.readEEprom(PRODUCT_VERSION, productVersion, sizeof(productVersion))) {
        uint16_t version = (productVersion[1] << 8) | productVersion[0];
        Serial.printf("✅ PN5180 Product Version: 0x%04X\n", version);

        if (version == 0x0302) { // PN5180 expected version
            communicationSuccess = true;
            nfcStatusMessage = "PN5180 Ready (v" + String(version, HEX) + ")";
        } else {
            Serial.printf("⚠️  Unexpected product version: 0x%04X (expected 0x0302)\n", version);
            nfcStatusMessage = "PN5180 Unknown Version (0x" + String(version, HEX) + ")";
        }
    } else {
        Serial.println("❌ Failed to read PN5180 product version");
        nfcStatusMessage = "PN5180 Communication Failed";
    }

    if (communicationSuccess) {
        Serial.println("✅ PN5180 initialized successfully!");
        nfcInitialized = true;

        // Enable RF field and configure for card reading
        Serial.println("🔧 Configuring PN5180 RF fields...");

        // Enable REQ pin to activate RF field
        digitalWrite(PN5180_REQ, HIGH);
        delay(50); // Allow RF field to stabilize
        Serial.println("   ✅ REQ pin enabled - RF field active");

        // Setup for ISO15693 (RFID cards)
        if (nfc15693.setupRF()) {
            Serial.println("   ✅ ISO15693 RF field configured");
        } else {
            Serial.println("   ❌ ISO15693 RF field setup failed");
        }

        Serial.println("✅ PN5180 ready for card reading!");

    } else {
        Serial.println("❌ PN5180 communication failed!");
        Serial.println("📋 Troubleshooting checklist:");
        Serial.println("   1. Check wiring connections:");
        Serial.println("      3.3V → 3.3V (NOT 5V!), GND → GND");
        Serial.printf("      MOSI → GPIO%d, MISO → GPIO%d\n", PN5180_MOSI, PN5180_MISO);
        Serial.printf("      SCK → GPIO%d, NSS → GPIO%d\n", PN5180_SCK, PN5180_NSS);
        Serial.printf("      RST → GPIO%d, REQ → GPIO%d\n", PN5180_RST, PN5180_REQ);
        Serial.printf("      BUSY → GPIO%d, IRQ → GPIO%d (optional)\n", PN5180_BUSY, PN5180_IRQ);
        Serial.println("   2. ⚠️  CRITICAL: Ensure 3.3V power, NOT 5V (will damage module)");
        Serial.println("   3. Check power supply (3.3V, adequate current)");
        Serial.println("   4. Check SPI bus conflicts");
        Serial.println("   5. Hardware failure");

        nfcInitialized = false;
    }

    Serial.println("=== PN5180 Initialization Complete ===");
}

// RF Field Management Functions
void enableRFField() {
    digitalWrite(PN5180_REQ, HIGH);
    delay(10); // Allow field to stabilize
    Serial.println("🔵 RF field enabled");
}

void disableRFField() {
    digitalWrite(PN5180_REQ, LOW);
    delay(5); // Allow field to shut down
    Serial.println("🔴 RF field disabled");
}

bool isRFFieldEnabled() {
    return digitalRead(PN5180_REQ) == HIGH;
}

void initializeHardware() {
    // Configure GPIO pins
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_BLUE_PIN, OUTPUT);
    pinMode(BUZZER_PIN, OUTPUT);

    // Configure PN5180 control pins
    pinMode(PN5180_NSS, OUTPUT);
    pinMode(PN5180_RST, OUTPUT);
    pinMode(PN5180_REQ, OUTPUT);
    pinMode(PN5180_BUSY, INPUT);
    pinMode(PN5180_IRQ, INPUT);

    // Set initial states
    digitalWrite(PN5180_NSS, HIGH);  // Deselect SPI
    digitalWrite(PN5180_RST, HIGH);  // Not in reset
    digitalWrite(PN5180_REQ, LOW);   // RF field initially off

    // Turn off all LEDs initially
    setStatusLED(0, 0, 0);

    // Initialize SPIFFS
    if (!SPIFFS.begin(true)) {
        Serial.println("❌ SPIFFS initialization failed");
    } else {
        Serial.println("✅ SPIFFS initialized");
    }
}

void handleCardReading() {
    // Auto-recovery: Check PN5180 connection every 60 seconds
    static unsigned long lastConnectionCheck = 0;
    if (millis() - lastConnectionCheck > 60000) { // Every 60 seconds
        Serial.println("🔧 === PN5180 CONNECTION CHECK ===");

        uint8_t productVersion[2];
        if (!nfc15693.readEEprom(PRODUCT_VERSION, productVersion, sizeof(productVersion))) {
            Serial.println("⚠️  PN5180 connection lost! Attempting to recover...");

            // Mark as uninitialized and attempt reinit
            nfcInitialized = false;
            nfcStatusMessage = "Connection Lost - Recovering...";

            // Attempt to reinitialize
            delay(500);
            initializePN5180();

            if (nfcInitialized) {
                Serial.println("✅ PN5180 connection recovered successfully!");
            } else {
                Serial.println("❌ PN5180 recovery failed - check physical connections");
            }
        } else {
            uint16_t version = (productVersion[1] << 8) | productVersion[0];
            Serial.printf("✅ PN5180 connection healthy (version: 0x%04X)\n", version);
        }
        lastConnectionCheck = millis();
    }

    // Debug card scanning status periodically
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
            Serial.println("   💡 TIP: Check physical wire connections to PN5180");
            lastNfcWarning = millis();
        }
        // Disable RF field when NFC not working to save power
        if (isRFFieldEnabled()) {
            disableRFField();
        }
        return; // Don't try to read if NFC isn't working
    }

    // Ensure RF field is enabled for card reading
    if (!isRFFieldEnabled()) {
        enableRFField();
        delay(20); // Allow RF field to fully stabilize before reading
    }

    // Try to read ISO15693 cards (RFID)
    uint8_t uid[8];
    if (nfc15693.getInventory(uid)) {
        handleCardDetected(uid, 8, "ISO15693");
        return;
    }

    // Try to read ISO14443 cards (NFC)
    uint8_t atqa[2];
    uint8_t sak[1];
    uint8_t uid14443[10];
    uint8_t uidLength;

    if (nfc14443.readCardSerial(atqa, sak, uid14443, &uidLength)) {
        handleCardDetected(uid14443, uidLength, "ISO14443");
        return;
    }

    // Power saving: Disable RF field during idle periods (no cards for 30 seconds)
    static unsigned long lastCardAttempt = 0;
    lastCardAttempt = millis();
    static unsigned long lastRFDisable = 0;

    if (millis() - lastCardRead > 30000 && millis() - lastRFDisable > 30000) { // 30 seconds idle
        if (isRFFieldEnabled()) {
            Serial.println("💤 Disabling RF field for power saving (no cards for 30s)");
            disableRFField();
            lastRFDisable = millis();
        }
    }
}

void handleCardDetected(uint8_t* uid, uint8_t uidLength, String cardType) {
    // Convert UID to hex string
    String cardUID = "";
    for (int i = 0; i < uidLength; i++) {
        if (uid[i] < 0x10) cardUID += "0";
        cardUID += String(uid[i], HEX);
    }
    cardUID.toUpperCase();

    // Check for duplicate reads (debounce)
    if (cardUID == lastCardUID && (millis() - lastCardRead) < 2000) {
        return; // Same card within 2 seconds, ignore
    }

    lastCardUID = cardUID;
    lastCardRead = millis();
    cardReadCount++;

    Serial.println("🎫 === CARD DETECTED ===");
    Serial.println("   Type: " + cardType);
    Serial.println("   UID: " + cardUID);
    Serial.println("   Length: " + String(uidLength) + " bytes");
    Serial.println("   Total cards read: " + String(cardReadCount));

    // Update display
    lv_label_set_text(card_label, ("Card: " + cardUID).c_str());
    lv_obj_set_style_text_color(card_label, lv_color_hex(0xFFFF00), 0);

    // Visual/audio feedback
    setStatusLED(255, 255, 0); // Yellow flash
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    setStatusLED(0, 255, 0); // Back to green

    // Send to server if registered and connected
    if (currentStatus == STATUS_APPROVED && WiFi.status() == WL_CONNECTED) {
        sendPunchData(cardUID, cardType);
    } else {
        Serial.println("⚠️  Card read but not sent - device not approved or no WiFi");
    }
}

void sendPunchData(String cardUID, String cardType) {
    Serial.println("📤 Sending punch data to server...");

    HTTPClient http;
    http.begin(config.serverHost + ":" + String(config.serverPort) + "/api/devices/clock-event");
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + config.apiToken);

    // Create punch data JSON
    DynamicJsonDocument punchData(1024);
    punchData["device_id"] = config.deviceId;
    punchData["credential_kind"] = (cardType == "ISO14443") ? "nfc" : "rfid";
    punchData["credential_value"] = cardUID;
    punchData["event_time"] = getISODateTime();
    punchData["event_type"] = "unknown";  // Let server determine
    punchData["confidence"] = 100;  // High confidence for PN5180

    // Send device timezone as numeric offset
    int timezoneOffset = config.timezone.length() > 0 ? getTimezoneOffset(config.timezone) : -5;
    punchData["timezone_offset"] = timezoneOffset;

    String jsonString;
    serializeJson(punchData, jsonString);

    Serial.println("📋 Punch Data: " + jsonString);

    int httpResponseCode = http.POST(jsonString);

    if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.println("📥 Server Response (" + String(httpResponseCode) + "): " + response);

        if (httpResponseCode == 200 || httpResponseCode == 201) {
            Serial.println("✅ Punch data sent successfully!");
            setStatusLED(0, 255, 0); // Green success
        } else {
            Serial.println("⚠️  Punch data sent but server returned error");
            setStatusLED(255, 165, 0); // Orange warning
        }
    } else {
        Serial.println("❌ Failed to send punch data: " + String(httpResponseCode));
        setStatusLED(255, 0, 0); // Red error
    }

    http.end();
}

// ... [Include all other necessary functions from the original firmware like:]
// - WiFi connection functions
// - Web server setup
// - Configuration management
// - Status functions
// - Utility functions

void setStatusLED(int red, int green, int blue) {
    analogWrite(LED_RED_PIN, red);
    analogWrite(LED_GREEN_PIN, green);
    analogWrite(LED_BLUE_PIN, blue);
}

String getStatusText() {
    switch (currentStatus) {
        case STATUS_NOT_CONFIGURED: return "Not Configured";
        case STATUS_CONFIGURED: return "Configured";
        case STATUS_REGISTERED: return "Registered";
        case STATUS_APPROVED: return "Approved & Ready";
        case STATUS_ERROR: return "Error: " + lastError;
        default: return "Unknown";
    }
}

String getISODateTime() {
    if (WiFi.status() != WL_CONNECTED) {
        return "1970-01-01T00:00:00";
    }

    timeClient.update();
    time_t rawTime = timeClient.getEpochTime();
    struct tm* timeInfo = localtime(&rawTime);

    char isoBuffer[32];
    snprintf(isoBuffer, sizeof(isoBuffer),
             "%04d-%02d-%02dT%02d:%02d:%02d",
             timeInfo->tm_year + 1900,
             timeInfo->tm_mon + 1,
             timeInfo->tm_mday,
             timeInfo->tm_hour,
             timeInfo->tm_min,
             timeInfo->tm_sec);

    return String(isoBuffer);
}

int getTimezoneOffset(String timezone) {
    // Simple timezone offset mapping
    if (timezone == "EST" || timezone == "America/New_York") return -5;
    if (timezone == "CST" || timezone == "America/Chicago") return -6;
    if (timezone == "MST" || timezone == "America/Denver") return -7;
    if (timezone == "PST" || timezone == "America/Los_Angeles") return -8;
    return -5; // Default EST
}

// Placeholder functions - implement based on original firmware
void loadConfiguration() { /* TODO: Implement */ }
void saveConfiguration() { /* TODO: Implement */ }
void connectToWiFi() { /* TODO: Implement */ }
void startAccessPoint() { /* TODO: Implement */ }
void initializeNTP() { /* TODO: Implement */ }
void initializeWebServer() { /* TODO: Implement */ }
void pollConfigurationUpdates() { /* TODO: Implement */ }
void sendHeartbeat() { /* TODO: Implement */ }