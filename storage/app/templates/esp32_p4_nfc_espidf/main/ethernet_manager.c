/**
 * Ethernet Manager Implementation for ESP32-P4
 * IP101GR PHY on ESP32-P4-Function-EV-Board v1.5.2
 */

#include "ethernet_manager.h"
#include "esp_netif.h"
#include "esp_eth.h"
#include "esp_event.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "nvs.h"
#include "driver/gpio.h"
#include <string.h>

static const char *TAG = "ETH_MANAGER";

// ESP32-P4-Function-EV-Board v1.5.2 GPIO pins for IP101GR
// Per ESP-IDF issue #14295 and board schematic
#define ETH_PHY_ADDR        1
#define ETH_MDC_GPIO        31   // GPIO 31 - Management Data Clock (corrected from 50)
#define ETH_MDIO_GPIO       52   // GPIO 52 - Management Data I/O
#define ETH_PHY_RST_GPIO    51   // GPIO 51 - PHY Reset
// Note: GPIO 50 is used by hardware for RMII reference clock, not MDC

// Ethernet handle
static esp_eth_handle_t eth_handle = NULL;
static esp_netif_t *eth_netif = NULL;
static bool eth_connected = false;
static bool eth_started = false;  // Track if Ethernet driver is started
static ethernet_config_t current_config = {0};

// NVS storage key
#define NVS_NAMESPACE "ethernet"
#define NVS_KEY_CONFIG "eth_config"

/**
 * Ethernet event handler
 */
static void eth_event_handler(void *arg, esp_event_base_t event_base,
                              int32_t event_id, void *event_data)
{
    uint8_t mac_addr[6] = {0};
    esp_eth_handle_t eth_handle = *(esp_eth_handle_t *)event_data;

    switch (event_id) {
    case ETHERNET_EVENT_CONNECTED:
        esp_eth_ioctl(eth_handle, ETH_CMD_G_MAC_ADDR, mac_addr);
        ESP_LOGI(TAG, "Ethernet Link Up");
        ESP_LOGI(TAG, "Ethernet HW Addr %02x:%02x:%02x:%02x:%02x:%02x",
                 mac_addr[0], mac_addr[1], mac_addr[2], mac_addr[3], mac_addr[4], mac_addr[5]);
        eth_connected = false;  // Wait for IP assignment
        break;
    case ETHERNET_EVENT_DISCONNECTED:
        ESP_LOGI(TAG, "Ethernet Link Down");
        eth_connected = false;
        break;
    case ETHERNET_EVENT_START:
        ESP_LOGI(TAG, "Ethernet Started");
        eth_started = true;
        break;
    case ETHERNET_EVENT_STOP:
        ESP_LOGI(TAG, "Ethernet Stopped");
        eth_connected = false;
        eth_started = false;
        break;
    default:
        break;
    }
}

/**
 * IP event handler
 */
static void got_ip_event_handler(void *arg, esp_event_base_t event_base,
                                 int32_t event_id, void *event_data)
{
    ip_event_got_ip_t *event = (ip_event_got_ip_t *)event_data;
    const esp_netif_ip_info_t *ip_info = &event->ip_info;

    ESP_LOGI(TAG, "Ethernet Got IP Address");
    ESP_LOGI(TAG, "~~~~~~~~~~~");
    ESP_LOGI(TAG, "ETHIP:" IPSTR, IP2STR(&ip_info->ip));
    ESP_LOGI(TAG, "ETHMASK:" IPSTR, IP2STR(&ip_info->netmask));
    ESP_LOGI(TAG, "ETHGW:" IPSTR, IP2STR(&ip_info->gw));
    ESP_LOGI(TAG, "~~~~~~~~~~~");
    eth_connected = true;
}

