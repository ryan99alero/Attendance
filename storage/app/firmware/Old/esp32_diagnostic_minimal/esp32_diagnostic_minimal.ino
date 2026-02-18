/*
 * ESP32-P4 Diagnostic - Ultra Minimal Test
 *
 * This version removes ALL complex initialization to isolate
 * the cause of execution failure. If this doesn't work, we have
 * a fundamental board/compilation issue.
 */

#include <Arduino.h>

// Use the same LED pin from the original firmware
#define LED_GREEN_PIN   46    // Available on pin header

void setup() {
    // Configure LED FIRST - before serial
    pinMode(LED_GREEN_PIN, OUTPUT);

    // Blink LED to show we're alive BEFORE any serial setup
    for (int i = 0; i < 10; i++) {
        digitalWrite(LED_GREEN_PIN, HIGH);
        delay(100);
        digitalWrite(LED_GREEN_PIN, LOW);
        delay(100);
    }

    // NOW try serial
    Serial.begin(115200);
    delay(2000); // Give USB CDC time to initialize

    Serial.println("🚀 MINIMAL DIAGNOSTIC WORKING!");
    Serial.println("✅ Setup completed successfully");
    Serial.flush();

    // Final confirmation blink
    for (int i = 0; i < 5; i++) {
        digitalWrite(LED_GREEN_PIN, HIGH);
        delay(200);
        digitalWrite(LED_GREEN_PIN, LOW);
        delay(200);
    }
}

void loop() {
    static int count = 0;

    // LED heartbeat
    digitalWrite(LED_GREEN_PIN, HIGH);
    delay(100);
    digitalWrite(LED_GREEN_PIN, LOW);
    delay(900);

    // Serial heartbeat
    Serial.printf("Heartbeat %d - Diagnostic working\n", ++count);
    delay(2000);
}