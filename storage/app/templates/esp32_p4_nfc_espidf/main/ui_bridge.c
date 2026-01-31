/**
 * UI Bridge - Connects SquareLine Studio UI to Backend Managers
 */

#include "ui_bridge.h"
#include "ui.h"
#include "features.h"
#include "esp_log.h"
#include "esp_system.h"
#include "esp_mac.h"
#include "esp_chip_info.h"
#include "esp_flash.h"
#include "esp_psram.h"
#include "esp_partition.h"
#include "esp_ota_ops.h"
#include "esp_sntp.h"
#include "esp_wifi.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "firmware_info.h"
#include "time_settings.h"
#include "wifi_manager.h"
#include "ethernet_manager.h"
#include "network_manager.h"
#include "api_client.h"
#include "nvs_flash.h"
#include "nvs.h"
#include <string.h>
#include <time.h>
#include <sys/time.h>

static const char *TAG = "UI_BRIDGE";

// Material Symbols icon codes for ui_font_Icons
// WiFi icon (0xE63E) - UTF-8 encoded
#define ICON_WIFI "\xEE\x98\xBE"
// Wired/Ethernet icon (0xEB2F) - UTF-8 encoded
#define ICON_ETHERNET "\xEE\xAC\xAF"

// Timer for updating main screen clock
static lv_timer_t *clock_timer = NULL;

// Track current focused textarea for keyboard input
static lv_obj_t *current_focused_textarea = NULL;

// WiFi network scanner dropdown
static lv_obj_t *wifi_scan_dropdown = NULL;
static wifi_scan_result_t wifi_scan_results[WIFI_MAX_SCAN_RESULTS];
static uint16_t wifi_scan_count = 0;
static bool wifi_scan_in_progress = false;

// Track if callbacks have been registered for each screen (screens are created lazily)
static bool network_screen_callbacks_registered = false;
static bool server_screen_callbacks_registered = false;
static bool device_screen_callbacks_registered = false;
static bool time_screen_callbacks_registered = false;

// Track current network type selection (false=WiFi, true=Wired)
static bool is_wired_mode = false;

// Forward declarations - Main/Admin
static void clock_tick_cb(lv_timer_t *timer);
static void settings_button_cb(lv_event_t *e);
static void admin_cancel_cb(lv_event_t *e);
static void admin_ok_cb(lv_event_t *e);
static void admin_keyboard_cb(lv_event_t *e);

// Forward declarations - Setup Configurations
static void setup_close_cb(lv_event_t *e);
static void setup_network_cb(lv_event_t *e);
static void setup_server_cb(lv_event_t *e);
static void setup_device_cb(lv_event_t *e);
static void setup_time_cb(lv_event_t *e);

// Forward declarations - Network Setup
static void network_cancel_cb(lv_event_t *e);
static void network_ok_cb(lv_event_t *e);
static void network_type_cb(lv_event_t *e);
static void network_dhcp_cb(lv_event_t *e);
static void network_textarea_focus_cb(lv_event_t *e);
static void network_keyboard_cb(lv_event_t *e);
static void network_ssid_click_cb(lv_event_t *e);
static void populate_network_fields(void);
static void save_network_config(void);
static void update_network_fields_visibility(void);

// Forward declarations - Server Setup
static void server_cancel_cb(lv_event_t *e);
static void server_ok_cb(lv_event_t *e);
static void server_test_cb(lv_event_t *e);
static void server_register_cb(lv_event_t *e);
static void populate_server_fields(void);
static void save_server_config(void);

// Forward declarations - Device Info
static void device_cancel_cb(lv_event_t *e);
static void device_ok_cb(lv_event_t *e);
static void device_hardware_cb(lv_event_t *e);
static void device_software_cb(lv_event_t *e);
static void populate_device_info(bool hardware);

// Forward declarations - Time Info
static void time_cancel_cb(lv_event_t *e);
static void time_ok_cb(lv_event_t *e);
// time_sync_cb removed - handled by ui_event_sync_now() in ui_events.c
static void populate_time_fields(void);

// Forward declarations - WiFi Scanner
static void wifi_scan_dropdown_select_cb(lv_event_t *e);
static void wifi_scan_dropdown_close_cb(lv_event_t *e);
static void wifi_scan_done_cb(uint16_t num_results);
static void create_wifi_scan_dropdown(void);
static void hide_wifi_scan_dropdown(void);
static const char* get_auth_mode_str(wifi_auth_mode_t authmode);
static const char* get_signal_icon(int8_t rssi);

// Forward declarations - Screen Callback Registration
static void register_network_screen_callbacks(void);
static void register_server_screen_callbacks(void);
static void register_device_screen_callbacks(void);
static void register_time_screen_callbacks(void);

// Other forward declarations
static void reset_password_color_cb(lv_timer_t *t);

// =====================================================
// INITIALIZATION
// =====================================================

