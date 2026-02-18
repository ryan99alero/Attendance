/**
 * WiFi Manager Implementation with comprehensive network configuration
 */

#include "wifi_manager.h"
#include "esp_wifi.h"
#include "esp_event.h"
#include "esp_log.h"
#include "esp_netif.h"
#include "esp_mac.h"
#include "nvs_flash.h"
#include "nvs.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/event_groups.h"
#include "lwip/dns.h"
#include <string.h>

static const char *TAG = "WIFI";
static const char *NVS_NAMESPACE = "wifi_config";

// Event group bits
#define WIFI_CONNECTED_BIT BIT0
#define WIFI_FAIL_BIT      BIT1
#define WIFI_SCANNING_BIT  BIT2

static EventGroupHandle_t s_wifi_event_group = NULL;
static wifi_network_config_t g_wifi_config = {0};
static int s_retry_num = 0;
static bool s_is_connected = false;
static bool s_is_initialized = false;
static esp_netif_t *s_netif_sta = NULL;
static int8_t s_rssi = 0;

// Async scan support
static wifi_scan_done_cb_t s_scan_done_callback = NULL;
static wifi_ap_record_t *s_scan_results = NULL;
static uint16_t s_scan_result_count = 0;
static bool s_scan_in_progress = false;

// Forward declarations
static void wifi_event_handler(void* arg, esp_event_base_t event_base,
                                int32_t event_id, void* event_data);

