/*
 * ESP32-P4 Test - Adafruit PN532 Library UART Mode
 *
 * This will test if the Adafruit library works with UART
 * Install library: "Adafruit PN532" in Arduino Library Manager
 */

#include <Wire.h>
#include <Adafruit_PN532.h>

// PN532 UART pins
#define PN532_TX_PIN    48  // ESP32 TX -> PN532 RX
#define PN532_RX_PIN    47  // ESP32 RX -> PN532 TX
#define PN532_RST_PIN   46

// Use Serial2 for PN532
HardwareSerial pn532Serial(2);

// Create PN532 instance for UART
Adafruit_PN532 nfc(PN532_RST_PIN, &pn532Serial);

void setup() {
    Serial.begin(115200);
    delay(2000);

    printf("\n========================================\n");
    printf("ESP32-P4 Adafruit PN532 UART Test\n");
    printf("========================================\n\n");

    printf("IMPORTANT: Set PN532 switches:\n");
    printf("  SEL0 = 0\n");
    printf("  SEL1 = 0\n");
    printf("  (HSU/UART mode)\n\n");

    // Initialize UART
    printf("[PN532] Initializing UART...\n");
    pn532Serial.begin(115200, SERIAL_8N1, PN532_RX_PIN, PN532_TX_PIN);
    printf("[PN532] UART on TX=%d, RX=%d\n", PN532_TX_PIN, PN532_RX_PIN);

    // Initialize PN532
    printf("[PN532] Calling nfc.begin()...\n");
    nfc.begin();

    uint32_t versiondata = nfc.getFirmwareVersion();
    if (!versiondata) {
        printf("❌ Didn't find PN53x board\n");
        while (1);
    }

    // Got version!
    printf("✅ Found chip PN5%02X\n", (versiondata >> 24) & 0xFF);
    printf("Firmware ver. %d.%d\n", (versiondata >> 16) & 0xFF, (versiondata >> 8) & 0xFF);

    // Configure to read RFID tags
    nfc.SAMConfig();

    printf("\n✅ SUCCESS! Adafruit library works!\n");
    printf("Waiting for NFC card...\n\n");
}

void loop() {
    uint8_t success;
    uint8_t uid[] = { 0, 0, 0, 0, 0, 0, 0 };
    uint8_t uidLength;

    success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 100);

    if (success) {
        printf("\n🎉 Card detected!\n");
        printf("UID Length: %d bytes\n", uidLength);
        printf("UID:");
        for (uint8_t i = 0; i < uidLength; i++) {
            printf(" %02X", uid[i]);
        }
        printf("\n\n");
        delay(1000);
    }

    delay(100);
}