esp_err_t ui_bridge_init(void) {
    ESP_LOGI(TAG, "Initializing UI Bridge");

    // =====================================================
    // MAIN SCREEN - Clock updates and Settings button
    // =====================================================

    // Start clock update timer (every 1 second)
    clock_timer = lv_timer_create(clock_tick_cb, 1000, NULL);
    if (clock_timer) {
        ESP_LOGI(TAG, "Clock timer started");
        clock_tick_cb(NULL);  // Immediate first update
    }

    // Settings button on main screen
    if (ui_mainscreen_button_settingsbutton) {
        lv_obj_add_event_cb(ui_mainscreen_button_settingsbutton, settings_button_cb, LV_EVENT_CLICKED, NULL);
    }

    // Initialize network icon (disconnected state with WiFi icon)
    if (ui_mainscreen_label_neticonlabel) {
        lv_label_set_text(ui_mainscreen_label_neticonlabel, ICON_WIFI);
        lv_obj_set_style_text_color(ui_mainscreen_label_neticonlabel, lv_color_hex(0xFF0000), 0);
    }

    // Initialize machine name from API config
    if (ui_mainscreen_label_machinenamelabel) {
        api_config_t *cfg = api_get_config();
        if (cfg && cfg->device_name[0] != '\0') {
            lv_label_set_text(ui_mainscreen_label_machinenamelabel, cfg->device_name);
        }
    }

    // =====================================================
    // ADMIN LOGIN SCREEN
    // =====================================================

    if (ui_adminlogin_button_cancelbutton) {
        lv_obj_add_event_cb(ui_adminlogin_button_cancelbutton, admin_cancel_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_adminlogin_button_okbutton) {
        lv_obj_add_event_cb(ui_adminlogin_button_okbutton, admin_ok_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_adminlogin_keyboard_passwordkeyboard) {
        lv_obj_add_event_cb(ui_adminlogin_keyboard_passwordkeyboard, admin_keyboard_cb, LV_EVENT_READY, NULL);
        lv_obj_add_event_cb(ui_adminlogin_keyboard_passwordkeyboard, admin_keyboard_cb, LV_EVENT_CANCEL, NULL);

        // Connect keyboard to password field
        if (ui_adminlogin_textarea_passwordinput) {
            lv_keyboard_set_textarea(ui_adminlogin_keyboard_passwordkeyboard, ui_adminlogin_textarea_passwordinput);
        }
    }

    // =====================================================
    // SETUP CONFIGURATIONS SCREEN
    // =====================================================

    if (ui_setupconfigurations_button_closebutton) {
        lv_obj_add_event_cb(ui_setupconfigurations_button_closebutton, setup_close_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_setupconfigurations_button_networksettingsbutton) {
        lv_obj_add_event_cb(ui_setupconfigurations_button_networksettingsbutton, setup_network_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_setupconfigurations_button_systemsetupbutton) {
        lv_obj_add_event_cb(ui_setupconfigurations_button_systemsetupbutton, setup_server_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_setupconfigurations_button_deviceinfobutton) {
        lv_obj_add_event_cb(ui_setupconfigurations_button_deviceinfobutton, setup_device_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_setupconfigurations_button_timeinfobutton) {
        lv_obj_add_event_cb(ui_setupconfigurations_button_timeinfobutton, setup_time_cb, LV_EVENT_CLICKED, NULL);
    }

    // NOTE: Network, Server, Device, and Time screens are created lazily by SquareLine
    // Their callbacks will be registered when navigating to each screen for the first time

    ESP_LOGI(TAG, "UI Bridge initialized");
    return ESP_OK;
}

// =====================================================
// CLOCK UPDATE
// =====================================================

static void clock_tick_cb(lv_timer_t *timer) {
    time_t now;
    struct tm timeinfo;
    time(&now);
    localtime_r(&now, &timeinfo);

    // Update time label
    if (ui_mainscreen_label_timelabel) {
        char time_str[16];
        strftime(time_str, sizeof(time_str), "%I:%M %p", &timeinfo);
        lv_label_set_text(ui_mainscreen_label_timelabel, time_str);
    }

    // Update date label
    if (ui_mainscreen_label_datelabel) {
        char date_str[32];
        strftime(date_str, sizeof(date_str), "%A, %B %d", &timeinfo);
        lv_label_set_text(ui_mainscreen_label_datelabel, date_str);
    }
}

// =====================================================
// MAIN SCREEN CALLBACKS
// =====================================================

static void settings_button_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Settings button pressed");
    _ui_screen_change(&ui_screen_adminlogin, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_adminlogin_screen_init);
}

// =====================================================
// ADMIN LOGIN CALLBACKS
// =====================================================

static void admin_cancel_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Admin login cancelled");
    if (ui_adminlogin_textarea_passwordinput) {
        lv_textarea_set_text(ui_adminlogin_textarea_passwordinput, "");
    }
    lv_screen_load(ui_screen_mainscreen);
}

static void admin_ok_cb(lv_event_t *e) {
    if (!ui_adminlogin_textarea_passwordinput) return;

    const char *password = lv_textarea_get_text(ui_adminlogin_textarea_passwordinput);

    if (strcmp(password, DEFAULT_ADMIN_PASSWORD) == 0) {
        ESP_LOGI(TAG, "Admin password accepted");
        lv_textarea_set_text(ui_adminlogin_textarea_passwordinput, "");
        _ui_screen_change(&ui_screen_setupconfigurations, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_setupconfigurations_screen_init);
    } else {
        ESP_LOGW(TAG, "Invalid admin password");
        lv_textarea_set_text(ui_adminlogin_textarea_passwordinput, "");
        lv_obj_set_style_bg_color(ui_adminlogin_textarea_passwordinput, lv_color_hex(0xFF0000), 0);
        lv_timer_create(reset_password_color_cb, 1000, ui_adminlogin_textarea_passwordinput);
    }
}

static void admin_keyboard_cb(lv_event_t *e) {
    lv_event_code_t code = lv_event_get_code(e);
    if (code == LV_EVENT_READY) {
        admin_ok_cb(e);
    } else if (code == LV_EVENT_CANCEL) {
        admin_cancel_cb(e);
    }
}

static void reset_password_color_cb(lv_timer_t *t) {
    lv_obj_t *ta = (lv_obj_t *)lv_timer_get_user_data(t);
    if (ta) {
        lv_obj_set_style_bg_color(ta, lv_color_hex(0x333333), 0);
    }
    lv_timer_delete(t);
}

// =====================================================
// SETUP CONFIGURATIONS CALLBACKS
// =====================================================

static void setup_close_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Setup menu closed");
    lv_screen_load(ui_screen_mainscreen);
}

static void setup_network_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Network settings selected");
    _ui_screen_change(&ui_screen_networksetup, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_networksetup_screen_init);
    register_network_screen_callbacks();
    populate_network_fields();
}

static void setup_server_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Server settings selected");
    _ui_screen_change(&ui_screen_serversetup, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_serversetup_screen_init);
    register_server_screen_callbacks();
    populate_server_fields();
}

static void setup_device_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Device info selected");
    _ui_screen_change(&ui_screen_deviceinformation, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_deviceinformation_screen_init);
    register_device_screen_callbacks();
    populate_device_info(true);
}

static void setup_time_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings selected");
    _ui_screen_change(&ui_screen_timeinformation, LV_SCR_LOAD_ANIM_FADE_ON, 200, 0, &ui_screen_timeinformation_screen_init);
    register_time_screen_callbacks();
    populate_time_fields();
}

// =====================================================
// NETWORK SETUP CALLBACKS
// =====================================================

static void network_cancel_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Network setup cancelled");
    hide_wifi_scan_dropdown();
    lv_screen_load(ui_screen_setupconfigurations);
}

static void network_ok_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Network setup saving...");
    hide_wifi_scan_dropdown();
    save_network_config();
    lv_screen_load(ui_screen_setupconfigurations);
}

static void network_type_cb(lv_event_t *e) {
    if (!ui_networksetup_switch_networktypeselector) return;

    is_wired_mode = lv_obj_has_state(ui_networksetup_switch_networktypeselector, LV_STATE_CHECKED);
    ESP_LOGI(TAG, "Network type changed: %s", is_wired_mode ? "Wired" : "WiFi");

    update_network_fields_visibility();
}

