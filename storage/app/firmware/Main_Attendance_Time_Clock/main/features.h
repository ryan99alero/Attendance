/**
 * Feature Configuration
 * Central location for enabling/disabling features across all modules
 */

#ifndef FEATURES_H
#define FEATURES_H

// Feature enables - These control which subsystems are compiled and initialized
#define DISPLAY_ENABLED 1       // Enable display and UI
#define NFC_ENABLED 1           // Enable NFC card reader
#define WIFI_ENABLED 1          // Enable WiFi networking (compile-time)
#define ETHERNET_ENABLED 1      // Enable Ethernet networking (compile-time)
#define API_ENABLED 1           // Enable API client (requires network)

// NOTE: WiFi and Ethernet can now run simultaneously on ESP32-P4-Function-EV-Board v1.5.2
// The GPIO 50 conflict has been resolved (GPIO 50 is RMII CLK, MDC is GPIO 31).
// Both interfaces initialize at startup. Use network_manager to switch between them dynamically.

// Device configuration
#define DEVICE_NAME "NFC Time Clock 01"  // Device name shown on display
#define DEFAULT_ADMIN_PASSWORD "admin"   // Default admin password for setup menu

#endif // FEATURES_H
