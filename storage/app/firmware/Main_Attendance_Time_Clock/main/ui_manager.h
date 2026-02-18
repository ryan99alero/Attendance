/**
 * UI Manager for NFC Time Clock Display
 * Manages LVGL interface with status bar, card info, and setup screens
 */

#ifndef UI_MANAGER_H
#define UI_MANAGER_H

#include "lvgl.h"
#include <stdbool.h>

#ifdef __cplusplus
extern "C" {
#endif

// Network status types
typedef enum {
    NET_STATUS_DISCONNECTED = 0,
    NET_STATUS_WIFI_CONNECTED,
    NET_STATUS_ETHERNET_CONNECTED,
    NET_STATUS_CONNECTING
} network_status_t;

// NFC reader status types
typedef enum {
    NFC_STATUS_DISABLED = 0,
    NFC_STATUS_READY,
    NFC_STATUS_READING,
    NFC_STATUS_ERROR
} nfc_status_t;

// Employee information structure
typedef struct {
    char employee_id[32];
    char name[64];
    char department[64];
    char photo_url[128];
    bool is_authorized;
    float today_hours;          // Hours worked today
    float week_hours;           // Hours worked this week
    float pay_period_hours;     // Hours worked this pay period
    float vacation_balance;     // Vacation hours balance
} employee_info_t;

// Card scan result structure
typedef struct {
    char card_uid[32];
    char card_type[32];
    employee_info_t employee;
    char timestamp[32];
    bool success;
    char message[128];
} card_scan_result_t;

// Setup callback function type
typedef void (*setup_password_callback_t)(const char *password, bool *is_valid);

/**
 * Initialize UI manager and create main screen
 * @param device_name Name of this time clock device
 * @return lv_obj_t* pointer to main screen
 */
lv_obj_t* ui_manager_init(const char *device_name);

/**
 * Update status bar icons
 * @param net_status Network connection status
 * @param nfc_status NFC reader status
 */
void ui_update_status(network_status_t net_status, nfc_status_t nfc_status);

/**
 * Update clock time display
 * @param time_str Time string in format "HH:MM:SS" or "HH:MM"
 * @param date_str Date string in format "YYYY-MM-DD" or "MMM DD, YYYY"
 */
void ui_update_time(const char *time_str, const char *date_str);

/**
 * Show card scan result on display
 * @param result Card scan result structure
 * @param display_duration_ms How long to show the result (0 = until next card)
 */
void ui_show_card_scan(const card_scan_result_t *result, uint32_t display_duration_ms);

/**
 * Show idle/ready screen
 * @param message Message to display (e.g., "Ready to scan" or "Place card on reader")
 */
void ui_show_ready_screen(const char *message);

/**
 * Show error message
 * @param error_msg Error message to display
 * @param duration_ms How long to show error (0 = indefinite)
 */
void ui_show_error(const char *error_msg, uint32_t duration_ms);

/**
 * Set setup button password callback
 * @param callback Function to call when password is entered
 */
void ui_set_setup_callback(setup_password_callback_t callback);

/**
 * Show setup/admin screen (after password entry)
 */
void ui_show_setup_screen(void);

/**
 * Hide setup screen and return to main
 */
void ui_hide_setup_screen(void);

/**
 * Update network info display
 * @param ip_address IP address string
 * @param signal_strength Signal strength 0-100 (for WiFi, -1 for ethernet)
 */
void ui_update_network_info(const char *ip_address, int signal_strength);

#ifdef __cplusplus
}
#endif

#endif // UI_MANAGER_H