esp_err_t wifi_manager_init(void) {
    if (s_is_initialized) {
        ESP_LOGW(TAG, "WiFi manager already initialized");
        return ESP_OK;
    }

    ESP_LOGI(TAG, "Initializing WiFi manager...");

    // Create event group
    s_wifi_event_group = xEventGroupCreate();
    if (s_wifi_event_group == NULL) {
        ESP_LOGE(TAG, "Failed to create event group");
        return ESP_FAIL;
    }

    // Initialize network interface
    esp_err_t ret = esp_netif_init();
    if (ret != ESP_OK && ret != ESP_ERR_INVALID_STATE) {
        ESP_LOGE(TAG, "Failed to initialize netif: %s", esp_err_to_name(ret));
        return ret;
    }

    // Create default event loop if not exists
    ret = esp_event_loop_create_default();
    if (ret != ESP_OK && ret != ESP_ERR_INVALID_STATE) {
        ESP_LOGE(TAG, "Failed to create event loop: %s", esp_err_to_name(ret));
        return ret;
    }

    // Create WiFi station interface
    s_netif_sta = esp_netif_create_default_wifi_sta();
    if (s_netif_sta == NULL) {
        ESP_LOGE(TAG, "Failed to create WiFi station interface");
        return ESP_FAIL;
    }

    // Initialize WiFi
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ret = esp_wifi_init(&cfg);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to initialize WiFi: %s", esp_err_to_name(ret));
        return ret;
    }

    // Set WiFi mode before setting MAC
    ret = esp_wifi_set_mode(WIFI_MODE_STA);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to set WiFi mode: %s", esp_err_to_name(ret));
        return ret;
    }

    // Set custom MAC address for WiFi - use factory base MAC + 1
    // MAC scheme: Hardware=base, WiFi=base+1, Ethernet=base+2
    // This ensures consistent MAC across reboots and firmware updates
    uint8_t base_mac[6];
    esp_read_mac(base_mac, ESP_MAC_BASE);
    uint8_t wifi_mac[6];
    memcpy(wifi_mac, base_mac, 6);
    wifi_mac[5] += 1;  // Increment last byte by 1 for WiFi
    ret = esp_wifi_set_mac(WIFI_IF_STA, wifi_mac);
    if (ret == ESP_OK) {
        ESP_LOGI(TAG, "WiFi MAC set to base+1: %02X:%02X:%02X:%02X:%02X:%02X",
                 wifi_mac[0], wifi_mac[1], wifi_mac[2], wifi_mac[3], wifi_mac[4], wifi_mac[5]);
    } else {
        ESP_LOGW(TAG, "Failed to set WiFi MAC: %s (using default)", esp_err_to_name(ret));
    }

    // Register event handlers
    ret = esp_event_handler_instance_register(WIFI_EVENT,
                                               ESP_EVENT_ANY_ID,
                                               &wifi_event_handler,
                                               NULL,
                                               NULL);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to register WiFi event handler: %s", esp_err_to_name(ret));
        return ret;
    }

    ret = esp_event_handler_instance_register(IP_EVENT,
                                               IP_EVENT_STA_GOT_IP,
                                               &wifi_event_handler,
                                               NULL,
                                               NULL);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to register IP event handler: %s", esp_err_to_name(ret));
        return ret;
    }

    // Set WiFi mode to station
    ret = esp_wifi_set_mode(WIFI_MODE_STA);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to set WiFi mode: %s", esp_err_to_name(ret));
        return ret;
    }

    // Set country configuration for proper channel scanning
    wifi_country_t country = {
        .cc = "US",           // Country code (use US for max compatibility)
        .schan = 1,           // Start channel
        .nchan = 11,          // Number of channels (US has 11 channels)
        .policy = WIFI_COUNTRY_POLICY_AUTO
    };
    ret = esp_wifi_set_country(&country);
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "Failed to set WiFi country: %s", esp_err_to_name(ret));
        // Continue anyway - this is not critical
    } else {
        ESP_LOGI(TAG, "WiFi country set to US (channels 1-11)");
    }

    // Set protocol to support 802.11b/g/n for maximum compatibility
    ret = esp_wifi_set_protocol(WIFI_IF_STA, WIFI_PROTOCOL_11B | WIFI_PROTOCOL_11G | WIFI_PROTOCOL_11N);
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "Failed to set WiFi protocol: %s", esp_err_to_name(ret));
    } else {
        ESP_LOGI(TAG, "WiFi protocol set to 802.11b/g/n");
    }

    // Set bandwidth to 20MHz for better compatibility
    ret = esp_wifi_set_bandwidth(WIFI_IF_STA, WIFI_BW_HT20);
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "Failed to set WiFi bandwidth: %s", esp_err_to_name(ret));
    } else {
        ESP_LOGI(TAG, "WiFi bandwidth set to 20MHz");
    }

    s_is_initialized = true;

    // Log WiFi chip information
    ESP_LOGI(TAG, "=== WiFi Configuration ===");
    ESP_LOGI(TAG, "  Host Chip: ESP32-P4 (RISC-V)");
    ESP_LOGI(TAG, "  WiFi Chip: ESP32-C6-MINI-1");
    ESP_LOGI(TAG, "  Protocols: 802.11 b/g/n (2.4 GHz)");
    ESP_LOGI(TAG, "  Mode: Station (STA)");
    ESP_LOGI(TAG, "WiFi manager initialized successfully");

    return ESP_OK;
}

