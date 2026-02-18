// FILE: main/time_settings.h
#pragma once
#include "lvgl.h"

#ifdef __cplusplus
extern "C" {
#endif

/**
 * Initialize time settings from NVS.
 * Call this early in app_main() AFTER nvs_flash_init().
 * Restores timezone and NTP server settings from persistent storage.
 */
void time_settings_init_nvs(void);

/**
 * Get the saved NTP server from NVS.
 * Returns "pool.ntp.org" if no server was saved.
 */
const char* time_settings_get_ntp_server(void);

/**
 * Create the timezone dropdown UI widget.
 */
lv_obj_t *time_settings_create(lv_obj_t *parent);

/**
 * Apply timezone by matching timezone name (e.g., "America/Chicago").
 * Searches the timezone list for a matching label and applies the
 * corresponding POSIX TZ string with full DST rules.
 * Also saves the selection to NVS for persistence.
 *
 * @param timezone_name The timezone name from server (e.g., "America/Chicago")
 * @return true if timezone was found and applied, false otherwise
 */
bool time_settings_apply_by_name(const char *timezone_name);

/**
 * Get the current POSIX TZ string for a given timezone offset.
 * Falls back to simple UTC offset if no matching timezone found.
 *
 * @param offset_seconds Timezone offset in seconds (e.g., -21600 for CST)
 * @param tz_buf Buffer to store the POSIX TZ string
 * @param buf_size Size of the buffer
 * @return true if a full POSIX TZ string was found, false if using fallback
 */
bool time_settings_get_posix_for_offset(int offset_seconds, char *tz_buf, size_t buf_size);

#ifdef __cplusplus
}
#endif
