/**
 * API Client for Time Attendance System
 * Handles HTTP communication with Laravel backend
 */

#ifndef API_CLIENT_H
#define API_CLIENT_H

#include "esp_err.h"
#include "ui_manager.h"  // For employee_info_t structure
#include <stdint.h>
#include <stdbool.h>

#ifdef __cplusplus
extern "C" {
#endif

// API configuration
typedef struct {
    char server_host[128];      // Server hostname or IP
    uint16_t server_port;       // Server port (80 for HTTP, 443 for HTTPS)
    char api_token[256];        // Bearer token for authentication
    char device_id[64];         // Device ID from registration
    char device_name[64];       // Friendly device name
    bool is_registered;         // Registration status
    bool is_approved;           // Approval status
} api_config_t;

// Time sync response data
typedef struct {
    char server_time[32];       // ISO 8601 time string
    int64_t unix_timestamp;     // Unix timestamp (seconds)
    char timezone[64];          // Timezone string (e.g., "America/New_York")
    int timezone_offset;        // Timezone offset in seconds from UTC
    char ntp_server[128];       // NTP server to use (if specified by server)
    bool use_server_time;       // True if device should use server time sync
    bool valid;                 // True if data is valid
} time_sync_data_t;

// Sync source tracking
typedef enum {
    SYNC_SOURCE_NONE = 0,
    SYNC_SOURCE_SERVER,
    SYNC_SOURCE_NTP,
    SYNC_SOURCE_MANUAL
} sync_source_t;

// Punch/time record data
typedef struct {
    char device_id[64];         // Device identifier
    char credential_kind[32];   // Card type: "nfc", "rfid", "mifare_classic", etc.
    char credential_value[64];  // Card UID
    char event_time[32];        // ISO 8601 datetime string
    char event_type[16];        // "clock_in", "clock_out", or "unknown"
    int confidence;             // Reading confidence 0-100
    int timezone_offset;        // Timezone offset in hours from UTC
} punch_data_t;

// API client functions

/**
 * Initialize API client
 * @param config API configuration structure
 * @return ESP_OK on success
 */
esp_err_t api_client_init(api_config_t *config);

/**
 * Save API config to NVS (persists across reboots)
 * @return ESP_OK on success
 */
esp_err_t api_save_config(void);

/**
 * Load API config from NVS (restores state after reboot)
 * @return ESP_OK on success, ESP_ERR_NVS_NOT_FOUND if no config exists
 */
esp_err_t api_load_config(void);

/**
 * Register device with server
 * @param mac_address Device MAC address
 * @param device_name Friendly device name
 * @return ESP_OK on success, sets api_token and device_id in config
 */
esp_err_t api_register_device(const char *mac_address, const char *device_name);

/**
 * Check device approval status
 * @return ESP_OK on success, updates is_approved in config
 */
esp_err_t api_check_status(void);

/**
 * Send punch/time record to server
 * @param punch_data Punch data structure
 * @return ESP_OK on success
 */
esp_err_t api_send_punch(const punch_data_t *punch_data);

/**
 * Health check - verify server is reachable
 * @return ESP_OK if server is healthy
 */
esp_err_t api_health_check(void);

/**
 * Get current server time (for clock synchronization)
 * @param time_str Buffer to store ISO 8601 time string (min 32 bytes)
 * @param time_str_size Size of time_str buffer
 * @return ESP_OK on success
 */
esp_err_t api_get_server_time(char *time_str, size_t time_str_size);

/**
 * Full time sync - gets time, timezone, NTP server from server
 * @param sync_data Pointer to time_sync_data_t structure to fill
 * @return ESP_OK on success
 */
esp_err_t api_sync_time(time_sync_data_t *sync_data);

/**
 * Send heartbeat/daily sync to server with device status
 * @param ip_address Current IP address of the device
 * @return ESP_OK on success
 */
esp_err_t api_send_heartbeat(const char *ip_address);

/**
 * Get current sync source
 * @return Current sync source enum
 */
sync_source_t api_get_sync_source(void);

/**
 * Get current time sync settings
 * @return Pointer to current time sync data (may have stale data if not recently synced)
 */
const time_sync_data_t* api_get_time_sync_data(void);

/**
 * Get employee information and hours by card ID
 * @param card_id Card UID to lookup
 * @param employee_info Pointer to structure to fill with employee data
 * @return ESP_OK on success
 */
esp_err_t api_get_employee_info(const char *card_id, employee_info_t *employee_info);

/**
 * Get API configuration
 * @return Pointer to current API config
 */
api_config_t* api_get_config(void);

#ifdef __cplusplus
}
#endif

#endif // API_CLIENT_H
