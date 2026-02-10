// FILE: main/time_settings.c
// ESP-IDF v5.x, LVGL v9.x
// - Creates a Time Zone dropdown (no keyboard needed)
// - Applies POSIX TZ via setenv("TZ", ...); tzset();
// - Saves/loads selection from NVS so it persists across reboots

#include "time_settings.h"
#include "lvgl.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "nvs.h"
#include <string.h>
#include <stdlib.h>
#include <stdbool.h>
#include <time.h>

static const char *TAG = "time_settings";
#define NVS_NS                 "app_settings"
#define NVS_KEY_TZ_POSIX       "timezone_posix"
#define NVS_KEY_TZ_INDEX       "timezone_idx"

// A compact US-focused list (add more as needed). POSIX TZ strings are required by ESP-IDF.
// Format notes: STD[+-]hh[:mm[:ss]]DST[+-]hh[:mm[:ss]],start[/time],end[/time]
// Daylight rules shown for typical North America DST.
typedef struct {
    const char *label;   // user-facing
    const char *posix;   // POSIX TZ string
} tz_entry_t;

static const tz_entry_t k_tz_list[] = {
    // Sorted alphabetically - labels MUST match Filament admin panel exactly
    // Index 0: Alaska Time
    { "Alaska Time (AKST/AKDT)", "AKST9AKDT,M3.2.0/2,M11.1.0/2" },        // UTC-9, DST
    // Index 1: Atlantic Time
    { "Atlantic Time (AST)", "AST4" },                                    // UTC-4, no DST
    // Index 2: Central Time
    { "Central Time (CST/CDT)", "CST6CDT,M3.2.0/2,M11.1.0/2" },           // UTC-6, DST
    // Index 3: Chamorro Time
    { "Chamorro Time (ChST)", "ChST-10" },                                // UTC+10, no DST
    // Index 4: Eastern Time
    { "Eastern Time (EST/EDT)", "EST5EDT,M3.2.0/2,M11.1.0/2" },           // UTC-5, DST
    // Index 5: Hawaii-Aleutian Time
    { "Hawaii-Aleutian Time (HST/HDT)", "HAST10HADT,M3.2.0/2,M11.1.0/2" },// UTC-10, DST
    // Index 6: Mountain Time
    { "Mountain Time (MST/MDT)", "MST7MDT,M3.2.0/2,M11.1.0/2" },          // UTC-7, DST
    // Index 7: Pacific Time
    { "Pacific Time (PST/PDT)", "PST8PDT,M3.2.0/2,M11.1.0/2" },           // UTC-8, DST
    // Index 8: Samoa Time
    { "Samoa Time (SST)", "SST11" },                                      // UTC-11, no DST
};
static const size_t k_tz_count = sizeof(k_tz_list) / sizeof(k_tz_list[0]);

// Forward declarations
static void apply_timezone_from_index(int idx);
static void save_timezone_nvs(int idx);
static int  load_timezone_index_nvs(int fallback_idx);
static void tz_roller_event_cb(lv_event_t *e);

// Call during app init (after nvs_flash_init, lvgl init, and after SNTP init if you use it).
// parent: any LVGL container/screen you want to place the control in.
lv_obj_t * time_settings_create(lv_obj_t *parent)
{
    // Build options string for lv_roller
    // LVGL wants options separated by '\n'
    lv_obj_t *roller = lv_roller_create(parent);
    {
        // Create options text
        size_t buf_len = 0;
        for (size_t i = 0; i < k_tz_count; ++i) buf_len += strlen(k_tz_list[i].label) + 1;
        char *opts = (char *)malloc(buf_len + 1);
        if (!opts) {
            ESP_LOGE(TAG, "Failed to alloc roller options");
            return roller;
        }
        opts[0] = '\0';
        for (size_t i = 0; i < k_tz_count; ++i) {
            strcat(opts, k_tz_list[i].label);
            if (i != k_tz_count - 1) strcat(opts, "\n");
        }
        lv_roller_set_options(roller, opts, LV_ROLLER_MODE_NORMAL);
        free(opts);
    }

    // Size/appearance (adjust to your theme)
    lv_obj_set_width(roller, 300);
    lv_roller_set_visible_row_count(roller, 4); // Show 4 rows at a time

    // Restore previously saved selection (default to "America/Chicago")
    int default_idx = 2; // index of "America/Chicago (CST/CDT)" in k_tz_list
    int saved_idx   = load_timezone_index_nvs(default_idx);
    if (saved_idx < 0 || saved_idx >= (int)k_tz_count) saved_idx = default_idx;

    lv_roller_set_selected(roller, saved_idx, LV_ANIM_OFF);
    apply_timezone_from_index(saved_idx); // apply immediately on build

    // Event: when user selects a different time zone
    lv_obj_add_event_cb(roller, tz_roller_event_cb, LV_EVENT_VALUE_CHANGED, NULL);

    return roller;
}

