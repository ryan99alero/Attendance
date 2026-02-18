/*
 * ESP32-P4 Flash Size Detection
 * Simple sketch to detect actual flash size
 */

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("=== ESP32-P4 Flash Detection ===");

    // Get flash chip size - only use functions that exist
    uint32_t flashSize = ESP.getFlashChipSize();

    Serial.printf("Flash Size (reported): %u bytes (%.1f MB)\n", flashSize, flashSize / 1024.0 / 1024.0);
    Serial.printf("Flash Size (hex): 0x%x\n", flashSize);

    // Try flash speed if available
    #ifdef ESP_getFlashChipSpeed
    Serial.printf("Flash Speed: %u Hz\n", ESP.getFlashChipSpeed());
    #endif

    // Basic ESP32-P4 info
    Serial.printf("Chip Model: %s\n", ESP.getChipModel());
    Serial.printf("Chip Revision: %u\n", ESP.getChipRevision());
    Serial.printf("CPU Frequency: %u MHz\n", ESP.getCpuFreqMHz());
    Serial.printf("Free Heap: %u bytes\n", ESP.getFreeHeap());

    // Check if it's actually 16MB or 32MB
    if (flashSize == 0x1000000) {
        Serial.println("✅ Hardware reports 16MB flash");
    } else if (flashSize == 0x2000000) {
        Serial.println("✅ Hardware reports 32MB flash");
    } else {
        Serial.printf("⚠️  Unexpected flash size: 0x%x\n", flashSize);
    }

    Serial.println("\n=== Recommended Arduino IDE Settings ===");
    if (flashSize <= 0x1000000) {
        Serial.println("Board: ESP32P4 Dev Module");
        Serial.println("Flash Size: 16MB (128Mb)");
        Serial.println("Partition Scheme: 16M Flash (3MB APP/9.9MB FATFS)");
    } else {
        Serial.println("Board: ESP32P4 Dev Module");
        Serial.println("Flash Size: 32MB (256Mb)");
        Serial.println("Partition Scheme: 32M Flash (13M APP/6.75M SPIFFS)");
    }
}

void loop() {
    delay(5000);
    Serial.println("Flash size check - see setup() output above");
}