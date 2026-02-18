#pragma once

// Manual configuration for ESP-Hosted slave target
// This is needed because the Kconfig option may not always be visible
#ifndef CONFIG_SLAVE_IDF_TARGET_ESP32C6
#define CONFIG_SLAVE_IDF_TARGET_ESP32C6 1
#endif
