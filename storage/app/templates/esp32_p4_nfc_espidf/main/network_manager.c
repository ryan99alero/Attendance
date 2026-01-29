/**
 * Network Manager Implementation
 * Provides unified interface for all network types (WiFi, Ethernet, Bluetooth)
 */

#include "features.h"  // Must be first - defines WIFI_ENABLED, ETHERNET_ENABLED, etc.
#include "network_manager.h"
#include "esp_log.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "nvs_flash.h"
#include "nvs.h"
#include <string.h>

// Include enabled network managers
#if WIFI_ENABLED
#include "wifi_manager.h"
#include "esp_wifi.h"  // For esp_wifi_deinit()
#endif

#if ETHERNET_ENABLED
#include "ethernet_manager.h"
#endif

// Include UI bridge for network status updates (required for monitoring)
#if DISPLAY_ENABLED
#include "ui_bridge.h"
#include "esp_lvgl_port.h"
#endif

static const char *TAG = "NETWORK_MANAGER";

// NVS namespace for network mode
#define NVS_NAMESPACE_NETWORK "network_mode"
#define NVS_KEY_MODE "mode"

// Current network mode (default to WiFi)
static network_mode_t current_mode = NETWORK_MODE_WIFI_ONLY;
static bool mode_loaded = false;

// Monitoring task handle
static TaskHandle_t monitoring_task_handle = NULL;
static bool is_monitoring = false;

#if DISPLAY_ENABLED
// Internal monitoring task (only used when display is enabled)
static void network_monitoring_task(void *pvParameters) {
    bool last_connected = false;
    bool last_is_wifi = false;
    char last_ip[16] = {0};

    ESP_LOGI(TAG, "Network monitoring task started");

    while (is_monitoring) {
        bool current_connected = false;
        bool current_is_wifi = false;
        char current_ip[16] = {0};

#if ETHERNET_ENABLED
        // Check Ethernet first (wired takes priority over wireless)
        if (ethernet_manager_is_connected()) {
            current_connected = true;
            current_is_wifi = false;
            ethernet_manager_get_ip_string(current_ip, sizeof(current_ip));
        }
#endif

#if WIFI_ENABLED
        // Check WiFi if Ethernet is not connected
        if (!current_connected && wifi_manager_is_connected()) {
            current_connected = true;
            current_is_wifi = true;
            wifi_manager_get_ip_string(current_ip, sizeof(current_ip));
        }
#endif

        // Update UI if status changed
        if (current_connected != last_connected ||
            current_is_wifi != last_is_wifi ||
            strcmp(current_ip, last_ip) != 0) {

            lvgl_port_lock(0);
            ui_bridge_update_network_status(current_connected, current_is_wifi, current_ip);
            lvgl_port_unlock();

            last_connected = current_connected;
            last_is_wifi = current_is_wifi;
            strncpy(last_ip, current_ip, sizeof(last_ip) - 1);

            ESP_LOGI(TAG, "Network status: %s, IP: %s",
                     current_connected ? (current_is_wifi ? "WiFi" : "Ethernet") : "Disconnected",
                     current_ip);
        }

        // Check every 2 seconds
        vTaskDelay(pdMS_TO_TICKS(2000));
    }

    ESP_LOGI(TAG, "Network monitoring task stopped");
    monitoring_task_handle = NULL;
    vTaskDelete(NULL);
}
#endif // DISPLAY_ENABLED

// Load network mode from NVS
static void load_network_mode(void) {
    if (mode_loaded) {
        return;  // Already loaded
    }

    nvs_handle_t nvs_handle;
    esp_err_t err = nvs_open(NVS_NAMESPACE_NETWORK, NVS_READONLY, &nvs_handle);

    if (err == ESP_OK) {
        uint8_t mode_value = (uint8_t)NETWORK_MODE_WIFI_ONLY;
        err = nvs_get_u8(nvs_handle, NVS_KEY_MODE, &mode_value);

        if (err == ESP_OK) {
            current_mode = (network_mode_t)mode_value;
            ESP_LOGI(TAG, "Loaded network mode: %s", network_manager_get_mode_string(current_mode));
        } else {
            ESP_LOGI(TAG, "No saved network mode, using default: WiFi Only");
            current_mode = NETWORK_MODE_WIFI_ONLY;
        }

        nvs_close(nvs_handle);
    } else {
        ESP_LOGI(TAG, "Network mode NVS not found, using default: WiFi Only");
        current_mode = NETWORK_MODE_WIFI_ONLY;
    }

    mode_loaded = true;
}

