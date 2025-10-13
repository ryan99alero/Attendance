/**
 * Network Manager - Unified interface for WiFi, Ethernet, and future Bluetooth
 * Abstracts network connectivity regardless of the underlying technology
 */

#ifndef NETWORK_MANAGER_H
#define NETWORK_MANAGER_H

#include "esp_err.h"
#include <stdbool.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

// Network types supported
typedef enum {
    NETWORK_TYPE_NONE = 0,
    NETWORK_TYPE_WIFI,
    NETWORK_TYPE_ETHERNET,
    NETWORK_TYPE_BLUETOOTH,  // For future expansion
} network_type_t;

// Network mode preference (stored in NVS)
typedef enum {
    NETWORK_MODE_WIFI_ONLY = 0,      // WiFi only (default)
    NETWORK_MODE_ETHERNET_ONLY = 1,  // Ethernet only
} network_mode_t;

/**
 * Set network mode preference and save to NVS
 * This requires a reboot to take effect
 * @param mode Network mode to use
 * @return ESP_OK on success
 */
esp_err_t network_manager_set_mode(network_mode_t mode);

/**
 * Switch to Ethernet mode (live, no reboot required)
 * Stops WiFi, initializes and starts Ethernet
 * @return ESP_OK on success
 */
esp_err_t network_manager_switch_to_ethernet(void);

/**
 * Switch to WiFi mode (live, no reboot required)
 * Stops Ethernet, initializes and starts WiFi
 * @return ESP_OK on success
 */
esp_err_t network_manager_switch_to_wifi(void);

/**
 * Get current network mode preference from NVS
 * @return Current network mode
 */
network_mode_t network_manager_get_mode(void);

/**
 * Get network mode as string
 * @param mode Network mode
 * @return String representation ("WiFi Only" or "Ethernet Only")
 */
const char* network_manager_get_mode_string(network_mode_t mode);

/**
 * Initialize network manager
 * This will initialize only the selected network subsystem based on mode preference
 * @return ESP_OK on success
 */
esp_err_t network_manager_init(void);

/**
 * Start network monitoring task
 * Monitors all network interfaces and updates UI automatically
 * @return ESP_OK on success
 */
esp_err_t network_manager_start_monitoring(void);

/**
 * Stop network monitoring task
 * @return ESP_OK on success
 */
esp_err_t network_manager_stop_monitoring(void);

/**
 * Check if any network interface is connected
 * @return true if connected, false otherwise
 */
bool network_manager_is_connected(void);

/**
 * Get the currently active network type
 * Priority: Ethernet > WiFi > Bluetooth
 * @return network_type_t
 */
network_type_t network_manager_get_active_type(void);

/**
 * Get current IP address as string from active interface
 * @param ip_str Buffer to store IP string (min 16 bytes)
 * @param ip_str_size Size of ip_str buffer
 * @return ESP_OK on success, ESP_ERR_INVALID_STATE if not connected
 */
esp_err_t network_manager_get_ip_string(char *ip_str, size_t ip_str_size);

/**
 * Get status string from active interface
 * @return Status string (e.g., "Connected to WiFi", "Ethernet Connected", "Disconnected")
 */
const char* network_manager_get_status_string(void);

/**
 * Get RSSI (signal strength) from active wireless interface
 * Only applicable for WiFi, returns 0 for wired connections
 * @return RSSI value in dBm
 */
int network_manager_get_rssi(void);

/**
 * Load and apply saved configurations for all network interfaces
 * Attempts to connect using saved settings
 * @return ESP_OK on success
 */
esp_err_t network_manager_load_and_connect(void);

#ifdef __cplusplus
}
#endif

#endif // NETWORK_MANAGER_H
