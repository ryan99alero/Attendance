/**
 * UI Manager Implementation
 */

#include "ui_manager.h"
#include "network_manager.h"
#include "wifi_manager.h"
#include "ethernet_manager.h"
#include "firmware_info.h"
#include "api_client.h"
#include "time_settings.h"
#include "esp_log.h"
#include "esp_system.h"
#include "esp_mac.h"
#include "features.h"
#include "esp_chip_info.h"
#include "esp_flash.h"
#include "esp_psram.h"
#include "esp_netif.h"
#include "esp_partition.h"
#include "esp_ota_ops.h"
#include "esp_timer.h"
#include "esp_sntp.h"
#include <stdio.h>
#include <string.h>
#include <time.h>
#include <sys/time.h>

static const char *TAG = "UI_MANAGER";

// Color scheme - modern dark theme
#define COLOR_BG        lv_color_hex(0x1E1E1E)  // Dark gray background
#define COLOR_STATUS_BG lv_color_hex(0x2D2D2D)  // Slightly lighter for status bar
#define COLOR_PRIMARY   lv_color_hex(0x0078D4)  // Microsoft blue
#define COLOR_SUCCESS   lv_color_hex(0x107C10)  // Green
#define COLOR_ERROR     lv_color_hex(0xE81123)  // Red
#define COLOR_WARNING   lv_color_hex(0xFF8C00)  // Orange
#define COLOR_TEXT      lv_color_hex(0xFFFFFF)  // White text
#define COLOR_TEXT_DIM  lv_color_hex(0xA0A0A0)  // Dimmed text

// UI objects
static lv_obj_t *main_screen = NULL;
static lv_obj_t *status_bar = NULL;
static lv_obj_t *content_area = NULL;
static lv_obj_t *setup_screen = NULL;
static lv_obj_t *password_screen = NULL;
static lv_obj_t *password_keyboard = NULL;
static lv_obj_t *network_config_screen = NULL;
static lv_obj_t *network_keyboard = NULL;
static lv_obj_t *input_preview_label = NULL;

// Network configuration UI state
static lv_obj_t *network_scan_list = NULL;
static lv_obj_t *network_config_container = NULL;
static lv_obj_t *device_info_container = NULL;
static lv_obj_t *device_info_content = NULL;  // Content area for hardware/software info
static uint8_t device_info_tab = 0;  // 0=Hardware, 1=Software

// Time settings UI state
static lv_obj_t *time_settings_container = NULL;
static lv_obj_t *time_display_label = NULL;
static lv_timer_t *time_update_timer = NULL;
static lv_timer_t *main_clock_timer = NULL;  // Timer for updating main landing page clock
static lv_obj_t *time_hour_roller = NULL;
static lv_obj_t *time_minute_roller = NULL;
static lv_obj_t *time_ampm_roller = NULL;
static lv_obj_t *time_month_roller = NULL;
static lv_obj_t *time_day_roller = NULL;
static lv_obj_t *time_year_roller = NULL;
static lv_obj_t *time_ntp_input = NULL;
static lv_obj_t *time_use_server_switch = NULL;

static lv_obj_t *network_ssid_input = NULL;
static lv_obj_t *network_password_input = NULL;
static lv_obj_t *network_hostname_input = NULL;
static lv_obj_t *network_dhcp_switch = NULL;
static lv_obj_t *network_static_container = NULL;
static lv_obj_t *network_ip_input = NULL;
static lv_obj_t *network_gateway_input = NULL;
static lv_obj_t *network_netmask_input = NULL;
static lv_obj_t *network_dns1_input = NULL;
static lv_obj_t *network_dns2_input = NULL;
static lv_obj_t *network_status_label = NULL;

// WiFi scan state (for thread-safe UI updates)
static uint16_t pending_scan_results = 0;
static bool scan_results_ready = false;
static lv_timer_t *scan_update_timer = NULL;
static uint16_t scan_page_index = 0;  // Current page of scan results (0-based)
static uint16_t scan_total_results = 0;  // Total number of scan results
static lv_obj_t *scan_prev_btn = NULL;
static lv_obj_t *scan_next_btn = NULL;

// Status bar elements
static lv_obj_t *label_device_name = NULL;
static lv_obj_t *label_time = NULL;
static lv_obj_t *label_date = NULL;
static lv_obj_t *icon_network = NULL;
static lv_obj_t *icon_nfc = NULL;
static lv_obj_t *label_network_info = NULL;

// Content area elements
static lv_obj_t *label_main_message = NULL;
static lv_obj_t *label_employee_name = NULL;
static lv_obj_t *label_employee_details = NULL;
static lv_obj_t *label_card_info = NULL;
static lv_obj_t *label_timestamp = NULL;
static lv_obj_t *icon_card_large = NULL;
static lv_obj_t *label_large_time = NULL;  // Large centered clock
static lv_obj_t *label_large_date = NULL;  // Large centered date

// Setup button
static lv_obj_t *btn_setup = NULL;

// Callbacks
static setup_password_callback_t password_callback = NULL;

// Forward declarations
static void create_status_bar(lv_obj_t *parent, const char *device_name);
static void create_content_area(lv_obj_t *parent);
static void create_setup_button(lv_obj_t *parent);
static void setup_button_clicked(lv_event_t *e);
static void create_password_screen(void);
static void password_entered(lv_event_t *e);
static void main_clock_tick(lv_timer_t *t);  // Timer callback for main landing page clock
static void keyboard_done_clicked(lv_event_t *e);  // Keyboard Done/Cancel handler
static void textarea_kb_close_cb(lv_event_t *e);  // Textarea keyboard close handler

lv_obj_t* ui_manager_init(const char *device_name) {
    ESP_LOGI(TAG, "Initializing UI Manager");

    // Initialize NVS for time settings persistence
    time_settings_init_nvs();

    // Create main screen
    main_screen = lv_scr_act();
    lv_obj_set_style_bg_color(main_screen, COLOR_BG, 0);

    // Disable scrolling on main screen
    lv_obj_clear_flag(main_screen, LV_OBJ_FLAG_SCROLLABLE);

    // Create status bar at top
    create_status_bar(main_screen, device_name);

    // Create main content area
    create_content_area(main_screen);

    // Create setup button (bottom right corner)
    create_setup_button(main_screen);

    // Create 1-second timer for updating landing page clock (ChatGPT fix)
    main_clock_timer = lv_timer_create(main_clock_tick, 1000, NULL);
    main_clock_tick(NULL);  // Update immediately to show current time

    // Show ready screen by default
    ui_show_ready_screen("Place card on reader");

    ESP_LOGI(TAG, "UI initialized with main clock timer");
    return main_screen;
}