esp_err_t network_manager_set_mode(network_mode_t mode) {
    nvs_handle_t nvs_handle;
    esp_err_t err = nvs_open(NVS_NAMESPACE_NETWORK, NVS_READWRITE, &nvs_handle);

    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Failed to open NVS for writing network mode");
        return err;
    }

    err = nvs_set_u8(nvs_handle, NVS_KEY_MODE, (uint8_t)mode);
    if (err == ESP_OK) {
        err = nvs_commit(nvs_handle);
        if (err == ESP_OK) {
            current_mode = mode;
            ESP_LOGI(TAG, "Network mode saved: %s (reboot required)", network_manager_get_mode_string(mode));
        }
    }

    nvs_close(nvs_handle);
    return err;
}

network_mode_t network_manager_get_mode(void) {
    if (!mode_loaded) {
        load_network_mode();
    }
    return current_mode;
}

const char* network_manager_get_mode_string(network_mode_t mode) {
    switch (mode) {
        case NETWORK_MODE_WIFI_ONLY:
            return "WiFi Only";
        case NETWORK_MODE_ETHERNET_ONLY:
            return "Ethernet Only";
        default:
            return "Unknown";
    }
}

esp_err_t network_manager_init(void) {
    ESP_LOGI(TAG, "Initializing network manager...");

    // Load network mode preference from NVS
    load_network_mode();
    ESP_LOGI(TAG, "Saved network mode: %s", network_manager_get_mode_string(current_mode));
    ESP_LOGI(TAG, "ONLY the selected interface will be connected - no fallback");

    esp_err_t ret = ESP_OK;
    bool any_initialized = false;

    // Initialize BOTH WiFi and Ethernet drivers (but only selected one will connect)
    // Initializing both allows runtime switching between interfaces
#if WIFI_ENABLED
    ESP_LOGI(TAG, "Initializing WiFi driver...");
    if (wifi_manager_init() == ESP_OK) {
        ESP_LOGI(TAG, "✅ WiFi driver initialized");
        any_initialized = true;
    } else {
        ESP_LOGE(TAG, "❌ WiFi driver initialization failed");
        ret = ESP_FAIL;
    }
#endif

#if ETHERNET_ENABLED
    ESP_LOGI(TAG, "Initializing Ethernet driver...");
    if (ethernet_manager_init() == ESP_OK) {
        ESP_LOGI(TAG, "✅ Ethernet driver initialized");
        any_initialized = true;
    } else {
        ESP_LOGE(TAG, "❌ Ethernet driver initialization failed");
        ret = ESP_FAIL;
    }
#endif

    if (!any_initialized) {
        ESP_LOGE(TAG, "No network interfaces initialized!");
        return ESP_FAIL;
    }

    ESP_LOGI(TAG, "Network manager initialized");
    ESP_LOGI(TAG, "Call load_and_connect() to connect the selected interface");
    return ret;
}

esp_err_t network_manager_start_monitoring(void) {
#if DISPLAY_ENABLED
    if (is_monitoring) {
        ESP_LOGW(TAG, "Monitoring already started");
        return ESP_ERR_INVALID_STATE;
    }

    is_monitoring = true;
    BaseType_t task_created = xTaskCreate(
        network_monitoring_task,
        "net_monitor",
        4096,
        NULL,
        5,
        &monitoring_task_handle
    );

    if (task_created != pdPASS) {
        ESP_LOGE(TAG, "Failed to create monitoring task");
        is_monitoring = false;
        return ESP_FAIL;
    }

    ESP_LOGI(TAG, "Network monitoring started");
    return ESP_OK;
#else
    ESP_LOGW(TAG, "Network monitoring disabled (DISPLAY_ENABLED=0)");
    return ESP_ERR_NOT_SUPPORTED;
#endif
}

