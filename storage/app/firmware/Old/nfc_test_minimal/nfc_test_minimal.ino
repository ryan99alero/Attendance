/*
 * Minimal NFC Reader Test
 *
 * This is a minimal test to isolate NFC communication issues.
 * Use this to test different pin configurations quickly.
 *
 * Upload this, open Serial Monitor, and check the output.
 * Try different SS_PIN and RST_PIN values if communication fails.
 */

#include <SPI.h>
#include <MFRC522.h>

// Pin Configuration - UPDATED TO MATCH YOUR CURRENT WIRING
#define SS_PIN 15    // Alternative SS (was 5)
#define RST_PIN 4    // Alternative RST (was 2)

// ESP32 Hardware SPI Pins (these are fixed):
// SCK = GPIO 18
// MISO = GPIO 19
// MOSI = GPIO 23

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
    Serial.begin(115200);
    delay(2000);

    Serial.println("=== ESP32 NFC Reader Minimal Test ===");
    Serial.printf("SS Pin: GPIO %d\n", SS_PIN);
    Serial.printf("RST Pin: GPIO %d\n", RST_PIN);
    Serial.println("SPI Pins: SCK=18, MISO=19, MOSI=23");
    Serial.println();

    // Test 1: Check pin states
    Serial.println("📋 Initial pin states:");
    pinMode(SS_PIN, INPUT);
    pinMode(RST_PIN, INPUT);
    Serial.printf("  SS (GPIO %d): %s\n", SS_PIN, digitalRead(SS_PIN) ? "HIGH" : "LOW");
    Serial.printf("  RST (GPIO %d): %s\n", RST_PIN, digitalRead(RST_PIN) ? "HIGH" : "LOW");

    // Test 2: Initialize SPI
    Serial.println("\n🔧 Initializing SPI...");
    SPI.begin();
    SPI.setDataMode(SPI_MODE0);
    SPI.setBitOrder(MSBFIRST);
    SPI.setClockDivider(SPI_CLOCK_DIV8); // Very slow for testing
    delay(100);
    Serial.println("✅ SPI initialized");

    // Test 3: Initialize MFRC522
    Serial.println("\n🔧 Initializing MFRC522...");
    mfrc522.PCD_Init();
    delay(100);
    Serial.println("✅ MFRC522 initialization called");

    // Test 4: Multiple communication attempts
    Serial.println("\n🔍 Testing communication...");
    bool success = false;

    for (int attempt = 1; attempt <= 5; attempt++) {
        Serial.printf("Attempt %d/5: ", attempt);

        byte version = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
        Serial.printf("Version = 0x%02X ", version);

        if (version == 0x91 || version == 0x92) {
            Serial.println("✅ SUCCESS! MFRC522 detected");
            success = true;
            break;
        } else if (version == 0x00) {
            Serial.println("❌ No response (0x00)");
        } else if (version == 0xFF) {
            Serial.println("❌ No communication (0xFF)");
        } else {
            Serial.printf("⚠️  Unknown version (0x%02X)\n", version);
        }

        delay(500);
    }

    // Test 5: Results and recommendations
    Serial.println("\n=== TEST RESULTS ===");
    if (success) {
        Serial.println("🎉 SUCCESS: NFC Reader is working!");
        Serial.println("You can now use the full firmware.");

        // Test self-test function
        Serial.println("\n🧪 Running self-test...");
        bool selfTest = mfrc522.PCD_PerformSelfTest();
        Serial.println(selfTest ? "✅ Self-test PASSED" : "❌ Self-test FAILED");

        // Re-init after self-test
        mfrc522.PCD_Init();

    } else {
        Serial.println("❌ FAILURE: Cannot communicate with NFC Reader");
        Serial.println("\n🔧 Try these solutions:");
        Serial.println("1. Check power: VCC must be 3.3V (NOT 5V!)");
        Serial.println("2. Check all connections with multimeter");
        Serial.println("3. Try different pins:");
        Serial.println("   SS_PIN: 15, 21, 22 (change in code)");
        Serial.println("   RST_PIN: 4, 16, 17 (change in code)");
        Serial.println("4. Use shorter jumper wires");
        Serial.println("5. Try a different PN532 module");
        Serial.println("6. Check module for SPI mode (not I2C/UART)");
    }

    Serial.println("\n=== Test Complete ===");
}

void loop() {
    if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
        Serial.print("🏷️  Card detected: ");
        for (byte i = 0; i < mfrc522.uid.size; i++) {
            Serial.printf("%02X", mfrc522.uid.uidByte[i]);
        }
        Serial.println();

        // Halt the card
        mfrc522.PICC_HaltA();
    }

    delay(100);
}