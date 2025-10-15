/**
 * @file time_settings.h
 * @brief Time Zone Settings Module
 *
 * Provides a self-contained time zone selection widget with:
 * - LVGL dropdown/roller for timezone selection
 * - Proper POSIX TZ strings with DST support
 * - NVS persistence across reboots
 * - Automatic timezone application via setenv/tzset
 */

#pragma once

#include "lvgl.h"

#ifdef __cplusplus
extern "C" {
#endif

/**
 * @brief Initialize NVS for time settings persistence
 *
 * Must be called once during app initialization, before creating widgets.
 * Safe to call multiple times (checks if already initialized).
 */
void time_settings_init_nvs(void);

/**
 * @brief Create timezone selection widget
 *
 * @param parent LVGL container to place the widget in
 * @return lv_obj_t* The created timezone roller widget
 *
 * Creates a self-contained timezone selector that:
 * - Loads saved timezone from NVS
 * - Applies timezone immediately
 * - Saves selection to NVS on change
 * - Uses proper POSIX TZ strings with DST rules
 */
lv_obj_t *time_settings_create_timezone_selector(lv_obj_t *parent);

/**
 * @brief Get currently selected timezone display name
 *
 * @return const char* User-friendly timezone name (e.g., "Central Time")
 */
const char *time_settings_get_current_timezone_name(void);

/**
 * @brief Get currently selected POSIX timezone string
 *
 * @return const char* POSIX TZ string (e.g., "CST6CDT,M3.2.0/2,M11.1.0/2")
 */
const char *time_settings_get_current_timezone_posix(void);

#ifdef __cplusplus
}
#endif