esp_err_t wifi_manager_scan(wifi_scan_result_t *results, uint16_t max_results, uint16_t *num_results) {
    if (results == NULL || num_results == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!s_is_initialized) {
        ESP_LOGE(TAG, "WiFi manager not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    ESP_LOGI(TAG, "Starting WiFi scan...");

    // Start WiFi if not already started
    esp_wifi_start();

    // Start scan
    wifi_scan_config_t scan_config = {
        .ssid = NULL,
        .bssid = NULL,
        .channel = 0,
        .show_hidden = false,
        .scan_type = WIFI_SCAN_TYPE_ACTIVE,
    };

    esp_err_t ret = esp_wifi_scan_start(&scan_config, true);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start WiFi scan: %s", esp_err_to_name(ret));
        return ret;
    }

    // Get scan results
    uint16_t ap_count = 0;
    esp_wifi_scan_get_ap_num(&ap_count);

    if (ap_count == 0) {
        ESP_LOGW(TAG, "No WiFi networks found");
        *num_results = 0;
        return ESP_OK;
    }

    // Limit to max_results
    if (ap_count > max_results) {
        ap_count = max_results;
    }

    wifi_ap_record_t *ap_info = malloc(sizeof(wifi_ap_record_t) * ap_count);
    if (ap_info == NULL) {
        ESP_LOGE(TAG, "Failed to allocate memory for scan results");
        return ESP_ERR_NO_MEM;
    }

    ret = esp_wifi_scan_get_ap_records(&ap_count, ap_info);
    if (ret != ESP_OK) {
        free(ap_info);
        ESP_LOGE(TAG, "Failed to get scan results: %s", esp_err_to_name(ret));
        return ret;
    }

    // Copy results
    for (int i = 0; i < ap_count; i++) {
        strncpy(results[i].ssid, (char*)ap_info[i].ssid, WIFI_MAX_SSID_LEN - 1);
        results[i].ssid[WIFI_MAX_SSID_LEN - 1] = '\0';
        results[i].rssi = ap_info[i].rssi;
        results[i].authmode = ap_info[i].authmode;
        results[i].channel = ap_info[i].primary;
    }

    *num_results = ap_count;
    free(ap_info);

    ESP_LOGI(TAG, "Found %d WiFi networks", ap_count);

    return ESP_OK;
}

esp_err_t wifi_manager_scan_async(wifi_scan_done_cb_t callback) {
    if (!s_is_initialized) {
        ESP_LOGE(TAG, "WiFi manager not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    if (s_scan_in_progress) {
        ESP_LOGW(TAG, "Scan already in progress");
        return ESP_ERR_INVALID_STATE;
    }

    ESP_LOGI(TAG, "Starting async WiFi scan...");

    // Store callback
    s_scan_done_callback = callback;
    s_scan_in_progress = true;

    // Start WiFi if not already started
    esp_err_t ret = esp_wifi_start();
    if (ret != ESP_OK && ret != ESP_ERR_WIFI_STATE) {
        ESP_LOGE(TAG, "Failed to start WiFi: %s", esp_err_to_name(ret));
        s_scan_in_progress = false;
        s_scan_done_callback = NULL;
        return ret;
    }
    ESP_LOGI(TAG, "WiFi started (or already running)");

    // Wait a moment for WiFi to be ready
    vTaskDelay(pdMS_TO_TICKS(100));

    // Start non-blocking scan on all channels with extended scan time
    wifi_scan_config_t scan_config = {
        .ssid = NULL,
        .bssid = NULL,
        .channel = 0,              // 0 = scan all channels
        .show_hidden = true,       // Show hidden networks too
        .scan_type = WIFI_SCAN_TYPE_ACTIVE,
        .scan_time = {
            .active = {
                .min = 100,        // Min active scan time per channel (ms)
                .max = 300         // Max active scan time per channel (ms)
            },
            .passive = 360        // Passive scan time per channel (ms)
        }
    };

    ESP_LOGI(TAG, "Starting WiFi scan on all channels (active scan 100-300ms per channel)...");
    ret = esp_wifi_scan_start(&scan_config, false);  // false = non-blocking
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start async WiFi scan: %s", esp_err_to_name(ret));
        s_scan_in_progress = false;
        s_scan_done_callback = NULL;
        return ret;
    }

    ESP_LOGI(TAG, "WiFi scan started successfully");
    return ESP_OK;
}

esp_err_t wifi_manager_get_scan_results(wifi_scan_result_t *results, uint16_t max_results, uint16_t *num_results) {
    if (results == NULL || num_results == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    if (s_scan_in_progress) {
        ESP_LOGW(TAG, "Scan still in progress");
        return ESP_ERR_INVALID_STATE;
    }

    if (s_scan_results == NULL || s_scan_result_count == 0) {
        *num_results = 0;
        return ESP_OK;
    }

    // Limit to max_results
    uint16_t count = s_scan_result_count;
    if (count > max_results) {
        count = max_results;
    }

    // Copy results
    for (int i = 0; i < count; i++) {
        strncpy(results[i].ssid, (char*)s_scan_results[i].ssid, WIFI_MAX_SSID_LEN - 1);
        results[i].ssid[WIFI_MAX_SSID_LEN - 1] = '\0';
        results[i].rssi = s_scan_results[i].rssi;
        results[i].authmode = s_scan_results[i].authmode;
        results[i].channel = s_scan_results[i].primary;
    }

    *num_results = count;
    return ESP_OK;
}

esp_err_t wifi_manager_load_config(wifi_network_config_t *config) {
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READONLY, &nvs_handle);
    if (ret != ESP_OK) {
        if (ret == ESP_ERR_NVS_NOT_FOUND) {
            ESP_LOGI(TAG, "No saved configuration (namespace not found - this is normal on first boot)");
        } else {
            ESP_LOGW(TAG, "Failed to open NVS: %s", esp_err_to_name(ret));
        }
        return ret;
    }

    // Load all configuration values
    size_t len;

    len = sizeof(config->ssid);
    ret = nvs_get_str(nvs_handle, "ssid", config->ssid, &len);
    if (ret != ESP_OK) goto load_error;

    len = sizeof(config->password);
    nvs_get_str(nvs_handle, "password", config->password, &len); // Optional

    len = sizeof(config->hostname);
    nvs_get_str(nvs_handle, "hostname", config->hostname, &len); // Optional

    uint8_t use_dhcp = 1;
    nvs_get_u8(nvs_handle, "use_dhcp", &use_dhcp);
    config->use_dhcp = use_dhcp;

    if (!config->use_dhcp) {
        len = sizeof(config->static_ip);
        nvs_get_str(nvs_handle, "static_ip", config->static_ip, &len);

        len = sizeof(config->static_gateway);
        nvs_get_str(nvs_handle, "static_gw", config->static_gateway, &len);

        len = sizeof(config->static_netmask);
        nvs_get_str(nvs_handle, "static_nm", config->static_netmask, &len);

        len = sizeof(config->static_dns_primary);
        nvs_get_str(nvs_handle, "static_dns1", config->static_dns_primary, &len);

        len = sizeof(config->static_dns_secondary);
        nvs_get_str(nvs_handle, "static_dns2", config->static_dns_secondary, &len);
    }

    uint8_t max_retry = 5;
    nvs_get_u8(nvs_handle, "max_retry", &max_retry);
    config->max_retry = max_retry;

    nvs_close(nvs_handle);
    ESP_LOGI(TAG, "Configuration loaded from NVS");
    return ESP_OK;

load_error:
    nvs_close(nvs_handle);
    ESP_LOGW(TAG, "Failed to load configuration: %s", esp_err_to_name(ret));
    return ret;
}

esp_err_t wifi_manager_save_config(const wifi_network_config_t *config) {
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READWRITE, &nvs_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to open NVS for writing: %s", esp_err_to_name(ret));
        return ret;
    }

    // Save all configuration values
    nvs_set_str(nvs_handle, "ssid", config->ssid);
    nvs_set_str(nvs_handle, "password", config->password);
    nvs_set_str(nvs_handle, "hostname", config->hostname);
    nvs_set_u8(nvs_handle, "use_dhcp", config->use_dhcp ? 1 : 0);

    if (!config->use_dhcp) {
        nvs_set_str(nvs_handle, "static_ip", config->static_ip);
        nvs_set_str(nvs_handle, "static_gw", config->static_gateway);
        nvs_set_str(nvs_handle, "static_nm", config->static_netmask);
        nvs_set_str(nvs_handle, "static_dns1", config->static_dns_primary);
        nvs_set_str(nvs_handle, "static_dns2", config->static_dns_secondary);
    }

    nvs_set_u8(nvs_handle, "max_retry", config->max_retry);

    ret = nvs_commit(nvs_handle);
    nvs_close(nvs_handle);

    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to commit NVS: %s", esp_err_to_name(ret));
        return ret;
    }

    ESP_LOGI(TAG, "Configuration saved to NVS");
    return ESP_OK;
}

