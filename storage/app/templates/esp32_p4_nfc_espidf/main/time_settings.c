// FILE: main/time_settings.c
// ESP-IDF v5.x, LVGL v9.x
// - Creates a Time Zone dropdown (no keyboard needed)
// - Applies POSIX TZ via setenv("TZ", ...); tzset();
// - Saves/loads selection from NVS so it persists across reboots

#include "lvgl.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "nvs.h"
#include <string.h>
#include <stdlib.h>
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
    // US Mainland
    { "America/New_York (ET)", "EST5EDT,M3.2.0/2,M11.1.0/2" },   // UTC-5, DST
    { "America/Chicago (CT)",  "CST6CDT,M3.2.0/2,M11.1.0/2" },   // UTC-6, DST
    { "America/Denver (MT)",   "MST7MDT,M3.2.0/2,M11.1.0/2" },   // UTC-7, DST
    { "America/Los_Angeles (PT)","PST8PDT,M3.2.0/2,M11.1.0/2" }, // UTC-8, DST
    // Non-DST examples
    { "America/Phoenix (MST no DST)", "MST7" },                  // UTC-7, no DST
    { "America/Anchorage (AKT)", "AKST9AKDT,M3.2.0/2,M11.1.0/2" },// UTC-9, DST
    { "Pacific/Honolulu (HST no DST)", "HST10" },                // UTC-10, no DST
    // UTC options
    { "UTC", "UTC0" },
    // Add more regions as needed (Europe, Asia, etc.) with proper POSIX rules.
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
    int default_idx = 1; // index of "America/Chicago (CT)" in k_tz_list
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

// Call once early in app (e.g., app_main) before creating the dropdown.
// Ensures NVS is ready for load/save above.
void time_settings_init_nvs(void)
{
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        ESP_ERROR_CHECK(nvs_flash_init());
    }
}
