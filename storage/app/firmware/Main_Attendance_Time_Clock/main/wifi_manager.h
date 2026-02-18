/**
 * WiFi Manager for ESP32-P4
 * Handles WiFi connection and comprehensive network configuration
 */

#ifndef WIFI_MANAGER_H
#define WIFI_MANAGER_H

#include "esp_err.h"
#include "esp_wifi.h"
#include <stdbool.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

#define WIFI_MAX_SCAN_RESULTS 20
#define WIFI_MAX_SSID_LEN 32
#define WIFI_MAX_PASSWORD_LEN 64
#define WIFI_MAX_HOSTNAME_LEN 32

// WiFi scan result
typedef struct {
    char ssid[WIFI_MAX_SSID_LEN];
    int8_t rssi;
    wifi_auth_mode_t authmode;
    uint8_t channel;
} wifi_scan_result_t;

// Network configuration
typedef struct {
    // WiFi credentials
    char ssid[WIFI_MAX_SSID_LEN];
    char password[WIFI_MAX_PASSWORD_LEN];

    // Network settings
    bool use_dhcp;                      // true = DHCP, false = static IP
    char hostname[WIFI_MAX_HOSTNAME_LEN]; // Device hostname

    // Static IP configuration (used when use_dhcp = false)
    char static_ip[16];                 // e.g., "192.168.1.100"
    char static_gateway[16];            // e.g., "192.168.1.1"
    char static_netmask[16];            // e.g., "255.255.255.0"
    char static_dns_primary[16];        // e.g., "8.8.8.8"
    char static_dns_secondary[16];      // e.g., "8.8.4.4" (optional)

    // Connection settings
    uint8_t max_retry;                  // Maximum connection retry attempts
} wifi_network_config_t;

// WiFi events callback
typedef void (*wifi_event_cb_t)(void *arg, esp_event_base_t event_base, int32_t event_id, void *event_data);

// WiFi scan completion callback
typedef void (*wifi_scan_done_cb_t)(uint16_t num_results);

/**
 * Initialize WiFi manager (sets up WiFi subsystem without connecting)
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_init(void);

/**
 * Scan for available WiFi networks (blocking)
 * @param results Array to store scan results
 * @param max_results Maximum number of results to return
 * @param num_results Output: number of networks found
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_scan(wifi_scan_result_t *results, uint16_t max_results, uint16_t *num_results);

/**
 * Start async WiFi scan (non-blocking)
 * @param callback Function to call when scan completes
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_scan_async(wifi_scan_done_cb_t callback);

/**
 * Get results from last async scan
 * @param results Array to store scan results
 * @param max_results Maximum number of results to return
 * @param num_results Output: number of networks found
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_get_scan_results(wifi_scan_result_t *results, uint16_t max_results, uint16_t *num_results);

/**
 * Load network configuration from NVS
 * @param config Network configuration structure to populate
 * @return ESP_OK on success, ESP_ERR_NVS_NOT_FOUND if no config exists
 */
esp_err_t wifi_manager_load_config(wifi_network_config_t *config);

/**
 * Save network configuration to NVS
 * @param config Network configuration to save
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_save_config(const wifi_network_config_t *config);

/**
 * Apply network configuration and connect
 * @param config Network configuration to apply
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_apply_config(const wifi_network_config_t *config);

/**
 * Connect to WiFi using current configuration
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_connect(void);

/**
 * Disconnect from WiFi network
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_disconnect(void);

/**
 * Check if WiFi is connected
 * @return true if connected, false otherwise
 */
bool wifi_manager_is_connected(void);

/**
 * Get WiFi connection status string
 * @return Status string
 */
const char* wifi_manager_get_status_string(void);

/**
 * Get current IP address as string
 * @param ip_str Buffer to store IP string (min 16 bytes)
 * @param ip_str_size Size of ip_str buffer
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_get_ip_string(char *ip_str, size_t ip_str_size);

/**
 * Get current gateway address as string
 * @param gw_str Buffer to store gateway string (min 16 bytes)
 * @param gw_str_size Size of gw_str buffer
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_get_gateway_string(char *gw_str, size_t gw_str_size);

/**
 * Get current netmask as string
 * @param nm_str Buffer to store netmask string (min 16 bytes)
 * @param nm_str_size Size of nm_str buffer
 * @return ESP_OK on success
 */
esp_err_t wifi_manager_get_netmask_string(char *nm_str, size_t nm_str_size);

/**
 * Get WiFi signal strength (RSSI)
 * @return RSSI value in dBm, or 0 if not connected
 */
int8_t wifi_manager_get_rssi(void);

/**
 * Get WiFi authentication mode as string
 * @param authmode WiFi authentication mode
 * @return Authentication mode string
 */
const char* wifi_manager_get_authmode_string(wifi_auth_mode_t authmode);

#ifdef __cplusplus
}
#endif

#endif // WIFI_MANAGER_H
