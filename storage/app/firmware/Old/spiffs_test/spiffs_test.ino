/*
 * SPIFFS Test and Format Tool
 * This sketch will test SPIFFS mounting and format it if needed
 */

#include "SPIFFS.h"

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("=== SPIFFS Test and Format Tool ===");
    Serial.println();

    // Test SPIFFS mounting
    Serial.println("1. Testing SPIFFS mount...");

    if (!SPIFFS.begin()) {
        Serial.println("SPIFFS mount failed!");
        Serial.println();

        Serial.println("2. Attempting to format SPIFFS...");
        if (SPIFFS.format()) {
            Serial.println("SPIFFS format successful!");

            Serial.println("3. Trying to mount again...");
            if (SPIFFS.begin()) {
                Serial.println("SPIFFS mount successful after format!");
            } else {
                Serial.println("SPIFFS mount still failed after format!");
                return;
            }
        } else {
            Serial.println("SPIFFS format failed!");
            return;
        }
    } else {
        Serial.println("SPIFFS mount successful!");
    }

    // Show SPIFFS info
    Serial.println();
    Serial.println("=== SPIFFS Information ===");
    Serial.printf("Total bytes: %u\n", SPIFFS.totalBytes());
    Serial.printf("Used bytes: %u\n", SPIFFS.usedBytes());
    Serial.printf("Free bytes: %u\n", SPIFFS.totalBytes() - SPIFFS.usedBytes());

    // List all files
    Serial.println();
    Serial.println("=== Files on SPIFFS ===");
    listFiles();

    // Test file creation
    Serial.println();
    Serial.println("=== Testing File Creation ===");
    testFileOperations();

    Serial.println();
    Serial.println("=== Test Complete ===");
    Serial.println("You can now upload your main firmware and SPIFFS data.");
}

void loop() {
    // Nothing to do here
    delay(1000);
}

void listFiles() {
    File root = SPIFFS.open("/");
    if (!root) {
        Serial.println("Failed to open root directory");
        return;
    }

    File file = root.openNextFile();
    if (!file) {
        Serial.println("No files found");
    }

    while (file) {
        Serial.printf("File: %s, Size: %u bytes\n", file.name(), file.size());
        file = root.openNextFile();
    }
    root.close();
}

void testFileOperations() {
    // Test writing a file
    Serial.println("Creating test file...");
    File testFile = SPIFFS.open("/test.txt", "w");
    if (testFile) {
        testFile.println("SPIFFS test file");
        testFile.println("Created by test sketch");
        testFile.close();
        Serial.println("Test file created successfully");
    } else {
        Serial.println("Failed to create test file");
        return;
    }

    // Test reading the file
    Serial.println("Reading test file...");
    testFile = SPIFFS.open("/test.txt", "r");
    if (testFile) {
        Serial.println("File contents:");
        while (testFile.available()) {
            Serial.write(testFile.read());
        }
        testFile.close();
    } else {
        Serial.println("Failed to read test file");
    }

    // Delete test file
    Serial.println("Deleting test file...");
    if (SPIFFS.remove("/test.txt")) {
        Serial.println("Test file deleted successfully");
    } else {
        Serial.println("Failed to delete test file");
    }
}