esp_err_t wifi_manager_apply_config(const wifi_network_config_t *config) {
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!s_is_initialized) {
        ESP_LOGE(TAG, "WiFi manager not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    // Copy configuration
    memcpy(&g_wifi_config, config, sizeof(wifi_network_config_t));

    // Set default max retry if not specified
    if (g_wifi_config.max_retry == 0) {
        g_wifi_config.max_retry = 5;
    }

    ESP_LOGI(TAG, "Applying WiFi configuration:");
    ESP_LOGI(TAG, "  SSID: %s", g_wifi_config.ssid);
    ESP_LOGI(TAG, "  DHCP: %s", g_wifi_config.use_dhcp ? "Yes" : "No");
    if (g_wifi_config.hostname[0] != '\0') {
        ESP_LOGI(TAG, "  Hostname: %s", g_wifi_config.hostname);
    }

    // Stop WiFi if running
    esp_wifi_stop();

    // Configure static IP if needed
    if (!g_wifi_config.use_dhcp) {
        ESP_LOGI(TAG, "Configuring static IP: %s", g_wifi_config.static_ip);

        // Stop DHCP client
        esp_netif_dhcpc_stop(s_netif_sta);

        // Parse and set IP info
        esp_netif_ip_info_t ip_info;
        ip_info.ip.addr = esp_ip4addr_aton(g_wifi_config.static_ip);
        ip_info.gw.addr = esp_ip4addr_aton(g_wifi_config.static_gateway);
        ip_info.netmask.addr = esp_ip4addr_aton(g_wifi_config.static_netmask);

        esp_err_t ret = esp_netif_set_ip_info(s_netif_sta, &ip_info);
        if (ret != ESP_OK) {
            ESP_LOGE(TAG, "Failed to set IP info: %s", esp_err_to_name(ret));
            return ret;
        }

        // Set DNS servers
        if (g_wifi_config.static_dns_primary[0] != '\0') {
            esp_netif_dns_info_t dns_info;
            dns_info.ip.u_addr.ip4.addr = esp_ip4addr_aton(g_wifi_config.static_dns_primary);
            dns_info.ip.type = IPADDR_TYPE_V4;
            esp_netif_set_dns_info(s_netif_sta, ESP_NETIF_DNS_MAIN, &dns_info);
        }

        if (g_wifi_config.static_dns_secondary[0] != '\0') {
            esp_netif_dns_info_t dns_info;
            dns_info.ip.u_addr.ip4.addr = esp_ip4addr_aton(g_wifi_config.static_dns_secondary);
            dns_info.ip.type = IPADDR_TYPE_V4;
            esp_netif_set_dns_info(s_netif_sta, ESP_NETIF_DNS_BACKUP, &dns_info);
        }
    } else {
        // Enable DHCP client
        esp_netif_dhcpc_start(s_netif_sta);
    }

    // Set hostname if specified
    if (g_wifi_config.hostname[0] != '\0') {
        esp_netif_set_hostname(s_netif_sta, g_wifi_config.hostname);
    }

    // Configure WiFi credentials
    wifi_config_t wifi_config = {
        .sta = {
            .threshold.authmode = WIFI_AUTH_OPEN,
            .pmf_cfg = {
                .capable = true,
                .required = false
            },
        },
    };

    strncpy((char*)wifi_config.sta.ssid, g_wifi_config.ssid, sizeof(wifi_config.sta.ssid));
    strncpy((char*)wifi_config.sta.password, g_wifi_config.password, sizeof(wifi_config.sta.password));

    esp_err_t ret = esp_wifi_set_config(WIFI_IF_STA, &wifi_config);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to set WiFi config: %s", esp_err_to_name(ret));
        return ret;
    }

    ESP_LOGI(TAG, "WiFi configuration applied successfully");

    return ESP_OK;
}