static void tz_roller_event_cb(lv_event_t *e)
{
    lv_obj_t *obj = lv_event_get_target(e);
    uint16_t idx = lv_roller_get_selected(obj);
    apply_timezone_from_index((int)idx);
    save_timezone_nvs((int)idx);
    ESP_LOGI(TAG, "Time zone changed to index %u: %s", idx, k_tz_list[idx].label);
}

static void apply_timezone_from_index(int idx)
{
    if (idx < 0 || idx >= (int)k_tz_count) return;
    const char *tz = k_tz_list[idx].posix;

    // Apply POSIX TZ to C library time conversion
    setenv("TZ", tz, 1);
    tzset();

    // Note: SNTP/timekeeping remains in UTC; tz only affects localtime()->tm conversions.
    // If you already have SNTP running and time set, this takes effect immediately for localtime() calls.
    ESP_LOGI(TAG, "Applied TZ: %s", tz);
}

static void save_timezone_nvs(int idx)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open(NVS_NS, NVS_READWRITE, &h);
    if (err != ESP_OK) { ESP_LOGE(TAG, "nvs_open: %s", esp_err_to_name(err)); return; }

    const char *tz = (idx >= 0 && idx < (int)k_tz_count) ? k_tz_list[idx].posix : "UTC0";

    err = nvs_set_i32(h, NVS_KEY_TZ_INDEX, idx);
    if (err == ESP_OK) err = nvs_set_str(h, NVS_KEY_TZ_POSIX, tz);
    if (err == ESP_OK) err = nvs_commit(h);
    nvs_close(h);

    if (err != ESP_OK) ESP_LOGE(TAG, "Failed to save TZ to NVS: %s", esp_err_to_name(err));
}

static int load_timezone_index_nvs(int fallback_idx)
{
    nvs_handle_t h;
    esp_err_t err = nvs_open(NVS_NS, NVS_READONLY, &h);
    if (err != ESP_OK) return fallback_idx;

    int32_t idx = fallback_idx;
    err = nvs_get_i32(h, NVS_KEY_TZ_INDEX, &idx);
    nvs_close(h);
    return (err == ESP_OK) ? (int)idx : fallback_idx;
}

// Saved NTP server (for external access)
static char s_saved_ntp_server[64] = {0};

// Get the saved NTP server (returns "pool.ntp.org" if none saved)
const char* time_settings_get_ntp_server(void)
{
    if (strlen(s_saved_ntp_server) > 0) {
        return s_saved_ntp_server;
    }
    return "pool.ntp.org";
}

// Look up timezone by name (e.g., "America/Chicago") and apply the full POSIX TZ string
bool time_settings_apply_by_name(const char *timezone_name)
{
    if (!timezone_name || strlen(timezone_name) == 0) {
        return false;
    }

    ESP_LOGI(TAG, "Looking up timezone: %s", timezone_name);

    // Search for matching timezone in our list
    for (size_t i = 0; i < k_tz_count; ++i) {
        // Check if the timezone name appears in the label
        // Labels are like "America/Chicago (CST/CDT)"
        if (strstr(k_tz_list[i].label, timezone_name) != NULL) {
            ESP_LOGI(TAG, "Found timezone match at index %zu: %s", i, k_tz_list[i].label);

            // Apply the full POSIX TZ string with DST rules
            apply_timezone_from_index((int)i);

            // Save to NVS
            save_timezone_nvs((int)i);

            return true;
        }
    }

    ESP_LOGW(TAG, "No matching timezone found for: %s", timezone_name);
    return false;
}

