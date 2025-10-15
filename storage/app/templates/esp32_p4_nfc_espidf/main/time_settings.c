/**
 * @file time_settings.c
 * @brief Time Zone Settings Module Implementation
 *
 * Self-contained timezone management with NVS persistence.
 * Uses proper POSIX TZ strings for accurate DST handling.
 * Uses button + modal list approach to avoid LVGL dropdown bugs.
 */

#include "time_settings.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "nvs.h"
#include <stdlib.h>
#include <string.h>
#include <time.h>

#define NVS_NS                 "app_settings"
#define NVS_KEY_TZ_INDEX       "timezone_idx"
#define NVS_KEY_TZ_POSIX       "timezone_posix"

static const char *TAG = "time_settings";
static bool nvs_ready = false;
static int  current_idx = -1;  // cache current selection

// UI elements for modal selector
static lv_obj_t *modal_overlay = NULL;
static lv_obj_t *tz_button = NULL;  // The button that shows current selection

typedef struct {
    const char *label;   // user-facing
    const char *posix;   // POSIX TZ string
} tz_entry_t;

/* Extend as needed */
static const tz_entry_t k_tz_list[] = {
    { "America/New_York (ET)",      "EST5EDT,M3.2.0/2,M11.1.0/2" },
    { "America/Chicago (CT)",       "CST6CDT,M3.2.0/2,M11.1.0/2" },
    { "America/Denver (MT)",        "MST7MDT,M3.2.0/2,M11.1.0/2" },
    { "America/Los_Angeles (PT)",   "PST8PDT,M3.2.0/2,M11.1.0/2" },
    { "America/Phoenix (no DST)",   "MST7" },
    { "America/Anchorage (AKT)",    "AKST9AKDT,M3.2.0/2,M11.1.0/2" },
    { "Pacific/Honolulu (no DST)",  "HST10" },
    { "UTC",                         "UTC0" },
};
static const size_t k_tz_count = sizeof(k_tz_list)/sizeof(k_tz_list[0]);

/* --------------------------- NVS helpers --------------------------- */
static int load_timezone_index_nvs(int fallback_idx) {
    if (!nvs_ready) return fallback_idx;
    nvs_handle_t h;
    if (nvs_open(NVS_NS, NVS_READONLY, &h) != ESP_OK) return fallback_idx;
    int32_t idx = fallback_idx;
    esp_err_t err = nvs_get_i32(h, NVS_KEY_TZ_INDEX, &idx);
    nvs_close(h);
    return (err == ESP_OK) ? (int)idx : fallback_idx;
}

static void save_timezone_nvs(int idx) {
    if (!nvs_ready) return;
    if (idx < 0 || idx >= (int)k_tz_count) return;
    nvs_handle_t h;
    if (nvs_open(NVS_NS, NVS_READWRITE, &h) != ESP_OK) return;
    nvs_set_i32(h, NVS_KEY_TZ_INDEX, idx);
    nvs_set_str(h, NVS_KEY_TZ_POSIX, k_tz_list[idx].posix);
    nvs_commit(h);
    nvs_close(h);
}

/* --------------------------- TZ apply --------------------------- */
static void apply_timezone_from_index(int idx) {
    if (idx < 0 || idx >= (int)k_tz_count) return;
    setenv("TZ", k_tz_list[idx].posix, 1);
    tzset();
    current_idx = idx;

    // Update button label if it exists
    if (tz_button) {
        lv_label_set_text(lv_obj_get_child(tz_button, 0), k_tz_list[idx].label);
    }

    ESP_LOGI(TAG, "Applied TZ: %s (%s)", k_tz_list[idx].posix, k_tz_list[idx].label);
}

/* --------------------------- Modal list events --------------------------- */
static void tz_list_item_clicked(lv_event_t *e) {
    int idx = (int)(intptr_t)lv_event_get_user_data(e);

    apply_timezone_from_index(idx);
    save_timezone_nvs(idx);

    // Close modal
    if (modal_overlay) {
        lv_obj_del(modal_overlay);
        modal_overlay = NULL;
    }
}

static void modal_close_clicked(lv_event_t *e) {
    if (modal_overlay) {
        lv_obj_del(modal_overlay);
        modal_overlay = NULL;
    }
}