static void update_network_fields_visibility(void) {
    // WiFi-only fields: SSID, Password containers
    if (ui_networksetup_container_ssidcontainer) {
        if (is_wired_mode) {
            lv_obj_add_flag(ui_networksetup_container_ssidcontainer, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_remove_flag(ui_networksetup_container_ssidcontainer, LV_OBJ_FLAG_HIDDEN);
        }
    }
    if (ui_networksetup_container_passwordcontainer) {
        if (is_wired_mode) {
            lv_obj_add_flag(ui_networksetup_container_passwordcontainer, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_remove_flag(ui_networksetup_container_passwordcontainer, LV_OBJ_FLAG_HIDDEN);
        }
    }

    // Wired-only fields: VLAN container
    if (ui_networksetup_container_vlancontainer) {
        if (is_wired_mode) {
            lv_obj_remove_flag(ui_networksetup_container_vlancontainer, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_add_flag(ui_networksetup_container_vlancontainer, LV_OBJ_FLAG_HIDDEN);
        }
    }
}

static void network_dhcp_cb(lv_event_t *e) {
    bool dhcp_enabled = lv_obj_has_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED);
    ESP_LOGI(TAG, "DHCP: %s", dhcp_enabled ? "enabled" : "disabled");

    if (ui_networksetup_container_manualipsettingscontainer) {
        if (dhcp_enabled) {
            lv_obj_add_flag(ui_networksetup_container_manualipsettingscontainer, LV_OBJ_FLAG_HIDDEN);
        } else {
            lv_obj_remove_flag(ui_networksetup_container_manualipsettingscontainer, LV_OBJ_FLAG_HIDDEN);
        }
    }
}

static void network_textarea_focus_cb(lv_event_t *e) {
    lv_obj_t *ta = lv_event_get_target(e);
    current_focused_textarea = ta;
    if (ui_networksetup_keyboard_networkkeyboard) {
        lv_keyboard_set_textarea(ui_networksetup_keyboard_networkkeyboard, ta);
    }
}

static void network_keyboard_cb(lv_event_t *e) {
    lv_event_code_t code = lv_event_get_code(e);
    if (code == LV_EVENT_READY || code == LV_EVENT_CANCEL) {
        if (current_focused_textarea) {
            lv_obj_remove_state(current_focused_textarea, LV_STATE_FOCUSED);
        }
    }
}

static void network_ssid_click_cb(lv_event_t *e) {
    if (is_wired_mode) return;  // Don't scan if in wired mode

    ESP_LOGI(TAG, "SSID field clicked - starting WiFi scan");
    wifi_scan_in_progress = true;
    wifi_scan_count = 0;
    create_wifi_scan_dropdown();

    esp_err_t err = wifi_manager_scan_async(wifi_scan_done_cb);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start WiFi scan: %s", esp_err_to_name(err));
        wifi_scan_in_progress = false;
        create_wifi_scan_dropdown();
    }
}

static void populate_network_fields(void) {
    // Determine current mode from saved preference
    network_mode_t mode = network_manager_get_mode();
    is_wired_mode = (mode == NETWORK_MODE_ETHERNET_ONLY);

    // Set switch state
    if (ui_networksetup_switch_networktypeselector) {
        if (is_wired_mode) {
            lv_obj_add_state(ui_networksetup_switch_networktypeselector, LV_STATE_CHECKED);
        } else {
            lv_obj_remove_state(ui_networksetup_switch_networktypeselector, LV_STATE_CHECKED);
        }
    }

    // Update field visibility
    update_network_fields_visibility();

    // Load WiFi config
    wifi_network_config_t wifi_config;
    if (wifi_manager_load_config(&wifi_config) == ESP_OK) {
        if (ui_networksetup_textarea_ssidinput) {
            lv_textarea_set_text(ui_networksetup_textarea_ssidinput, wifi_config.ssid);
        }
        if (ui_networksetup_textarea_passwordinput) {
            lv_textarea_set_text(ui_networksetup_textarea_passwordinput, wifi_config.password);
        }
        if (ui_networksetup_textarea_hostnameinput) {
            lv_textarea_set_text(ui_networksetup_textarea_hostnameinput, wifi_config.hostname);
        }
        if (ui_networksetup_switch_dhcpswitch) {
            if (wifi_config.use_dhcp) {
                lv_obj_add_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED);
            } else {
                lv_obj_remove_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED);
            }
            network_dhcp_cb(NULL);
        }
        if (!wifi_config.use_dhcp) {
            if (ui_networksetup_textarea_ipaddressinput) {
                lv_textarea_set_text(ui_networksetup_textarea_ipaddressinput, wifi_config.static_ip);
            }
            if (ui_networksetup_textarea_gatewayinput) {
                lv_textarea_set_text(ui_networksetup_textarea_gatewayinput, wifi_config.static_gateway);
            }
            if (ui_networksetup_textarea_netmaskinput) {
                lv_textarea_set_text(ui_networksetup_textarea_netmaskinput, wifi_config.static_netmask);
            }
            if (ui_networksetup_textarea_dnsinput) {
                lv_textarea_set_text(ui_networksetup_textarea_dnsinput, wifi_config.static_dns_primary);
            }
            if (ui_networksetup_textarea_dns2input) {
                lv_textarea_set_text(ui_networksetup_textarea_dns2input, wifi_config.static_dns_secondary);
            }
        }
    } else {
        // Set defaults
        if (ui_networksetup_switch_dhcpswitch) {
            lv_obj_add_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED);
            network_dhcp_cb(NULL);
        }
    }

    // Load Ethernet config for VLAN
    ethernet_config_t eth_config;
    if (ethernet_manager_load_config(&eth_config) == ESP_OK) {
        // If we have ethernet hostname, use it when in wired mode
        if (is_wired_mode && ui_networksetup_textarea_hostnameinput && strlen(eth_config.hostname) > 0) {
            lv_textarea_set_text(ui_networksetup_textarea_hostnameinput, eth_config.hostname);
        }
        // TODO: Load VLAN from eth_config when implemented
    }

    // Update connection status label
    if (ui_networksetup_label_wifidisconnected) {
        bool wifi_connected = wifi_manager_is_connected();
        bool eth_connected = ethernet_manager_is_connected();

        if (is_wired_mode && eth_connected) {
            char ip[16];
            ethernet_manager_get_ip_string(ip, sizeof(ip));
            char status[48];
            snprintf(status, sizeof(status), "Connected: %s", ip);
            lv_label_set_text(ui_networksetup_label_wifidisconnected, status);
            lv_obj_set_style_text_color(ui_networksetup_label_wifidisconnected, lv_color_hex(0x00FF00), 0);
        } else if (!is_wired_mode && wifi_connected) {
            char ip[16];
            wifi_manager_get_ip_string(ip, sizeof(ip));
            char status[48];
            snprintf(status, sizeof(status), "Connected: %s", ip);
            lv_label_set_text(ui_networksetup_label_wifidisconnected, status);
            lv_obj_set_style_text_color(ui_networksetup_label_wifidisconnected, lv_color_hex(0x00FF00), 0);
        } else {
            lv_label_set_text(ui_networksetup_label_wifidisconnected, "Disconnected");
            lv_obj_set_style_text_color(ui_networksetup_label_wifidisconnected, lv_color_hex(0xFF0000), 0);
        }
    }
}

