/*
 * ESP32-P4 Test - Adafruit PN532 Library SPI Mode
 *
 * Test if Adafruit library works with SPI
 */

#include <Wire.h>
#include <SPI.h>
#include <Adafruit_PN532.h>

// PN532 SPI pins
#define PN532_SCK       20
#define PN532_MISO      21
#define PN532_MOSI      22
#define PN532_SS        23
#define PN532_RST       32

// Create PN532 instance for SPI
Adafruit_PN532 nfc(PN532_SS, &SPI);

void setup() {
    Serial.begin(115200);
    delay(2000);

    printf("\n========================================\n");
    printf("ESP32-P4 Adafruit PN532 SPI Test\n");
    printf("========================================\n\n");

    printf("IMPORTANT: Set PN532 switches:\n");
    printf("  SEL0 = 1 (ON)\n");
    printf("  SEL1 = 0 (OFF)\n");
    printf("  (SPI mode)\n");
    printf("Use 8-pin header!\n\n");

    // Initialize SPI
    printf("[PN532] Initializing SPI...\n");
    SPI.begin(PN532_SCK, PN532_MISO, PN532_MOSI, PN532_SS);

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

    printf("\n✅ SUCCESS! SPI mode works!\n");
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
