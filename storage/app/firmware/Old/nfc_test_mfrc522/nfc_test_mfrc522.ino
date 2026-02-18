/*
 * MFRC522 RFID Reader Test
 *
 * This is specifically for the RFID-RC522 module (MFRC522 chip)
 * Uses SPI communication with enhanced diagnostics
 *
 * WIRING FOR MFRC522:
 * MFRC522 → ESP32
 * VCC → 3.3V (IMPORTANT: NOT 5V!)
 * GND → GND
 * SCK → GPIO 18
 * MISO → GPIO 19
 * MOSI → GPIO 23
 * SDA/SS → GPIO 21 (using safer pin)
 * RST → GPIO 22 (using safer pin)
 */

#include <SPI.h>
#include <MFRC522.h>

// Pin Configuration - Using safer pins for MFRC522
#define SS_PIN 21    // SDA/SS pin
#define RST_PIN 22   // RST pin

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
    Serial.begin(115200);
    delay(2000);

    Serial.println("=== ESP32 MFRC522 RFID Test ===");
    Serial.println("Using SPI communication:");
    Serial.printf("  SS/SDA → GPIO %d\n", SS_PIN);
    Serial.printf("  RST → GPIO %d\n", RST_PIN);
    Serial.println("  SCK → GPIO 18");
    Serial.println("  MISO → GPIO 19");
    Serial.println("  MOSI → GPIO 23");
    Serial.println("  VCC → 3.3V");
    Serial.println("  GND → GND");
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
    SPI.setClockDivider(SPI_CLOCK_DIV4); // Conservative clock speed
    delay(100);
    Serial.println("✅ SPI initialized");

    // Test 3: Initialize MFRC522
    Serial.println("\n🔧 Initializing MFRC522...");
    mfrc522.PCD_Init();
    delay(200);
    Serial.println("✅ MFRC522 initialization called");

    // Test 4: Multiple communication attempts with detailed diagnostics
    Serial.println("\n🔍 Testing MFRC522 communication...");
    bool success = false;
    byte version = 0xFF;

    for (int attempt = 1; attempt <= 5; attempt++) {
        Serial.printf("Attempt %d/5: ", attempt);

        // Try to read version register
        version = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
        Serial.printf("Version = 0x%02X ", version);

        // Check for valid MFRC522 versions
        if (version == 0x91) {
            Serial.println("✅ SUCCESS! MFRC522 v1.0 detected");
            success = true;
            break;
        } else if (version == 0x92) {
            Serial.println("✅ SUCCESS! MFRC522 v2.0 detected");
            success = true;
            break;
        } else if (version == 0x88) {
            Serial.println("✅ SUCCESS! MFRC522 clone detected");
            success = true;
            break;
        } else if (version == 0x00) {
            Serial.println("❌ No response (0x00) - check wiring");
        } else if (version == 0xFF) {
            Serial.println("❌ No communication (0xFF) - check power/pins");
        } else {
            Serial.printf("⚠️  Unknown version (0x%02X) - might still work\n", version);
            // Some clones return different version numbers but still work
            success = true;
            break;
        }

        delay(500);
    }

    // Test 5: Results and self-test
    Serial.println("\n=== TEST RESULTS ===");
    if (success) {
        Serial.println("🎉 SUCCESS: MFRC522 is communicating!");
        Serial.printf("   Chip Version: 0x%02X\n", version);

        // Run self-test
        Serial.println("\n🧪 Running MFRC522 self-test...");
        bool selfTestResult = mfrc522.PCD_PerformSelfTest();

        if (selfTestResult) {
            Serial.println("✅ Self-test PASSED - MFRC522 is fully functional");
        } else {
            Serial.println("⚠️  Self-test FAILED - but may still work for basic operations");
        }

        // Re-initialize after self-test
        mfrc522.PCD_Init();

        // Configure for card detection
        Serial.println("\n🔧 Configuring for card detection...");

        Serial.println("✅ MFRC522 ready for card reading!");
        Serial.println("\n🏷️  Place an RFID card near the reader...");

    } else {
        Serial.println("❌ FAILURE: Cannot communicate with MFRC522");
        Serial.println("\n🔧 Troubleshooting steps:");
        Serial.println("1. Power Supply:");
        Serial.println("   - VCC MUST be 3.3V (NOT 5V!)");
        Serial.println("   - Check voltage with multimeter");
        Serial.println("   - Ensure GND connection is solid");

        Serial.println("2. Wiring Check:");
        Serial.printf("   - SS/SDA → GPIO %d\n", SS_PIN);
        Serial.printf("   - RST → GPIO %d\n", RST_PIN);
        Serial.println("   - SCK → GPIO 18");
        Serial.println("   - MISO → GPIO 19");
        Serial.println("   - MOSI → GPIO 23");

        Serial.println("3. Hardware Issues:");
        Serial.println("   - Try shorter jumper wires");
        Serial.println("   - Check for loose breadboard connections");
        Serial.println("   - Test with different MFRC522 module");

        Serial.println("4. Alternative Pins:");
        Serial.println("   - Try SS=5, RST=2 (original pins)");
        Serial.println("   - Try SS=15, RST=4");

        while (1) {
            delay(1000); // Stop here if initialization failed
        }
    }

    Serial.println("\n=== Initialization Complete ===");
}

void loop() {
    // Look for new cards
    if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {

        Serial.println("\n🎉 CARD DETECTED!");
        Serial.print("   Card UID: ");

        // Print UID in hex format
        String uidString = "";
        for (byte i = 0; i < mfrc522.uid.size; i++) {
            Serial.printf("%02X", mfrc522.uid.uidByte[i]);
            uidString += String(mfrc522.uid.uidByte[i], HEX);
            if (i < mfrc522.uid.size - 1) {
                Serial.print(":");
                uidString += ":";
            }
        }
        Serial.println();

        Serial.printf("   UID Length: %d bytes\n", mfrc522.uid.size);
        Serial.printf("   Card Type: ");

        // Identify card type
        MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
        Serial.println(mfrc522.PICC_GetTypeName(piccType));

        Serial.println("   Remove card and try another...\n");

        // Halt the card to prevent repeated reads
        mfrc522.PICC_HaltA();
        mfrc522.PCD_StopCrypto1();

        // Brief delay before next read
        delay(1000);
    }

    delay(100);
}

// Optional: Function to dump card details (uncomment if you want more info)
/*
void dumpCardInfo() {
    Serial.println("Card details:");
    mfrc522.PICC_DumpToSerial(&(mfrc522.uid));
}
*/