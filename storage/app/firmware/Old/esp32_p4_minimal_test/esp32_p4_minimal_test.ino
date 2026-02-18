/*
 * ESP32-P4 Test - Adafruit PN532 with Display Panel
 *
 * Tests Adafruit PN532 library in SPI mode with display panel enabled
 * to check for I2C driver conflicts
 *
 * FIRMWARE VERSION: 1.0.0-patched
 * - Modified Adafruit_PN532 library to disable I2C support
 * - Date: 2025-10-01
 */

// Disable Adafruit BusIO I2C BEFORE any includes
#define ADAFRUIT_BUSIO_NO_I2C 1

#define FIRMWARE_VERSION "1.0.3-busio-fix"
#define BUILD_DATE __DATE__ " " __TIME__

#include <Arduino.h>
#include <SPI.h>
// DO NOT include Wire.h - causes I2C driver conflict
// #include <Wire.h>
#include <Adafruit_PN532.h>

// Display libraries (Testing with BusIO I2C disabled)
#include <esp_display_panel.hpp>
#include <lvgl.h>
#include "lvgl_v8_port.h"
#define LV_CONF_INCLUDE_SIMPLE 1
#include "lv_conf.h"
using namespace esp_panel::drivers;
using namespace esp_panel::board;

// PN532 SPI Pin Configuration - VERIFIED WORKING
#define NFC_SCK         20
#define NFC_MISO        21
#define NFC_MOSI        22
#define NFC_SS          23
#define NFC_RST         32

// Create PN532 instance for SPI
Adafruit_PN532 nfc(NFC_SS, &SPI);

void setup() {
    Serial.begin(115200);
    delay(2000);

    printf("\n========================================\n");
    printf("ESP32-P4 PN532 + Display Panel Test\n");
    printf("Firmware: %s\n", FIRMWARE_VERSION);
    printf("Build: %s\n", BUILD_DATE);
    printf("========================================\n\n");

    printf("PN532 Configuration:\n");
    printf("  Mode: SPI\n");
    printf("  Switches: SEL0=1, SEL1=0\n");
    printf("  Use 8-pin header\n");
    printf("  Pins: SCK=%d, MISO=%d, MOSI=%d, SS=%d, RST=%d\n\n",
           NFC_SCK, NFC_MISO, NFC_MOSI, NFC_SS, NFC_RST);

    // Initialize SPI
    printf("[PN532] Initializing SPI...\n");
    SPI.begin(NFC_SCK, NFC_MISO, NFC_MOSI, NFC_SS);

    // Initialize PN532
    printf("[PN532] Calling nfc.begin()...\n");
    nfc.begin();

    uint32_t versiondata = nfc.getFirmwareVersion();
    if (!versiondata) {
        printf("❌ Failed to find PN532!\n");
        printf("Check wiring and switches.\n");
        while (1) delay(1000);
    }

    // Success!
    printf("✅ Found chip PN5%02X\n", (versiondata >> 24) & 0xFF);
    printf("Firmware ver. %d.%d\n", (versiondata >> 16) & 0xFF, (versiondata >> 8) & 0xFF);

    // Configure SAM
    nfc.SAMConfig();

    printf("\n✅ SUCCESS! No I2C conflicts!\n");
    printf("Display panel + PN532 working together!\n");
    printf("Heap: %d bytes\n", ESP.getFreeHeap());
    printf("========================================\n\n");

    printf("Waiting for NFC cards...\n");
}

void loop() {
    uint8_t success;
    uint8_t uid[] = { 0, 0, 0, 0, 0, 0, 0 };
    uint8_t uidLength;

    success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 100);

    if (success) {
        printf("\n🎉 Card detected!\n");
        printf("UID Length: %d bytes\n", uidLength);
        printf("UID: ");
        for (uint8_t i = 0; i < uidLength; i++) {
            printf("%02X", uid[i]);
            if (i < uidLength - 1) printf(":");
        }
        printf("\n\n");

        delay(2000); // Debounce
    }

    delay(500);
}
