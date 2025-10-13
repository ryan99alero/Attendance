/**
 * UI Manager Implementation
 */

#include "ui_manager.h"
#include "network_manager.h"
#include "wifi_manager.h"
#include "ethernet_manager.h"
#include "firmware_info.h"
#include "esp_log.h"
#include "esp_system.h"
#include <stdio.h>
#include <string.h>

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

// Network configuration UI state
static lv_obj_t *network_scan_list = NULL;
static lv_obj_t *network_config_container = NULL;
static lv_obj_t *device_info_container = NULL;
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

lv_obj_t* ui_manager_init(const char *device_name) {
    ESP_LOGI(TAG, "Initializing UI Manager");

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

    // Show ready screen by default
    ui_show_ready_screen("Place card on reader");

    ESP_LOGI(TAG, "UI initialized");
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
    if (label_time != NULL) {
        lv_label_set_text(label_time, time_str);
    }
    if (label_date != NULL) {
        lv_label_set_text(label_date, date_str);
    }
}

void ui_show_card_scan(const card_scan_result_t *result, uint32_t display_duration_ms) {
    if (result == NULL || content_area == NULL) return;

    // Hide ready screen elements
    lv_obj_add_flag(icon_card_large, LV_OBJ_FLAG_HIDDEN);
    lv_obj_add_flag(label_main_message, LV_OBJ_FLAG_HIDDEN);

    // Show scan result elements
    lv_obj_clear_flag(label_employee_name, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_employee_details, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_card_info, LV_OBJ_FLAG_HIDDEN);
    lv_obj_clear_flag(label_timestamp, LV_OBJ_FLAG_HIDDEN);

    // Update content
    if (result->success && result->employee.is_authorized) {
        lv_label_set_text(label_employee_name, result->employee.name);
        lv_obj_set_style_text_color(label_employee_name, COLOR_SUCCESS, 0);

        char details[128];
        snprintf(details, sizeof(details), "ID: %s\n%s",
                 result->employee.employee_id, result->employee.department);
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
    lv_label_set_text(title, "WiFi Configuration");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);

    // Status label
    network_status_label = lv_label_create(network_config_container);
    lv_label_set_text(network_status_label, wifi_manager_is_connected() ? "Connected" : "Disconnected");
    lv_obj_set_style_text_font(network_status_label, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(network_status_label, COLOR_TEXT_DIM, 0);

    // SSID input
    lv_obj_t *ssid_label = lv_label_create(network_config_container);
    lv_label_set_text(ssid_label, "SSID:");
    lv_obj_set_style_text_font(ssid_label, &lv_font_montserrat_14, 0);

    network_ssid_input = lv_textarea_create(network_config_container);
    lv_obj_set_size(network_ssid_input, 850, 35);
    lv_textarea_set_placeholder_text(network_ssid_input, "WiFi network name");
    lv_textarea_set_one_line(network_ssid_input, true);
    lv_obj_set_style_text_font(network_ssid_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_ssid_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

    // Password input
    lv_obj_t *pwd_label = lv_label_create(network_config_container);
    lv_label_set_text(pwd_label, "Password:");
    lv_obj_set_style_text_font(pwd_label, &lv_font_montserrat_14, 0);

    network_password_input = lv_textarea_create(network_config_container);
    lv_obj_set_size(network_password_input, 850, 35);
    lv_textarea_set_placeholder_text(network_password_input, "WiFi password");
    lv_textarea_set_password_mode(network_password_input, true);
    lv_textarea_set_one_line(network_password_input, true);
    lv_obj_set_style_text_font(network_password_input, &lv_font_montserrat_14, 0);
    lv_obj_add_event_cb(network_password_input, network_input_focused, LV_EVENT_FOCUSED, NULL);

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

    // Static IP inputs
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

    // Scan Networks button - DISABLED (WiFi scanning not available)
    // Manual SSID/password entry only
    // lv_obj_t *btn_scan = lv_btn_create(btn_container);
    // lv_obj_set_size(btn_scan, 150, 35);
    // lv_obj_set_style_bg_color(btn_scan, COLOR_PRIMARY, 0);
    // lv_obj_add_event_cb(btn_scan, network_scan_clicked, LV_EVENT_CLICKED, NULL);
    // lv_obj_t *btn_scan_label = lv_label_create(btn_scan);
    // lv_label_set_text(btn_scan_label, LV_SYMBOL_WIFI " Scan");
    // lv_obj_set_style_text_font(btn_scan_label, &lv_font_montserrat_14, 0);
    // lv_obj_center(btn_scan_label);

    // Save & Connect button
    lv_obj_t *btn_save = lv_btn_create(btn_container);
    lv_obj_set_size(btn_save, 250, 35);
    lv_obj_set_style_bg_color(btn_save, COLOR_SUCCESS, 0);
    lv_obj_add_event_cb(btn_save, network_save_and_connect_clicked, LV_EVENT_CLICKED, NULL);
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

// Input field focused - attach keyboard
static void network_input_focused(lv_event_t *e) {
    lv_obj_t *textarea = lv_event_get_target(e);
    if (network_keyboard != NULL) {
        lv_keyboard_set_textarea(network_keyboard, textarea);
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

    // Create scan results list
    if (network_config_container != NULL) {
        network_scan_list = lv_list_create(network_config_container);
        lv_obj_set_size(network_scan_list, 850, 150);
        lv_obj_align(network_scan_list, LV_ALIGN_TOP_MID, 0, 350);
        lv_obj_set_style_bg_color(network_scan_list, COLOR_STATUS_BG, 0);
        lv_obj_set_style_border_color(network_scan_list, COLOR_PRIMARY, 0);

        // Get scan results
        wifi_scan_result_t results[WIFI_MAX_SCAN_RESULTS];
        uint16_t count = 0;
        esp_err_t ret = wifi_manager_get_scan_results(results, WIFI_MAX_SCAN_RESULTS, &count);

        if (ret == ESP_OK) {
            for (int i = 0; i < count; i++) {
                char item_text[128];
                const char *auth_str = wifi_manager_get_authmode_string(results[i].authmode);
                snprintf(item_text, sizeof(item_text), "%s (%d dBm) - %s",
                         results[i].ssid, results[i].rssi, auth_str);

                lv_obj_t *btn = lv_list_add_btn(network_scan_list, LV_SYMBOL_WIFI, item_text);
                lv_obj_set_style_text_font(btn, &lv_font_montserrat_14, 0);

                // Store SSID in user data (allocate and copy)
                char *ssid_copy = malloc(WIFI_MAX_SSID_LEN);
                if (ssid_copy) {
                    strncpy(ssid_copy, results[i].ssid, WIFI_MAX_SSID_LEN - 1);
                    ssid_copy[WIFI_MAX_SSID_LEN - 1] = '\0';
                    lv_obj_add_event_cb(btn, network_scan_item_clicked, LV_EVENT_CLICKED, ssid_copy);
                }
            }
        }
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

        // Free the allocated SSID string
        free(ssid);

        // Hide scan list
        if (network_scan_list != NULL) {
            lv_obj_del(network_scan_list);
            network_scan_list = NULL;
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

    // Ensure network config screen is visible
    if (network_config_screen != NULL) {
        lv_obj_clear_flag(network_config_screen, LV_OBJ_FLAG_HIDDEN);
    }

    // Create info container
    device_info_container = lv_obj_create(network_config_screen);
    lv_obj_set_size(device_info_container, 950, 520);
    lv_obj_align(device_info_container, LV_ALIGN_TOP_MID, 0, 15);
    lv_obj_set_style_bg_color(device_info_container, COLOR_STATUS_BG, 0);
    lv_obj_set_style_border_color(device_info_container, COLOR_PRIMARY, 0);
    lv_obj_set_style_border_width(device_info_container, 2, 0);
    lv_obj_set_scrollbar_mode(device_info_container, LV_SCROLLBAR_MODE_AUTO);

    // Title
    lv_obj_t *title = lv_label_create(device_info_container);
    lv_label_set_text(title, "Device Information");
    lv_obj_set_style_text_font(title, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(title, COLOR_PRIMARY, 0);
    lv_obj_align(title, LV_ALIGN_TOP_MID, 0, 15);

    // Info text container
    lv_obj_t *info_text = lv_label_create(device_info_container);
    lv_obj_set_width(info_text, 900);
    lv_obj_align(info_text, LV_ALIGN_TOP_LEFT, 25, 60);
    lv_obj_set_style_text_font(info_text, &lv_font_montserrat_14, 0);
    lv_obj_set_style_text_color(info_text, lv_color_white(), 0);
    lv_label_set_long_mode(info_text, LV_LABEL_LONG_WRAP);

    // Build info string with board specs
    char info_buf[1024];
    snprintf(info_buf, sizeof(info_buf),
        "FIRMWARE\n"
        "  Version: %s\n"
        "  Build Date: %s\n"
        "  Build Time: %s\n\n"
        "HARDWARE\n"
        "  Board: ESP32-P4-Function-EV-Board\n"
        "  Main MCU: ESP32-P4 (RISC-V, Dual-core)\n"
        "  WiFi/BT MCU: ESP32-C6-MINI-1\n"
        "  Display: 7\" 1024x600 RGB LCD\n"
        "  Touch: GT911 Capacitive Touch\n"
        "  Flash: 16MB\n\n"
        "CONNECTIVITY\n"
        "  WiFi: 802.11 b/g/n (ESP32-C6)\n"
        "  Bluetooth: BLE 5.0 (ESP32-C6)\n"
        "  NFC: PN532 (SPI)\n"
        "  Ethernet: Supported (not configured)\n\n"
        "MEMORY\n"
        "  Free Heap: %lu bytes\n"
        "  Min Free Heap: %lu bytes",
        FIRMWARE_VERSION,
        FIRMWARE_BUILD_DATE,
        FIRMWARE_BUILD_TIME,
        (unsigned long)esp_get_free_heap_size(),
        (unsigned long)esp_get_minimum_free_heap_size()
    );

    lv_label_set_text(info_text, info_buf);

    // Back button
    lv_obj_t *btn_back = lv_btn_create(device_info_container);
    lv_obj_set_size(btn_back, 200, 40);
    lv_obj_align(btn_back, LV_ALIGN_BOTTOM_MID, 0, -15);
    lv_obj_set_style_bg_color(btn_back, COLOR_ERROR, 0);
    lv_obj_add_event_cb(btn_back, setup_back_clicked, LV_EVENT_CLICKED, NULL);

    lv_obj_t *btn_back_label = lv_label_create(btn_back);
    lv_label_set_text(btn_back_label, "Back");
    lv_obj_set_style_text_font(btn_back_label, &lv_font_montserrat_14, 0);
    lv_obj_center(btn_back_label);
}

static void setup_time_clicked(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings clicked (not implemented)");
    // TODO: Show time configuration screen
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

    if (setup_screen != NULL) {
        lv_obj_del(setup_screen);
        setup_screen = NULL;
    }
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