esp_err_t ethernet_manager_init(void)
{
    ESP_LOGI(TAG, "Initializing Ethernet");

    // Initialize TCP/IP network interface (should be called only once in application)
    // Note: This might already be called by WiFi manager
    static bool netif_initialized = false;
    if (!netif_initialized) {
        ESP_ERROR_CHECK(esp_netif_init());
        netif_initialized = true;
    }

    // Create default event loop if not already created
    // Note: This might already be created by WiFi manager
    static bool event_loop_created = false;
    if (!event_loop_created) {
        esp_err_t ret = esp_event_loop_create_default();
        if (ret == ESP_OK || ret == ESP_ERR_INVALID_STATE) {
            event_loop_created = true;
        } else {
            ESP_LOGE(TAG, "Failed to create event loop: %s", esp_err_to_name(ret));
            return ret;
        }
    }

    // Create new default instance of esp-netif for Ethernet
    esp_netif_config_t netif_cfg = ESP_NETIF_DEFAULT_ETH();
    eth_netif = esp_netif_new(&netif_cfg);
    if (eth_netif == NULL) {
        ESP_LOGE(TAG, "Failed to create netif");
        return ESP_FAIL;
    }

    // Register user defined event handers
    ESP_ERROR_CHECK(esp_event_handler_register(ETH_EVENT, ESP_EVENT_ANY_ID, &eth_event_handler, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(IP_EVENT, IP_EVENT_ETH_GOT_IP, &got_ip_event_handler, NULL));

    // Init MAC and PHY configs to default
    eth_mac_config_t mac_config = ETH_MAC_DEFAULT_CONFIG();
    eth_phy_config_t phy_config = ETH_PHY_DEFAULT_CONFIG();

    // Update PHY config with IP101GR settings
    phy_config.phy_addr = ETH_PHY_ADDR;
    phy_config.reset_gpio_num = ETH_PHY_RST_GPIO;

    // Create MAC instance with new smi_gpio config (ESP-IDF v5.5+)
    eth_esp32_emac_config_t esp32_emac_config = ETH_ESP32_EMAC_DEFAULT_CONFIG();
    esp32_emac_config.smi_gpio.mdc_num = ETH_MDC_GPIO;
    esp32_emac_config.smi_gpio.mdio_num = ETH_MDIO_GPIO;

    esp_eth_mac_t *mac = esp_eth_mac_new_esp32(&esp32_emac_config, &mac_config);
    if (mac == NULL) {
        ESP_LOGE(TAG, "Failed to create MAC");
        return ESP_FAIL;
    }

    // Create PHY instance
    esp_eth_phy_t *phy = esp_eth_phy_new_ip101(&phy_config);
    if (phy == NULL) {
        ESP_LOGE(TAG, "Failed to create PHY");
        return ESP_FAIL;
    }

    // Install Ethernet driver
    esp_eth_config_t eth_config = ETH_DEFAULT_CONFIG(mac, phy);
    esp_err_t ret = esp_eth_driver_install(&eth_config, &eth_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to install Ethernet driver: %s", esp_err_to_name(ret));
        return ret;
    }

    // Attach Ethernet driver to TCP/IP stack
    // The glue layer automatically registers all necessary event handlers
    // (CONNECTED, DISCONNECTED, START, STOP) - no need to register manually
    void *eth_glue = esp_eth_new_netif_glue(eth_handle);
    ret = esp_netif_attach(eth_netif, eth_glue);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to attach netif: %s", esp_err_to_name(ret));
        return ret;
    }

    ESP_LOGI(TAG, "Ethernet initialized successfully");
    ESP_LOGI(TAG, "  PHY: IP101GR (addr %d)", ETH_PHY_ADDR);
    ESP_LOGI(TAG, "  MDC: GPIO%d, MDIO: GPIO%d, RST: GPIO%d",
             ETH_MDC_GPIO, ETH_MDIO_GPIO, ETH_PHY_RST_GPIO);

    return ESP_OK;
}