static void save_network_config(void) {
    if (is_wired_mode) {
        // Save Ethernet config
        ethernet_config_t config = {0};

        if (ui_networksetup_textarea_hostnameinput) {
            strncpy(config.hostname, lv_textarea_get_text(ui_networksetup_textarea_hostnameinput), sizeof(config.hostname) - 1);
        }

        config.use_dhcp = ui_networksetup_switch_dhcpswitch ? lv_obj_has_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED) : true;

        if (!config.use_dhcp) {
            if (ui_networksetup_textarea_ipaddressinput) {
                strncpy(config.static_ip, lv_textarea_get_text(ui_networksetup_textarea_ipaddressinput), sizeof(config.static_ip) - 1);
            }
            if (ui_networksetup_textarea_gatewayinput) {
                strncpy(config.static_gateway, lv_textarea_get_text(ui_networksetup_textarea_gatewayinput), sizeof(config.static_gateway) - 1);
            }
            if (ui_networksetup_textarea_netmaskinput) {
                strncpy(config.static_netmask, lv_textarea_get_text(ui_networksetup_textarea_netmaskinput), sizeof(config.static_netmask) - 1);
            }
            if (ui_networksetup_textarea_dnsinput) {
                strncpy(config.static_dns_primary, lv_textarea_get_text(ui_networksetup_textarea_dnsinput), sizeof(config.static_dns_primary) - 1);
            }
            if (ui_networksetup_textarea_dns2input) {
                strncpy(config.static_dns_secondary, lv_textarea_get_text(ui_networksetup_textarea_dns2input), sizeof(config.static_dns_secondary) - 1);
            }
        }

        // TODO: Get VLAN from ui_networksetup_textarea_vlaninput

        ESP_LOGI(TAG, "Saving Ethernet config - hostname: %s, DHCP: %s", config.hostname, config.use_dhcp ? "yes" : "no");

        if (ethernet_manager_save_config(&config) == ESP_OK) {
            ESP_LOGI(TAG, "Ethernet config saved to NVS");
            network_manager_set_mode(NETWORK_MODE_ETHERNET_ONLY);
            ESP_LOGI(TAG, "Network mode set to ETHERNET_ONLY");

            // STOP WiFi first - only one interface allowed at a time
            ESP_LOGI(TAG, "Stopping WiFi before starting Ethernet...");
            wifi_manager_disconnect();
            esp_wifi_stop();
            vTaskDelay(pdMS_TO_TICKS(200));

            if (ethernet_manager_apply_config(&config) == ESP_OK) {
                ESP_LOGI(TAG, "Ethernet config applied, starting...");
                ethernet_manager_start();
            }
        }
    } else {
        // Save WiFi config
        wifi_network_config_t config = {0};

        if (ui_networksetup_textarea_ssidinput) {
            strncpy(config.ssid, lv_textarea_get_text(ui_networksetup_textarea_ssidinput), sizeof(config.ssid) - 1);
        }
        if (ui_networksetup_textarea_passwordinput) {
            strncpy(config.password, lv_textarea_get_text(ui_networksetup_textarea_passwordinput), sizeof(config.password) - 1);
        }
        if (ui_networksetup_textarea_hostnameinput) {
            strncpy(config.hostname, lv_textarea_get_text(ui_networksetup_textarea_hostnameinput), sizeof(config.hostname) - 1);
        }

        config.use_dhcp = ui_networksetup_switch_dhcpswitch ? lv_obj_has_state(ui_networksetup_switch_dhcpswitch, LV_STATE_CHECKED) : true;

        if (!config.use_dhcp) {
            if (ui_networksetup_textarea_ipaddressinput) {
                strncpy(config.static_ip, lv_textarea_get_text(ui_networksetup_textarea_ipaddressinput), sizeof(config.static_ip) - 1);
            }
            if (ui_networksetup_textarea_gatewayinput) {
                strncpy(config.static_gateway, lv_textarea_get_text(ui_networksetup_textarea_gatewayinput), sizeof(config.static_gateway) - 1);
            }
            if (ui_networksetup_textarea_netmaskinput) {
                strncpy(config.static_netmask, lv_textarea_get_text(ui_networksetup_textarea_netmaskinput), sizeof(config.static_netmask) - 1);
            }
            if (ui_networksetup_textarea_dnsinput) {
                strncpy(config.static_dns_primary, lv_textarea_get_text(ui_networksetup_textarea_dnsinput), sizeof(config.static_dns_primary) - 1);
            }
            if (ui_networksetup_textarea_dns2input) {
                strncpy(config.static_dns_secondary, lv_textarea_get_text(ui_networksetup_textarea_dns2input), sizeof(config.static_dns_secondary) - 1);
            }
        }

        config.max_retry = 5;

        ESP_LOGI(TAG, "Saving WiFi config - SSID: %s", config.ssid);

        if (wifi_manager_save_config(&config) == ESP_OK) {
            ESP_LOGI(TAG, "WiFi config saved to NVS");
            network_manager_set_mode(NETWORK_MODE_WIFI_ONLY);
            ESP_LOGI(TAG, "Network mode set to WIFI_ONLY");

            // STOP Ethernet first - only one interface allowed at a time
            ESP_LOGI(TAG, "Stopping Ethernet before starting WiFi...");
            ethernet_manager_stop();
            vTaskDelay(pdMS_TO_TICKS(200));

            if (wifi_manager_apply_config(&config) == ESP_OK) {
                ESP_LOGI(TAG, "WiFi config applied, connecting...");
                wifi_manager_connect();
            }
        }
    }
}

// =====================================================
// SERVER SETUP CALLBACKS
// =====================================================

static void server_cancel_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Server setup cancelled");
    lv_screen_load(ui_screen_setupconfigurations);
}

static void server_ok_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Server setup saving...");
    save_server_config();
    lv_screen_load(ui_screen_setupconfigurations);
}

static void server_test_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Testing server connection...");

    // Get current values from fields
    const char *host = "";
    const char *port_str = "80";

    if (ui_serversetup_textarea_serverurlinput) {
        host = lv_textarea_get_text(ui_serversetup_textarea_serverurlinput);
    }
    if (ui_serversetup_textarea_portinput) {
        port_str = lv_textarea_get_text(ui_serversetup_textarea_portinput);
    }

    // Temporarily configure API client
    api_config_t temp_config = {0};
    strncpy(temp_config.server_host, host, sizeof(temp_config.server_host) - 1);
    temp_config.server_port = atoi(port_str);
    api_client_init(&temp_config);

    // Test connection
    esp_err_t ret = api_health_check();

    // Update status display
    if (ui_serversetup_textarea_statusinput) {
        if (ret == ESP_OK) {
            lv_textarea_set_text(ui_serversetup_textarea_statusinput, "Connected!");
            ESP_LOGI(TAG, "Server connection successful");
        } else {
            lv_textarea_set_text(ui_serversetup_textarea_statusinput, "Connection Failed");
            ESP_LOGE(TAG, "Server connection failed");
        }
    }
}

static void server_register_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Registering device...");

    // Get device name
    const char *device_name = "ESP32-TimeClock";
    if (ui_serversetup_textarea_devicenameinput) {
        const char *input = lv_textarea_get_text(ui_serversetup_textarea_devicenameinput);
        if (input && strlen(input) > 0) {
            device_name = input;
        }
    }

    // Get MAC address
    uint8_t mac[6];
    esp_read_mac(mac, ESP_MAC_WIFI_STA);
    char mac_str[18];
    snprintf(mac_str, sizeof(mac_str), "%02X:%02X:%02X:%02X:%02X:%02X",
             mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

    // Update status
    if (ui_serversetup_textarea_statusinput) {
        lv_textarea_set_text(ui_serversetup_textarea_statusinput, "Registering...");
    }

    // Register
    esp_err_t ret = api_register_device(mac_str, device_name);

    if (ret == ESP_OK) {
        api_config_t *cfg = api_get_config();

        if (ui_serversetup_textarea_statusinput) {
            lv_textarea_set_text(ui_serversetup_textarea_statusinput, "Registered!");
        }
        if (ui_serversetup_textarea_deviceidinput) {
            lv_textarea_set_text(ui_serversetup_textarea_deviceidinput, cfg->device_id);
        }

        ESP_LOGI(TAG, "Device registered successfully: %s", cfg->device_id);
    } else {
        if (ui_serversetup_textarea_statusinput) {
            lv_textarea_set_text(ui_serversetup_textarea_statusinput, "Registration Failed");
        }
        ESP_LOGE(TAG, "Device registration failed");
    }
}

