/*
 * ESP32-P4 Test Phase 1 - ISOLATED
 *
 * Absolute minimal test - just serial output and basic system info
 * This should run forever without any issues
 */

#include <Arduino.h>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>

unsigned long last_heartbeat = 0;
int loop_count = 0;

void setup() {
    // MINIMAL setup - just serial
    Serial.begin(115200);
    delay(1000); // Give serial time to initialize

    // Test basic serial output first
    Serial.print("BASIC TEST: ");
    Serial.println("Can you see this message?");

    Serial.println();
    Serial.println("=== ESP32-P4 TEST PHASE 1 ===");
    Serial.println("Minimal test - Serial + FreeRTOS only");
    Serial.printf("CPU Frequency: %d MHz\n", getCpuFrequencyMhz());
    Serial.printf("Free Heap: %d bytes\n", ESP.getFreeHeap());
    Serial.printf("Chip Model: %s\n", ESP.getChipModel());
    Serial.printf("Chip Revision: %d\n", ESP.getChipRevision());
    Serial.println("==============================");

    Serial.println("✅ PHASE 1: Basic serial communication working");
    Serial.println("If you see heartbeat messages every 5 seconds, Phase 1 is STABLE");
    Serial.println("Let this run for 2-3 minutes to confirm stability");
}

void loop() {
    // Super simple loop - just heartbeat every 2 seconds
    if (millis() - last_heartbeat > 2000) {
        loop_count++;
        Serial.print("HEARTBEAT #");
        Serial.print(loop_count);
        Serial.print(" - Time: ");
        Serial.print(millis() / 1000);
        Serial.print("s - Heap: ");
        Serial.print(ESP.getFreeHeap());
        Serial.println(" bytes");
        last_heartbeat = millis();
    }

    // Use simple delay for now to avoid FreeRTOS complications
    delay(100);
}