esp_err_t ethernet_manager_start(void)
{
    if (eth_handle == NULL) {
        ESP_LOGE(TAG, "Ethernet not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    if (eth_started) {
        ESP_LOGW(TAG, "Ethernet already started");
        return ESP_OK;  // Already started, not an error
    }

    ESP_LOGI(TAG, "Starting Ethernet");
    esp_err_t ret = esp_eth_start(eth_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to start Ethernet: %s", esp_err_to_name(ret));
    }
    return ret;
}

esp_err_t ethernet_manager_stop(void)
{
    if (eth_handle == NULL) {
        ESP_LOGE(TAG, "Ethernet not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    if (!eth_started) {
        ESP_LOGW(TAG, "Ethernet already stopped");
        return ESP_OK;  // Already stopped, not an error
    }

    ESP_LOGI(TAG, "Stopping Ethernet");
    eth_connected = false;
    esp_err_t ret = esp_eth_stop(eth_handle);
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "Ethernet stop returned: %s (continuing anyway)", esp_err_to_name(ret));
        // Mark as stopped even if stop fails (MAC filter errors are non-fatal)
        eth_started = false;
        return ESP_OK;  // Don't treat as hard error - allow WiFi switch to continue
    }
    return ret;
}

bool ethernet_manager_is_connected(void)
{
    return eth_connected;
}

const char* ethernet_manager_get_status_string(void)
{
    if (eth_connected) {
        return "Connected";
    } else if (eth_handle != NULL) {
        return "Disconnected";
    } else {
        return "Not initialized";
    }
}

esp_err_t ethernet_manager_load_config(ethernet_config_t *config)
{
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READONLY, &nvs_handle);
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "No saved Ethernet configuration found");
        return ESP_ERR_NVS_NOT_FOUND;
    }

    size_t required_size = sizeof(ethernet_config_t);
    ret = nvs_get_blob(nvs_handle, NVS_KEY_CONFIG, config, &required_size);
    nvs_close(nvs_handle);

    if (ret == ESP_OK) {
        ESP_LOGI(TAG, "Loaded Ethernet configuration from NVS");
        memcpy(&current_config, config, sizeof(ethernet_config_t));
    }

    return ret;
}

esp_err_t ethernet_manager_save_config(const ethernet_config_t *config)
{
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READWRITE, &nvs_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to open NVS");
        return ret;
    }

    ret = nvs_set_blob(nvs_handle, NVS_KEY_CONFIG, config, sizeof(ethernet_config_t));
    if (ret == ESP_OK) {
        ret = nvs_commit(nvs_handle);
        if (ret == ESP_OK) {
            ESP_LOGI(TAG, "Saved Ethernet configuration to NVS");
            memcpy(&current_config, config, sizeof(ethernet_config_t));
        }
    }

    nvs_close(nvs_handle);
    return ret;
}

