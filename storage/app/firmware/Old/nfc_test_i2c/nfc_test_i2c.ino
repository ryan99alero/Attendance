/*
 * PN532 NFC Reader Test - I2C Mode
 *
 * This uses I2C communication instead of SPI, which is often more reliable.
 *
 * WIRING FOR I2C MODE:
 * PN532 Module → ESP32
 * VCC → 3.3V
 * GND → GND
 * SDA → GPIO 21 (default I2C SDA)
 * SCL → GPIO 22 (default I2C SCL)
 *
 * Note: Make sure your PN532 module is configured for I2C mode
 * (check jumpers or switches on the module)
 */

#include <Wire.h>
#include <PN532_I2C.h>
#include <PN532.h>
#include <NfcAdapter.h>

// I2C Configuration
PN532_I2C pn532_i2c(Wire);
PN532 nfc(pn532_i2c);

void setup() {
    Serial.begin(115200);
    delay(2000);

    Serial.println("=== ESP32 PN532 I2C Test ===");
    Serial.println("Using I2C communication:");
    Serial.println("  SDA → GPIO 21");
    Serial.println("  SCL → GPIO 22");
    Serial.println("  VCC → 3.3V");
    Serial.println("  GND → GND");
    Serial.println();

    // Initialize I2C
    Serial.println("🔧 Initializing I2C...");
    Wire.begin();
    Wire.setClock(100000); // 100kHz for stability
    Serial.println("✅ I2C initialized");

    // Test I2C scanner first
    Serial.println("\n🔍 Scanning I2C bus...");
    scanI2C();

    // Initialize PN532
    Serial.println("\n🔧 Initializing PN532...");
    nfc.begin();

    // Check PN532 firmware version
    Serial.println("🔍 Testing PN532 communication...");
    uint32_t versiondata = nfc.getFirmwareVersion();

    if (!versiondata) {
        Serial.println("❌ FAILURE: No PN532 found on I2C bus");
        Serial.println("\n🔧 Troubleshooting:");
        Serial.println("1. Check module is in I2C mode (not SPI/UART)");
        Serial.println("2. Verify wiring: SDA=21, SCL=22, VCC=3.3V, GND=GND");
        Serial.println("3. Check jumpers on PN532 module");
        Serial.println("4. Try different I2C address (default 0x24)");
        Serial.println("5. Use multimeter to verify connections");

        while (1) {
            delay(1000);
        }
    }

    Serial.println("✅ SUCCESS: PN532 found!");
    Serial.print("   Firmware Version: ");
    Serial.print((versiondata >> 24) & 0xFF, DEC);
    Serial.print(".");
    Serial.println((versiondata >> 16) & 0xFF, DEC);

    Serial.print("   Chip: PN5");
    Serial.println((versiondata >> 24) & 0xFF, DEC);

    // Configure PN532 to read RFID tags
    Serial.println("\n🔧 Configuring for RFID reading...");
    nfc.setPassiveActivationRetries(0xFF);
    nfc.SAMConfig();

    Serial.println("✅ PN532 configured successfully!");
    Serial.println("\n🏷️  Ready to read cards - place a card near the reader");
    Serial.println("=== Test Complete - Starting Card Reading ===\n");
}

void loop() {
    boolean success;
    uint8_t uid[] = { 0, 0, 0, 0, 0, 0, 0 };
    uint8_t uidLength;

    // Wait for a card
    success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, &uid[0], &uidLength);

    if (success) {
        Serial.println("🎉 Card detected!");
        Serial.print("   UID: ");
        for (uint8_t i = 0; i < uidLength; i++) {
            Serial.printf("%02X", uid[i]);
        }
        Serial.println();
        Serial.printf("   Length: %d bytes\n", uidLength);

        // Wait for card to be removed
        Serial.println("   Remove card to scan another...\n");
        delay(2000);
    } else {
        // No card detected, just a short delay
        delay(100);
    }
}

void scanI2C() {
    int deviceCount = 0;

    for (int address = 1; address < 127; address++) {
        Wire.beginTransmission(address);
        int error = Wire.endTransmission();

        if (error == 0) {
            Serial.printf("   I2C device found at 0x%02X\n", address);
            deviceCount++;

            // Check if it's likely a PN532 (common addresses: 0x24, 0x48)
            if (address == 0x24) {
                Serial.println("     ^ This looks like a PN532!");
            }
        }
    }

    if (deviceCount == 0) {
        Serial.println("   No I2C devices found");
        Serial.println("   ⚠️  Check wiring and power supply");
    } else {
        Serial.printf("   Found %d device(s)\n", deviceCount);
    }
}