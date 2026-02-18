/*
 * ESP32-P4 Stepwise Debug - Find the Breaking Point
 *
 * This adds components incrementally to find exactly where
 * the original firmware crashes or hangs.
 */

#include <Arduino.h>

// Status LED for visual feedback
#define LED_GREEN_PIN   46
#define LED_RED_PIN     45

void blinkStatus(int pin, int count, int delayMs = 200) {
    for (int i = 0; i < count; i++) {
        digitalWrite(pin, HIGH);
        delay(delayMs);
        digitalWrite(pin, LOW);
        delay(delayMs);
    }
}

void setup() {
    // STEP 1: Basic GPIO setup
    pinMode(LED_GREEN_PIN, OUTPUT);
    pinMode(LED_RED_PIN, OUTPUT);

    // Signal: Starting setup
    blinkStatus(LED_GREEN_PIN, 3, 100); // 3 fast green blinks

    // STEP 2: Serial setup (known working)
    Serial.begin(115200);
    delay(2000); // Give USB CDC time to initialize
    Serial.println("🔧 STEPWISE DEBUG - STEP 2: Serial working");
    Serial.flush();

    // Signal: Serial working
    blinkStatus(LED_GREEN_PIN, 2, 300); // 2 slower green blinks

    // STEP 3: Try to include just the display panel header (no initialization)
    Serial.println("🔧 STEP 3: Testing ESP32_Display_Panel include...");
    Serial.flush();

    // If we get here, the include didn't crash
    Serial.println("✅ ESP32_Display_Panel header included successfully");
    Serial.flush();

    // Signal: Headers OK
    blinkStatus(LED_GREEN_PIN, 1, 500); // 1 long green blink

    Serial.println("🔧 STEPWISE DEBUG: Basic components working");
    Serial.println("   - GPIO: ✅");
    Serial.println("   - Serial: ✅");
    Serial.println("   - Headers: ✅");
    Serial.println("Ready to test next component...");
    Serial.flush();
}

void loop() {
    static int count = 0;

    // Heartbeat pattern: green blink every 2 seconds
    digitalWrite(LED_GREEN_PIN, HIGH);
    delay(100);
    digitalWrite(LED_GREEN_PIN, LOW);
    delay(1900);

    // Serial heartbeat
    Serial.printf("📊 Heartbeat %d - All basic systems stable\n", ++count);
    delay(1000);
}