static void create_status_bar(lv_obj_t *parent, const char *device_name) {
    // Status bar container - 60px height
    status_bar = lv_obj_create(parent);
    lv_obj_set_size(status_bar, LV_PCT(100), 60);
    lv_obj_align(status_bar, LV_ALIGN_TOP_MID, 0, 0);
    lv_obj_set_style_bg_color(status_bar, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_width(status_bar, 0, 0);
    lv_obj_set_style_radius(status_bar, 0, 0);
    lv_obj_set_style_pad_all(status_bar, 10, 0);

    // Network icon (left side)
    icon_network = lv_label_create(status_bar);
    lv_label_set_text(icon_network, LV_SYMBOL_WIFI);
    lv_obj_align(icon_network, LV_ALIGN_LEFT_MID, 10, 0);
    lv_obj_set_style_text_font(icon_network, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(icon_network, COLOR_TEXT_DIM, 0);

    // Network info text (next to network icon)
    label_network_info = lv_label_create(status_bar);
    lv_label_set_text(label_network_info, "Disconnected");
    lv_obj_align_to(label_network_info, icon_network, LV_ALIGN_OUT_RIGHT_MID, 10, 0);
    lv_obj_set_style_text_color(label_network_info, COLOR_TEXT_DIM, 0);

    // NFC status icon (left of network, but separate)
    icon_nfc = lv_label_create(status_bar);
    lv_label_set_text(icon_nfc, LV_SYMBOL_BLUETOOTH);  // Using bluetooth icon for NFC
    lv_obj_align_to(icon_nfc, label_network_info, LV_ALIGN_OUT_RIGHT_MID, 20, 0);
    lv_obj_set_style_text_font(icon_nfc, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(icon_nfc, COLOR_SUCCESS, 0);

    // Device name (center)
    label_device_name = lv_label_create(status_bar);
    lv_label_set_text(label_device_name, device_name);
    lv_obj_align(label_device_name, LV_ALIGN_CENTER, 0, -5);
    lv_obj_set_style_text_font(label_device_name, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_device_name, COLOR_PRIMARY, 0);

    // Time (right side)
    label_time = lv_label_create(status_bar);
    lv_label_set_text(label_time, "00:00");
    lv_obj_align(label_time, LV_ALIGN_TOP_RIGHT, -10, 5);
    lv_obj_set_style_text_font(label_time, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_time, COLOR_TEXT, 0);

    // Date (below time)
    label_date = lv_label_create(status_bar);
    lv_label_set_text(label_date, "---");
    lv_obj_align(label_date, LV_ALIGN_BOTTOM_RIGHT, -10, -5);
    lv_obj_set_style_text_font(label_date, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_date, COLOR_TEXT_DIM, 0);
}

static void create_content_area(lv_obj_t *parent) {
    // Main content area (below status bar, above setup button area)
    content_area = lv_obj_create(parent);
    lv_obj_set_size(content_area, LV_PCT(100), LV_PCT(100) - 120);  // Leave space for status bar and button
    lv_obj_align(content_area, LV_ALIGN_TOP_MID, 0, 60);
    lv_obj_set_style_bg_color(content_area, COLOR_BG, 0);
    lv_obj_set_style_border_width(content_area, 0, 0);
    lv_obj_set_style_radius(content_area, 0, 0);
    lv_obj_set_flex_flow(content_area, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(content_area, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_pad_all(content_area, 20, 0);

    // Disable scrolling on content area
    lv_obj_clear_flag(content_area, LV_OBJ_FLAG_SCROLLABLE);

    // Large clock display (shown on ready screen) - ChatGPT fix: use large font + good contrast
    label_large_time = lv_label_create(content_area);
    lv_label_set_text(label_large_time, "--:--");
    lv_obj_set_style_text_font(label_large_time, &lv_font_montserrat_48, 0);  // Actually large font!
    lv_obj_set_style_text_color(label_large_time, COLOR_TEXT, 0);  // High contrast (not COLOR_PRIMARY)
    lv_obj_set_style_text_align(label_large_time, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_set_style_text_letter_space(label_large_time, 6, 0);
    lv_obj_set_style_pad_all(label_large_time, 8, 0);
    // No tinted background - removed for visibility

    // Large date display (below clock)
    label_large_date = lv_label_create(content_area);
    lv_label_set_text(label_large_date, "---");
    lv_obj_set_style_text_font(label_large_date, &lv_font_montserrat_28, 0);  // Large font for date
    lv_obj_set_style_text_color(label_large_date, COLOR_TEXT_DIM, 0);  // Dimmed but visible
    lv_obj_set_style_text_align(label_large_date, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_set_style_pad_all(label_large_date, 4, 0);

    // Large card icon (shown on ready screen)
    icon_card_large = lv_label_create(content_area);
    lv_label_set_text(icon_card_large, LV_SYMBOL_CALL);  // Using call icon as card placeholder
    lv_obj_set_style_text_font(icon_card_large, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(icon_card_large, COLOR_PRIMARY, 0);

    // Main message (ready screen or status)
    label_main_message = lv_label_create(content_area);
    lv_label_set_text(label_main_message, "Place card on reader");
    lv_obj_set_style_text_font(label_main_message, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_main_message, COLOR_TEXT, 0);
    lv_obj_set_style_text_align(label_main_message, LV_TEXT_ALIGN_CENTER, 0);

    // Employee name (large, for card scan result)
    label_employee_name = lv_label_create(content_area);
    lv_label_set_text(label_employee_name, "");
    lv_obj_set_style_text_font(label_employee_name, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_employee_name, COLOR_SUCCESS, 0);
    lv_obj_set_style_text_align(label_employee_name, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_add_flag(label_employee_name, LV_OBJ_FLAG_HIDDEN);

    // Employee details (department, ID)
    label_employee_details = lv_label_create(content_area);
    lv_label_set_text(label_employee_details, "");
    lv_obj_set_style_text_font(label_employee_details, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_employee_details, COLOR_TEXT, 0);
    lv_obj_set_style_text_align(label_employee_details, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_add_flag(label_employee_details, LV_OBJ_FLAG_HIDDEN);

    // Card info (UID, type)
    label_card_info = lv_label_create(content_area);
    lv_label_set_text(label_card_info, "");
    lv_obj_set_style_text_font(label_card_info, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_card_info, COLOR_TEXT_DIM, 0);
    lv_obj_set_style_text_align(label_card_info, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_add_flag(label_card_info, LV_OBJ_FLAG_HIDDEN);

    // Timestamp
    label_timestamp = lv_label_create(content_area);
    lv_label_set_text(label_timestamp, "");
    lv_obj_set_style_text_font(label_timestamp, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(label_timestamp, COLOR_TEXT, 0);
    lv_obj_set_style_text_align(label_timestamp, LV_TEXT_ALIGN_CENTER, 0);
    lv_obj_add_flag(label_timestamp, LV_OBJ_FLAG_HIDDEN);
}

static void create_setup_button(lv_obj_t *parent) {
    // Small setup button in bottom right corner
    btn_setup = lv_btn_create(parent);
    lv_obj_set_size(btn_setup, 80, 50);
    lv_obj_align(btn_setup, LV_ALIGN_BOTTOM_RIGHT, -20, -10);
    lv_obj_set_style_bg_color(btn_setup, COLOR_STATUS_BG, 0);
    lv_obj_set_style_bg_color(btn_setup, COLOR_PRIMARY, LV_STATE_PRESSED);

    lv_obj_t *label = lv_label_create(btn_setup);
    lv_label_set_text(label, LV_SYMBOL_SETTINGS);
    lv_obj_set_style_text_font(label, &lv_font_montserrat_14, 0);
    lv_obj_center(label);

    lv_obj_add_event_cb(btn_setup, setup_button_clicked, LV_EVENT_CLICKED, NULL);
}

static void setup_button_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Setup button clicked");
    create_password_screen();
}

static void create_password_screen(void) {
    // Create password entry screen overlay
    password_screen = lv_obj_create(lv_scr_act());
    lv_obj_set_size(password_screen, LV_PCT(100), LV_PCT(100));
    lv_obj_set_style_bg_color(password_screen, lv_color_hex(0x000000), 0);
    lv_obj_set_style_bg_opa(password_screen, LV_OPA_90, 0);
    lv_obj_center(password_screen);
    lv_obj_clear_flag(password_screen, LV_OBJ_FLAG_SCROLLABLE);

    // Password entry container - compact layout at top
    // Screen is 1024x600, keyboard will be 240px, so container can be 320px tall
    lv_obj_t *container = lv_obj_create(password_screen);
    lv_obj_set_size(container, 900, 130);
    lv_obj_align(container, LV_ALIGN_TOP_MID, 0, 15);
    lv_obj_set_style_bg_color(container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(container, 2, 0);
    lv_obj_clear_flag(container, LV_OBJ_FLAG_SCROLLABLE);

    // Title
    lv_obj_t *title = lv_label_create(container);
    lv_label_set_text(title, "Admin Access - Enter Password");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 10);

    // Password input (using textarea with password mode)
    lv_obj_t *password_input = lv_textarea_create(container);
    lv_obj_set_size(password_input, 500, 45);
    lv_obj_align(password_input, LV_ALIGN_TOP_MID, 0, 50);
    lv_textarea_set_placeholder_text(password_input, "Enter password");
    lv_textarea_set_password_mode(password_input, true);
    lv_textarea_set_one_line(password_input, true);
    lv_obj_set_style_text_font(password_input, &lv_font_montserrat_14, 0);

    // OK button - to the right of password input
    lv_obj_t *btn_ok = lv_btn_create(container);
    lv_obj_set_size(btn_ok, 120, 45);
    lv_obj_align(btn_ok, LV_ALIGN_TOP_RIGHT, -50, 50);
    lv_obj_set_style_bg_color(btn_ok, COLOR_SUCCESS, 0);
    lv_obj_add_event_cb(btn_ok, password_entered, LV_EVENT_CLICKED, password_input);

    lv_obj_t *btn_ok_label = lv_label_create(btn_ok);
    lv_label_set_text(btn_ok_label, "OK");
    lv_obj_set_style_text_font(btn_ok_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_ok_label);

    // Cancel button - to the left of password input
    lv_obj_t *btn_cancel = lv_btn_create(container);
    lv_obj_set_size(btn_cancel, 120, 45);
    lv_obj_align(btn_cancel, LV_ALIGN_TOP_LEFT, 50, 50);
    lv_obj_set_style_bg_color(btn_cancel, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_cancel, password_entered, LV_EVENT_CLICKED, NULL);  // NULL = cancel

    lv_obj_t *btn_cancel_label = lv_label_create(btn_cancel);
    lv_label_set_text(btn_cancel_label, "Cancel");
    lv_obj_set_style_text_font(btn_cancel_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_cancel_label);

    // Create keyboard - more compact for 7" screen
    // 1024x600 screen, use 240px height (40% of screen) instead of 300px (50%)
    password_keyboard = lv_keyboard_create(password_screen);
    lv_obj_set_size(password_keyboard, LV_PCT(100), 240);
    lv_obj_align(password_keyboard, LV_ALIGN_BOTTOM_MID, 0, 0);

    // Set keyboard mode to text (includes special characters)
    lv_keyboard_set_mode(password_keyboard, LV_KEYBOARD_MODE_TEXT_LOWER);

    // Attach keyboard to password textarea
    lv_keyboard_set_textarea(password_keyboard, password_input);
}

static void password_entered(lv_event_t *e) {
    lv_obj_t *password_input = lv_event_get_user_data(e);

    if (password_input == NULL) {
        // Cancel clicked
        ESP_LOGI(TAG, "Password entry cancelled");
        lv_obj_del(password_screen);
        password_screen = NULL;
        password_keyboard = NULL;
        return;
    }

    const char *password = lv_textarea_get_text(password_input);
    ESP_LOGI(TAG, "Password entered: %s", password);

    // Call callback to validate password
    if (password_callback != NULL) {
        bool is_valid = false;
        password_callback(password, &is_valid);

        if (is_valid) {
            ESP_LOGI(TAG, "Password valid, showing setup screen");
            lv_obj_del(password_screen);
            password_screen = NULL;
            password_keyboard = NULL;
            ui_show_setup_screen();
        } else {
            ESP_LOGW(TAG, "Invalid password");
            // Show error - change input border to red
            lv_obj_set_style_border_color(password_input, COLOR_ERROR, 0);
            lv_obj_set_style_border_width(password_input, 3, 0);
        }
    } else {
        ESP_LOGW(TAG, "No password callback set");
        lv_obj_del(password_screen);
        password_screen = NULL;
    }
}

void ui_update_status(network_status_t net_status, nfc_status_t nfc_status) {
    if (icon_network == NULL || icon_nfc == NULL) return;

    // Update network icon and color
    switch (net_status) {
        case NET_STATUS_WIFI_CONNECTED:
            lv_label_set_text(icon_network, LV_SYMBOL_WIFI);
            lv_obj_set_style_text_color(icon_network, COLOR_SUCCESS, 0);
            lv_label_set_text(label_network_info, "WiFi");
            break;
        case NET_STATUS_ETHERNET_CONNECTED:
            lv_label_set_text(icon_network, LV_SYMBOL_USB);  // Use USB icon for ethernet
            lv_obj_set_style_text_color(icon_network, COLOR_SUCCESS, 0);
            lv_label_set_text(label_network_info, "Ethernet");
            break;
        case NET_STATUS_CONNECTING:
            lv_label_set_text(icon_network, LV_SYMBOL_WIFI);
            lv_obj_set_style_text_color(icon_network, COLOR_WARNING, 0);
            lv_label_set_text(label_network_info, "Connecting...");
            break;
        case NET_STATUS_DISCONNECTED:
        default:
            lv_label_set_text(icon_network, LV_SYMBOL_WIFI);
            lv_obj_set_style_text_color(icon_network, COLOR_ERROR, 0);
            lv_label_set_text(label_network_info, "Disconnected");
            break;
    }

    // Update NFC icon and color
    switch (nfc_status) {
        case NFC_STATUS_READY:
            lv_obj_set_style_text_color(icon_nfc, COLOR_SUCCESS, 0);
            break;
        case NFC_STATUS_READING:
            lv_obj_set_style_text_color(icon_nfc, COLOR_PRIMARY, 0);
            break;
        case NFC_STATUS_ERROR:
            lv_obj_set_style_text_color(icon_nfc, COLOR_ERROR, 0);
            break;
        case NFC_STATUS_DISABLED:
        default:
            lv_obj_set_style_text_color(icon_nfc, COLOR_TEXT_DIM, 0);
            break;
    }
}

void ui_update_time(const char *time_str, const char *date_str) {
    // Update small time/date in status bar
    if (label_time != NULL) {
        lv_label_set_text(label_time, time_str);
    }
    if (label_date != NULL) {
        lv_label_set_text(label_date, date_str);
    }

    // Update large clock display in content area (no padding needed with large fonts)
    if (label_large_time != NULL) {
        lv_label_set_text(label_large_time, time_str);
    }
    if (label_large_date != NULL) {
        lv_label_set_text(label_large_date, date_str);
    }
}

void ui_show_card_scan(const card_scan_result_t *result, uint32_t display_duration_ms) {
    if (result == NULL || content_area == NULL) return;

    // Hide ready screen elements
    lv_obj_add_flag(icon_card_large, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_main_message, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_large_time, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_large_date, LV_OBJ_FLAG_HIDDEN);

    // Show scan result elements
    lv_obj_clear_flag(label_employee_name, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_employee_details, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_card_info, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_timestamp, LV_OBJ_FLAG_HIDDEN);

    // Update content
    if (result->success && result->employee.is_authorized) {
        lv_label_set_text(label_employee_name, result->employee.name);
        lv_obj_set_style_text_color(label_employee_name, COLOR_SUCCESS, 0);

        char details[256];
        snprintf(details, sizeof(details),
                 "ID: %s | %s\n"
                 "Today: %.1f hrs | Week: %.1f hrs\n"
                 "Pay Period: %.1f hrs | Vacation: %.1f hrs",
                 result->employee.employee_id,
                 result->employee.department,
                 result->employee.today_hours,
                 result->employee.week_hours,
                 result->employee.pay_period_hours,
                 result->employee.vacation_balance);
        lv_label_set_text(label_employee_details, details);
    } else {
        lv_label_set_text(label_employee_name, "UNAUTHORIZED");
        lv_obj_set_style_text_color(label_employee_name, COLOR_ERROR, 0);
        lv_label_set_text(label_employee_details, result->message);
    }

    char card_info[128];
    snprintf(card_info, sizeof(card_info), "Card: %s (%s)",
             result->card_uid, result->card_type);
    lv_label_set_text(label_card_info, card_info);

    lv_label_set_text(label_timestamp, result->timestamp);

    // Auto-hide after duration
    if (display_duration_ms > 0) {
        // TODO: Add timer to call ui_show_ready_screen() after duration
    }
}

void ui_show_ready_screen(const char *message) {
    if (content_area == NULL) return;

    // Hide scan result elements
    lv_obj_add_flag(label_employee_name, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_employee_details, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_card_info, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_timestamp, LV_OBJ_FLAG_HIDDEN);

    // Show ready screen elements
    lv_obj_clear_flag(icon_card_large, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_main_message, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_large_time, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_large_date, LV_OBJ_FLAG_HIDDEN);

    lv_label_set_text(label_main_message, message);
    lv_obj_set_style_text_color(label_main_message, COLOR_TEXT, 0);
}

void ui_show_error(const char *error_msg, uint32_t duration_ms) {
    if (label_main_message == NULL) return;

    // Show error in main message area
    lv_obj_clear_flag(label_main_message, LV_OBJ_FLAG_HIDDEN);
    lv_label_set_text(label_main_message, error_msg);
    lv_obj_set_style_text_color(label_main_message, COLOR_ERROR, 0);

    // TODO: Add timer to return to ready screen after duration
}

void ui_set_setup_callback(setup_password_callback_t callback) {
    password_callback = callback;
}

// Setup screen button event handlers
static void setup_back_clicked(lv_event_t *e) {
    ui_hide_setup_screen();
}

// Helper function to get WiFi band from channel
static const char* get_wifi_band_string(uint8_t channel) {
    if (channel >= 1 && channel <= 14) {
        return "2.4GHz";  // 2.4 GHz band (channels 1-14)
    } else if (channel >= 36 && channel <= 177) {
        return "5GHz";    // 5 GHz band (channels 36-177)
    } else if (channel >= 1 && channel <= 233) {
        return "6GHz";    // 6 GHz band (channels 1-233 in 6GHz operation)
    }
    return "Unknown";
}

// Network configuration event handlers - Forward declarations
static void network_back_clicked(lv_event_t *e);
static void network_interface_selected(lv_event_t *e);
static void network_dhcp_toggled(lv_event_t *e);
static void network_save_and_connect_clicked(lv_event_t *e);
static void network_input_focused(lv_event_t *e);
static void network_scan_clicked(lv_event_t *e);
static void network_scan_done(uint16_t num_results);
static void network_scan_update_ui(lv_timer_t *timer);
static void network_scan_item_clicked(lv_event_t *e);
static void network_scan_button_cleanup(lv_event_t *e);
static void scan_prev_page_clicked(lv_event_t *e);
static void scan_next_page_clicked(lv_event_t *e);
static void create_network_config_ui(void);
static void create_ethernet_config_ui(void);
static void create_interface_selection_screen(void);

// Interface selection screen - choose WiFi or Ethernet
static void create_interface_selection_screen(void) {
    ESP_LOGI(TAG, "Showing interface selection screen");

    // Delete existing config container if any
    if (network_config_container != NULL) {
        lv_obj_del(network_config_container);
        network_config_container = NULL;
    }

    // Create interface selection container
    network_config_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(network_config_container, 900, 280);
    lv_obj_align(network_config_container, LV_ALIGN_TOP_MID, 0, 15);
    lv_obj_set_style_bg_color(network_config_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(network_config_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(network_config_container, 2, 0);
    lv_obj_clear_flag(network_config_container, LV_OBJ_FLAG_SCROLLABLE);

    // Title
    lv_obj_t *title = lv_label_create(network_config_container);
    lv_label_set_text(title, "Select Network Interface");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 20);

    // Left column container for WiFi and Ethernet buttons
    lv_obj_t *left_container = lv_obj_create(network_config_container);
    lv_obj_set_size(left_container, 500, 200);
    lv_obj_align(left_container, LV_ALIGN_LEFT_MID, 20, 15);
    lv_obj_set_style_bg_opa(left_container, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(left_container, 0, 0);
    lv_obj_set_style_pad_all(left_container, 0, 0);
    lv_obj_set_flex_flow(left_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(left_container, LV_FLEX_ALIGN_SPACE_EVENLY, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(left_container, LV_OBJ_FLAG_SCROLLABLE);

    // WiFi button
    lv_obj_t *btn_wifi = lv_btn_create(left_container);
    lv_obj_set_size(btn_wifi, 450, 70);
    lv_obj_set_style_bg_color(btn_wifi, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_wifi, network_interface_selected, LV_EVENT_CLICKED, (void*)0); // 0 = WiFi

    lv_obj_t *btn_wifi_label = lv_label_create(btn_wifi);
    lv_label_set_text(btn_wifi_label, LV_SYMBOL_WIFI "  WiFi Network");
    lv_obj_set_style_text_font(btn_wifi_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_wifi_label);

    // Ethernet button
    lv_obj_t *btn_ethernet = lv_btn_create(left_container);
    lv_obj_set_size(btn_ethernet, 450, 70);
    lv_obj_set_style_bg_color(btn_ethernet, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_ethernet, network_interface_selected, LV_EVENT_CLICKED, (void*)1); // 1 = Ethernet

    lv_obj_t *btn_ethernet_label = lv_label_create(btn_ethernet);
    lv_label_set_text(btn_ethernet_label, LV_SYMBOL_USB "  Wired / Ethernet");
    lv_obj_set_style_text_font(btn_ethernet_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_ethernet_label);

    // Right column container for Back button
    lv_obj_t *right_container = lv_obj_create(network_config_container);
    lv_obj_set_size(right_container, 320, 200);
    lv_obj_align(right_container, LV_ALIGN_RIGHT_MID, -20, 15);
    lv_obj_set_style_bg_opa(right_container, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(right_container, 0, 0);
    lv_obj_set_style_pad_all(right_container, 0, 0);
    lv_obj_set_flex_flow(right_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(right_container, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(right_container, LV_OBJ_FLAG_SCROLLABLE);

    // Back button
    lv_obj_t *btn_back = lv_btn_create(right_container);
    lv_obj_set_size(btn_back, 200, 70);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, network_back_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Back");
    lv_obj_set_style_text_font(btn_back_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_back_label);
}

// Ethernet save and connect button clicked
static void ethernet_save_and_connect_clicked(lv_event_t *e);

// Create Ethernet configuration screen
static void create_ethernet_config_ui(void) {
    ESP_LOGI(TAG, "Creating Ethernet configuration UI");

    // Delete interface selection container if it exists
    if (network_config_container != NULL) {
        lv_obj_del(network_config_container);
        network_config_container = NULL;
    }

    // Create scrollable config container (320px height, leaves room for keyboard)
    network_config_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(network_config_container, 900, 320);
    lv_obj_align(network_config_container, LV_ALIGN_TOP_MID, 0, 15);
    lv_obj_set_style_bg_color(network_config_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(network_config_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(network_config_container, 2, 0);
    lv_obj_set_flex_flow(network_config_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(network_config_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_pad_all(network_config_container, 15, 0);
    lv_obj_set_style_pad_row(network_config_container, 8, 0);

    // Title
    lv_obj_t *title = lv_label_create(network_config_container);
    lv_label_set_text(title, "Ethernet Configuration");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);

    // Status label
    network_status_label = lv_label_create(network_config_container);
    lv_label_set_text(network_status_label, "Disconnected");
    lv_obj_set_style_text_font(network_status_label, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(network_status_label, COLOR_TEXT_DIM, 0);

    // Hostname input
    lv_obj_t *hostname_label = lv_label_create(network_config_container);
    lv_label_set_text(hostname_label, "Hostname:");
    lv_obj_set_style_text_font(hostname_label, &lv_font_montserrat_14, 0);

    network_hostname_input = lv_textarea_create(network_config_container);
    lv_obj_set_size(network_hostname_input, 850, 35);
    lv_textarea_set_placeholder_text(network_hostname_input, "Device hostname (e.g., Clock1)");
    lv_textarea_set_one_line(network_hostname_input, true);
    lv_obj_set_style_text_font(network_hostname_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_hostname_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // DHCP toggle
    lv_obj_t *dhcp_container = lv_obj_create(network_config_container);
    lv_obj_set_size(dhcp_container, 850, 35);
    lv_obj_set_style_bg_opa(dhcp_container, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(dhcp_container, 0, 0);
    lv_obj_clear_flag(dhcp_container, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *dhcp_label = lv_label_create(dhcp_container);
    lv_label_set_text(dhcp_label, "DHCP (Auto IP):");
    lv_obj_set_style_text_font(dhcp_label, &lv_font_montserrat_14, 0);
    lv_obj_align(dhcp_label, LV_ALIGN_LEFT_MID, 0, 0);

    network_dhcp_switch = lv_switch_create(dhcp_container);
    lv_obj_align(network_dhcp_switch, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_obj_add_state(network_dhcp_switch, LV_STATE_CHECKED);  // DHCP enabled by default
    lv_obj_add_event_cb(network_dhcp_switch, network_dhcp_toggled, LV_EVENT_VALUE_CHANGED, NULL);

    // Static IP fields container (hidden by default)
    network_static_container = lv_obj_create(network_config_container);
    lv_obj_set_size(network_static_container, 850, 200);
    lv_obj_set_style_bg_color(network_static_container, COLOR_BG, 0);
    lv_obj_set_flex_flow(network_static_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(network_static_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_pad_all(network_static_container, 10, 0);
    lv_obj_set_style_pad_row(network_static_container, 5, 0);
    lv_obj_add_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);  // Hidden by default

    // Static IP inputs (same as WiFi config)
    lv_obj_t *ip_label = lv_label_create(network_static_container);
    lv_label_set_text(ip_label, "IP Address:");
    lv_obj_set_style_text_font(ip_label, &lv_font_montserrat_14, 0);
    network_ip_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_ip_input, 800, 30);
    lv_textarea_set_placeholder_text(network_ip_input, "e.g., 192.168.1.100");
    lv_textarea_set_one_line(network_ip_input, true);
    lv_obj_add_event_cb(network_ip_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    lv_obj_t *gw_label = lv_label_create(network_static_container);
    lv_label_set_text(gw_label, "Gateway:");
    lv_obj_set_style_text_font(gw_label, &lv_font_montserrat_14, 0);
    network_gateway_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_gateway_input, 800, 30);
    lv_textarea_set_placeholder_text(network_gateway_input, "e.g., 192.168.1.1");
    lv_textarea_set_one_line(network_gateway_input, true);
    lv_obj_add_event_cb(network_gateway_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    lv_obj_t *nm_label = lv_label_create(network_static_container);
    lv_label_set_text(nm_label, "Netmask:");
    lv_obj_set_style_text_font(nm_label, &lv_font_montserrat_14, 0);
    network_netmask_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_netmask_input, 800, 30);
    lv_textarea_set_placeholder_text(network_netmask_input, "e.g., 255.255.255.0");
    lv_textarea_set_one_line(network_netmask_input, true);
    lv_obj_add_event_cb(network_netmask_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    lv_obj_t *dns1_label = lv_label_create(network_static_container);
    lv_label_set_text(dns1_label, "DNS Primary:");
    lv_obj_set_style_text_font(dns1_label, &lv_font_montserrat_14, 0);
    network_dns1_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_dns1_input, 800, 30);
    lv_textarea_set_placeholder_text(network_dns1_input, "e.g., 8.8.8.8");
    lv_textarea_set_one_line(network_dns1_input, true);
    lv_obj_add_event_cb(network_dns1_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    lv_obj_t *dns2_label = lv_label_create(network_static_container);
    lv_label_set_text(dns2_label, "DNS Secondary:");
    lv_obj_set_style_text_font(dns2_label, &lv_font_montserrat_14, 0);
    network_dns2_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_dns2_input, 800, 30);
    lv_textarea_set_placeholder_text(network_dns2_input, "e.g., 8.8.4.4 (optional)");
    lv_textarea_set_one_line(network_dns2_input, true);
    lv_obj_add_event_cb(network_dns2_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // Buttons container
    lv_obj_t *btn_container = lv_obj_create(network_config_container);
    lv_obj_set_size(btn_container, 850, 40);
    lv_obj_set_style_bg_opa(btn_container, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(btn_container, 0, 0);
    lv_obj_set_flex_flow(btn_container, LV_FLEX_FLOW_ROW);
    lv_obj_set_flex_align(btn_container, LV_FLEX_ALIGN_SPACE_EVENLY, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(btn_container, LV_OBJ_FLAG_SCROLLABLE);

    // Save & Connect button
    lv_obj_t *btn_save = lv_btn_create(btn_container);
    lv_obj_set_size(btn_save, 250, 35);
    lv_obj_set_style_bg_color(btn_save, COLOR_SUCCESS, 0);
    lv_obj_add_event_cb(btn_save, ethernet_save_and_connect_clicked, LV_EVENT_CLICKED, NULL);
    lv_obj_t *btn_save_label = lv_label_create(btn_save);
    lv_label_set_text(btn_save_label, "Save & Connect");
    lv_obj_set_style_text_font(btn_save_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_save_label);

    // Back button
    lv_obj_t *btn_back = lv_btn_create(btn_container);
    lv_obj_set_size(btn_back, 150, 35);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, network_back_clicked, LV_EVENT_CLICKED, NULL);
    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Back");
    lv_obj_set_style_text_font(btn_back_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_back_label);

    // Try to load saved configuration (will add ethernet_manager include at top of file)
    // TODO: Load Ethernet config from ethernet_manager
}

// Interface selected - show appropriate config screen
static void network_interface_selected(lv_event_t *e) {
    uintptr_t interface_type = (uintptr_t)lv_event_get_user_data(e);

    if (interface_type == 0) {
        ESP_LOGI(TAG, "WiFi interface selected");
        create_network_config_ui();  // WiFi config
    } else {
        ESP_LOGI(TAG, "Ethernet interface selected");
        create_ethernet_config_ui();  // Ethernet config
    }
}

// DHCP toggle switched
static void network_dhcp_toggled(lv_event_t *e) {
    lv_obj_t *sw = lv_event_get_target(e);
    bool dhcp_enabled = lv_obj_has_state(sw, LV_STATE_CHECKED);

    ESP_LOGI(TAG, "DHCP %s", dhcp_enabled ? "enabled" : "disabled");

    // Show/hide static IP fields
    if (network_static_container != NULL) {
        if (dhcp_enabled) {
            lv_obj_add_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_clear_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);
        }
    }
}

// Create comprehensive network configuration screen
static void create_network_config_ui(void) {
    ESP_LOGI(TAG, "Creating network configuration UI");

    // Delete scan container if it exists
    if (network_config_container != NULL) {
        lv_obj_del(network_config_container);
        network_config_container = NULL;
    }

    // Create compact config container (no scrolling needed)
    network_config_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(network_config_container, 900, 520);
    lv_obj_align(network_config_container, LV_ALIGN_TOP_MID, 0, 15);
    lv_obj_set_style_bg_color(network_config_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(network_config_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(network_config_container, 2, 0);
    lv_obj_clear_flag(network_config_container, LV_OBJ_FLAG_SCROLLABLE);

    // Title and Status row
    lv_obj_t *header_row = lv_obj_create(network_config_container);
    lv_obj_set_size(header_row, 850, 40);
    lv_obj_align(header_row, LV_ALIGN_TOP_MID, 0, 10);
    lv_obj_set_style_bg_opa(header_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(header_row, 0, 0);
    lv_obj_set_style_pad_all(header_row, 0, 0);
    lv_obj_clear_flag(header_row, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *title = lv_label_create(header_row);
    lv_label_set_text(title, "WiFi Configuration");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_LEFT_MID, 0, 0);

    network_status_label = lv_label_create(header_row);
    lv_label_set_text(network_status_label, wifi_manager_is_connected() ? "Connected" : "Disconnected");
    lv_obj_set_style_text_font(network_status_label, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(network_status_label, COLOR_TEXT_DIM, 0);
    lv_obj_align(network_status_label, LV_ALIGN_RIGHT_MID, 0, 0);

    // SSID row
    lv_obj_t *ssid_row = lv_obj_create(network_config_container);
    lv_obj_set_size(ssid_row, 850, 35);
    lv_obj_align(ssid_row, LV_ALIGN_TOP_MID, 0, 60);
    lv_obj_set_style_bg_opa(ssid_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(ssid_row, 0, 0);
    lv_obj_set_style_pad_all(ssid_row, 0, 0);
    lv_obj_clear_flag(ssid_row, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *ssid_label = lv_label_create(ssid_row);
    lv_label_set_text(ssid_label, "SSID:");
    lv_obj_set_style_text_font(ssid_label, &lv_font_montserrat_14, 0);
    lv_obj_align(ssid_label, LV_ALIGN_LEFT_MID, 0, 0);

    network_ssid_input = lv_textarea_create(ssid_row);
    lv_obj_set_size(network_ssid_input, 680, 35);
    lv_obj_align(network_ssid_input, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_textarea_set_placeholder_text(network_ssid_input, "WiFi network name");
    lv_textarea_set_one_line(network_ssid_input, true);
    lv_obj_set_style_text_font(network_ssid_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_ssid_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // Password row
    lv_obj_t *pwd_row = lv_obj_create(network_config_container);
    lv_obj_set_size(pwd_row, 850, 35);
    lv_obj_align(pwd_row, LV_ALIGN_TOP_MID, 0, 105);
    lv_obj_set_style_bg_opa(pwd_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(pwd_row, 0, 0);
    lv_obj_set_style_pad_all(pwd_row, 0, 0);
    lv_obj_clear_flag(pwd_row, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *pwd_label = lv_label_create(pwd_row);
    lv_label_set_text(pwd_label, "Password:");
    lv_obj_set_style_text_font(pwd_label, &lv_font_montserrat_14, 0);
    lv_obj_align(pwd_label, LV_ALIGN_LEFT_MID, 0, 0);

    network_password_input = lv_textarea_create(pwd_row);
    lv_obj_set_size(network_password_input, 680, 35);
    lv_obj_align(network_password_input, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_textarea_set_placeholder_text(network_password_input, "WiFi password");
    lv_textarea_set_password_mode(network_password_input, true);
    lv_textarea_set_one_line(network_password_input, true);
    lv_obj_set_style_text_font(network_password_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_password_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // Hostname row
    lv_obj_t *host_row = lv_obj_create(network_config_container);
    lv_obj_set_size(host_row, 850, 35);
    lv_obj_align(host_row, LV_ALIGN_TOP_MID, 0, 150);
    lv_obj_set_style_bg_opa(host_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(host_row, 0, 0);
    lv_obj_set_style_pad_all(host_row, 0, 0);
    lv_obj_clear_flag(host_row, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *hostname_label = lv_label_create(host_row);
    lv_label_set_text(hostname_label, "Hostname:");
    lv_obj_set_style_text_font(hostname_label, &lv_font_montserrat_14, 0);
    lv_obj_align(hostname_label, LV_ALIGN_LEFT_MID, 0, 0);

    network_hostname_input = lv_textarea_create(host_row);
    lv_obj_set_size(network_hostname_input, 680, 35);
    lv_obj_align(network_hostname_input, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_textarea_set_placeholder_text(network_hostname_input, "e.g., Clock1");
    lv_textarea_set_one_line(network_hostname_input, true);
    lv_obj_set_style_text_font(network_hostname_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_hostname_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // DHCP toggle row
    lv_obj_t *dhcp_row = lv_obj_create(network_config_container);
    lv_obj_set_size(dhcp_row, 850, 35);
    lv_obj_align(dhcp_row, LV_ALIGN_TOP_MID, 0, 195);
    lv_obj_set_style_bg_opa(dhcp_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(dhcp_row, 0, 0);
    lv_obj_set_style_pad_all(dhcp_row, 0, 0);
    lv_obj_clear_flag(dhcp_row, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *dhcp_label = lv_label_create(dhcp_row);
    lv_label_set_text(dhcp_label, "DHCP (Auto IP):");
    lv_obj_set_style_text_font(dhcp_label, &lv_font_montserrat_14, 0);
    lv_obj_align(dhcp_label, LV_ALIGN_LEFT_MID, 0, 0);

    network_dhcp_switch = lv_switch_create(dhcp_row);
    lv_obj_align(network_dhcp_switch, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_obj_add_state(network_dhcp_switch, LV_STATE_CHECKED);  // DHCP enabled by default
    lv_obj_add_event_cb(network_dhcp_switch, network_dhcp_toggled, LV_EVENT_VALUE_CHANGED, NULL);

    // Static IP fields container (hidden by default) - note: not displayed on initial screen
    network_static_container = lv_obj_create(network_config_container);
    lv_obj_set_size(network_static_container, 850, 200);
    lv_obj_align(network_static_container, LV_ALIGN_TOP_MID, 0, 240);
    lv_obj_set_style_bg_opa(network_static_container, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(network_static_container, 0, 0);
    lv_obj_set_style_pad_all(network_static_container, 0, 0);
    lv_obj_add_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);  // Hidden by default
    lv_obj_clear_flag(network_static_container, LV_OBJ_FLAG_SCROLLABLE);

    // Static IP inputs - compact inline layout
    network_ip_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_ip_input, 400, 30);
    lv_obj_align(network_ip_input, LV_ALIGN_TOP_LEFT, 0, 0);
    lv_textarea_set_placeholder_text(network_ip_input, "IP: 192.168.1.100");
    lv_textarea_set_one_line(network_ip_input, true);
    lv_obj_add_event_cb(network_ip_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    network_gateway_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_gateway_input, 400, 30);
    lv_obj_align(network_gateway_input, LV_ALIGN_TOP_RIGHT, 0, 0);
    lv_textarea_set_placeholder_text(network_gateway_input, "Gateway: 192.168.1.1");
    lv_textarea_set_one_line(network_gateway_input, true);
    lv_obj_add_event_cb(network_gateway_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    network_netmask_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_netmask_input, 400, 30);
    lv_obj_align(network_netmask_input, LV_ALIGN_TOP_LEFT, 0, 40);
    lv_textarea_set_placeholder_text(network_netmask_input, "Netmask: 255.255.255.0");
    lv_textarea_set_one_line(network_netmask_input, true);
    lv_obj_add_event_cb(network_netmask_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    network_dns1_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_dns1_input, 400, 30);
    lv_obj_align(network_dns1_input, LV_ALIGN_TOP_RIGHT, 0, 40);
    lv_textarea_set_placeholder_text(network_dns1_input, "DNS1: 8.8.8.8");
    lv_textarea_set_one_line(network_dns1_input, true);
    lv_obj_add_event_cb(network_dns1_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    network_dns2_input = lv_textarea_create(network_static_container);
    lv_obj_set_size(network_dns2_input, 400, 30);
    lv_obj_align(network_dns2_input, LV_ALIGN_TOP_LEFT, 0, 80);
    lv_textarea_set_placeholder_text(network_dns2_input, "DNS2: 8.8.4.4 (optional)");
    lv_textarea_set_one_line(network_dns2_input, true);
    lv_obj_add_event_cb(network_dns2_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // Buttons at bottom
    lv_obj_t *btn_row = lv_obj_create(network_config_container);
    lv_obj_set_size(btn_row, 850, 45);
    lv_obj_align(btn_row, LV_ALIGN_BOTTOM_MID, 0, -10);
    lv_obj_set_style_bg_opa(btn_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(btn_row, 0, 0);
    lv_obj_set_style_pad_all(btn_row, 0, 0);
    lv_obj_clear_flag(btn_row, LV_OBJ_FLAG_SCROLLABLE);

    // Scan WiFi button
    lv_obj_t *btn_scan = lv_btn_create(btn_row);
    lv_obj_set_size(btn_scan, 180, 40);
    lv_obj_align(btn_scan, LV_ALIGN_LEFT_MID, 0, 0);
    lv_obj_set_style_bg_color(btn_scan, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_scan, network_scan_clicked, LV_EVENT_CLICKED, NULL);
    lv_obj_t *btn_scan_label = lv_label_create(btn_scan);
    lv_label_set_text(btn_scan_label, LV_SYMBOL_WIFI " Scan WiFi");
    lv_obj_set_style_text_font(btn_scan_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_scan_label);

    // Save & Connect button
    lv_obj_t *btn_save = lv_btn_create(btn_row);
    lv_obj_set_size(btn_save, 250, 40);
    lv_obj_align(btn_save, LV_ALIGN_CENTER, 0, 0);
    lv_obj_set_style_bg_color(btn_save, COLOR_SUCCESS, 0);
    lv_obj_add_event_cb(btn_save, network_save_and_connect_clicked, LV_EVENT_CLICKED, NULL);
    lv_obj_t *btn_save_label = lv_label_create(btn_save);
    lv_label_set_text(btn_save_label, "Save & Connect");
    lv_obj_set_style_text_font(btn_save_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_save_label);

    // Back button
    lv_obj_t *btn_back = lv_btn_create(btn_row);
    lv_obj_set_size(btn_back, 150, 40);
    lv_obj_align(btn_back, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, network_back_clicked, LV_EVENT_CLICKED, NULL);
    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Back");
    lv_obj_set_style_text_font(btn_back_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_back_label);

    // Try to load saved configuration
    wifi_network_config_t saved_config;
    if (wifi_manager_load_config(&saved_config) == ESP_OK) {
        lv_textarea_set_text(network_ssid_input, saved_config.ssid);
        lv_textarea_set_text(network_password_input, saved_config.password);
        lv_textarea_set_text(network_hostname_input, saved_config.hostname);

        if (saved_config.use_dhcp) {
            lv_obj_add_state(network_dhcp_switch, LV_STATE_CHECKED);
            lv_obj_add_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_clear_state(network_dhcp_switch, LV_STATE_CHECKED);
            lv_obj_clear_flag(network_static_container, LV_OBJ_FLAG_HIDDEN);
            lv_textarea_set_text(network_ip_input, saved_config.static_ip);
            lv_textarea_set_text(network_gateway_input, saved_config.static_gateway);
            lv_textarea_set_text(network_netmask_input, saved_config.static_netmask);
            lv_textarea_set_text(network_dns1_input, saved_config.static_dns_primary);
            lv_textarea_set_text(network_dns2_input, saved_config.static_dns_secondary);
        }
    }
}

// Input preview text changed
static void input_preview_text_changed(lv_event_t *e) {
    lv_obj_t *textarea = lv_event_get_target(e);
    if (input_preview_label != NULL) {
        const char *text = lv_textarea_get_text(textarea);
        if (text != NULL && strlen(text) > 0) {
            lv_label_set_text(input_preview_label, text);
        } else {
            lv_label_set_text(input_preview_label, "[Empty]");
        }
    }
}

// Keyboard Done/Cancel button clicked - hide keyboard and save value
static void keyboard_done_clicked(lv_event_t *e) {
    lv_event_code_t code = lv_event_get_code(e);

    ESP_LOGI(TAG, "Keyboard event: %d", code);

    // Handle READY (OK button) or CANCEL (close button) events
    if (code == LV_EVENT_READY || code == LV_EVENT_CANCEL) {
        ESP_LOGI(TAG, "Keyboard %s clicked - hiding keyboard", code == LV_EVENT_READY ? "Done" : "Cancel");

        // Hide keyboard and overlay
        if (network_keyboard != NULL) {
            lv_obj_add_flag(network_keyboard, LV_OBJ_FLAG_HIDDEN);
        }
        if (network_config_screen != NULL) {
            lv_obj_add_flag(network_config_screen, LV_OBJ_FLAG_HIDDEN);
        }
        if (input_preview_label != NULL) {
            lv_obj_add_flag(input_preview_label, LV_OBJ_FLAG_HIDDEN);
        }

        // Clear focus to update display
        lv_obj_t *ta = lv_keyboard_get_textarea(network_keyboard);
        if (ta != NULL) {
            lv_obj_clear_state(ta, LV_STATE_FOCUSED);
        }
    }
}

// Textarea keyboard close callback - handles textarea events for keyboard close
static void textarea_kb_close_cb(lv_event_t *e) {
    lv_event_code_t code = lv_event_get_code(e);

    // Update preview label when value changes
    if (code == LV_EVENT_VALUE_CHANGED) {
        if (input_preview_label) {
            const char *txt = lv_textarea_get_text(lv_event_get_target(e));
            lv_label_set_text(input_preview_label, txt ? txt : "");
        }
        return;
    }

    // Close keyboard on READY, CANCEL, or DEFOCUSED events
    if (code == LV_EVENT_READY || code == LV_EVENT_CANCEL || code == LV_EVENT_DEFOCUSED) {
        ESP_LOGI(TAG, "Textarea event %d - closing keyboard", code);

        // Just hide, don't delete (safer during event processing)
        if (network_keyboard) {
            lv_obj_add_flag(network_keyboard, LV_OBJ_FLAG_HIDDEN);
        }
        if (network_config_screen) {
            lv_obj_add_flag(network_config_screen, LV_OBJ_FLAG_HIDDEN);
        }
        if (input_preview_label) {
            lv_obj_add_flag(input_preview_label, LV_OBJ_FLAG_HIDDEN);
        }
    }
}

// Robust keyboard helper - ensures keyboard shows and is wired correctly (ChatGPT fix)
static void show_keyboard_for(lv_obj_t *ta) {
    if (!ta) return;

    // Ensure overlay + keyboard exist
    if (network_config_screen == NULL) {
        network_config_screen = lv_obj_create(lv_scr_act());
        lv_obj_set_size(network_config_screen, LV_PCT(100), LV_PCT(100));
        lv_obj_set_style_bg_color(network_config_screen, lv_color_hex(0x000000), 0);
        lv_obj_set_style_bg_opa(network_config_screen, LV_OPA_90, 0);
        lv_obj_center(network_config_screen);
    }
    // Make sure overlay is visible
    lv_obj_clear_flag(network_config_screen, LV_OBJ_FLAG_HIDDEN);
    if (network_keyboard == NULL) {
        network_keyboard = lv_keyboard_create(network_config_screen);
        lv_obj_set_size(network_keyboard, LV_PCT(100), 240);
        lv_obj_align(network_keyboard, LV_ALIGN_BOTTOM_MID, 0, 0);
        lv_keyboard_set_mode(network_keyboard, LV_KEYBOARD_MODE_TEXT_LOWER);

        // Add event handler to catch all keyboard events for debugging
        lv_obj_add_event_cb(network_keyboard, keyboard_done_clicked, LV_EVENT_ALL, NULL);

        // Red preview label
        input_preview_label = lv_label_create(network_config_screen);
        lv_obj_set_size(input_preview_label, 950, 50);
        lv_obj_align_to(input_preview_label, network_keyboard, LV_ALIGN_OUT_TOP_MID, 0, -10);
        lv_obj_set_style_bg_color(input_preview_label, lv_color_hex(0xFF4444), 0);
        lv_obj_set_style_bg_opa(input_preview_label, LV_OPA_90, 0);
        lv_obj_set_style_border_color(input_preview_label, lv_color_hex(0xFFFFFF), 0);
        lv_obj_set_style_border_width(input_preview_label, 2, 0);
        lv_obj_set_style_radius(input_preview_label, 8, 0);
        lv_obj_set_style_pad_all(input_preview_label, 10, 0);
        lv_obj_set_style_text_color(input_preview_label, lv_color_hex(0xFFFFFF), 0);
        lv_obj_set_style_text_font(input_preview_label, &lv_font_montserrat_14, 0);
        lv_label_set_text(input_preview_label, "");
        lv_label_set_long_mode(input_preview_label, LV_LABEL_LONG_SCROLL_CIRCULAR);
    }

    // Attach keyboard to this textarea
    lv_keyboard_set_textarea(network_keyboard, ta);

    // Remove any existing callback first to prevent duplicates, then add new one
    lv_obj_remove_event_cb(ta, textarea_kb_close_cb);
    lv_obj_add_event_cb(ta, textarea_kb_close_cb, LV_EVENT_ALL, NULL);

    // Make sure keyboard and preview are visible and on top
    lv_obj_clear_flag(network_keyboard, LV_OBJ_FLAG_HIDDEN);
    lv_obj_move_foreground(network_keyboard);
    if (input_preview_label) {
        lv_obj_clear_flag(input_preview_label, LV_OBJ_FLAG_HIDDEN);
        lv_obj_move_foreground(input_preview_label);
    }

    // Focus the textarea explicitly (helps on some builds)
    lv_obj_add_state(ta, LV_STATE_FOCUSED);
    lv_textarea_set_cursor_click_pos(ta, true);

    // Initialize preview with current text or placeholder
    if (input_preview_label) {
        const char *text = lv_textarea_get_text(ta);
        if (text && text[0]) {
            lv_label_set_text(input_preview_label, text);
        } else {
            const char *ph = lv_textarea_get_placeholder_text(ta);
            if (ph) {
                char buf[128];
                snprintf(buf, sizeof(buf), "[Placeholder: %s]", ph);
                lv_label_set_text(input_preview_label, buf);
            } else {
                lv_label_set_text(input_preview_label, "[Empty]");
            }
        }
    }

    // Ensure VALUE_CHANGED keeps the red preview in sync
    if (!lv_obj_has_flag(ta, LV_OBJ_FLAG_USER_1)) {
        lv_obj_add_event_cb(ta, input_preview_text_changed, LV_EVENT_VALUE_CHANGED, NULL);
        lv_obj_add_flag(ta, LV_OBJ_FLAG_USER_1);
    }

    ESP_LOGI(TAG, "Keyboard shown for textarea");
}

// Input field focused - attach keyboard (updated to use robust helper)
static void network_input_focused(lv_event_t *e) {
    lv_obj_t *textarea = lv_event_get_target(e);
    lv_event_code_t code = lv_event_get_code(e);

    // Handle PRESSED, CLICKED, and FOCUSED events (ChatGPT fix)
    switch (code) {
        case LV_EVENT_PRESSED:
        case LV_EVENT_CLICKED:
        case LV_EVENT_FOCUSED:
            show_keyboard_for(textarea);
            break;
        default:
            break;
    }
}

// Save configuration and connect
static void network_save_and_connect_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Save & Connect clicked");

    // Gather configuration from UI
    wifi_network_config_t config = {0};
    strncpy(config.ssid, lv_textarea_get_text(network_ssid_input), sizeof(config.ssid) - 1);
    strncpy(config.password, lv_textarea_get_text(network_password_input), sizeof(config.password) - 1);
    strncpy(config.hostname, lv_textarea_get_text(network_hostname_input), sizeof(config.hostname) - 1);
    config.use_dhcp = lv_obj_has_state(network_dhcp_switch, LV_STATE_CHECKED);

    if (!config.use_dhcp) {
        strncpy(config.static_ip, lv_textarea_get_text(network_ip_input), sizeof(config.static_ip) - 1);
        strncpy(config.static_gateway, lv_textarea_get_text(network_gateway_input), sizeof(config.static_gateway) - 1);
        strncpy(config.static_netmask, lv_textarea_get_text(network_netmask_input), sizeof(config.static_netmask) - 1);
        strncpy(config.static_dns_primary, lv_textarea_get_text(network_dns1_input), sizeof(config.static_dns_primary) - 1);
        strncpy(config.static_dns_secondary, lv_textarea_get_text(network_dns2_input), sizeof(config.static_dns_secondary) - 1);
    }

    config.max_retry = 3;

    // Validate SSID
    if (strlen(config.ssid) == 0) {
        ESP_LOGW(TAG, "SSID is empty");
        lv_label_set_text(network_status_label, "Error: SSID required");
        lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        return;
    }

    // Update status
    lv_label_set_text(network_status_label, "Saving and connecting...");
    lv_obj_set_style_text_color(network_status_label, COLOR_WARNING, 0);

    // Save to NVS
    esp_err_t ret = wifi_manager_save_config(&config);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to save WiFi config");
        lv_label_set_text(network_status_label, "Error: Failed to save");
        lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        return;
    }

    ESP_LOGI(TAG, "WiFi configuration saved to NVS");

    // Switch to WiFi mode (stops Ethernet if running, initializes WiFi, applies config, and connects)
    lv_label_set_text(network_status_label, "Connecting...");
    lv_obj_set_style_text_color(network_status_label, COLOR_WARNING, 0);
    lv_refr_now(NULL);  // Force UI update

    ret = network_manager_switch_to_wifi();
    if (ret == ESP_OK) {
        ESP_LOGI(TAG, "WiFi switch initiated, waiting for connection...");

        // Wait up to 10 seconds for connection
        bool connected = false;
        char ip_str[16] = {0};
        for (int i = 0; i < 20; i++) {  // 20 x 500ms = 10 seconds
            vTaskDelay(pdMS_TO_TICKS(500));
            if (wifi_manager_is_connected()) {
                wifi_manager_get_ip_string(ip_str, sizeof(ip_str));
                connected = true;
                break;
            }
        }

        if (connected) {
            ESP_LOGI(TAG, "✅ WiFi connected successfully: %s", ip_str);
            char msg[64];
            snprintf(msg, sizeof(msg), "✅ Connected\nIP: %s", ip_str);
            lv_label_set_text(network_status_label, msg);
            lv_obj_set_style_text_color(network_status_label, COLOR_SUCCESS, 0);
        } else {
            ESP_LOGW(TAG, "WiFi connection timeout");
            lv_label_set_text(network_status_label, "Connection timeout\nCheck credentials");
            lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        }
    } else {
        ESP_LOGE(TAG, "Failed to switch to WiFi mode");
        lv_label_set_text(network_status_label, "Failed to switch to WiFi");
        lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
    }
}

// Ethernet save configuration and connect
static void ethernet_save_and_connect_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Ethernet Save & Connect clicked");

    // Gather configuration from UI
    ethernet_config_t config = {0};
    strncpy(config.hostname, lv_textarea_get_text(network_hostname_input), sizeof(config.hostname) - 1);
    config.use_dhcp = lv_obj_has_state(network_dhcp_switch, LV_STATE_CHECKED);

    if (!config.use_dhcp) {
        strncpy(config.static_ip, lv_textarea_get_text(network_ip_input), sizeof(config.static_ip) - 1);
        strncpy(config.static_gateway, lv_textarea_get_text(network_gateway_input), sizeof(config.static_gateway) - 1);
        strncpy(config.static_netmask, lv_textarea_get_text(network_netmask_input), sizeof(config.static_netmask) - 1);
        strncpy(config.static_dns_primary, lv_textarea_get_text(network_dns1_input), sizeof(config.static_dns_primary) - 1);
        strncpy(config.static_dns_secondary, lv_textarea_get_text(network_dns2_input), sizeof(config.static_dns_secondary) - 1);
    }

    // Update status
    lv_label_set_text(network_status_label, "Saving and connecting...");
    lv_obj_set_style_text_color(network_status_label, COLOR_WARNING, 0);

    // Save to NVS
    esp_err_t ret = ethernet_manager_save_config(&config);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to save Ethernet config");
        lv_label_set_text(network_status_label, "Error: Failed to save");
        lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        return;
    }

    ESP_LOGI(TAG, "Ethernet configuration saved to NVS");

    // Switch to Ethernet mode (stops WiFi, initializes Ethernet, applies config, and starts)
    lv_label_set_text(network_status_label, "Connecting...");
    lv_obj_set_style_text_color(network_status_label, COLOR_WARNING, 0);
    lv_refr_now(NULL);  // Force UI update

    ret = network_manager_switch_to_ethernet();
    if (ret == ESP_OK) {
        ESP_LOGI(TAG, "Ethernet switch initiated, waiting for connection...");

        // Wait up to 10 seconds for connection
        bool connected = false;
        char ip_str[16] = {0};
        for (int i = 0; i < 20; i++) {  // 20 x 500ms = 10 seconds
            vTaskDelay(pdMS_TO_TICKS(500));
            if (ethernet_manager_is_connected()) {
                ethernet_manager_get_ip_string(ip_str, sizeof(ip_str));
                connected = true;
                break;
            }
        }

        if (connected) {
            ESP_LOGI(TAG, "✅ Ethernet connected successfully: %s", ip_str);
            char msg[64];
            snprintf(msg, sizeof(msg), "✅ Connected\nIP: %s", ip_str);
            lv_label_set_text(network_status_label, msg);
            lv_obj_set_style_text_color(network_status_label, COLOR_SUCCESS, 0);
        } else {
            ESP_LOGW(TAG, "Ethernet connection timeout");
            lv_label_set_text(network_status_label, "Connection timeout\nCheck cable & settings");
            lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        }
    } else {
        ESP_LOGE(TAG, "Failed to switch to Ethernet mode");
        lv_label_set_text(network_status_label, "Failed to switch to Ethernet");
        lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
    }
}

// Back button - return to setup menu
static void network_back_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Network config back clicked");

    // Clean up allocated SSID strings from scan results
    if (network_scan_list != NULL) {
        // Note: LVGL will handle cleanup of widgets, we just need to ensure
        // malloc'd user data is freed if we stored any persistent references
        network_scan_list = NULL;
    }

    // Delete network config screen
    if (network_config_screen != NULL) {
        lv_obj_del(network_config_screen);
        network_config_screen = NULL;
        network_keyboard = NULL;
        network_config_container = NULL;
        network_ssid_input = NULL;
        network_password_input = NULL;
        network_hostname_input = NULL;
        network_dhcp_switch = NULL;
        network_static_container = NULL;
        network_ip_input = NULL;
        network_gateway_input = NULL;
        network_netmask_input = NULL;
        network_dns1_input = NULL;
        network_dns2_input = NULL;
        network_status_label = NULL;
    }

    // Show setup screen again
    if (setup_screen != NULL) {
        lv_obj_clear_flag(setup_screen, LV_OBJ_FLAG_HIDDEN);
    }
}

// WiFi scan button clicked - start async scan
static void network_scan_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "WiFi scan button clicked");

    // Reset pagination to first page for new scan
    scan_page_index = 0;
    scan_total_results = 0;

    // Update status
    if (network_status_label != NULL) {
        lv_label_set_text(network_status_label, "Scanning for networks...");
        lv_obj_set_style_text_color(network_status_label, COLOR_WARNING, 0);
    }

    // Create/restart timer to poll for scan results
    if (scan_update_timer != NULL) {
        lv_timer_del(scan_update_timer);
    }
    // Check every 100ms for scan completion
    scan_update_timer = lv_timer_create(network_scan_update_ui, 100, NULL);

    // Start async scan with callback
    esp_err_t ret = wifi_manager_scan_async(network_scan_done);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start WiFi scan: %s", esp_err_to_name(ret));
        if (network_status_label != NULL) {
            lv_label_set_text(network_status_label, "Failed to start scan");
            lv_obj_set_style_text_color(network_status_label, COLOR_ERROR, 0);
        }
        // Stop timer if scan failed to start
        if (scan_update_timer != NULL) {
            lv_timer_del(scan_update_timer);
            scan_update_timer = NULL;
        }
    }
}

// Callback when async WiFi scan completes (called from WiFi event task)
// This runs in a different task context, so we can't modify LVGL objects directly
static void network_scan_done(uint16_t num_results) {
    ESP_LOGI(TAG, "WiFi scan completed, found %d networks", num_results);

    // Store results count and set flag
    pending_scan_results = num_results;
    scan_results_ready = true;

    // The timer will pick this up and update the UI from the LVGL task
}

// Timer callback to update UI with scan results (runs in LVGL task)
static void network_scan_update_ui(lv_timer_t *timer) {
    // Check if we have results to process
    if (!scan_results_ready) {
        return;
    }

    scan_results_ready = false;
    uint16_t num_results = pending_scan_results;

    ESP_LOGI(TAG, "Processing scan results in UI: %d networks", num_results);

    // Update status
    if (network_status_label != NULL) {
        char buf[64];
        snprintf(buf, sizeof(buf), "Found %d networks", num_results);
        lv_label_set_text(network_status_label, buf);
        lv_obj_set_style_text_color(network_status_label, COLOR_SUCCESS, 0);
    }

    // Delete old scan list if exists
    if (network_scan_list != NULL) {
        lv_obj_del(network_scan_list);
        network_scan_list = NULL;
    }

    if (num_results == 0) {
        // Stop timer after processing
        if (scan_update_timer != NULL) {
            lv_timer_del(scan_update_timer);
            scan_update_timer = NULL;
        }
        return;
    }

    // Create scan results container with simple buttons (not using lv_list)
    if (network_config_container != NULL) {
        // Get scan results first
        wifi_scan_result_t results[WIFI_MAX_SCAN_RESULTS];
        uint16_t count = 0;
        esp_err_t ret = wifi_manager_get_scan_results(results, WIFI_MAX_SCAN_RESULTS, &count);

        ESP_LOGI(TAG, "Attempting to create scan list: ret=%d, count=%d, container=%p",
                 ret, count, network_config_container);

        if (ret == ESP_OK && count > 0) {
            // Store total results for pagination
            scan_total_results = count;

            // Calculate pagination
            const uint16_t networks_per_page = 5;
            uint16_t total_pages = (count + networks_per_page - 1) / networks_per_page;
            uint16_t start_idx = scan_page_index * networks_per_page;
            uint16_t end_idx = start_idx + networks_per_page;
            if (end_idx > count) end_idx = count;
            uint16_t display_count = end_idx - start_idx;

            ESP_LOGI(TAG, "Pagination: page %d/%d, showing networks %d-%d of %d",
                     scan_page_index + 1, total_pages, start_idx + 1, end_idx, count);

            // Create a simple container for scan results
            network_scan_list = lv_obj_create(network_config_container);
            if (network_scan_list == NULL) {
                ESP_LOGE(TAG, "Failed to create scan list container!");
                return;
            }

            // Position below DHCP toggle at y=240, above buttons (which are at bottom -10)
            lv_obj_set_size(network_scan_list, 850, 215);  // Height for 5 network buttons + 1 pagination button
            lv_obj_set_pos(network_scan_list, 25, 240);
            lv_obj_set_style_bg_color(network_scan_list, lv_color_hex(0x1e1e1e), 0);
            lv_obj_set_style_border_color(network_scan_list, COLOR_PRIMARY, 0);
            lv_obj_set_style_border_width(network_scan_list, 3, 0);
            lv_obj_set_style_pad_all(network_scan_list, 8, 0);
            lv_obj_set_style_pad_row(network_scan_list, 5, 0);
            lv_obj_set_flex_flow(network_scan_list, LV_FLEX_FLOW_COLUMN);
            lv_obj_clear_flag(network_scan_list, LV_OBJ_FLAG_HIDDEN);
            lv_obj_clear_flag(network_scan_list, LV_OBJ_FLAG_SCROLLABLE);
            lv_obj_move_foreground(network_scan_list);

            ESP_LOGI(TAG, "Scan container created at %p, adding up to %d networks...", network_scan_list, display_count);

            // Add network buttons for current page (skip empty SSIDs)
            uint16_t buttons_added = 0;
            for (uint16_t i = 0; i < display_count && buttons_added < 5; i++) {
                uint16_t result_idx = start_idx + i;

                // Skip networks with empty SSIDs
                if (results[result_idx].ssid[0] == '\0' || strlen(results[result_idx].ssid) == 0) {
                    ESP_LOGW(TAG, "  Skipping network %d with empty SSID", result_idx);
                    continue;
                }

                char item_text[128];
                const char *auth_str = wifi_manager_get_authmode_string(results[result_idx].authmode);
                const char *band_str = get_wifi_band_string(results[result_idx].channel);
                snprintf(item_text, sizeof(item_text), "%s (%d dBm) %s - %s",
                         results[result_idx].ssid, results[result_idx].rssi, band_str, auth_str);

                // Create button directly in container
                lv_obj_t *btn = lv_btn_create(network_scan_list);
                if (btn == NULL) {
                    ESP_LOGE(TAG, "Failed to create button for network %d", result_idx);
                    continue;
                }

                lv_obj_set_size(btn, 820, 35);
                lv_obj_set_style_bg_color(btn, COLOR_PRIMARY, 0);
                lv_obj_set_style_radius(btn, 5, 0);

                lv_obj_t *label = lv_label_create(btn);
                if (label == NULL) {
                    ESP_LOGE(TAG, "Failed to create label for network %d", result_idx);
                    lv_obj_del(btn);
                    continue;
                }

                lv_label_set_text(label, item_text);
                lv_obj_set_style_text_font(label, &lv_font_montserrat_14, 0);
                lv_obj_center(label);

                // Store SSID in user data and add cleanup handler
                char *ssid_copy = malloc(WIFI_MAX_SSID_LEN);
                if (ssid_copy) {
                    strncpy(ssid_copy, results[result_idx].ssid, WIFI_MAX_SSID_LEN - 1);
                    ssid_copy[WIFI_MAX_SSID_LEN - 1] = '\0';
                    lv_obj_add_event_cb(btn, network_scan_item_clicked, LV_EVENT_CLICKED, ssid_copy);
                    // Add delete handler to free the SSID when button is deleted
                    lv_obj_add_event_cb(btn, network_scan_button_cleanup, LV_EVENT_DELETE, ssid_copy);
                } else {
                    ESP_LOGE(TAG, "Failed to allocate SSID copy for network %d", result_idx);
                }

                ESP_LOGI(TAG, "  Added button %d: %s", buttons_added, results[result_idx].ssid);
                buttons_added++;
            }

            // Add pagination buttons only if there are multiple pages
            if (total_pages > 1) {
                // Previous button (only show if not on first page)
                if (scan_page_index > 0) {
                    scan_prev_btn = lv_btn_create(network_scan_list);
                    lv_obj_set_size(scan_prev_btn, 150, 30);
                    lv_obj_set_style_bg_color(scan_prev_btn, COLOR_PRIMARY, 0);
                    lv_obj_add_event_cb(scan_prev_btn, scan_prev_page_clicked, LV_EVENT_CLICKED, NULL);

                    lv_obj_t *prev_label = lv_label_create(scan_prev_btn);
                    lv_label_set_text(prev_label, "< Previous");
                    lv_obj_center(prev_label);

                    ESP_LOGI(TAG, "  Added Previous button");
                }

                // Next button (only show if not on last page)
                if (scan_page_index < total_pages - 1) {
                    scan_next_btn = lv_btn_create(network_scan_list);
                    lv_obj_set_size(scan_next_btn, 150, 30);
                    lv_obj_set_style_bg_color(scan_next_btn, COLOR_PRIMARY, 0);
                    lv_obj_add_event_cb(scan_next_btn, scan_next_page_clicked, LV_EVENT_CLICKED, NULL);

                    lv_obj_t *next_label = lv_label_create(scan_next_btn);
                    lv_label_set_text(next_label, "Next >");
                    lv_obj_center(next_label);

                    ESP_LOGI(TAG, "  Added Next button");
                }
            }

            lv_obj_invalidate(network_scan_list);
            lv_refr_now(NULL);  // Force immediate screen refresh
            ESP_LOGI(TAG, "Scan list COMPLETE with %d networks on page %d/%d, widget=%p",
                     display_count, scan_page_index + 1, total_pages, network_scan_list);
        } else {
            ESP_LOGW(TAG, "No scan results to display (ret=%d, count=%d)", ret, count);
        }
    } else {
        ESP_LOGE(TAG, "network_config_container is NULL!");
    }

    // Stop timer after processing
    if (scan_update_timer != NULL) {
        lv_timer_del(scan_update_timer);
        scan_update_timer = NULL;
    }
}

// Scan result item clicked - populate SSID field
static void network_scan_item_clicked(lv_event_t *e) {
    char *ssid = (char*)lv_event_get_user_data(e);
    if (ssid != NULL && network_ssid_input != NULL) {
        ESP_LOGI(TAG, "Selected network: %s", ssid);
        lv_textarea_set_text(network_ssid_input, ssid);

        // Update status
        if (network_status_label != NULL) {
            char buf[64];
            snprintf(buf, sizeof(buf), "Selected: %s", ssid);
            lv_label_set_text(network_status_label, buf);
            lv_obj_set_style_text_color(network_status_label, COLOR_SUCCESS, 0);
        }

        // Note: Don't free ssid here - it will be freed by DELETE event handler

        // Hide scan list (this will trigger DELETE events on all children)
        if (network_scan_list != NULL) {
            lv_obj_del(network_scan_list);
            network_scan_list = NULL;
        }
    }
}

// Cleanup handler for scan button deletion - frees malloc'd SSID
static void network_scan_button_cleanup(lv_event_t *e) {
    char *ssid = (char*)lv_event_get_user_data(e);
    if (ssid != NULL) {
        ESP_LOGI(TAG, "Freeing SSID string: %p", ssid);
        free(ssid);
    }
}

// Previous page button clicked
static void scan_prev_page_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Previous page clicked, current page: %d", scan_page_index);

    if (scan_page_index > 0) {
        scan_page_index--;

        // Delete current scan list and recreate with new page
        if (network_scan_list != NULL) {
            lv_obj_del(network_scan_list);
            network_scan_list = NULL;
        }

        // Mark results as ready to trigger UI update
        scan_results_ready = true;

        // Create timer to update UI (runs on LVGL thread)
        if (scan_update_timer == NULL) {
            scan_update_timer = lv_timer_create(network_scan_update_ui, 50, NULL);
            lv_timer_set_repeat_count(scan_update_timer, 1);
        }
    }
}

// Next page button clicked
static void scan_next_page_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Next page clicked, current page: %d, total: %d", scan_page_index, scan_total_results);

    const uint16_t networks_per_page = 5;
    uint16_t total_pages = (scan_total_results + networks_per_page - 1) / networks_per_page;

    if (scan_page_index < total_pages - 1) {
        scan_page_index++;

        // Delete current scan list and recreate with new page
        if (network_scan_list != NULL) {
            lv_obj_del(network_scan_list);
            network_scan_list = NULL;
        }

        // Mark results as ready to trigger UI update
        scan_results_ready = true;

        // Create timer to update UI (runs on LVGL thread)
        if (scan_update_timer == NULL) {
            scan_update_timer = lv_timer_create(network_scan_update_ui, 50, NULL);
            lv_timer_set_repeat_count(scan_update_timer, 1);
        }
    }
}

// Entry point from setup menu
static void setup_network_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Network settings clicked");

    // Hide setup screen
    if (setup_screen != NULL) {
        lv_obj_add_flag(setup_screen, LV_OBJ_FLAG_HIDDEN);
    }

    // Create network config screen overlay
    network_config_screen = lv_obj_create(lv_scr_act());
    lv_obj_set_size(network_config_screen, LV_PCT(100), LV_PCT(100));
    lv_obj_set_style_bg_color(network_config_screen, lv_color_hex(0x000000), 0);
    lv_obj_set_style_bg_opa(network_config_screen, LV_OPA_90, 0);
    lv_obj_center(network_config_screen);
    lv_obj_clear_flag(network_config_screen, LV_OBJ_FLAG_SCROLLABLE);

    // Create keyboard at bottom (shared for all screens)
    network_keyboard = lv_keyboard_create(network_config_screen);
    lv_obj_set_size(network_keyboard, LV_PCT(100), 240);
    lv_obj_align(network_keyboard, LV_ALIGN_BOTTOM_MID, 0, 0);
    lv_keyboard_set_mode(network_keyboard, LV_KEYBOARD_MODE_TEXT_LOWER);

    // Show interface selection screen first (WiFi or Ethernet)
    create_interface_selection_screen();
}

// Forward declarations for device info tab handlers
static void device_info_tab_clicked(lv_event_t *e);
static void device_info_update_content(void);

static void setup_device_info_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Device info clicked");

    // Hide setup screen
    if (setup_screen != NULL) {
        lv_obj_add_flag(setup_screen, LV_OBJ_FLAG_HIDDEN);
    }

    // Delete existing info container if any
    if (device_info_container != NULL) {
        lv_obj_del(device_info_container);
        device_info_container = NULL;
    }

    // Delete existing network config container if any
    if (network_config_container != NULL) {
        lv_obj_del(network_config_container);
        network_config_container = NULL;
    }

    // Create or ensure network config screen is visible
    if (network_config_screen == NULL) {
        network_config_screen = lv_obj_create(lv_scr_act());
        lv_obj_set_size(network_config_screen, LV_PCT(100), LV_PCT(100));
        lv_obj_set_style_bg_color(network_config_screen, lv_color_hex(0x000000), 0);
        lv_obj_set_style_bg_opa(network_config_screen, LV_OPA_90, 0);
        lv_obj_center(network_config_screen);
        lv_obj_clear_flag(network_config_screen, LV_OBJ_FLAG_SCROLLABLE);
        ESP_LOGI(TAG, "Created network_config_screen overlay");
    } else {
        lv_obj_clear_flag(network_config_screen, LV_OBJ_FLAG_HIDDEN);
    }

    // Reset to Hardware tab
    device_info_tab = 0;

    // Create info container
    device_info_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(device_info_container, 950, 540);
    lv_obj_align(device_info_container, LV_ALIGN_TOP_MID, 0, 10);
    lv_obj_set_style_bg_color(device_info_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(device_info_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(device_info_container, 2, 0);
    lv_obj_clear_flag(device_info_container, LV_OBJ_FLAG_SCROLLABLE);

    // Title
    lv_obj_t *title = lv_label_create(device_info_container);
    lv_label_set_text(title, "Device Information");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 10);

    // Tab buttons row
    lv_obj_t *tab_row = lv_obj_create(device_info_container);
    lv_obj_set_size(tab_row, 900, 45);
    lv_obj_set_pos(tab_row, 25, 40);
    lv_obj_set_style_bg_opa(tab_row, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(tab_row, 0, 0);
    lv_obj_set_style_pad_all(tab_row, 0, 0);
    lv_obj_clear_flag(tab_row, LV_OBJ_FLAG_SCROLLABLE);

    // Hardware tab button
    lv_obj_t *btn_hw = lv_btn_create(tab_row);
    lv_obj_set_size(btn_hw, 200, 40);
    lv_obj_align(btn_hw, LV_ALIGN_LEFT_MID, 0, 0);
    lv_obj_set_style_bg_color(btn_hw, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_hw, device_info_tab_clicked, LV_EVENT_CLICKED, (void*)0);

    lv_obj_t *btn_hw_label = lv_label_create(btn_hw);
    lv_label_set_text(btn_hw_label, "Hardware");
    lv_obj_center(btn_hw_label);

    // Software tab button
    lv_obj_t *btn_sw = lv_btn_create(tab_row);
    lv_obj_set_size(btn_sw, 200, 40);
    lv_obj_align(btn_sw, LV_ALIGN_LEFT_MID, 210, 0);
    lv_obj_set_style_bg_color(btn_sw, lv_color_hex(0x555555), 0);
    lv_obj_add_event_cb(btn_sw, device_info_tab_clicked, LV_EVENT_CLICKED, (void*)1);

    lv_obj_t *btn_sw_label = lv_label_create(btn_sw);
    lv_label_set_text(btn_sw_label, "Software");
    lv_obj_center(btn_sw_label);

    // Content area (scrollable)
    device_info_content = lv_obj_create(device_info_container);
    lv_obj_set_size(device_info_content, 900, 380);
    lv_obj_set_pos(device_info_content, 25, 95);
    lv_obj_set_style_bg_color(device_info_content, lv_color_hex(0x1E1E1E), 0);
    lv_obj_set_style_border_width(device_info_content, 1, 0);
    lv_obj_set_style_border_color(device_info_content, lv_color_hex(0x444444), 0);
    lv_obj_set_scrollbar_mode(device_info_content, LV_SCROLLBAR_MODE_AUTO);

    // Update content for current tab
    device_info_update_content();

    // Back button
    lv_obj_t *btn_back = lv_btn_create(device_info_container);
    lv_obj_set_size(btn_back, 200, 40);
    lv_obj_align(btn_back, LV_ALIGN_BOTTOM_MID, 0, -10);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, setup_back_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Back");
    lv_obj_center(btn_back_label);
}

// Tab button clicked - switch between Hardware/Software tabs
static void device_info_tab_clicked(lv_event_t *e) {
    uint8_t tab = (uint8_t)(uintptr_t)lv_event_get_user_data(e);

    if (tab == device_info_tab) return;  // Already on this tab

    device_info_tab = tab;
    ESP_LOGI(TAG, "Switched to %s tab", tab == 0 ? "Hardware" : "Software");

    // Update tab button colors
    lv_obj_t *tab_row = lv_obj_get_child(device_info_container, 1);  // Tab row is 2nd child
    lv_obj_t *btn_hw = lv_obj_get_child(tab_row, 0);
    lv_obj_t *btn_sw = lv_obj_get_child(tab_row, 1);

    if (device_info_tab == 0) {
        lv_obj_set_style_bg_color(btn_hw, COLOR_PRIMARY, 0);
        lv_obj_set_style_bg_color(btn_sw, lv_color_hex(0x555555), 0);
    } else {
        lv_obj_set_style_bg_color(btn_hw, lv_color_hex(0x555555), 0);
        lv_obj_set_style_bg_color(btn_sw, COLOR_PRIMARY, 0);
    }

    // Update content
    device_info_update_content();
}

// Update device info content based on selected tab
static void device_info_update_content(void) {
    if (device_info_content == NULL) return;

    // Clear existing content
    lv_obj_clean(device_info_content);

    // Create info text label
    lv_obj_t *info_text = lv_label_create(device_info_content);
    lv_obj_set_width(info_text, 870);
    lv_obj_align(info_text, LV_ALIGN_TOP_LEFT, 15, 15);
    lv_obj_set_style_text_font(info_text, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(info_text, lv_color_white(), 0);
    lv_label_set_long_mode(info_text, LV_LABEL_LONG_WRAP);

    char info_buf[2048];

    if (device_info_tab == 0) {
        // HARDWARE TAB
        // Get chip info
        esp_chip_info_t chip_info;
        esp_chip_info(&chip_info);

        // Get flash size
        uint32_t flash_size = 0;
        esp_flash_get_size(NULL, &flash_size);

        // Get MAC addresses
        uint8_t mac_wifi[6] = {0};
        uint8_t mac_eth[6] = {0};
        esp_efuse_mac_get_default(mac_wifi);
        esp_read_mac(mac_eth, ESP_MAC_ETH);

        // Get memory info
        size_t free_heap = esp_get_free_heap_size();
        size_t min_free_heap = esp_get_minimum_free_heap_size();
        size_t total_heap = heap_caps_get_total_size(MALLOC_CAP_DEFAULT);
        size_t psram_size = esp_psram_get_size();
        size_t psram_free = heap_caps_get_free_size(MALLOC_CAP_SPIRAM);

        snprintf(info_buf, sizeof(info_buf),
            "ESP32-P4 SYSTEM\n"
            "  Chip Model: ESP32-P4\n"
            "  Cores: %d x RISC-V\n"
            "  Revision: %d.%d\n"
            "  Clock: 360 MHz\n\n"

            "MEMORY\n"
            "  Internal RAM: %lu KB total\n"
            "  RAM Free: %lu KB (%lu KB min)\n"
            "  PSRAM: %lu MB total\n"
            "  PSRAM Free: %lu MB\n"
            "  Flash: %lu MB\n\n"

            "NETWORK INTERFACES\n"
            "  WiFi MAC: %02X:%02X:%02X:%02X:%02X:%02X\n"
            "  Ethernet MAC: %02X:%02X:%02X:%02X:%02X:%02X\n"
            "  WiFi: %s\n"
            "  Ethernet: %s\n\n"

            "PERIPHERALS\n"
            "  Display: 7\" 1024x600 MIPI-DSI (GPIO 26,27)\n"
            "  Touch: GT911 I2C (GPIO 7,8)\n"
            "  NFC: PN532 SPI (GPIO TBD)\n"
            "  WiFi/BT: ESP32-C6 SDIO (GPIO 14-19,54)\n"
            "  Ethernet: W5500 SPI (GPIO 0,1,2,3,4)\n\n"

            "STORAGE\n"
            "  NVS: Factory partition\n"
            "  Storage: 13 MB data partition",

            chip_info.cores,
            chip_info.revision / 100, chip_info.revision % 100,
            (unsigned long)(total_heap / 1024),
            (unsigned long)(free_heap / 1024),
            (unsigned long)(min_free_heap / 1024),
            (unsigned long)(psram_size / (1024*1024)),
            (unsigned long)(psram_free / (1024*1024)),
            (unsigned long)(flash_size / (1024*1024)),
            mac_wifi[0], mac_wifi[1], mac_wifi[2], mac_wifi[3], mac_wifi[4], mac_wifi[5],
            mac_eth[0], mac_eth[1], mac_eth[2], mac_eth[3], mac_eth[4], mac_eth[5],
            wifi_manager_is_connected() ? "Connected" : "Disconnected",
            ethernet_manager_is_connected() ? "Connected" : "Disconnected"
        );

    } else {
        // SOFTWARE TAB
        const esp_partition_t *running = esp_ota_get_running_partition();

        snprintf(info_buf, sizeof(info_buf),
            "FIRMWARE\n"
            "  Version: %s\n"
            "  Build Date: %s\n"
            "  Build Time: %s\n"
            "  ESP-IDF: %s\n"
            "  Partition: %s (0x%lx)\n\n"

            "FEATURES\n"
            "  Display: Enabled (LVGL 9.3.0)\n"
            "  NFC Reader: Enabled (PN532)\n"
            "  WiFi: Enabled (ESP32-C6)\n"
            "  Ethernet: Enabled (W5500)\n"
            "  API Client: %s\n\n"

            "DEVICE CONFIGURATION\n"
            "  Device Name: %s\n"
            "  Admin Password: (configured)\n\n"

            "API ENDPOINTS\n"
            "  POST /api/v1/clock-events\n"
            "  GET /api/v1/employees/:id\n"
            "  GET /api/v1/device/status\n"
            "  PUT /api/v1/device/config\n\n"

            "NETWORK PROTOCOLS\n"
            "  HTTP Client: enabled\n"
            "  HTTPS/TLS: mbedTLS 3.6.0\n"
            "  mDNS: Avahi compatible\n"
            "  NTP: SNTP client\n\n"

            "SYSTEM STATUS\n"
            "  Uptime: %lu seconds\n"
            "  Free Heap: %lu KB\n"
            "  Task Count: Active",

            FIRMWARE_VERSION,
            FIRMWARE_BUILD_DATE,
            FIRMWARE_BUILD_TIME,
            esp_get_idf_version(),
            running ? running->label : "unknown",
            running ? (unsigned long)running->address : 0,
            "Disabled",  // API_ENABLED from features.h
            "NFC Time Clock 01",  // DEVICE_NAME from features.h
            (unsigned long)(esp_timer_get_time() / 1000000),
            (unsigned long)(esp_get_free_heap_size() / 1024)
        );
    }

    lv_label_set_text(info_text, info_buf);
}

// Ensure API client is initialized
static void ensure_api_initialized(void) {
    api_config_t *config = api_get_config();

    // Check if already initialized (server_host will be empty if not)
    if (config->server_host[0] == '\0') {
        ESP_LOGI(TAG, "Initializing API client...");

        // Get MAC address
        uint8_t mac[6];
        esp_read_mac(mac, ESP_MAC_WIFI_STA);

        // Configure API client with Herd server
        api_config_t api_config = {
            .server_host = "attend.test",
            .server_port = 80,
            .is_registered = false,
            .is_approved = false
        };
        strcpy(api_config.device_name, "ESP32-P4-NFC-Clock");

        api_client_init(&api_config);
        ESP_LOGI(TAG, "API client initialized with server: %s:%d",
                api_config.server_host, api_config.server_port);
    }
}

// Time update timer callback
static void time_display_update_timer(lv_timer_t *timer) {
    if (time_display_label == NULL) return;

    time_t now;
    struct tm timeinfo;
    time(&now);
    localtime_r(&now, &timeinfo);

    char time_str[64];
    snprintf(time_str, sizeof(time_str), "%02d:%02d:%02d %s",
             timeinfo.tm_hour == 0 ? 12 : (timeinfo.tm_hour > 12 ? timeinfo.tm_hour - 12 : timeinfo.tm_hour),
             timeinfo.tm_min,
             timeinfo.tm_sec,
             timeinfo.tm_hour >= 12 ? "PM" : "AM");

    char date_str[64];
    snprintf(date_str, sizeof(date_str), "%s %02d, %d",
             (const char*[]){"Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"}[timeinfo.tm_mon],
             timeinfo.tm_mday,
             timeinfo.tm_year + 1900);

    char display_str[128];
    snprintf(display_str, sizeof(display_str), "%s\n%s", time_str, date_str);
    lv_label_set_text(time_display_label, display_str);
}

// Main clock timer - updates the large clock on landing page (ChatGPT fix)
static void main_clock_tick(lv_timer_t *t) {
    time_t now;
    struct tm ti;
    time(&now);
    localtime_r(&now, &ti);

    char time_str[32];
    // 12-hour format with AM/PM
    int hour12 = (ti.tm_hour == 0) ? 12 : (ti.tm_hour > 12 ? ti.tm_hour - 12 : ti.tm_hour);
    snprintf(time_str, sizeof(time_str), "%02d:%02d", hour12, ti.tm_min);

    static const char *MONS[] = {"Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"};
    char date_str[32];
    snprintf(date_str, sizeof(date_str), "%s %02d, %d", MONS[ti.tm_mon], ti.tm_mday, ti.tm_year + 1900);

    // Update both status-bar and large landing page labels
    ui_update_time(time_str, date_str);
}

// Sync time button clicked
static void time_sync_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Time sync button clicked");

    // Check if network is connected before attempting any sync
    if (!network_manager_is_connected()) {
        ESP_LOGE(TAG, "❌ Cannot sync time: No network connection");
        ESP_LOGW(TAG, "Please connect to WiFi or Ethernet first");
        return;
    }

    // Check if we should use TimeClock server or NTP
    if (time_use_server_switch != NULL && lv_obj_has_state(time_use_server_switch, LV_STATE_CHECKED)) {
        ESP_LOGI(TAG, "Syncing time with TimeClock Server");

        // Ensure API client is initialized
        ensure_api_initialized();

        char time_str[64];
        esp_err_t err = api_get_server_time(time_str, sizeof(time_str));

        if (err == ESP_OK) {
            ESP_LOGI(TAG, "Received server time: %s", time_str);

            // Parse ISO 8601 format: "2025-10-14T03:37:41.080066Z" or "2025-10-14T03:37:41Z"
            struct tm timeinfo = {0};
            int year, month, day, hour, minute, second;

            // Parse the datetime string
            int parsed = sscanf(time_str, "%d-%d-%dT%d:%d:%d",
                               &year, &month, &day, &hour, &minute, &second);

            if (parsed >= 6) {
                timeinfo.tm_year = year - 1900;  // Years since 1900
                timeinfo.tm_mon = month - 1;      // Months since January (0-11)
                timeinfo.tm_mday = day;
                timeinfo.tm_hour = hour;
                timeinfo.tm_min = minute;
                timeinfo.tm_sec = second;
                timeinfo.tm_isdst = -1;           // Let system determine DST

                // Convert to time_t and set system time
                time_t new_time = mktime(&timeinfo);
                struct timeval tv = { .tv_sec = new_time, .tv_usec = 0 };

                if (settimeofday(&tv, NULL) == 0) {
                    ESP_LOGI(TAG, "✅ System time synchronized with TimeClock Server");
                    ESP_LOGI(TAG, "   Time set to: %04d-%02d-%02d %02d:%02d:%02d",
                            year, month, day, hour, minute, second);
                } else {
                    ESP_LOGE(TAG, "❌ Failed to set system time");
                }
            } else {
                ESP_LOGE(TAG, "❌ Failed to parse time string: %s", time_str);
            }
        } else {
            ESP_LOGE(TAG, "❌ Failed to get time from server: %s", esp_err_to_name(err));
            ESP_LOGW(TAG, "Make sure device is connected to network and server is reachable");
        }
    } else {
        ESP_LOGI(TAG, "Syncing time with NTP");

        // Get NTP server from input
        const char *ntp_server = "pool.ntp.org";  // default
        if (time_ntp_input != NULL) {
            const char *input_text = lv_textarea_get_text(time_ntp_input);
            if (input_text != NULL && strlen(input_text) > 0) {
                ntp_server = input_text;
            }
        }

        // Stop SNTP if running
        if (esp_sntp_enabled()) {
            esp_sntp_stop();
        }

        // Configure and start SNTP
        esp_sntp_setoperatingmode(SNTP_OPMODE_POLL);
        esp_sntp_setservername(0, ntp_server);
        esp_sntp_init();

        ESP_LOGI(TAG, "✅ SNTP sync initiated with server: %s", ntp_server);
        ESP_LOGI(TAG, "Time synchronization may take a few seconds...");
    }
}

// Apply manual time settings
static void time_apply_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Apply time settings clicked");

    if (time_hour_roller == NULL || time_minute_roller == NULL || time_ampm_roller == NULL ||
        time_month_roller == NULL || time_day_roller == NULL || time_year_roller == NULL) {
        ESP_LOGE(TAG, "Time input controls not initialized");
        return;
    }

    // Get selected values from rollers
    uint16_t hour_idx = lv_roller_get_selected(time_hour_roller);
    uint16_t minute_idx = lv_roller_get_selected(time_minute_roller);
    uint16_t ampm_idx = lv_roller_get_selected(time_ampm_roller);
    uint16_t month_idx = lv_roller_get_selected(time_month_roller);
    uint16_t day_idx = lv_roller_get_selected(time_day_roller);
    uint16_t year_idx = lv_roller_get_selected(time_year_roller);

    // Convert to actual values
    int hour = hour_idx + 1;  // 1-12
    if (ampm_idx == 1) {  // PM
        if (hour != 12) hour += 12;
    } else {  // AM
        if (hour == 12) hour = 0;
    }

    int minute = minute_idx;
    int month = month_idx;  // 0-11
    int day = day_idx + 1;  // 1-31
    int year = 2025 + year_idx;  // Starting from 2025

    // Set system time
    struct tm timeinfo = {
        .tm_sec = 0,
        .tm_min = minute,
        .tm_hour = hour,
        .tm_mday = day,
        .tm_mon = month,
        .tm_year = year - 1900,
        .tm_isdst = -1
    };

    time_t new_time = mktime(&timeinfo);
    struct timeval tv = { .tv_sec = new_time, .tv_usec = 0 };
    settimeofday(&tv, NULL);

    ESP_LOGI(TAG, "Time set to: %04d-%02d-%02d %02d:%02d:00", year, month + 1, day, hour, minute);

    // Timezone is already applied by time_settings module
}

// Time settings back button
static void time_settings_back_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings back button clicked");

    // Stop update timer
    if (time_update_timer != NULL) {
        lv_timer_del(time_update_timer);
        time_update_timer = NULL;
    }

    // Delete time settings container
    if (time_settings_container != NULL) {
        lv_obj_del(time_settings_container);
        time_settings_container = NULL;
    }

    // Delete overlay screen if needed
    if (network_config_screen != NULL) {
        lv_obj_del(network_config_screen);
        network_config_screen = NULL;
    }

    // Clear references
    time_display_label = NULL;
    time_hour_roller = NULL;
    time_minute_roller = NULL;
    time_ampm_roller = NULL;
    time_month_roller = NULL;
    time_day_roller = NULL;
    time_year_roller = NULL;
    time_ntp_input = NULL;
    time_use_server_switch = NULL;
}

static void setup_time_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings clicked");
    time_settings_init_nvs();

    // Create overlay screen if it doesn't exist
    if (network_config_screen == NULL) {
        network_config_screen = lv_obj_create(lv_scr_act());
        lv_obj_set_size(network_config_screen, LV_PCT(100), LV_PCT(100));
        lv_obj_set_style_bg_color(network_config_screen, lv_color_hex(0x000000), 0);
        lv_obj_set_style_bg_opa(network_config_screen, LV_OPA_90, 0);
        lv_obj_center(network_config_screen);
    }

    // Create keyboard at bottom if it doesn't exist (shared for all input screens)
    if (network_keyboard == NULL) {
        network_keyboard = lv_keyboard_create(network_config_screen);
        lv_obj_set_size(network_keyboard, LV_PCT(100), 240);
        lv_obj_align(network_keyboard, LV_ALIGN_BOTTOM_MID, 0, 0);
        lv_keyboard_set_mode(network_keyboard, LV_KEYBOARD_MODE_TEXT_LOWER);

        // Create floating text preview label above keyboard
        input_preview_label = lv_label_create(network_config_screen);
        lv_obj_set_size(input_preview_label, 950, 50);
        lv_obj_align_to(input_preview_label, network_keyboard, LV_ALIGN_OUT_TOP_MID, 0, -10);
        lv_obj_set_style_bg_color(input_preview_label, lv_color_hex(0xFF4444), 0);  // Red background
        lv_obj_set_style_bg_opa(input_preview_label, LV_OPA_90, 0);
        lv_obj_set_style_border_color(input_preview_label, lv_color_hex(0xFFFFFF), 0);
        lv_obj_set_style_border_width(input_preview_label, 2, 0);
        lv_obj_set_style_radius(input_preview_label, 8, 0);
        lv_obj_set_style_pad_all(input_preview_label, 10, 0);
        lv_obj_set_style_text_color(input_preview_label, lv_color_hex(0xFFFFFF), 0);
        lv_obj_set_style_text_font(input_preview_label, &lv_font_montserrat_14, 0);
        lv_label_set_text(input_preview_label, "");
        lv_label_set_long_mode(input_preview_label, LV_LABEL_LONG_SCROLL_CIRCULAR);
    }

    // Create time settings container
    time_settings_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(time_settings_container, 900, 550);
    lv_obj_center(time_settings_container);
    lv_obj_set_style_bg_color(time_settings_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(time_settings_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(time_settings_container, 2, 0);
    lv_obj_set_flex_flow(time_settings_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(time_settings_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_pad_all(time_settings_container, 20, 0);
    lv_obj_set_style_pad_gap(time_settings_container, 15, 0);

    // Title
    lv_obj_t *title = lv_label_create(time_settings_container);
    lv_label_set_text(title, LV_SYMBOL_SETTINGS "  Time Settings");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);

    // Current time display
    lv_obj_t *time_display_container = lv_obj_create(time_settings_container);
    lv_obj_set_size(time_display_container, 850, 80);
    lv_obj_set_style_bg_color(time_display_container, COLOR_BG, 0);
    lv_obj_set_style_border_width(time_display_container, 1, 0);
    lv_obj_set_style_border_color(time_display_container, COLOR_PRIMARY, 0);

    time_display_label = lv_label_create(time_display_container);
    lv_obj_set_style_text_font(time_display_label, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(time_display_label, COLOR_TEXT, 0);
    lv_obj_center(time_display_label);

    // Start timer to update display every second
    time_display_update_timer(NULL);  // Initial update
    time_update_timer = lv_timer_create(time_display_update_timer, 1000, NULL);

    // Sync button
    lv_obj_t *btn_sync = lv_btn_create(time_settings_container);
    lv_obj_set_size(btn_sync, 200, 45);
    lv_obj_set_style_bg_color(btn_sync, COLOR_SUCCESS, 0);
    lv_obj_add_event_cb(btn_sync, time_sync_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_sync_label = lv_label_create(btn_sync);
    lv_label_set_text(btn_sync_label, LV_SYMBOL_REFRESH "  Sync Now");
    lv_obj_center(btn_sync_label);

    // Manual time setting section
    lv_obj_t *manual_label = lv_label_create(time_settings_container);
    lv_label_set_text(manual_label, "Manual Time & Date");
    lv_obj_set_style_text_color(manual_label, COLOR_TEXT, 0);

    // Time rollers row
    lv_obj_t *time_row = lv_obj_create(time_settings_container);
    lv_obj_set_size(time_row, 850, 120);
    lv_obj_set_flex_flow(time_row, LV_FLEX_FLOW_ROW);
    lv_obj_set_flex_align(time_row, LV_FLEX_ALIGN_SPACE_EVENLY, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_bg_opa(time_row, LV_OPA_0, 0);
    lv_obj_set_style_border_width(time_row, 0, 0);
    lv_obj_clear_flag(time_row, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(time_row, LV_SCROLLBAR_MODE_OFF);

    // Hour roller with label
    lv_obj_t *hour_container = lv_obj_create(time_row);
    lv_obj_set_size(hour_container, 80, 110);
    lv_obj_set_style_bg_opa(hour_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(hour_container, 0, 0);
    lv_obj_set_flex_flow(hour_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(hour_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(hour_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(hour_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *hour_label = lv_label_create(hour_container);
    lv_label_set_text(hour_label, "Hour");
    lv_obj_set_style_text_color(hour_label, COLOR_TEXT, 0);

    time_hour_roller = lv_roller_create(hour_container);
    lv_roller_set_options(time_hour_roller, "01\n02\n03\n04\n05\n06\n07\n08\n09\n10\n11\n12", LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_hour_roller, 80, 90);
    lv_roller_set_visible_row_count(time_hour_roller, 3);

    // Minute roller with label
    lv_obj_t *minute_container = lv_obj_create(time_row);
    lv_obj_set_size(minute_container, 80, 110);
    lv_obj_set_style_bg_opa(minute_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(minute_container, 0, 0);
    lv_obj_set_flex_flow(minute_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(minute_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(minute_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(minute_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *minute_label = lv_label_create(minute_container);
    lv_label_set_text(minute_label, "Minute");
    lv_obj_set_style_text_color(minute_label, COLOR_TEXT, 0);

    time_minute_roller = lv_roller_create(minute_container);
    char minute_opts[300];
    minute_opts[0] = '\0';
    for (int i = 0; i < 60; i++) {
        char buf[8];
        snprintf(buf, sizeof(buf), "%02d%s", i, i < 59 ? "\n" : "");
        strcat(minute_opts, buf);
    }
    lv_roller_set_options(time_minute_roller, minute_opts, LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_minute_roller, 80, 90);
    lv_roller_set_visible_row_count(time_minute_roller, 3);

    // AM/PM roller with label
    lv_obj_t *ampm_container = lv_obj_create(time_row);
    lv_obj_set_size(ampm_container, 80, 110);
    lv_obj_set_style_bg_opa(ampm_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(ampm_container, 0, 0);
    lv_obj_set_flex_flow(ampm_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(ampm_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(ampm_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(ampm_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *ampm_label = lv_label_create(ampm_container);
    lv_label_set_text(ampm_label, "AM/PM");
    lv_obj_set_style_text_color(ampm_label, COLOR_TEXT, 0);

    time_ampm_roller = lv_roller_create(ampm_container);
    lv_roller_set_options(time_ampm_roller, "AM\nPM", LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_ampm_roller, 80, 90);
    lv_roller_set_visible_row_count(time_ampm_roller, 2);

    // Date rollers row
    lv_obj_t *date_row = lv_obj_create(time_settings_container);
    lv_obj_set_size(date_row, 850, 120);
    lv_obj_set_flex_flow(date_row, LV_FLEX_FLOW_ROW);
    lv_obj_set_flex_align(date_row, LV_FLEX_ALIGN_SPACE_EVENLY, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_bg_opa(date_row, LV_OPA_0, 0);
    lv_obj_set_style_border_width(date_row, 0, 0);
    lv_obj_clear_flag(date_row, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(date_row, LV_SCROLLBAR_MODE_OFF);

    // Month roller with label
    lv_obj_t *month_container = lv_obj_create(date_row);
    lv_obj_set_size(month_container, 80, 110);
    lv_obj_set_style_bg_opa(month_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(month_container, 0, 0);
    lv_obj_set_flex_flow(month_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(month_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(month_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(month_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *month_label = lv_label_create(month_container);
    lv_label_set_text(month_label, "Month");
    lv_obj_set_style_text_color(month_label, COLOR_TEXT, 0);

    time_month_roller = lv_roller_create(month_container);
    lv_roller_set_options(time_month_roller, "Jan\nFeb\nMar\nApr\nMay\nJun\nJul\nAug\nSep\nOct\nNov\nDec", LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_month_roller, 80, 90);
    lv_roller_set_visible_row_count(time_month_roller, 3);

    // Day roller with label
    lv_obj_t *day_container = lv_obj_create(date_row);
    lv_obj_set_size(day_container, 80, 110);
    lv_obj_set_style_bg_opa(day_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(day_container, 0, 0);
    lv_obj_set_flex_flow(day_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(day_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(day_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(day_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *day_label = lv_label_create(day_container);
    lv_label_set_text(day_label, "Day");
    lv_obj_set_style_text_color(day_label, COLOR_TEXT, 0);

    time_day_roller = lv_roller_create(day_container);
    char day_opts[150];
    day_opts[0] = '\0';
    for (int i = 1; i <= 31; i++) {
        char buf[8];
        snprintf(buf, sizeof(buf), "%02d%s", i, i < 31 ? "\n" : "");
        strcat(day_opts, buf);
    }
    lv_roller_set_options(time_day_roller, day_opts, LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_day_roller, 80, 90);
    lv_roller_set_visible_row_count(time_day_roller, 3);

    // Year roller with label
    lv_obj_t *year_container = lv_obj_create(date_row);
    lv_obj_set_size(year_container, 80, 110);
    lv_obj_set_style_bg_opa(year_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(year_container, 0, 0);
    lv_obj_set_flex_flow(year_container, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(year_container, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_clear_flag(year_container, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(year_container, LV_SCROLLBAR_MODE_OFF);

    lv_obj_t *year_label = lv_label_create(year_container);
    lv_label_set_text(year_label, "Year");
    lv_obj_set_style_text_color(year_label, COLOR_TEXT, 0);

    time_year_roller = lv_roller_create(year_container);
    lv_roller_set_options(time_year_roller, "2025\n2026\n2027\n2028\n2029\n2030\n2031\n2032\n2033\n2034\n2035", LV_ROLLER_MODE_NORMAL);
    lv_obj_set_size(time_year_roller, 80, 90);
    lv_roller_set_visible_row_count(time_year_roller, 3);

    // Settings row
    lv_obj_t *settings_row = lv_obj_create(time_settings_container);
    lv_obj_set_size(settings_row, 850, 80);
    lv_obj_set_flex_flow(settings_row, LV_FLEX_FLOW_ROW);
    lv_obj_set_flex_align(settings_row, LV_FLEX_ALIGN_SPACE_BETWEEN, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_bg_opa(settings_row, LV_OPA_0, 0);
    lv_obj_set_style_border_width(settings_row, 0, 0);
    lv_obj_set_style_pad_left(settings_row, 20, 0);
    lv_obj_set_style_pad_right(settings_row, 20, 0);
    lv_obj_clear_flag(settings_row, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(settings_row, LV_SCROLLBAR_MODE_OFF);

    // Timezone selector (using modular time_settings component)
    lv_obj_t *tz_container = lv_obj_create(settings_row);
    lv_obj_set_size(tz_container, 350, 70);
    lv_obj_set_style_bg_opa(tz_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(tz_container, 0, 0);

    lv_obj_t *tz_label = lv_label_create(tz_container);
    lv_label_set_text(tz_label, "Timezone:");
    lv_obj_set_style_text_color(tz_label, COLOR_TEXT, 0);
    lv_obj_align(tz_label, LV_ALIGN_TOP_LEFT, 0, 0);

    // Use the time_settings module to create timezone dropdown (with scrollable popup)
    lv_obj_t *tz_dropdown = time_settings_create(tz_container);
    // Note: sizing is handled by time_settings module, just position it
    lv_obj_align(tz_dropdown, LV_ALIGN_BOTTOM_LEFT, 0, 0);

    // NTP server input
    lv_obj_t *ntp_container = lv_obj_create(settings_row);
    lv_obj_set_size(ntp_container, 300, 70);
    lv_obj_set_style_bg_opa(ntp_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(ntp_container, 0, 0);

    lv_obj_t *ntp_label = lv_label_create(ntp_container);
    lv_label_set_text(ntp_label, "NTP Server:");
    lv_obj_set_style_text_color(ntp_label, COLOR_TEXT, 0);
    lv_obj_align(ntp_label, LV_ALIGN_TOP_LEFT, 0, 0);

    time_ntp_input = lv_textarea_create(ntp_container);
    lv_obj_set_size(time_ntp_input, 290, 40);
    lv_obj_align(time_ntp_input, LV_ALIGN_BOTTOM_LEFT, 0, 0);
    lv_textarea_set_one_line(time_ntp_input, true);
    lv_textarea_set_placeholder_text(time_ntp_input, "pool.ntp.org");
    lv_obj_add_flag(time_ntp_input, LV_OBJ_FLAG_CLICKABLE);  // Make sure it's clickable
    lv_obj_clear_flag(time_ntp_input, LV_OBJ_FLAG_SCROLLABLE);  // Disable scrolling
    lv_obj_set_scrollbar_mode(time_ntp_input, LV_SCROLLBAR_MODE_OFF);  // Hide scrollbar
    lv_obj_add_event_cb(time_ntp_input, network_input_focused, LV_EVENT_PRESSED, NULL);  // Use PRESSED event
    lv_obj_add_event_cb(time_ntp_input, network_input_focused, LV_EVENT_CLICKED, NULL);  // Also CLICKED
    lv_obj_add_event_cb(time_ntp_input, network_input_focused, LV_EVENT_FOCUSED, NULL);  // And FOCUSED

    // Use server switch
    lv_obj_t *switch_container = lv_obj_create(settings_row);
    lv_obj_set_size(switch_container, 220, 70);
    lv_obj_set_style_bg_opa(switch_container, LV_OPA_0, 0);
    lv_obj_set_style_border_width(switch_container, 0, 0);

    lv_obj_t *switch_label = lv_label_create(switch_container);
    lv_label_set_text(switch_label, "Use TimeClock Server:");
    lv_obj_set_style_text_color(switch_label, COLOR_TEXT, 0);
    lv_obj_align(switch_label, LV_ALIGN_TOP_LEFT, 0, 0);

    time_use_server_switch = lv_switch_create(switch_container);
    lv_obj_align(time_use_server_switch, LV_ALIGN_BOTTOM_LEFT, 0, 0);

    // Buttons row
    lv_obj_t *btn_row = lv_obj_create(time_settings_container);
    lv_obj_set_size(btn_row, 850, 50);
    lv_obj_set_flex_flow(btn_row, LV_FLEX_FLOW_ROW);
    lv_obj_set_flex_align(btn_row, LV_FLEX_ALIGN_SPACE_EVENLY, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);
    lv_obj_set_style_bg_opa(btn_row, LV_OPA_0, 0);
    lv_obj_set_style_border_width(btn_row, 0, 0);
    lv_obj_clear_flag(btn_row, LV_OBJ_FLAG_SCROLLABLE);
    lv_obj_set_scrollbar_mode(btn_row, LV_SCROLLBAR_MODE_OFF);

    // Apply button
    lv_obj_t *btn_apply = lv_btn_create(btn_row);
    lv_obj_set_size(btn_apply, 200, 45);
    lv_obj_set_style_bg_color(btn_apply, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_apply, time_apply_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_apply_label = lv_label_create(btn_apply);
    lv_label_set_text(btn_apply_label, LV_SYMBOL_OK "  Apply");
    lv_obj_center(btn_apply_label);

    // Back button
    lv_obj_t *btn_back = lv_btn_create(btn_row);
    lv_obj_set_size(btn_back, 200, 45);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, time_settings_back_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, LV_SYMBOL_LEFT "  Back");
    lv_obj_center(btn_back_label);

    // Set current time to rollers
    time_t now;
    struct tm timeinfo;
    time(&now);
    localtime_r(&now, &timeinfo);

    int display_hour = timeinfo.tm_hour == 0 ? 12 : (timeinfo.tm_hour > 12 ? timeinfo.tm_hour - 12 : timeinfo.tm_hour);
    lv_roller_set_selected(time_hour_roller, display_hour - 1, LV_ANIM_OFF);
    lv_roller_set_selected(time_minute_roller, timeinfo.tm_min, LV_ANIM_OFF);
    lv_roller_set_selected(time_ampm_roller, timeinfo.tm_hour >= 12 ? 1 : 0, LV_ANIM_OFF);
    lv_roller_set_selected(time_month_roller, timeinfo.tm_mon, LV_ANIM_OFF);
    lv_roller_set_selected(time_day_roller, timeinfo.tm_mday - 1, LV_ANIM_OFF);
    lv_roller_set_selected(time_year_roller, (timeinfo.tm_year + 1900 - 2025), LV_ANIM_OFF);
}

void ui_show_setup_screen(void) {
    ESP_LOGI(TAG, "Showing setup screen");

    // Create setup screen overlay
    setup_screen = lv_obj_create(lv_scr_act());
    lv_obj_set_size(setup_screen, LV_PCT(100), LV_PCT(100));
    lv_obj_set_style_bg_color(setup_screen, lv_color_hex(0x000000), 0);
    lv_obj_set_style_bg_opa(setup_screen, LV_OPA_90, 0);
    lv_obj_center(setup_screen);

    // Setup menu container - centered
    lv_obj_t *container = lv_obj_create(setup_screen);
    lv_obj_set_size(container, 700, 500);
    lv_obj_center(container);
    lv_obj_set_style_bg_color(container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(container, 2, 0);

    // Title
    lv_obj_t *title = lv_label_create(container);
    lv_label_set_text(title, "Setup & Configuration");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 20);

    // Network Settings Button
    lv_obj_t *btn_network = lv_btn_create(container);
    lv_obj_set_size(btn_network, 600, 60);
    lv_obj_align(btn_network, LV_ALIGN_TOP_MID, 0, 70);
    lv_obj_set_style_bg_color(btn_network, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_network, setup_network_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_network_label = lv_label_create(btn_network);
    lv_label_set_text(btn_network_label, LV_SYMBOL_WIFI "  Network Settings");
    lv_obj_set_style_text_font(btn_network_label, &lv_font_montserrat_14, 0);
    lv_obj_align(btn_network_label, LV_ALIGN_LEFT_MID, 20, 0);

    // Device Info Button
    lv_obj_t *btn_device = lv_btn_create(container);
    lv_obj_set_size(btn_device, 600, 60);
    lv_obj_align(btn_device, LV_ALIGN_TOP_MID, 0, 150);
    lv_obj_set_style_bg_color(btn_device, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_device, setup_device_info_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_device_label = lv_label_create(btn_device);
    lv_label_set_text(btn_device_label, LV_SYMBOL_SETTINGS "  Device Information");
    lv_obj_set_style_text_font(btn_device_label, &lv_font_montserrat_14, 0);
    lv_obj_align(btn_device_label, LV_ALIGN_LEFT_MID, 20, 0);

    // Time Settings Button
    lv_obj_t *btn_time = lv_btn_create(container);
    lv_obj_set_size(btn_time, 600, 60);
    lv_obj_align(btn_time, LV_ALIGN_TOP_MID, 0, 230);
    lv_obj_set_style_bg_color(btn_time, COLOR_PRIMARY, 0);
    lv_obj_add_event_cb(btn_time, setup_time_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_time_label = lv_label_create(btn_time);
    lv_label_set_text(btn_time_label, LV_SYMBOL_REFRESH "  Time Settings");
    lv_obj_set_style_text_font(btn_time_label, &lv_font_montserrat_14, 0);
    lv_obj_align(btn_time_label, LV_ALIGN_LEFT_MID, 20, 0);

    // Back/Close Button
    lv_obj_t *btn_back = lv_btn_create(container);
    lv_obj_set_size(btn_back, 200, 50);
    lv_obj_align(btn_back, LV_ALIGN_BOTTOM_MID, 0, -20);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, setup_back_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Close");
    lv_obj_set_style_text_font(btn_back_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_back_label);
}

void ui_hide_setup_screen(void) {
    ESP_LOGI(TAG, "Hiding setup screen");

    // Stop time update timer if running
    if (time_update_timer != NULL) {
        lv_timer_del(time_update_timer);
        time_update_timer = NULL;
    }

    // Delete time settings container if visible
    if (time_settings_container != NULL) {
        lv_obj_del(time_settings_container);
        time_settings_container = NULL;
    }

    // Delete device info container if visible
    if (device_info_container != NULL) {
        lv_obj_del(device_info_container);
        device_info_container = NULL;
    }

    // Delete network config container if visible
    if (network_config_container != NULL) {
        lv_obj_del(network_config_container);
        network_config_container = NULL;
    }

    // Delete network config screen overlay if visible
    if (network_config_screen != NULL) {
        lv_obj_del(network_config_screen);
        network_config_screen = NULL;
    }

    // Delete keyboard if visible
    if (network_keyboard != NULL) {
        lv_obj_del(network_keyboard);
        network_keyboard = NULL;
    }

    // Delete input preview label if visible
    if (input_preview_label != NULL) {
        lv_obj_del(input_preview_label);
        input_preview_label = NULL;
    }

    if (setup_screen != NULL) {
        lv_obj_del(setup_screen);
        setup_screen = NULL;
    }

    // Clear time settings references
    time_display_label = NULL;
    time_hour_roller = NULL;
    time_minute_roller = NULL;
    time_ampm_roller = NULL;
    time_month_roller = NULL;
    time_day_roller = NULL;
    time_year_roller = NULL;
    time_ntp_input = NULL;
    time_use_server_switch = NULL;
}

void ui_update_network_info(const char *ip_address, int signal_strength) {
    if (label_network_info == NULL || icon_network == NULL) return;

    char info[64];

    // Convert RSSI to percentage (assuming signal_strength is RSSI in dBm or already percentage)
    int rssi_dbm = signal_strength;
    int percentage = 0;

    // If signal_strength looks like RSSI (negative value)
    if (signal_strength < 0) {
        // Convert RSSI (dBm) to percentage
        // RSSI typically ranges from -100 (worst) to -30 (best)
        rssi_dbm = signal_strength;
        if (rssi_dbm >= -50) {
            percentage = 100;  // Excellent
        } else if (rssi_dbm >= -60) {
            percentage = 80;   // Good
        } else if (rssi_dbm >= -70) {
            percentage = 60;   // Fair
        } else if (rssi_dbm >= -80) {
            percentage = 40;   // Poor
        } else if (rssi_dbm >= -90) {
            percentage = 20;   // Very poor
        } else {
            percentage = 10;   // Almost no signal
        }

        // Update icon color based on signal strength
        if (percentage >= 70) {
            lv_obj_set_style_text_color(icon_network, COLOR_SUCCESS, 0);  // Green
        } else if (percentage >= 40) {
            lv_obj_set_style_text_color(icon_network, COLOR_WARNING, 0);  // Orange
        } else {
            lv_obj_set_style_text_color(icon_network, COLOR_ERROR, 0);    // Red
        }

        // Display WiFi with signal strength bars
        snprintf(info, sizeof(info), "WiFi %d%% (%d dBm)", percentage, rssi_dbm);
    } else if (signal_strength > 0) {
        // Already a percentage value
        percentage = signal_strength;

        // Update icon color
        if (percentage >= 70) {
            lv_obj_set_style_text_color(icon_network, COLOR_SUCCESS, 0);
        } else if (percentage >= 40) {
            lv_obj_set_style_text_color(icon_network, COLOR_WARNING, 0);
        } else {
            lv_obj_set_style_text_color(icon_network, COLOR_ERROR, 0);
        }

        snprintf(info, sizeof(info), "WiFi %d%%", percentage);
    } else {
        // Ethernet or no signal info (signal_strength == 0)
        lv_obj_set_style_text_color(icon_network, COLOR_SUCCESS, 0);
        snprintf(info, sizeof(info), "%s", ip_address);
    }

    lv_label_set_text(label_network_info, info);
}
