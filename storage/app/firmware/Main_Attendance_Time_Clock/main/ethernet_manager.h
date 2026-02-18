/**
 * Ethernet Manager for ESP32-P4
 * Handles Ethernet connection with IP101GR PHY
 */

#ifndef ETHERNET_MANAGER_H
#define ETHERNET_MANAGER_H

#include "esp_err.h"
#include "esp_eth.h"
#include <stdbool.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

// Ethernet configuration
typedef struct {
    // Network settings
    bool use_dhcp;                      // true = DHCP, false = static IP
    char hostname[32];                  // Device hostname

    // Static IP configuration (used when use_dhcp = false)
    char static_ip[16];                 // e.g., "192.168.1.100"
    char static_gateway[16];            // e.g., "192.168.1.1"
    char static_netmask[16];            // e.g., "255.255.255.0"
    char static_dns_primary[16];        // e.g., "8.8.8.8"
    char static_dns_secondary[16];      // e.g., "8.8.4.4" (optional)
} ethernet_config_t;

/**
 * Initialize Ethernet manager
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_init(void);

/**
 * Start Ethernet connection
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_start(void);

/**
 * Stop Ethernet connection
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_stop(void);

/**
 * Check if Ethernet is connected
 * @return true if connected, false otherwise
 */
bool ethernet_manager_is_connected(void);

/**
 * Get Ethernet connection status string
 * @return Status string
 */
const char* ethernet_manager_get_status_string(void);

/**
 * Load Ethernet configuration from NVS
 * @param config Ethernet configuration structure to populate
 * @return ESP_OK on success, ESP_ERR_NVS_NOT_FOUND if no config exists
 */
esp_err_t ethernet_manager_load_config(ethernet_config_t *config);

/**
 * Save Ethernet configuration to NVS
 * @param config Ethernet configuration to save
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_save_config(const ethernet_config_t *config);

/**
 * Apply Ethernet configuration
 * @param config Ethernet configuration to apply
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_apply_config(const ethernet_config_t *config);

/**
 * Get current IP address as string
 * @param ip_str Buffer to store IP string (min 16 bytes)
 * @param ip_str_size Size of ip_str buffer
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_get_ip_string(char *ip_str, size_t ip_str_size);

/**
 * Get current gateway address as string
 * @param gw_str Buffer to store gateway string (min 16 bytes)
 * @param gw_str_size Size of gw_str buffer
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_get_gateway_string(char *gw_str, size_t gw_str_size);

/**
 * Get current netmask as string
 * @param nm_str Buffer to store netmask string (min 16 bytes)
 * @param nm_str_size Size of nm_str buffer
 * @return ESP_OK on success
 */
esp_err_t ethernet_manager_get_netmask_string(char *nm_str, size_t nm_str_size);

#ifdef __cplusplus
}
#endif

#endif // ETHERNET_MANAGER_H
