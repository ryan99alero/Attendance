/*
 * ESP32-P4 NFC Reader Only (No WiFi)
 *
 * Simplified firmware for testing NFC functionality without WiFi complications
 * ESP32-P4 + ESP32-C6 dual-chip architecture support
 */

#include <SPI.h>
#include <MFRC522.h>

// NFC Module V3 Pin Definitions (same as before)
#define RFID_SS_PIN     5
#define RFID_RST_PIN    22
#define RFID_IRQ_PIN    47

// SPI pins
#define RFID_MOSI       6
#define RFID_MISO       7
#define RFID_SCK        8

// Status pins
#define LED_RED_PIN     45
#define LED_GREEN_PIN   46
#define LED_BLUE_PIN    48
#define BUZZER_PIN      25

// Global Objects
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);

// Variables
bool nfcInitialized = false;
String lastCardUID = "";
unsigned long cardReadCount = 0;
unsigned long lastCardRead = 0;

void setup() {
    Serial.begin(115200);
    delay(1000); // ESP32-P4 stability delay

    Serial.println("========================================");
    Serial.println("ESP32-P4 NFC Reader Test (No WiFi)");
    Serial.println("========================================");

    // Initialize hardware
    initializeHardware();

    // Initialize NFC
    initializeMFRC522();

    Serial.println("ESP32-P4 NFC Reader initialized successfully");
    setStatusLED(0, 255, 0); // Green - ready
}

void loop() {
    // Simple NFC card reading loop
    if (nfcInitialized) {
        handleCardReading();
    }

    // Status blink
    static unsigned long lastBlink = 0;
    if (millis() - lastBlink > 2000) {
        Serial.printf("📊 Status: NFC=%s, Cards Read=%lu\n",
                     nfcInitialized ? "Ready" : "Error", cardReadCount);
        lastBlink = millis();
    }

    delay(100);
}

void initializeHardware() {
    // Configure GPIO pins
    pinMode(LED_RED_PIN, OUTPUT);
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_BLUE_PIN, OUTPUT);
    pinMode(BUZZER_PIN, OUTPUT);

    // Configure MFRC522 control pins
    pinMode(RFID_SS_PIN, OUTPUT);
    pinMode(RFID_RST_PIN, OUTPUT);
    if (RFID_IRQ_PIN != -1) {
        pinMode(RFID_IRQ_PIN, INPUT);
    }

    // Set initial states
    digitalWrite(RFID_SS_PIN, HIGH);
    digitalWrite(RFID_RST_PIN, HIGH);

    // Turn off all LEDs initially
    setStatusLED(0, 0, 0);
    Serial.println("✅ Hardware initialized");
}

void initializeMFRC522() {
    Serial.println("=== MFRC522 NFC Module V3 Initialization ===");

    // Initialize SPI
    Serial.println("🔧 Initializing SPI...");
    SPI.begin(RFID_SCK, RFID_MISO, RFID_MOSI, RFID_SS_PIN);
    SPI.setFrequency(4000000); // 4 MHz
    delay(100);
    Serial.println("✅ SPI initialized");

    // Initialize MFRC522
    Serial.println("🔧 Initializing MFRC522...");
    rfid.PCD_Init();
    delay(200);

    // Test communication
    Serial.println("🔍 Testing MFRC522 communication...");
    byte version = rfid.PCD_ReadRegister(rfid.VersionReg);
    Serial.printf("Version = 0x%02X ", version);

    if (version == 0x91 || version == 0x92 || version == 0x88) {
        Serial.println("✅ SUCCESS! MFRC522 detected");
        nfcInitialized = true;

        // Self-test
        Serial.println("🧪 Running self-test...");
        bool selfTest = rfid.PCD_PerformSelfTest();
        Serial.println(selfTest ? "✅ Self-test PASSED" : "❌ Self-test FAILED");

        // Re-initialize after self-test
        rfid.PCD_Init();
        Serial.println("✅ MFRC522 ready for card reading!");

    } else {
        Serial.println("❌ MFRC522 communication failed!");
        Serial.println("Check wiring:");
        Serial.printf("  MOSI → GPIO%d, MISO → GPIO%d\n", RFID_MOSI, RFID_MISO);
        Serial.printf("  SCK → GPIO%d, SS → GPIO%d\n", RFID_SCK, RFID_SS_PIN);
        Serial.printf("  RST → GPIO%d, VCC → 3.3V\n", RFID_RST_PIN);
        nfcInitialized = false;
    }
}

void handleCardReading() {
    // Look for new cards
    if (!rfid.PICC_IsNewCardPresent()) {
        return;
    }

    // Read card serial
    if (!rfid.PICC_ReadCardSerial()) {
        return;
    }

    // Convert UID to hex string
    String cardUID = "";
    for (int i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) cardUID += "0";
        cardUID += String(rfid.uid.uidByte[i], HEX);
    }
    cardUID.toUpperCase();

    // Debounce - ignore same card within 2 seconds
    if (cardUID == lastCardUID && (millis() - lastCardRead) < 2000) {
        rfid.PICC_HaltA();
        rfid.PCD_StopCrypto1();
        return;
    }

    lastCardUID = cardUID;
    lastCardRead = millis();
    cardReadCount++;

    // Get card type
    MFRC522::PICC_Type piccType = rfid.PICC_GetType(rfid.uid.sak);
    String cardTypeName = rfid.PICC_GetTypeName(piccType);

    Serial.println("🎫 === CARD DETECTED ===");
    Serial.println("   UID: " + cardUID);
    Serial.println("   Type: " + cardTypeName);
    Serial.println("   Size: " + String(rfid.uid.size) + " bytes");
    Serial.println("   Total: " + String(cardReadCount));

    // Visual/audio feedback
    setStatusLED(255, 255, 0); // Yellow
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    setStatusLED(0, 255, 0); // Back to green

    // Halt card
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
}

void setStatusLED(int red, int green, int blue) {
    analogWrite(LED_RED_PIN, red);
    analogWrite(LED_GREEN_PIN, green);
    analogWrite(LED_BLUE_PIN, blue);
}