static void populate_server_fields(void) {
    api_config_t *cfg = api_get_config();

    if (ui_serversetup_textarea_serverurlinput) {
        lv_textarea_set_text(ui_serversetup_textarea_serverurlinput, cfg->server_host);
    }
    if (ui_serversetup_textarea_portinput) {
        char port_str[8];
        snprintf(port_str, sizeof(port_str), "%d", cfg->server_port);
        lv_textarea_set_text(ui_serversetup_textarea_portinput, port_str);
    }
    if (ui_serversetup_textarea_devicenameinput) {
        lv_textarea_set_text(ui_serversetup_textarea_devicenameinput, cfg->device_name);
    }
    if (ui_serversetup_textarea_statusinput) {
        lv_textarea_set_text(ui_serversetup_textarea_statusinput, cfg->is_registered ? "Registered" : "Not Registered");
    }
    if (ui_serversetup_textarea_deviceidinput) {
        lv_textarea_set_text(ui_serversetup_textarea_deviceidinput, cfg->device_id[0] ? cfg->device_id : "---");
    }
}

static void save_server_config(void) {
    api_config_t config = {0};

    if (ui_serversetup_textarea_serverurlinput) {
        strncpy(config.server_host, lv_textarea_get_text(ui_serversetup_textarea_serverurlinput), sizeof(config.server_host) - 1);
    }
    if (ui_serversetup_textarea_portinput) {
        config.server_port = atoi(lv_textarea_get_text(ui_serversetup_textarea_portinput));
    }
    if (ui_serversetup_textarea_devicenameinput) {
        strncpy(config.device_name, lv_textarea_get_text(ui_serversetup_textarea_devicenameinput), sizeof(config.device_name) - 1);
    }

    // Preserve existing registration info
    api_config_t *current = api_get_config();
    strncpy(config.api_token, current->api_token, sizeof(config.api_token) - 1);
    strncpy(config.device_id, current->device_id, sizeof(config.device_id) - 1);
    config.is_registered = current->is_registered;
    config.is_approved = current->is_approved;

    api_client_init(&config);

    // Save to NVS so config persists across reboots
    api_save_config();
    ESP_LOGI(TAG, "Server config saved to NVS: %s:%d", config.server_host, config.server_port);
}

// =====================================================
// DEVICE INFORMATION CALLBACKS
// =====================================================

static void device_cancel_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Device info closed");
    lv_screen_load(ui_screen_setupconfigurations);
}

static void device_ok_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Device info OK");
    lv_screen_load(ui_screen_setupconfigurations);
}

static void device_hardware_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Hardware tab selected");
    populate_device_info(true);
}

static void device_software_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Software tab selected");
    populate_device_info(false);
}

static void populate_device_info(bool hardware) {
    if (!ui_deviceinformation_container_informationarea) return;

    lv_obj_clean(ui_deviceinformation_container_informationarea);

    lv_obj_t *info_label = lv_label_create(ui_deviceinformation_container_informationarea);
    lv_obj_set_width(info_label, LV_PCT(100));
    lv_label_set_long_mode(info_label, LV_LABEL_LONG_WRAP);
    lv_obj_set_style_text_color(info_label, lv_color_hex(0xFFFFFF), 0);

    char info_text[512];

    if (hardware) {
        esp_chip_info_t chip_info;
        esp_chip_info(&chip_info);

        uint32_t flash_size = 0;
        esp_flash_get_size(NULL, &flash_size);

        size_t psram_size = esp_psram_get_size();

        // MAC scheme: Hardware=base, WiFi=base+1, Ethernet=base+2
        uint8_t base_mac[6];
        esp_read_mac(base_mac, ESP_MAC_BASE);

        // WiFi MAC is base + 1
        uint8_t wifi_mac[6];
        memcpy(wifi_mac, base_mac, 6);
        wifi_mac[5] += 1;

        // Ethernet MAC is base + 2
        uint8_t eth_mac[6];
        memcpy(eth_mac, base_mac, 6);
        eth_mac[5] += 2;

        bool wifi_connected = wifi_manager_is_connected();
        bool eth_connected = ethernet_manager_is_connected();
        const char *active_interface = "None";
        if (eth_connected) {
            active_interface = "Ethernet";
        } else if (wifi_connected) {
            active_interface = "WiFi";
        }

        char wifi_ip[16] = "N/A";
        char eth_ip[16] = "N/A";
        if (wifi_connected) {
            wifi_manager_get_ip_string(wifi_ip, sizeof(wifi_ip));
        }
        if (eth_connected) {
            ethernet_manager_get_ip_string(eth_ip, sizeof(eth_ip));
        }

        snprintf(info_text, sizeof(info_text),
            "Chip: ESP32-P4\n"
            "Cores: %d\n"
            "Flash: %lu MB\n"
            "PSRAM: %lu MB\n"
            "Free Heap: %lu KB\n"
            "\n--- MAC Addresses ---\n"
            "Hardware: %02X:%02X:%02X:%02X:%02X:%02X\n"
            "WiFi: %02X:%02X:%02X:%02X:%02X:%02X\n"
            "Wired: %02X:%02X:%02X:%02X:%02X:%02X\n"
            "\n--- Network ---\n"
            "Active: %s\n"
            "WiFi: %s (%s)\n"
            "Wired: %s (%s)",
            chip_info.cores,
            (unsigned long)(flash_size / (1024 * 1024)),
            (unsigned long)(psram_size / (1024 * 1024)),
            (unsigned long)(esp_get_free_heap_size() / 1024),
            base_mac[0], base_mac[1], base_mac[2], base_mac[3], base_mac[4], base_mac[5],
            wifi_mac[0], wifi_mac[1], wifi_mac[2], wifi_mac[3], wifi_mac[4], wifi_mac[5],
            eth_mac[0], eth_mac[1], eth_mac[2], eth_mac[3], eth_mac[4], eth_mac[5],
            active_interface,
            wifi_connected ? "Connected" : "Disconnected", wifi_ip,
            eth_connected ? "Connected" : "Disconnected", eth_ip
        );
    } else {
        const esp_app_desc_t *app_desc = esp_app_get_description();

        // Get registration status
        api_config_t *api_config = api_get_config();
        const char *reg_status = "Not Registered";
        if (api_config && api_config->is_registered) {
            if (api_config->is_approved) {
                reg_status = "Registered & Approved";
            } else {
                reg_status = "Registered (Pending Approval)";
            }
        }

        // Get device ID
        const char *device_id = (api_config && strlen(api_config->device_id) > 0)
                                 ? api_config->device_id : "N/A";

        // Get current clock time
        time_t now;
        struct tm timeinfo;
        char clock_time[32] = "Not Set";
        time(&now);
        if (now > 1704067200) {  // After Jan 1, 2024 = time is likely valid
            localtime_r(&now, &timeinfo);
            strftime(clock_time, sizeof(clock_time), "%Y-%m-%d %H:%M:%S", &timeinfo);
        }

        // Get sync info
        const time_sync_data_t *sync_data = api_get_time_sync_data();
        sync_source_t sync_source = api_get_sync_source();

        const char *sync_source_str = "None";
        switch (sync_source) {
            case SYNC_SOURCE_SERVER: sync_source_str = "Server"; break;
            case SYNC_SOURCE_NTP: sync_source_str = "NTP"; break;
            case SYNC_SOURCE_MANUAL: sync_source_str = "Manual"; break;
            default: sync_source_str = "None"; break;
        }

        const char *timezone_str = (sync_data && strlen(sync_data->timezone) > 0)
                                    ? sync_data->timezone : "UTC";
        const char *ntp_server_str = (sync_data && strlen(sync_data->ntp_server) > 0)
                                      ? sync_data->ntp_server : "pool.ntp.org";

        snprintf(info_text, sizeof(info_text),
            "--- Firmware ---\n"
            "Version: %s\n"
            "Build: %s %s\n"
            "IDF: %s\n"
            "\n--- Registration ---\n"
            "Status: %s\n"
            "Device ID: %s\n"
            "\n--- Time Settings ---\n"
            "Clock Time: %s\n"
            "Timezone: %s\n"
            "NTP Server: %s\n"
            "Sync Source: %s",
            FIRMWARE_VERSION,
            FIRMWARE_BUILD_DATE, FIRMWARE_BUILD_TIME,
            esp_get_idf_version(),
            reg_status,
            device_id,
            clock_time,
            timezone_str,
            ntp_server_str,
            sync_source_str
        );
    }

    lv_label_set_text(info_label, info_text);

    // Update tab button states
    if (ui_deviceinformation_button_harwarebutton) {
        if (hardware) {
            lv_obj_add_state(ui_deviceinformation_button_harwarebutton, LV_STATE_CHECKED);
        } else {
            lv_obj_remove_state(ui_deviceinformation_button_harwarebutton, LV_STATE_CHECKED);
        }
    }
    if (ui_deviceinformation_button_softwarebutton) {
        if (!hardware) {
            lv_obj_add_state(ui_deviceinformation_button_softwarebutton, LV_STATE_CHECKED);
        } else {
            lv_obj_remove_state(ui_deviceinformation_button_softwarebutton, LV_STATE_CHECKED);
        }
    }
}

