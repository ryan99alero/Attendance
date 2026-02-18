/*
 * ESP32-P4 Test Phase 2 - ISOLATED
 *
 * Add basic board initialization (NO LVGL)
 * Tests if ESP32_Display_Panel library causes issues
 */

#include <Arduino.h>
#include <esp_display_panel.hpp>
#include <freertos/FreeRTOS.h>
#include <freertos/task.h>

using namespace esp_panel::drivers;
using namespace esp_panel::board;

unsigned long last_heartbeat = 0;
int loop_count = 0;

Board *board = nullptr;
bool board_initialized = false;

void setup() {
    Serial.begin(115200);
    Serial.setTxTimeoutMs(0);
    vTaskDelay(pdMS_TO_TICKS(100));

    Serial.println();
    Serial.println("=== ESP32-P4 TEST PHASE 2 ===");
    Serial.println("Board initialization test (NO LVGL)");
    Serial.printf("Free Heap: %d bytes\n", ESP.getFreeHeap());
    Serial.println("==================================");

    // Test board initialization
    Serial.println("🔧 Step 1: Creating board object...");
    board = new Board();

    if (board == nullptr) {
        Serial.println("❌ FAILED: Could not create board object");
        Serial.println("❌ CRITICAL: Board creation failed - hardware issue?");
        return;
    }
    Serial.println("✅ Board object created successfully");

    Serial.println("🔧 Step 2: Calling board->init()...");
    Serial.flush();
    board->init();
    Serial.println("✅ Board->init() completed without crash");

    Serial.println("🔧 Step 3: Calling board->begin()...");
    Serial.flush();
    if (!board->begin()) {
        Serial.println("❌ FAILED: Board->begin() returned false");
        Serial.println("⚠️  Board init failed but didn't crash - display hardware issue?");
        board_initialized = false;
    } else {
        Serial.println("✅ Board->begin() successful");
        board_initialized = true;
    }

    Serial.printf("📊 Free Heap after board init: %d bytes\n", ESP.getFreeHeap());
    Serial.println("🎯 PHASE 2 COMPLETE - Monitoring serial stability...");
    Serial.println("If serial stays active, board init is NOT the culprit");
}

void loop() {
    if (millis() - last_heartbeat > 5000) {
        loop_count++;
        Serial.printf("[%lu] HEARTBEAT #%d - Board: %s - Heap: %d - PHASE 2\n",
                      millis() / 1000, loop_count,
                      board_initialized ? "OK" : "FAILED",
                      ESP.getFreeHeap());
        last_heartbeat = millis();
    }

    vTaskDelay(pdMS_TO_TICKS(100));
}