esp_err_t wifi_manager_connect(void) {
    if (!s_is_initialized) {
        ESP_LOGE(TAG, "WiFi manager not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    ESP_LOGI(TAG, "Starting WiFi connection...");

    // Reset retry counter
    s_retry_num = 0;

    // Clear event bits
    xEventGroupClearBits(s_wifi_event_group, WIFI_CONNECTED_BIT | WIFI_FAIL_BIT);

    // Start WiFi
    esp_err_t ret = esp_wifi_start();
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start WiFi: %s", esp_err_to_name(ret));
        return ret;
    }

    // Wait for connection or failure (with timeout)
    EventBits_t bits = xEventGroupWaitBits(s_wifi_event_group,
            WIFI_CONNECTED_BIT | WIFI_FAIL_BIT,
            pdFALSE,
            pdFALSE,
            pdMS_TO_TICKS(30000)); // 30 second timeout

    if (bits & WIFI_CONNECTED_BIT) {
        ESP_LOGI(TAG, "✅ Connected to WiFi: %s", g_wifi_config.ssid);
        return ESP_OK;
    } else if (bits & WIFI_FAIL_BIT) {
        ESP_LOGE(TAG, "❌ Failed to connect to WiFi: %s", g_wifi_config.ssid);
        return ESP_FAIL;
    } else {
        ESP_LOGE(TAG, "WiFi connection timeout");
        return ESP_ERR_TIMEOUT;
    }
}

esp_err_t wifi_manager_disconnect(void) {
    ESP_LOGI(TAG, "Disconnecting from WiFi...");
    s_is_connected = false;
    return esp_wifi_disconnect();
}

bool wifi_manager_is_connected(void) {
    return s_is_connected;
}

const char* wifi_manager_get_status_string(void) {
    if (s_is_connected) {
        return "Connected";
    } else if (s_retry_num > 0 && s_retry_num < g_wifi_config.max_retry) {
        return "Connecting...";
    } else if (s_retry_num >= g_wifi_config.max_retry) {
        return "Failed";
    } else {
        return "Disconnected";
    }
}

esp_err_t wifi_manager_get_ip_string(char *ip_str, size_t ip_str_size) {
    if (ip_str == NULL || ip_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!s_is_connected || s_netif_sta == NULL) {
        strcpy(ip_str, "0.0.0.0");
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    if (esp_netif_get_ip_info(s_netif_sta, &ip_info) == ESP_OK) {
        snprintf(ip_str, ip_str_size, IPSTR, IP2STR(&ip_info.ip));
        return ESP_OK;
    }

    strcpy(ip_str, "0.0.0.0");
    return ESP_FAIL;
}

esp_err_t wifi_manager_get_gateway_string(char *gw_str, size_t gw_str_size) {
    if (gw_str == NULL || gw_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!s_is_connected || s_netif_sta == NULL) {
        strcpy(gw_str, "0.0.0.0");
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    if (esp_netif_get_ip_info(s_netif_sta, &ip_info) == ESP_OK) {
        snprintf(gw_str, gw_str_size, IPSTR, IP2STR(&ip_info.gw));
        return ESP_OK;
    }

    strcpy(gw_str, "0.0.0.0");
    return ESP_FAIL;
}

esp_err_t wifi_manager_get_netmask_string(char *nm_str, size_t nm_str_size) {
    if (nm_str == NULL || nm_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!s_is_connected || s_netif_sta == NULL) {
        strcpy(nm_str, "0.0.0.0");
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    if (esp_netif_get_ip_info(s_netif_sta, &ip_info) == ESP_OK) {
        snprintf(nm_str, nm_str_size, IPSTR, IP2STR(&ip_info.netmask));
        return ESP_OK;
    }

    strcpy(nm_str, "0.0.0.0");
    return ESP_FAIL;
}

int8_t wifi_manager_get_rssi(void) {
    if (!s_is_connected) {
        return 0;
    }

    wifi_ap_record_t ap_info;
    if (esp_wifi_sta_get_ap_info(&ap_info) == ESP_OK) {
        s_rssi = ap_info.rssi;
        return s_rssi;
    }

    return s_rssi;
}

const char* wifi_manager_get_authmode_string(wifi_auth_mode_t authmode) {
    switch (authmode) {
        case WIFI_AUTH_OPEN:            return "Open";
        case WIFI_AUTH_WEP:             return "WEP";
        case WIFI_AUTH_WPA_PSK:         return "WPA";
        case WIFI_AUTH_WPA2_PSK:        return "WPA2";
        case WIFI_AUTH_WPA_WPA2_PSK:    return "WPA/WPA2";
        case WIFI_AUTH_WPA2_ENTERPRISE: return "WPA2-ENT";
        case WIFI_AUTH_WPA3_PSK:        return "WPA3";
        case WIFI_AUTH_WPA2_WPA3_PSK:   return "WPA2/WPA3";
        default:                        return "Unknown";
    }
}

// WiFi event handler
static void wifi_event_handler(void* arg, esp_event_base_t event_base,
                                int32_t event_id, void* event_data)
{
    if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_START) {
        ESP_LOGI(TAG, "WiFi started, connecting...");
        esp_wifi_connect();
    } else if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_STA_DISCONNECTED) {
        if (s_retry_num < g_wifi_config.max_retry) {
            esp_wifi_connect();
            s_retry_num++;
            ESP_LOGI(TAG, "Retry connection to WiFi... (%d/%d)", s_retry_num, g_wifi_config.max_retry);
        } else {
            xEventGroupSetBits(s_wifi_event_group, WIFI_FAIL_BIT);
            ESP_LOGE(TAG,"Failed to connect to WiFi");
        }
        s_is_connected = false;
    } else if (event_base == IP_EVENT && event_id == IP_EVENT_STA_GOT_IP) {
        ip_event_got_ip_t* event = (ip_event_got_ip_t*) event_data;
        ESP_LOGI(TAG, "Got IP address: " IPSTR, IP2STR(&event->ip_info.ip));
        s_retry_num = 0;
        s_is_connected = true;
        xEventGroupSetBits(s_wifi_event_group, WIFI_CONNECTED_BIT);
    } else if (event_base == WIFI_EVENT && event_id == WIFI_EVENT_SCAN_DONE) {
        ESP_LOGI(TAG, "WIFI_EVENT_SCAN_DONE received");

        // Handle async scan completion
        if (s_scan_in_progress) {
            s_scan_in_progress = false;

            // Get number of APs found
            esp_err_t ret = esp_wifi_scan_get_ap_num(&s_scan_result_count);
            if (ret != ESP_OK) {
                ESP_LOGE(TAG, "Failed to get AP count: %s", esp_err_to_name(ret));
                s_scan_result_count = 0;
            } else {
                ESP_LOGI(TAG, "Async scan complete, found %d networks", s_scan_result_count);
            }

            // Free previous results if any
            if (s_scan_results != NULL) {
                free(s_scan_results);
                s_scan_results = NULL;
            }

            // Allocate memory for results
            if (s_scan_result_count > 0) {
                s_scan_results = malloc(sizeof(wifi_ap_record_t) * s_scan_result_count);
                if (s_scan_results != NULL) {
                    uint16_t count = s_scan_result_count;
                    ret = esp_wifi_scan_get_ap_records(&count, s_scan_results);
                    if (ret != ESP_OK) {
                        ESP_LOGE(TAG, "Failed to get AP records: %s", esp_err_to_name(ret));
                        free(s_scan_results);
                        s_scan_results = NULL;
                        s_scan_result_count = 0;
                    } else {
                        s_scan_result_count = count;
                        ESP_LOGI(TAG, "Retrieved %d AP records", count);
                        // Log first few SSIDs for debugging
                        for (int i = 0; i < (count < 3 ? count : 3); i++) {
                            ESP_LOGI(TAG, "  AP[%d]: %s (RSSI: %d)", i, s_scan_results[i].ssid, s_scan_results[i].rssi);
                        }
                    }
                } else {
                    ESP_LOGE(TAG, "Failed to allocate memory for scan results");
                    s_scan_result_count = 0;
                }
            } else {
                ESP_LOGW(TAG, "Scan completed but found 0 networks. WiFi may not be configured correctly.");
            }

            // Call user callback if registered
            if (s_scan_done_callback != NULL) {
                s_scan_done_callback(s_scan_result_count);
            }
        } else {
            ESP_LOGW(TAG, "SCAN_DONE event received but no scan was in progress");
        }
    }
}