esp_err_t ethernet_manager_apply_config(const ethernet_config_t *config)
{
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    if (eth_netif == NULL) {
        ESP_LOGE(TAG, "Ethernet netif not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    // Stop DHCP client if running
    esp_netif_dhcpc_stop(eth_netif);

    if (config->use_dhcp) {
        ESP_LOGI(TAG, "Configuring Ethernet for DHCP");
        esp_netif_dhcpc_start(eth_netif);
    } else {
        ESP_LOGI(TAG, "Configuring Ethernet for Static IP");

        // Parse and set static IP configuration
        esp_netif_ip_info_t ip_info;
        memset(&ip_info, 0, sizeof(esp_netif_ip_info_t));

        // Convert strings to IP addresses
        if (esp_netif_str_to_ip4(config->static_ip, &ip_info.ip) != ESP_OK) {
            ESP_LOGE(TAG, "Invalid IP address: %s", config->static_ip);
            return ESP_ERR_INVALID_ARG;
        }

        if (esp_netif_str_to_ip4(config->static_gateway, &ip_info.gw) != ESP_OK) {
            ESP_LOGE(TAG, "Invalid gateway: %s", config->static_gateway);
            return ESP_ERR_INVALID_ARG;
        }

        if (esp_netif_str_to_ip4(config->static_netmask, &ip_info.netmask) != ESP_OK) {
            ESP_LOGE(TAG, "Invalid netmask: %s", config->static_netmask);
            return ESP_ERR_INVALID_ARG;
        }

        // Set static IP
        esp_err_t ret = esp_netif_set_ip_info(eth_netif, &ip_info);
        if (ret != ESP_OK) {
            ESP_LOGE(TAG, "Failed to set IP info: %s", esp_err_to_name(ret));
            return ret;
        }

        // Set DNS servers if specified
        if (strlen(config->static_dns_primary) > 0) {
            esp_netif_dns_info_t dns_info;
            if (esp_netif_str_to_ip4(config->static_dns_primary, &dns_info.ip.u_addr.ip4) == ESP_OK) {
                dns_info.ip.type = ESP_IPADDR_TYPE_V4;
                esp_netif_set_dns_info(eth_netif, ESP_NETIF_DNS_MAIN, &dns_info);
                ESP_LOGI(TAG, "Set primary DNS: %s", config->static_dns_primary);
            }
        }

        if (strlen(config->static_dns_secondary) > 0) {
            esp_netif_dns_info_t dns_info;
            if (esp_netif_str_to_ip4(config->static_dns_secondary, &dns_info.ip.u_addr.ip4) == ESP_OK) {
                dns_info.ip.type = ESP_IPADDR_TYPE_V4;
                esp_netif_set_dns_info(eth_netif, ESP_NETIF_DNS_BACKUP, &dns_info);
                ESP_LOGI(TAG, "Set secondary DNS: %s", config->static_dns_secondary);
            }
        }
    }

    // Set hostname if specified
    if (strlen(config->hostname) > 0) {
        esp_netif_set_hostname(eth_netif, config->hostname);
        ESP_LOGI(TAG, "Set hostname: %s", config->hostname);
    }

    memcpy(&current_config, config, sizeof(ethernet_config_t));
    return ESP_OK;
}

esp_err_t ethernet_manager_get_ip_string(char *ip_str, size_t ip_str_size)
{
    if (ip_str == NULL || ip_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (eth_netif == NULL || !eth_connected) {
        strncpy(ip_str, "0.0.0.0", ip_str_size - 1);
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    esp_err_t ret = esp_netif_get_ip_info(eth_netif, &ip_info);
    if (ret == ESP_OK) {
        snprintf(ip_str, ip_str_size, IPSTR, IP2STR(&ip_info.ip));
    } else {
        strncpy(ip_str, "0.0.0.0", ip_str_size - 1);
    }

    return ret;
}

esp_err_t ethernet_manager_get_gateway_string(char *gw_str, size_t gw_str_size)
{
    if (gw_str == NULL || gw_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (eth_netif == NULL || !eth_connected) {
        strncpy(gw_str, "0.0.0.0", gw_str_size - 1);
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    esp_err_t ret = esp_netif_get_ip_info(eth_netif, &ip_info);
    if (ret == ESP_OK) {
        snprintf(gw_str, gw_str_size, IPSTR, IP2STR(&ip_info.gw));
    } else {
        strncpy(gw_str, "0.0.0.0", gw_str_size - 1);
    }

    return ret;
}

esp_err_t ethernet_manager_get_netmask_string(char *nm_str, size_t nm_str_size)
{
    if (nm_str == NULL || nm_str_size < 16) {
        return ESP_ERR_INVALID_ARG;
    }

    if (eth_netif == NULL || !eth_connected) {
        strncpy(nm_str, "0.0.0.0", nm_str_size - 1);
        return ESP_ERR_INVALID_STATE;
    }

    esp_netif_ip_info_t ip_info;
    esp_err_t ret = esp_netif_get_ip_info(eth_netif, &ip_info);
    if (ret == ESP_OK) {
        snprintf(nm_str, nm_str_size, IPSTR, IP2STR(&ip_info.netmask));
    } else {
        strncpy(nm_str, "0.0.0.0", nm_str_size - 1);
    }

    return ret;
}