/* --------------------------- Button click opens modal --------------------------- */
static void tz_button_clicked(lv_event_t *e) {
    if (modal_overlay) return;  // Already open

    // Create modal overlay
    modal_overlay = lv_obj_create(lv_scr_act());
    lv_obj_set_size(modal_overlay, LV_PCT(100), LV_PCT(100));
    lv_obj_set_style_bg_color(modal_overlay, lv_color_hex(0x000000), 0);
    lv_obj_set_style_bg_opa(modal_overlay, LV_OPA_70, 0);
    lv_obj_set_style_border_width(modal_overlay, 0, 0);
    lv_obj_clear_flag(modal_overlay, LV_OBJ_FLAG_SCROLLABLE);

    // Create list container
    lv_obj_t *list_container = lv_obj_create(modal_overlay);
    lv_obj_set_size(list_container, 400, 400);
    lv_obj_center(list_container);
    lv_obj_set_style_radius(list_container, 10, 0);

    // Title
    lv_obj_t *title = lv_label_create(list_container);
    lv_label_set_text(title, "Select Timezone");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 10);

    // Scrollable list
    lv_obj_t *list = lv_obj_create(list_container);
    lv_obj_set_size(list, 380, 300);
    lv_obj_align(list, LV_ALIGN_TOP_MID, 0, 40);
    lv_obj_set_flex_flow(list, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_style_pad_all(list, 5, 0);
    lv_obj_set_style_pad_row(list, 5, 0);

    // Add timezone buttons
    for (size_t i = 0; i < k_tz_count; i++) {
        lv_obj_t *btn = lv_btn_create(list);
        lv_obj_set_width(btn, LV_PCT(100));
        lv_obj_set_height(btn, 40);

        lv_obj_t *label = lv_label_create(btn);
        lv_label_set_text(label, k_tz_list[i].label);
        lv_obj_center(label);

        // Highlight current selection
        if ((int)i == current_idx) {
            lv_obj_set_style_bg_color(btn, lv_color_hex(0x0078D4), 0);
        }

        lv_obj_add_event_cb(btn, tz_list_item_clicked, LV_EVENT_CLICKED, (void*)(intptr_t)i);
    }

    // Close button
    lv_obj_t *close_btn = lv_btn_create(list_container);
    lv_obj_set_size(close_btn, 100, 40);
    lv_obj_align(close_btn, LV_ALIGN_BOTTOM_MID, 0, -10);

    lv_obj_t *close_label = lv_label_create(close_btn);
    lv_label_set_text(close_label, "Close");
    lv_obj_center(close_label);

    lv_obj_add_event_cb(close_btn, modal_close_clicked, LV_EVENT_CLICKED, NULL);
}

/* --------------------------- Public API --------------------------- */
void time_settings_init_nvs(void) {
    if (nvs_ready) return;
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        ESP_ERROR_CHECK(nvs_flash_init());
    }
    nvs_ready = true;
}

/* Create a button that opens a modal timezone selector */
lv_obj_t *time_settings_create_timezone_selector(lv_obj_t *parent) {
    /* Restore previously saved selection (default to America/Chicago) */
    int default_idx = 1;
    int saved_idx = load_timezone_index_nvs(default_idx);
    if (saved_idx < 0 || saved_idx >= (int)k_tz_count) saved_idx = default_idx;

    current_idx = saved_idx;
    apply_timezone_from_index(saved_idx);

    /* Create button that shows current timezone */
    tz_button = lv_btn_create(parent);
    lv_obj_set_width(tz_button, 300);
    lv_obj_set_height(tz_button, 40);
    lv_obj_set_style_pad_all(tz_button, 10, 0);

    lv_obj_t *label = lv_label_create(tz_button);
    lv_label_set_text(label, k_tz_list[saved_idx].label);
    lv_obj_set_style_text_align(label, LV_TEXT_ALIGN_LEFT, 0);
    lv_obj_align(label, LV_ALIGN_LEFT_MID, 0, 0);

    // Add down arrow indicator
    lv_obj_t *arrow = lv_label_create(tz_button);
    lv_label_set_text(arrow, LV_SYMBOL_DOWN);
    lv_obj_align(arrow, LV_ALIGN_RIGHT_MID, 0, 0);

    lv_obj_add_event_cb(tz_button, tz_button_clicked, LV_EVENT_CLICKED, NULL);

    ESP_LOGI(TAG, "Timezone button created, current: %s", k_tz_list[saved_idx].label);

    return tz_button;
}

const char *time_settings_get_current_timezone_name(void) {
    if (current_idx < 0 || current_idx >= (int)k_tz_count) return NULL;
    return k_tz_list[current_idx].label;
}

const char *time_settings_get_current_timezone_posix(void) {
    if (current_idx < 0 || current_idx >= (int)k_tz_count) return NULL;
    return k_tz_list[current_idx].posix;
}