esp_err_t network_manager_stop_monitoring(void) {
#if DISPLAY_ENABLED
    if (!is_monitoring) {
        ESP_LOGW(TAG, "Monitoring not started");
        return ESP_ERR_INVALID_STATE;
    }

    is_monitoring = false;

    // Wait for task to finish
    int timeout = 10;
    while (monitoring_task_handle != NULL && timeout > 0) {
        vTaskDelay(pdMS_TO_TICKS(100));
        timeout--;
    }

    if (monitoring_task_handle != NULL) {
        ESP_LOGW(TAG, "Monitoring task did not stop gracefully");
        return ESP_ERR_TIMEOUT;
    }

    ESP_LOGI(TAG, "Network monitoring stopped");
    return ESP_OK;
#else
    ESP_LOGW(TAG, "Network monitoring disabled (DISPLAY_ENABLED=0)");
    return ESP_ERR_NOT_SUPPORTED;
#endif
}

bool network_manager_is_connected(void) {
    // Check Ethernet first (higher priority)
#if ETHERNET_ENABLED
    if (ethernet_manager_is_connected()) {
        return true;
    }
#endif

    // Then check WiFi
#if WIFI_ENABLED
    if (wifi_manager_is_connected()) {
        return true;
    }
#endif

    return false;
}

network_type_t network_manager_get_active_type(void) {
    // Priority: Ethernet > WiFi > Bluetooth
#if ETHERNET_ENABLED
    if (ethernet_manager_is_connected()) {
        return NETWORK_TYPE_ETHERNET;
    }
#endif

#if WIFI_ENABLED
    if (wifi_manager_is_connected()) {
        return NETWORK_TYPE_WIFI;
    }
#endif

    return NETWORK_TYPE_NONE;
}

