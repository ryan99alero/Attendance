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

#ifdef __cplusplus
}
#endif