// =====================================================
// TIME INFORMATION CALLBACKS
// =====================================================

static void time_cancel_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings cancelled");
    lv_screen_load(ui_screen_setupconfigurations);
}

static void time_ok_cb(lv_event_t *e) {
    ESP_LOGI(TAG, "Time settings OK");
    lv_screen_load(ui_screen_setupconfigurations);
}

// NOTE: time_sync_cb removed - sync is handled by ui_event_sync_now() in ui_events.c
// which is called via SquareLine-generated ui_event_timeinformation_button_syncbutton()

static void populate_time_fields(void) {
    // Read saved settings from NVS
    char ntp_server[64] = "pool.ntp.org";  // default
    char timezone_name[64] = "";
    int timezone_index = 7;  // default to Pacific Time

    nvs_handle_t nvs_h;
    esp_err_t err = nvs_open("app_settings", NVS_READONLY, &nvs_h);
    if (err == ESP_OK) {
        // Read NTP server
        size_t ntp_len = sizeof(ntp_server);
        if (nvs_get_str(nvs_h, "ntp_server", ntp_server, &ntp_len) == ESP_OK) {
            ESP_LOGI(TAG, "Loaded NTP server from NVS: %s", ntp_server);
        }

        // Read timezone name
        size_t tz_len = sizeof(timezone_name);
        if (nvs_get_str(nvs_h, "timezone_name", timezone_name, &tz_len) == ESP_OK) {
            ESP_LOGI(TAG, "Loaded timezone from NVS: %s", timezone_name);

            // Map timezone name to dropdown index
            // Dropdown options:
            // 0 - Alaska Time (AKST/AKDT) - America/Anchorage
            // 1 - Atlantic Time (AST) - America/Puerto_Rico
            // 2 - Central Time (CST/CDT) - America/Chicago
            // 3 - Chamorro Time (ChST) - Pacific/Guam
            // 4 - Eastern Time (EST/EDT) - America/New_York
            // 5 - Hawaii–Aleutian Time (HST/HDT) - Pacific/Honolulu
            // 6 - Mountain Time (MST/MDT) - America/Denver
            // 7 - Pacific Time (PST/PDT) - America/Los_Angeles
            // 8 - Samoa Time (SST) - Pacific/Pago_Pago
            if (strstr(timezone_name, "Anchorage") || strstr(timezone_name, "Alaska")) {
                timezone_index = 0;
            } else if (strstr(timezone_name, "Puerto_Rico") || strstr(timezone_name, "Atlantic")) {
                timezone_index = 1;
            } else if (strstr(timezone_name, "Chicago") || strstr(timezone_name, "Central")) {
                timezone_index = 2;
            } else if (strstr(timezone_name, "Guam") || strstr(timezone_name, "Chamorro")) {
                timezone_index = 3;
            } else if (strstr(timezone_name, "New_York") || strstr(timezone_name, "Eastern")) {
                timezone_index = 4;
            } else if (strstr(timezone_name, "Honolulu") || strstr(timezone_name, "Hawaii")) {
                timezone_index = 5;
            } else if (strstr(timezone_name, "Denver") || strstr(timezone_name, "Mountain")) {
                timezone_index = 6;
            } else if (strstr(timezone_name, "Los_Angeles") || strstr(timezone_name, "Pacific")) {
                timezone_index = 7;
            } else if (strstr(timezone_name, "Pago_Pago") || strstr(timezone_name, "Samoa")) {
                timezone_index = 8;
            }
            ESP_LOGI(TAG, "Mapped timezone '%s' to dropdown index %d", timezone_name, timezone_index);
        }

        nvs_close(nvs_h);
    } else {
        ESP_LOGW(TAG, "Could not open NVS for time settings, using defaults");
    }

    // Populate NTP server field
    if (ui_timeinformation_textarea_ntpinput) {
        lv_textarea_set_text(ui_timeinformation_textarea_ntpinput, ntp_server);
        ESP_LOGI(TAG, "Set NTP field to: %s", ntp_server);
    }

    // Populate AM/PM based on current time
    time_t now;
    struct tm timeinfo;
    time(&now);
    localtime_r(&now, &timeinfo);

    if (ui_timeinformation_dropdown_ampm) {
        lv_dropdown_set_selected(ui_timeinformation_dropdown_ampm, timeinfo.tm_hour >= 12 ? 1 : 0);
    }

    // Populate timezone dropdown
    if (ui_timeinformation_dropdown_timezone) {
        lv_dropdown_set_selected(ui_timeinformation_dropdown_timezone, timezone_index);
        ESP_LOGI(TAG, "Set timezone dropdown to index %d", timezone_index);
    }
}