esp_err_t network_manager_get_ip_string(char *ip_str, size_t ip_str_size) {
    if (ip_str == NULL || ip_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    network_type_t active_type = network_manager_get_active_type();

    switch (active_type) {
#if ETHERNET_ENABLED
        case NETWORK_TYPE_ETHERNET:
            return ethernet_manager_get_ip_string(ip_str, ip_str_size);
#endif

#if WIFI_ENABLED
        case NETWORK_TYPE_WIFI:
            return wifi_manager_get_ip_string(ip_str, ip_str_size);
#endif

        default:
            strncpy(ip_str, "0.0.0.0", ip_str_size - 1);
            return ESP_ERR_INVALID_STATE;
    }
}

const char* network_manager_get_status_string(void) {
    network_type_t active_type = network_manager_get_active_type();

    switch (active_type) {
#if ETHERNET_ENABLED
        case NETWORK_TYPE_ETHERNET:
            return ethernet_manager_get_status_string();
#endif

#if WIFI_ENABLED
        case NETWORK_TYPE_WIFI:
            return wifi_manager_get_status_string();
#endif

        default:
            return "Disconnected";
    }
}

int network_manager_get_rssi(void) {
    network_type_t active_type = network_manager_get_active_type();

#if WIFI_ENABLED
    if (active_type == NETWORK_TYPE_WIFI) {
        return wifi_manager_get_rssi();
    }
#endif

    // Wired connections don't have RSSI
    return 0;
}

esp_err_t network_manager_load_and_connect(void) {
    ESP_EARLY_LOGI(TAG, ">>> load_and_connect ENTERED <<<");
    ESP_LOGI(TAG, "Loading saved network configurations...");
    ESP_EARLY_LOGI(TAG, "About to get mode string...");
    ESP_LOGI(TAG, "Selected mode: %s", network_manager_get_mode_string(current_mode));
    ESP_EARLY_LOGI(TAG, "Mode string OK");

    bool connected = false;

    // ONLY connect to the selected interface - NO fallback to other interface
    // This ensures only one network interface is active at a time
    if (current_mode == NETWORK_MODE_ETHERNET_ONLY) {
#if ETHERNET_ENABLED
        // Connect Ethernet ONLY - do not start WiFi
        ESP_LOGI(TAG, "Mode is Ethernet Only - WiFi will NOT be started");
        ethernet_config_t eth_config;
        if (ethernet_manager_load_config(&eth_config) == ESP_OK) {
            ESP_LOGI(TAG, "Found saved Ethernet configuration");
            if (ethernet_manager_apply_config(&eth_config) == ESP_OK) {
                ESP_LOGI(TAG, "Starting Ethernet...");
                if (ethernet_manager_start() == ESP_OK) {
                    // Wait for connection with polling (max 5 seconds)
                    ESP_LOGI(TAG, "Waiting for Ethernet connection...");
                    for (int i = 0; i < 10; i++) {  // 10 x 500ms = 5 seconds
                        vTaskDelay(pdMS_TO_TICKS(500));
                        if (ethernet_manager_is_connected()) {
                            char eth_ip_str[16];
                            ethernet_manager_get_ip_string(eth_ip_str, sizeof(eth_ip_str));
                            ESP_LOGI(TAG, "✅ Ethernet connected! IP: %s (after %d ms)", eth_ip_str, (i + 1) * 500);
                            connected = true;

#if DISPLAY_ENABLED
                            lvgl_port_lock(0);
                            ui_bridge_update_network_status(true, false, eth_ip_str);
                            lvgl_port_unlock();
#endif
                            break;
                        }
                        ESP_LOGD(TAG, "Ethernet connection check %d/10...", i + 1);
                    }

                    if (!connected) {
                        ESP_LOGW(TAG, "Ethernet connection timeout after 5 seconds");
                        ESP_LOGW(TAG, "Check cable connection. WiFi will NOT be used as fallback.");
                    }
                } else {
                    ESP_LOGE(TAG, "Failed to start Ethernet");
                }
            } else {
                ESP_LOGE(TAG, "Failed to apply Ethernet config");
            }
        } else {
            ESP_LOGI(TAG, "No saved Ethernet configuration - starting with DHCP");
            if (ethernet_manager_start() == ESP_OK) {
                for (int i = 0; i < 10; i++) {
                    vTaskDelay(pdMS_TO_TICKS(500));
                    if (ethernet_manager_is_connected()) {
                        char eth_ip_str[16];
                        ethernet_manager_get_ip_string(eth_ip_str, sizeof(eth_ip_str));
                        ESP_LOGI(TAG, "✅ Ethernet connected (DHCP)! IP: %s", eth_ip_str);
                        connected = true;

#if DISPLAY_ENABLED
                        lvgl_port_lock(0);
                        ui_bridge_update_network_status(true, false, eth_ip_str);
                        lvgl_port_unlock();
#endif
                        break;
                    }
                }
            }
        }
#else
        ESP_LOGE(TAG, "Ethernet mode selected but ETHERNET_ENABLED=0 in build");
#endif
    } else if (current_mode == NETWORK_MODE_WIFI_ONLY) {
#if WIFI_ENABLED
        // Connect WiFi ONLY - do not start Ethernet
        ESP_LOGI(TAG, "Mode is WiFi Only - Ethernet will NOT be started");
        wifi_network_config_t wifi_config;
        if (wifi_manager_load_config(&wifi_config) == ESP_OK) {
            ESP_LOGI(TAG, "Found saved WiFi configuration");
            if (wifi_manager_apply_config(&wifi_config) == ESP_OK) {
                ESP_LOGI(TAG, "Connecting to WiFi: %s", wifi_config.ssid);
                if (wifi_manager_connect() == ESP_OK) {
                    char wifi_ip_str[16];
                    wifi_manager_get_ip_string(wifi_ip_str, sizeof(wifi_ip_str));
                    ESP_LOGI(TAG, "✅ Connected to %s! IP: %s", wifi_config.ssid, wifi_ip_str);
                    connected = true;

#if DISPLAY_ENABLED
                    lvgl_port_lock(0);
                    ui_bridge_update_network_status(true, true, wifi_ip_str);
                    lvgl_port_unlock();
#endif
                } else {
                    ESP_LOGE(TAG, "WiFi connection failed. Ethernet will NOT be used as fallback.");
                }
            }
        } else {
            ESP_LOGI(TAG, "No saved WiFi configuration");
            ESP_LOGI(TAG, "Please configure WiFi in Network Setup screen");
        }
#else
        ESP_LOGE(TAG, "WiFi mode selected but WIFI_ENABLED=0 in build");
#endif
    }

    if (!connected) {
        ESP_LOGI(TAG, "No network connection established");
        ESP_LOGI(TAG, "Use Setup menu to configure network");
        return ESP_ERR_NOT_FOUND;
    }

    return ESP_OK;
}

esp_err_t network_manager_switch_to_ethernet(void) {
    ESP_LOGI(TAG, "Switching to Ethernet mode...");

#ifndef ETHERNET_ENABLED
    ESP_LOGE(TAG, "Ethernet is disabled in build");
    return ESP_ERR_NOT_SUPPORTED;
#endif

#if WIFI_ENABLED
    // Stop WiFi (but keep it initialized for future use)
    ESP_LOGI(TAG, "Stopping WiFi (keeping initialized for fast switching)...");
    wifi_manager_disconnect();
    vTaskDelay(pdMS_TO_TICKS(100));
#endif

#if ETHERNET_ENABLED
    // Load and apply saved Ethernet configuration
    ethernet_config_t eth_config;
    if (ethernet_manager_load_config(&eth_config) == ESP_OK) {
        ESP_LOGI(TAG, "Applying saved Ethernet configuration...");
        if (ethernet_manager_apply_config(&eth_config) == ESP_OK) {
            if (ethernet_manager_start() == ESP_OK) {
                ESP_LOGI(TAG, "✅ Switched to Ethernet mode");

                // Update mode and save to NVS
                current_mode = NETWORK_MODE_ETHERNET_ONLY;
                network_manager_set_mode(NETWORK_MODE_ETHERNET_ONLY);

                return ESP_OK;
            }
        }
    } else {
        ESP_LOGW(TAG, "No saved Ethernet configuration found");
        // Start anyway with default DHCP
        if (ethernet_manager_start() == ESP_OK) {
            current_mode = NETWORK_MODE_ETHERNET_ONLY;
            network_manager_set_mode(NETWORK_MODE_ETHERNET_ONLY);
            return ESP_OK;
        }
    }
#endif

    return ESP_FAIL;
}

esp_err_t network_manager_switch_to_wifi(void) {
    ESP_LOGI(TAG, "Switching to WiFi mode...");

#ifndef WIFI_ENABLED
    ESP_LOGE(TAG, "WiFi is disabled in build");
    return ESP_ERR_NOT_SUPPORTED;
#endif

#if ETHERNET_ENABLED
    // Stop Ethernet (but keep it initialized for future use)
    ESP_LOGI(TAG, "Stopping Ethernet (keeping initialized for fast switching)...");
    esp_err_t eth_stop_ret = ethernet_manager_stop();
    ESP_LOGI(TAG, "Ethernet stop result: %s", esp_err_to_name(eth_stop_ret));
    vTaskDelay(pdMS_TO_TICKS(500));  // Give more time for Ethernet to fully stop
#endif

#if WIFI_ENABLED
    // Load and apply saved WiFi configuration
    ESP_LOGI(TAG, "Loading WiFi configuration from NVS...");
    wifi_network_config_t wifi_config;
    esp_err_t load_ret = wifi_manager_load_config(&wifi_config);
    if (load_ret == ESP_OK) {
        ESP_LOGI(TAG, "WiFi config loaded, SSID: %s", wifi_config.ssid);
        ESP_LOGI(TAG, "Applying WiFi configuration...");
        esp_err_t apply_ret = wifi_manager_apply_config(&wifi_config);
        if (apply_ret == ESP_OK) {
            ESP_LOGI(TAG, "WiFi config applied, connecting...");
            esp_err_t connect_ret = wifi_manager_connect();
            ESP_LOGI(TAG, "WiFi connect result: %s", esp_err_to_name(connect_ret));
            if (connect_ret == ESP_OK) {
                ESP_LOGI(TAG, "✅ Switched to WiFi mode");

                // Update mode and save to NVS
                current_mode = NETWORK_MODE_WIFI_ONLY;
                network_manager_set_mode(NETWORK_MODE_WIFI_ONLY);

                return ESP_OK;
            } else {
                ESP_LOGE(TAG, "WiFi connect failed: %s", esp_err_to_name(connect_ret));
            }
        } else {
            ESP_LOGE(TAG, "WiFi apply config failed: %s", esp_err_to_name(apply_ret));
        }
    } else {
        ESP_LOGE(TAG, "WiFi load config failed: %s", esp_err_to_name(load_ret));
        ESP_LOGE(TAG, "Cannot switch to WiFi without saved configuration");
        return ESP_ERR_NOT_FOUND;
    }
#endif

    ESP_LOGE(TAG, "Failed to switch to WiFi mode");
    return ESP_FAIL;
}