// Get the POSIX TZ string for a given offset, preferring full DST rules
bool time_settings_get_posix_for_offset(int offset_seconds, char *tz_buf, size_t buf_size)
{
    if (!tz_buf || buf_size == 0) {
        return false;
    }

    int offset_hours = offset_seconds / 3600;

    // Map common US offsets to their timezone entries with DST rules
    // Note: offset_seconds is negative for west of UTC
    int target_idx = -1;

    switch (offset_hours) {
        case -9:  // Alaska Time (AKST)
            target_idx = 0;
            break;
        case -8:  // Pacific Time (PST)
            target_idx = 7;
            break;
        case -7:  // Mountain Time (MST)
            target_idx = 6;
            break;
        case -6:  // Central Time (CST)
            target_idx = 2;
            break;
        case -5:  // Eastern Time (EST)
            target_idx = 4;
            break;
        case -4:  // Atlantic Time (AST)
            target_idx = 1;
            break;
        case -10: // Hawaii-Aleutian Time (HST)
            target_idx = 5;
            break;
        case -11: // Samoa Time (SST)
            target_idx = 8;
            break;
        case 10:  // Chamorro Time (ChST)
            target_idx = 3;
            break;
    }

    if (target_idx >= 0 && target_idx < (int)k_tz_count) {
        strncpy(tz_buf, k_tz_list[target_idx].posix, buf_size - 1);
        tz_buf[buf_size - 1] = '\0';
        ESP_LOGI(TAG, "Using full POSIX TZ for offset %d: %s", offset_hours, tz_buf);
        return true;
    }

    // Fallback: generate simple UTC offset string
    // POSIX TZ sign is inverted: UTC-6 means 6 hours behind UTC, written as "UTC6"
    if (offset_hours <= 0) {
        snprintf(tz_buf, buf_size, "UTC%d", -offset_hours);
    } else {
        snprintf(tz_buf, buf_size, "UTC-%d", offset_hours);
    }
    ESP_LOGW(TAG, "No matching TZ entry for offset %d, using fallback: %s", offset_hours, tz_buf);
    return false;
}

// Call once early in app (e.g., app_main) after nvs_flash_init() has been called.
// This restores timezone and NTP settings from NVS so they persist across reboots.
// NOTE: This should be called AFTER nvs_flash_init() is done in main.c
void time_settings_init_nvs(void)
{
    // Note: We assume nvs_flash_init() has already been called in main.c
    // Do NOT call nvs_flash_init() here again

    // Try to load and apply timezone_posix from server sync (takes precedence over dropdown index)
    nvs_handle_t h;
    esp_err_t err = nvs_open(NVS_NS, NVS_READONLY, &h);
    if (err == ESP_OK) {
        // Load timezone POSIX string
        char tz_posix[64] = {0};
        size_t tz_len = sizeof(tz_posix);
        err = nvs_get_str(h, NVS_KEY_TZ_POSIX, tz_posix, &tz_len);
        if (err == ESP_OK && strlen(tz_posix) > 0) {
            setenv("TZ", tz_posix, 1);
            tzset();
            ESP_LOGI(TAG, "Restored timezone from NVS: TZ=%s", tz_posix);
        } else {
            ESP_LOGI(TAG, "No timezone in NVS, using default");
        }

        // Load NTP server if saved
        size_t ntp_len = sizeof(s_saved_ntp_server);
        err = nvs_get_str(h, "ntp_server", s_saved_ntp_server, &ntp_len);
        if (err == ESP_OK && strlen(s_saved_ntp_server) > 0) {
            ESP_LOGI(TAG, "Restored NTP server from NVS: %s", s_saved_ntp_server);
        } else {
            // Use default NTP server
            strncpy(s_saved_ntp_server, "pool.ntp.org", sizeof(s_saved_ntp_server) - 1);
            ESP_LOGI(TAG, "No NTP server in NVS, using default: %s", s_saved_ntp_server);
        }

        nvs_close(h);
    } else {
        ESP_LOGW(TAG, "Could not open NVS for time settings: %s", esp_err_to_name(err));
        // Use defaults
        strncpy(s_saved_ntp_server, "pool.ntp.org", sizeof(s_saved_ntp_server) - 1);
    }
}