// =====================================================
// SCREEN CALLBACK REGISTRATION
// =====================================================

static void register_network_screen_callbacks(void) {
    if (network_screen_callbacks_registered) return;

    ESP_LOGI(TAG, "Registering Network screen callbacks");

    if (ui_networksetup_button_okbutton) {
        lv_obj_add_event_cb(ui_networksetup_button_okbutton, network_ok_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_networksetup_button_cancelbutton) {
        lv_obj_add_event_cb(ui_networksetup_button_cancelbutton, network_cancel_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_networksetup_switch_networktypeselector) {
        lv_obj_add_event_cb(ui_networksetup_switch_networktypeselector, network_type_cb, LV_EVENT_VALUE_CHANGED, NULL);
    }
    if (ui_networksetup_switch_dhcpswitch) {
        lv_obj_add_event_cb(ui_networksetup_switch_dhcpswitch, network_dhcp_cb, LV_EVENT_VALUE_CHANGED, NULL);
    }
    if (ui_networksetup_keyboard_networkkeyboard) {
        lv_obj_add_event_cb(ui_networksetup_keyboard_networkkeyboard, network_keyboard_cb, LV_EVENT_READY, NULL);
        lv_obj_add_event_cb(ui_networksetup_keyboard_networkkeyboard, network_keyboard_cb, LV_EVENT_CANCEL, NULL);
    }
    if (ui_networksetup_textarea_ssidinput) {
        lv_obj_add_event_cb(ui_networksetup_textarea_ssidinput, network_ssid_click_cb, LV_EVENT_CLICKED, NULL);
    }

    // Connect textareas to focus callback
    lv_obj_t *textareas[] = {
        ui_networksetup_textarea_ssidinput,
        ui_networksetup_textarea_passwordinput,
        ui_networksetup_textarea_hostnameinput,
        ui_networksetup_textarea_vlaninput,
        ui_networksetup_textarea_ipaddressinput,
        ui_networksetup_textarea_gatewayinput,
        ui_networksetup_textarea_netmaskinput,
        ui_networksetup_textarea_dnsinput,
        ui_networksetup_textarea_dns2input
    };
    for (int i = 0; i < sizeof(textareas)/sizeof(textareas[0]); i++) {
        if (textareas[i]) {
            lv_obj_add_event_cb(textareas[i], network_textarea_focus_cb, LV_EVENT_FOCUSED, NULL);
        }
    }

    network_screen_callbacks_registered = true;
}

static void register_server_screen_callbacks(void) {
    if (server_screen_callbacks_registered) return;

    ESP_LOGI(TAG, "Registering Server screen callbacks");

    if (ui_serversetup_button_okbutton) {
        lv_obj_add_event_cb(ui_serversetup_button_okbutton, server_ok_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_serversetup_button_cancelbutton) {
        lv_obj_add_event_cb(ui_serversetup_button_cancelbutton, server_cancel_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_serversetup_button_testconnectionbutton) {
        lv_obj_add_event_cb(ui_serversetup_button_testconnectionbutton, server_test_cb, LV_EVENT_CLICKED, NULL);
    }
    // NOTE: Register button handler is in ui_events.c (ui_event_server_register)
    // Do not add duplicate handler here to avoid double registration
    // if (ui_serversetup_button_registerbutton) {
    //     lv_obj_add_event_cb(ui_serversetup_button_registerbutton, server_register_cb, LV_EVENT_CLICKED, NULL);
    // }

    server_screen_callbacks_registered = true;
}

static void register_device_screen_callbacks(void) {
    if (device_screen_callbacks_registered) return;

    ESP_LOGI(TAG, "Registering Device screen callbacks");

    // Note: Cancel button was removed from Device Information screen
    if (ui_deviceinformation_button_okbutton) {
        lv_obj_add_event_cb(ui_deviceinformation_button_okbutton, device_ok_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_deviceinformation_button_harwarebutton) {
        lv_obj_add_event_cb(ui_deviceinformation_button_harwarebutton, device_hardware_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_deviceinformation_button_softwarebutton) {
        lv_obj_add_event_cb(ui_deviceinformation_button_softwarebutton, device_software_cb, LV_EVENT_CLICKED, NULL);
    }

    device_screen_callbacks_registered = true;
}

static void register_time_screen_callbacks(void) {
    if (time_screen_callbacks_registered) return;

    ESP_LOGI(TAG, "Registering Time screen callbacks");

    if (ui_timeinformation_button_cancelbutton) {
        lv_obj_add_event_cb(ui_timeinformation_button_cancelbutton, time_cancel_cb, LV_EVENT_CLICKED, NULL);
    }
    if (ui_timeinformation_button_okbutton) {
        lv_obj_add_event_cb(ui_timeinformation_button_okbutton, time_ok_cb, LV_EVENT_CLICKED, NULL);
    }
    // Sync button handled by SquareLine-generated ui_event_timeinformation_button_syncbutton()
    // which calls ui_event_sync_now() in ui_events.c

    time_screen_callbacks_registered = true;
}

// =====================================================
// NETWORK STATUS UPDATE
// =====================================================

void ui_bridge_update_network_status(bool connected, bool is_wifi, const char *ip_addr) {
    if (ui_mainscreen_label_neticonlabel) {
        if (connected) {
            // Use Material Symbols icons from ui_font_Icons
            lv_label_set_text(ui_mainscreen_label_neticonlabel, is_wifi ? ICON_WIFI : ICON_ETHERNET);
            lv_obj_set_style_text_color(ui_mainscreen_label_neticonlabel, lv_color_hex(0x00FF00), 0);
        } else {
            lv_label_set_text(ui_mainscreen_label_neticonlabel, ICON_WIFI);
            lv_obj_set_style_text_color(ui_mainscreen_label_neticonlabel, lv_color_hex(0xFF0000), 0);
        }
    }

    // Update machine name from API config
    if (ui_mainscreen_label_machinenamelabel) {
        api_config_t *cfg = api_get_config();
        if (cfg && cfg->device_name[0] != '\0') {
            lv_label_set_text(ui_mainscreen_label_machinenamelabel, cfg->device_name);
        }
    }
}

void ui_bridge_refresh_clock(void) {
    clock_tick_cb(NULL);
}

void ui_bridge_show_notification(const char *message, uint32_t duration_ms) {
    ESP_LOGI(TAG, "Notification: %s", message);
}

// =====================================================
// WIFI NETWORK SCANNER DROPDOWN
// =====================================================

static const char* get_auth_mode_str(wifi_auth_mode_t authmode) {
    switch (authmode) {
        case WIFI_AUTH_OPEN:            return "Open";
        case WIFI_AUTH_WEP:             return "WEP";
        case WIFI_AUTH_WPA_PSK:         return "WPA";
        case WIFI_AUTH_WPA2_PSK:        return "WPA2";
        case WIFI_AUTH_WPA_WPA2_PSK:    return "WPA/WPA2";
        case WIFI_AUTH_WPA3_PSK:        return "WPA3";
        case WIFI_AUTH_WPA2_WPA3_PSK:   return "WPA2/WPA3";
        case WIFI_AUTH_ENTERPRISE:      return "Enterprise";
        default:                        return "Unknown";
    }
}

static const char* get_signal_icon(int8_t rssi) {
    if (rssi >= -50) return "****";
    else if (rssi >= -60) return "*** ";
    else if (rssi >= -70) return "**  ";
    else return "*   ";
}

static void hide_wifi_scan_dropdown(void) {
    if (wifi_scan_dropdown) {
        lv_obj_delete(wifi_scan_dropdown);
        wifi_scan_dropdown = NULL;
    }
}

static void wifi_scan_dropdown_select_cb(lv_event_t *e) {
    lv_obj_t *btn = lv_event_get_target(e);
    uint32_t idx = lv_obj_get_index(btn);

    if (idx < wifi_scan_count) {
        ESP_LOGI(TAG, "Selected WiFi: %s", wifi_scan_results[idx].ssid);

        if (ui_networksetup_textarea_ssidinput) {
            lv_textarea_set_text(ui_networksetup_textarea_ssidinput, wifi_scan_results[idx].ssid);
        }

        if (ui_networksetup_textarea_passwordinput && ui_networksetup_keyboard_networkkeyboard) {
            lv_keyboard_set_textarea(ui_networksetup_keyboard_networkkeyboard, ui_networksetup_textarea_passwordinput);
            lv_obj_add_state(ui_networksetup_textarea_passwordinput, LV_STATE_FOCUSED);
        }
    }

    hide_wifi_scan_dropdown();
}

static void wifi_scan_dropdown_close_cb(lv_event_t *e) {
    hide_wifi_scan_dropdown();
}

static void create_wifi_scan_dropdown(void) {
    hide_wifi_scan_dropdown();

    if (!ui_networksetup_textarea_ssidinput) return;

    lv_obj_t *screen = lv_screen_active();

    wifi_scan_dropdown = lv_obj_create(screen);
    lv_obj_set_size(wifi_scan_dropdown, LV_PCT(80), LV_PCT(70));
    lv_obj_center(wifi_scan_dropdown);
    lv_obj_set_style_bg_color(wifi_scan_dropdown, lv_color_hex(0x2D2D2D), 0);
    lv_obj_set_style_bg_opa(wifi_scan_dropdown, LV_OPA_COVER, 0);
    lv_obj_set_style_border_color(wifi_scan_dropdown, lv_color_hex(0x4A90D9), 0);
    lv_obj_set_style_border_width(wifi_scan_dropdown, 2, 0);
    lv_obj_set_style_radius(wifi_scan_dropdown, 10, 0);
    lv_obj_set_style_pad_all(wifi_scan_dropdown, 10, 0);
    lv_obj_set_flex_flow(wifi_scan_dropdown, LV_FLEX_FLOW_COLUMN);
    lv_obj_set_flex_align(wifi_scan_dropdown, LV_FLEX_ALIGN_START, LV_FLEX_ALIGN_CENTER, LV_FLEX_ALIGN_CENTER);

    lv_obj_t *title_bar = lv_obj_create(wifi_scan_dropdown);
    lv_obj_set_size(title_bar, LV_PCT(100), 40);
    lv_obj_set_style_bg_opa(title_bar, LV_OPA_TRANSP, 0);
    lv_obj_set_style_border_width(title_bar, 0, 0);
    lv_obj_set_style_pad_all(title_bar, 0, 0);
    lv_obj_remove_flag(title_bar, LV_OBJ_FLAG_SCROLLABLE);

    lv_obj_t *title = lv_label_create(title_bar);
    lv_label_set_text(title, wifi_scan_in_progress ? "Scanning WiFi Networks..." : "Select WiFi Network");
    lv_obj_set_style_text_color(title, lv_color_hex(0xFFFFFF), 0);
    lv_obj_align(title, LV_ALIGN_LEFT_MID, 0, 0);

    lv_obj_t *close_btn = lv_button_create(title_bar);
    lv_obj_set_size(close_btn, 30, 30);
    lv_obj_align(close_btn, LV_ALIGN_RIGHT_MID, 0, 0);
    lv_obj_set_style_bg_color(close_btn, lv_color_hex(0xFF5555), 0);
    lv_obj_add_event_cb(close_btn, wifi_scan_dropdown_close_cb, LV_EVENT_CLICKED, NULL);

    lv_obj_t *close_label = lv_label_create(close_btn);
    lv_label_set_text(close_label, LV_SYMBOL_CLOSE);
    lv_obj_center(close_label);

    lv_obj_t *list = lv_list_create(wifi_scan_dropdown);
    lv_obj_set_size(list, LV_PCT(100), LV_PCT(100));
    lv_obj_set_flex_grow(list, 1);
    lv_obj_set_style_bg_color(list, lv_color_hex(0x1A1A1A), 0);
    lv_obj_set_style_border_width(list, 0, 0);
    lv_obj_set_style_radius(list, 5, 0);

    if (wifi_scan_in_progress) {
        lv_obj_t *loading = lv_label_create(list);
        lv_label_set_text(loading, "Searching for networks...");
        lv_obj_set_style_text_color(loading, lv_color_hex(0xAAAAAA), 0);
        lv_obj_center(loading);
    } else if (wifi_scan_count == 0) {
        lv_obj_t *empty = lv_label_create(list);
        lv_label_set_text(empty, "No networks found. Tap to retry.");
        lv_obj_set_style_text_color(empty, lv_color_hex(0xAAAAAA), 0);
        lv_obj_center(empty);
    } else {
        for (int i = 0; i < wifi_scan_count; i++) {
            char item_text[96];
            snprintf(item_text, sizeof(item_text), "%s %s  Ch:%d  %s",
                     get_signal_icon(wifi_scan_results[i].rssi),
                     wifi_scan_results[i].ssid,
                     wifi_scan_results[i].channel,
                     get_auth_mode_str(wifi_scan_results[i].authmode));

            lv_obj_t *btn = lv_list_add_button(list, LV_SYMBOL_WIFI, item_text);
            lv_obj_set_style_bg_color(btn, lv_color_hex(0x333333), 0);
            lv_obj_set_style_bg_color(btn, lv_color_hex(0x4A90D9), LV_STATE_PRESSED);
            lv_obj_set_style_text_color(btn, lv_color_hex(0xFFFFFF), 0);
            lv_obj_add_event_cb(btn, wifi_scan_dropdown_select_cb, LV_EVENT_CLICKED, NULL);
        }
    }
}

static void wifi_scan_done_cb(uint16_t num_results) {
    ESP_LOGI(TAG, "WiFi scan complete, found %d networks", num_results);

    wifi_scan_in_progress = false;
    wifi_manager_get_scan_results(wifi_scan_results, WIFI_MAX_SCAN_RESULTS, &wifi_scan_count);
    create_wifi_scan_dropdown();
}
