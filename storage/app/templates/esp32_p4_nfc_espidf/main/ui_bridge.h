/**
 * UI Bridge - Connects SquareLine Studio UI to Backend Managers
 *
 * This module bridges the gap between SquareLine-generated UI components
 * and the backend managers (wifi_manager, network_manager, time_settings, etc.)
 */

#ifndef UI_BRIDGE_H
#define UI_BRIDGE_H

#include "lvgl.h"
#include "esp_err.h"

#ifdef __cplusplus
extern "C" {
#endif

/**
 * Initialize the UI bridge
 * Must be called after ui_init() to connect SquareLine components to backends
 */
esp_err_t ui_bridge_init(void);

/**
 * Update network status icon on main screen
 * @param connected true if network is connected
 * @param is_wifi true if WiFi, false if Ethernet
 * @param ip_addr IP address string (or NULL if disconnected)
 */
void ui_bridge_update_network_status(bool connected, bool is_wifi, const char *ip_addr);

/**
 * Force refresh of main screen clock
 */
void ui_bridge_refresh_clock(void);

/**
 * Show a notification message
 * @param message Message to display
 * @param duration_ms Duration in milliseconds (0 for permanent)
 */
void ui_bridge_show_notification(const char *message, uint32_t duration_ms);

#ifdef __cplusplus
}
#endif

#endif // UI_BRIDGE_H
