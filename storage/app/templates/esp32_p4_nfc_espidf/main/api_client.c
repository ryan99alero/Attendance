/**
 * API Client Implementation for Time Attendance System
 */

#include "api_client.h"
#include "esp_http_client.h"
#include "esp_log.h"
#include "esp_mac.h"
#include "nvs_flash.h"
#include "cJSON.h"
#include <string.h>

static const char *TAG = "API_CLIENT";

// NVS namespace for API config persistence
#define NVS_NAMESPACE_API "api_config"

// Global API configuration - initialized with defaults
static api_config_t g_api_config = {
    .server_host = "192.168.29.25",
    .server_port = 8000,
    .device_name = "ESP32-TimeClock",
    .api_token = "",
    .device_id = "",
    .is_registered = false,
    .is_approved = false
};

// Response buffer for HTTP client
static char response_buffer[2048];
static int response_buffer_len = 0;

// Time sync tracking
static sync_source_t g_sync_source = SYNC_SOURCE_NONE;
static time_sync_data_t g_time_sync_data = {0};

// HTTP event handler - captures response body
static esp_err_t http_event_handler(esp_http_client_event_t *evt) {
    switch (evt->event_id) {
        case HTTP_EVENT_ERROR:
            ESP_LOGE(TAG, "HTTP_EVENT_ERROR");
            break;
        case HTTP_EVENT_ON_CONNECTED:
            ESP_LOGI(TAG, "HTTP connected");
            break;
        case HTTP_EVENT_HEADER_SENT:
            ESP_LOGD(TAG, "HTTP_EVENT_HEADER_SENT");
            break;
        case HTTP_EVENT_ON_HEADER:
            ESP_LOGD(TAG, "Header: %s = %s", evt->header_key, evt->header_value);
            break;
        case HTTP_EVENT_ON_DATA:
            ESP_LOGI(TAG, "HTTP_EVENT_ON_DATA, len=%d", evt->data_len);
            // Append data to response buffer
            if (response_buffer_len + evt->data_len < sizeof(response_buffer) - 1) {
                memcpy(response_buffer + response_buffer_len, evt->data, evt->data_len);
                response_buffer_len += evt->data_len;
                response_buffer[response_buffer_len] = '\0';
            }
            break;
        case HTTP_EVENT_ON_FINISH:
            ESP_LOGI(TAG, "HTTP_EVENT_ON_FINISH, total response len=%d", response_buffer_len);
            break;
        case HTTP_EVENT_DISCONNECTED:
            ESP_LOGD(TAG, "HTTP_EVENT_DISCONNECTED");
            break;
        default:
            break;
    }
    return ESP_OK;
}

esp_err_t api_client_init(api_config_t *config) {
    if (config == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    memcpy(&g_api_config, config, sizeof(api_config_t));
    ESP_LOGI(TAG, "API client initialized");
    ESP_LOGI(TAG, "Server: %s:%d", g_api_config.server_host, g_api_config.server_port);

    return ESP_OK;
}

/**
 * Save API config to NVS (persists across reboots)
 */
esp_err_t api_save_config(void) {
    ESP_LOGW(TAG, "========== SAVING API CONFIG TO NVS ==========");
    ESP_LOGI(TAG, "  server_host: %s", g_api_config.server_host);
    ESP_LOGI(TAG, "  server_port: %d", g_api_config.server_port);
    ESP_LOGI(TAG, "  device_id: %s", g_api_config.device_id);
    ESP_LOGI(TAG, "  device_name: %s", g_api_config.device_name);
    ESP_LOGI(TAG, "  is_registered: %d", g_api_config.is_registered);
    ESP_LOGI(TAG, "  api_token: %.20s...", g_api_config.api_token);

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE_API, NVS_READWRITE, &nvs_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to open NVS for writing: %s", esp_err_to_name(ret));
        return ret;
    }
    ESP_LOGI(TAG, "NVS opened for writing");

    // Save individual fields with error checking
    esp_err_t err;

    err = nvs_set_str(nvs_handle, "srv_host", g_api_config.server_host);
    ESP_LOGI(TAG, "  nvs_set srv_host: %s", esp_err_to_name(err));

    err = nvs_set_u16(nvs_handle, "srv_port", g_api_config.server_port);
    ESP_LOGI(TAG, "  nvs_set srv_port: %s", esp_err_to_name(err));

    err = nvs_set_str(nvs_handle, "api_token", g_api_config.api_token);
    ESP_LOGI(TAG, "  nvs_set api_token: %s", esp_err_to_name(err));

    err = nvs_set_str(nvs_handle, "device_id", g_api_config.device_id);
    ESP_LOGI(TAG, "  nvs_set device_id: %s", esp_err_to_name(err));

    err = nvs_set_str(nvs_handle, "dev_name", g_api_config.device_name);
    ESP_LOGI(TAG, "  nvs_set dev_name: %s", esp_err_to_name(err));

    err = nvs_set_u8(nvs_handle, "registered", g_api_config.is_registered ? 1 : 0);
    ESP_LOGI(TAG, "  nvs_set registered: %s", esp_err_to_name(err));

    err = nvs_set_u8(nvs_handle, "approved", g_api_config.is_approved ? 1 : 0);
    ESP_LOGI(TAG, "  nvs_set approved: %s", esp_err_to_name(err));

    ret = nvs_commit(nvs_handle);
    ESP_LOGI(TAG, "  nvs_commit: %s", esp_err_to_name(ret));

    nvs_close(nvs_handle);

    if (ret == ESP_OK) {
        ESP_LOGW(TAG, "✅ API config saved to NVS successfully!");
    } else {
        ESP_LOGE(TAG, "❌ Failed to commit NVS: %s", esp_err_to_name(ret));
    }

    return ret;
}

/**
 * Load API config from NVS (restores state after reboot)
 */
esp_err_t api_load_config(void) {
    ESP_LOGW(TAG, "========== LOADING API CONFIG FROM NVS ==========");

    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE_API, NVS_READONLY, &nvs_handle);
    if (ret != ESP_OK) {
        if (ret == ESP_ERR_NVS_NOT_FOUND) {
            ESP_LOGI(TAG, "No saved API config found (first boot or namespace doesn't exist)");
        } else {
            ESP_LOGW(TAG, "Failed to open NVS for reading: %s", esp_err_to_name(ret));
        }
        return ret;
    }
    ESP_LOGI(TAG, "NVS opened for reading");

    // Load individual fields with error checking
    esp_err_t err;
    size_t len;

    len = sizeof(g_api_config.server_host);
    err = nvs_get_str(nvs_handle, "srv_host", g_api_config.server_host, &len);
    ESP_LOGI(TAG, "  nvs_get srv_host: %s -> '%s'", esp_err_to_name(err), g_api_config.server_host);

    err = nvs_get_u16(nvs_handle, "srv_port", &g_api_config.server_port);
    ESP_LOGI(TAG, "  nvs_get srv_port: %s -> %d", esp_err_to_name(err), g_api_config.server_port);

    len = sizeof(g_api_config.api_token);
    err = nvs_get_str(nvs_handle, "api_token", g_api_config.api_token, &len);
    ESP_LOGI(TAG, "  nvs_get api_token: %s -> %.20s...", esp_err_to_name(err), g_api_config.api_token);

    len = sizeof(g_api_config.device_id);
    err = nvs_get_str(nvs_handle, "device_id", g_api_config.device_id, &len);
    ESP_LOGI(TAG, "  nvs_get device_id: %s -> '%s'", esp_err_to_name(err), g_api_config.device_id);

    len = sizeof(g_api_config.device_name);
    err = nvs_get_str(nvs_handle, "dev_name", g_api_config.device_name, &len);
    ESP_LOGI(TAG, "  nvs_get dev_name: %s -> '%s'", esp_err_to_name(err), g_api_config.device_name);

    uint8_t is_registered = 0;
    err = nvs_get_u8(nvs_handle, "registered", &is_registered);
    g_api_config.is_registered = (is_registered == 1);
    ESP_LOGI(TAG, "  nvs_get registered: %s -> %d", esp_err_to_name(err), is_registered);

    uint8_t is_approved = 0;
    err = nvs_get_u8(nvs_handle, "approved", &is_approved);
    g_api_config.is_approved = (is_approved == 1);
    ESP_LOGI(TAG, "  nvs_get approved: %s -> %d", esp_err_to_name(err), is_approved);

    nvs_close(nvs_handle);

    ESP_LOGW(TAG, "========== API CONFIG LOADED ==========");
    ESP_LOGI(TAG, "  Server: %s:%d", g_api_config.server_host, g_api_config.server_port);
    ESP_LOGI(TAG, "  Device ID: %s", g_api_config.device_id);
    ESP_LOGI(TAG, "  Registered: %s", g_api_config.is_registered ? "YES" : "NO");

    return ESP_OK;
}

esp_err_t api_register_device(const char *mac_address, const char *device_name) {
    esp_err_t ret = ESP_FAIL;

    ESP_LOGW(TAG, "===== API REGISTER DEVICE START =====");
    ESP_LOGI(TAG, "Device: %s, MAC: %s", device_name, mac_address);
    ESP_LOGI(TAG, "Server: %s:%d", g_api_config.server_host, g_api_config.server_port);

    // Clear response buffer
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/register",
             g_api_config.server_host, g_api_config.server_port);

    // Build JSON payload
    cJSON *root = cJSON_CreateObject();
    cJSON_AddStringToObject(root, "mac_address", mac_address);
    cJSON_AddStringToObject(root, "device_name", device_name);

    char *json_data = cJSON_PrintUnformatted(root);
    ESP_LOGI(TAG, "POST %s", url);
    ESP_LOGI(TAG, "JSON: %s", json_data);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 10000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);
    if (client == NULL) {
        ESP_LOGE(TAG, "Failed to init HTTP client");
        cJSON_Delete(root);
        free(json_data);
        return ESP_FAIL;
    }

    // Set headers and POST data
    esp_http_client_set_method(client, HTTP_METHOD_POST);
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_header(client, "Accept", "application/json");
    esp_http_client_set_post_field(client, json_data, strlen(json_data));

    ESP_LOGI(TAG, "Performing HTTP request...");

    // Perform request
    esp_err_t err = esp_http_client_perform(client);

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        ESP_LOGI(TAG, "HTTP Status: %d", status_code);
        ESP_LOGI(TAG, "Response (%d bytes): %s", response_buffer_len, response_buffer);

        if (status_code == 200 || status_code == 201) {
            if (response_buffer_len > 0) {
                // Parse JSON response
                cJSON *response_json = cJSON_Parse(response_buffer);
                if (response_json != NULL) {
                    // Check success field
                    cJSON *success = cJSON_GetObjectItem(response_json, "success");
                    if (success && cJSON_IsTrue(success)) {
                        // Get data object
                        cJSON *data = cJSON_GetObjectItem(response_json, "data");
                        if (data != NULL) {
                            cJSON *token = cJSON_GetObjectItem(data, "api_token");
                            cJSON *dev_id = cJSON_GetObjectItem(data, "device_id");

                            if (token && cJSON_IsString(token) && dev_id && cJSON_IsString(dev_id)) {
                                strncpy(g_api_config.api_token, token->valuestring, sizeof(g_api_config.api_token) - 1);
                                strncpy(g_api_config.device_id, dev_id->valuestring, sizeof(g_api_config.device_id) - 1);
                                strncpy(g_api_config.device_name, device_name, sizeof(g_api_config.device_name) - 1);
                                g_api_config.is_registered = true;

                                ESP_LOGW(TAG, "✅ REGISTRATION SUCCESSFUL!");
                                ESP_LOGI(TAG, "Device ID: %s", g_api_config.device_id);
                                ESP_LOGI(TAG, "Token: %.16s...", g_api_config.api_token);

                                // Save to NVS so registration persists across reboots
                                api_save_config();

                                ret = ESP_OK;
                            } else {
                                ESP_LOGE(TAG, "Missing api_token or device_id in response");
                            }
                        } else {
                            ESP_LOGE(TAG, "Missing 'data' object in response");
                        }
                    } else {
                        cJSON *msg = cJSON_GetObjectItem(response_json, "message");
                        ESP_LOGE(TAG, "Server returned success=false: %s",
                                 msg ? msg->valuestring : "unknown error");
                    }
                    cJSON_Delete(response_json);
                } else {
                    ESP_LOGE(TAG, "Failed to parse JSON response");
                }
            } else {
                ESP_LOGE(TAG, "Empty response body");
            }
        } else {
            ESP_LOGE(TAG, "HTTP error %d", status_code);
            if (response_buffer_len > 0) {
                ESP_LOGE(TAG, "Error response: %s", response_buffer);
            }
        }
    } else {
        ESP_LOGE(TAG, "HTTP request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(root);
    free(json_data);

    ESP_LOGW(TAG, "===== API REGISTER DEVICE END (ret=%d) =====", ret);
    return ret;
}

esp_err_t api_check_status(void) {
    if (!g_api_config.is_registered || strlen(g_api_config.api_token) == 0) {
        ESP_LOGW(TAG, "Device not registered, cannot check status");
        return ESP_ERR_INVALID_STATE;
    }

    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/status",
             g_api_config.server_host, g_api_config.server_port);

    // Clear response buffer before request
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Set authorization header
    char auth_header[512];
    snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_config.api_token);
    esp_http_client_set_header(client, "Authorization", auth_header);

    // Perform GET request
    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);

        if (status_code == 200 && response_buffer_len > 0) {
            // Parse response from static buffer (filled by event handler)
            cJSON *response_json = cJSON_Parse(response_buffer);
            if (response_json != NULL) {
                cJSON *status = cJSON_GetObjectItem(response_json, "status");
                if (status != NULL && cJSON_IsString(status)) {
                    if (strcmp(status->valuestring, "approved") == 0) {
                        g_api_config.is_approved = true;
                        ESP_LOGI(TAG, "Device status: APPROVED");
                    } else {
                        g_api_config.is_approved = false;
                        ESP_LOGI(TAG, "Device status: %s", status->valuestring);
                    }
                    ret = ESP_OK;
                }
                cJSON_Delete(response_json);
            }
        }
    }

    esp_http_client_cleanup(client);
    return ret;
}

esp_err_t api_send_punch(const punch_data_t *punch_data) {
    if (punch_data == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!g_api_config.is_registered || strlen(g_api_config.api_token) == 0) {
        ESP_LOGE(TAG, "Cannot send punch - device not registered or no API token");
        return ESP_ERR_INVALID_STATE;
    }

    ESP_LOGI(TAG, "🚀 Sending punch data...");
    ESP_LOGI(TAG, "   Device: %s", punch_data->device_id);
    ESP_LOGI(TAG, "   Card: %s (%s)", punch_data->credential_value, punch_data->credential_kind);
    ESP_LOGI(TAG, "   Time: %s", punch_data->event_time);

    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/punch",
             g_api_config.server_host, g_api_config.server_port);

    // Build JSON payload
    cJSON *root = cJSON_CreateObject();
    cJSON_AddStringToObject(root, "device_id", punch_data->device_id);
    cJSON_AddStringToObject(root, "credential_kind", punch_data->credential_kind);
    cJSON_AddStringToObject(root, "credential_value", punch_data->credential_value);
    cJSON_AddStringToObject(root, "event_time", punch_data->event_time);
    cJSON_AddStringToObject(root, "event_type", punch_data->event_type);
    cJSON_AddNumberToObject(root, "confidence", punch_data->confidence);
    cJSON_AddNumberToObject(root, "timezone_offset", punch_data->timezone_offset);

    char *json_data = cJSON_PrintUnformatted(root);
    ESP_LOGI(TAG, "POST %s", url);
    ESP_LOGD(TAG, "JSON: %s", json_data);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 10000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Set headers
    char auth_header[512];
    snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_config.api_token);
    esp_http_client_set_header(client, "Authorization", auth_header);
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_method(client, HTTP_METHOD_POST);
    esp_http_client_set_post_field(client, json_data, strlen(json_data));

    // Perform request
    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        ESP_LOGI(TAG, "📥 Response status: %d", status_code);

        if (status_code == 200) {
            ESP_LOGI(TAG, "✅ Punch recorded successfully!");
            ret = ESP_OK;
        } else {
            ESP_LOGE(TAG, "❌ Punch failed with status %d", status_code);
        }
    } else {
        ESP_LOGE(TAG, "❌ HTTP request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(root);
    free(json_data);

    return ret;
}

esp_err_t api_health_check(void) {
    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/health",
             g_api_config.server_host, g_api_config.server_port);

    ESP_LOGI(TAG, "Health check: %s", url);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Perform GET request
    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        if (status_code == 200) {
            ESP_LOGI(TAG, "✅ Server is healthy");
            ret = ESP_OK;
        } else {
            ESP_LOGE(TAG, "Health check failed: %d", status_code);
        }
    } else {
        ESP_LOGE(TAG, "Health check request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    return ret;
}

esp_err_t api_get_server_time(char *time_str, size_t time_str_size) {
    if (time_str == NULL || time_str_size < 32) {
        return ESP_ERR_INVALID_ARG;
    }

    // Build URL with device_id so server can return device-specific time settings
    // (timezone, NTP server, etc.)
    char url[512];
    if (g_api_config.is_registered && strlen(g_api_config.device_id) > 0) {
        snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/time?device_id=%s",
                 g_api_config.server_host, g_api_config.server_port, g_api_config.device_id);
    } else {
        // Not registered yet - use MAC address for identification
        uint8_t mac[6];
        esp_read_mac(mac, ESP_MAC_BASE);
        snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/time?mac=%02X:%02X:%02X:%02X:%02X:%02X",
                 g_api_config.server_host, g_api_config.server_port,
                 mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
    }

    ESP_LOGI(TAG, "Time sync request: %s", url);

    // Clear response buffer before request
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Add authorization header if registered
    if (g_api_config.is_registered && strlen(g_api_config.api_token) > 0) {
        char auth_header[512];
        snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_config.api_token);
        esp_http_client_set_header(client, "Authorization", auth_header);
    }

    // Perform GET request
    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        ESP_LOGI(TAG, "Time sync response: %d", status_code);

        if (status_code == 200 && response_buffer_len > 0) {
            ESP_LOGI(TAG, "Time response: %s", response_buffer);

            // Parse response from static buffer (filled by event handler)
            cJSON *response_json = cJSON_Parse(response_buffer);
            if (response_json != NULL) {
                // Get server time
                cJSON *time_obj = cJSON_GetObjectItem(response_json, "server_time");
                if (time_obj != NULL && cJSON_IsString(time_obj)) {
                    strncpy(time_str, time_obj->valuestring, time_str_size - 1);
                    time_str[time_str_size - 1] = '\0';
                    ESP_LOGI(TAG, "Server time: %s", time_str);
                    ret = ESP_OK;
                } else {
                    ESP_LOGE(TAG, "No server_time in response");
                }

                // Log additional fields if present (for debugging)
                cJSON *timezone = cJSON_GetObjectItem(response_json, "timezone");
                if (timezone && cJSON_IsString(timezone)) {
                    ESP_LOGI(TAG, "Timezone: %s", timezone->valuestring);
                }

                cJSON *ntp_server = cJSON_GetObjectItem(response_json, "ntp_server");
                if (ntp_server && cJSON_IsString(ntp_server)) {
                    ESP_LOGI(TAG, "NTP server: %s", ntp_server->valuestring);
                }

                cJSON_Delete(response_json);
            } else {
                ESP_LOGE(TAG, "Failed to parse JSON response");
            }
        } else if (status_code == 200) {
            ESP_LOGE(TAG, "Empty response body");
        }
    } else {
        ESP_LOGE(TAG, "Time sync request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    return ret;
}

esp_err_t api_get_employee_info(const char *card_id, employee_info_t *employee_info) {
    if (card_id == NULL || employee_info == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/employee/%s",
             g_api_config.server_host, g_api_config.server_port, card_id);

    ESP_LOGI(TAG, "Fetching employee info: %s", url);

    // Clear response buffer before request
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Perform GET request
    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);

        if (status_code == 200 && response_buffer_len > 0) {
            ESP_LOGD(TAG, "Response: %s", response_buffer);

            // Parse response from static buffer (filled by event handler)
            cJSON *response_json = cJSON_Parse(response_buffer);
            if (response_json != NULL) {
                cJSON *success = cJSON_GetObjectItem(response_json, "success");
                if (success != NULL && cJSON_IsTrue(success)) {
                    // Get employee data
                    cJSON *employee = cJSON_GetObjectItem(response_json, "employee");
                    if (employee != NULL) {
                        cJSON *name = cJSON_GetObjectItem(employee, "name");
                        cJSON *emp_id = cJSON_GetObjectItem(employee, "id");
                        cJSON *dept = cJSON_GetObjectItem(employee, "department");

                        if (name != NULL && cJSON_IsString(name)) {
                            strncpy(employee_info->name, name->valuestring, sizeof(employee_info->name) - 1);
                        }
                        if (emp_id != NULL && cJSON_IsNumber(emp_id)) {
                            snprintf(employee_info->employee_id, sizeof(employee_info->employee_id), "%d", emp_id->valueint);
                        }
                        if (dept != NULL && cJSON_IsString(dept)) {
                            strncpy(employee_info->department, dept->valuestring, sizeof(employee_info->department) - 1);
                        }
                        employee_info->is_authorized = true;
                    }

                    // Get hours data
                    cJSON *hours = cJSON_GetObjectItem(response_json, "hours");
                    if (hours != NULL) {
                        cJSON *today = cJSON_GetObjectItem(hours, "today_hours");
                        cJSON *week = cJSON_GetObjectItem(hours, "week_hours");
                        cJSON *pay_period = cJSON_GetObjectItem(hours, "pay_period_hours");

                        if (today != NULL && cJSON_IsNumber(today)) {
                            employee_info->today_hours = (float)today->valuedouble;
                        }
                        if (week != NULL && cJSON_IsNumber(week)) {
                            employee_info->week_hours = (float)week->valuedouble;
                        }
                        if (pay_period != NULL && cJSON_IsNumber(pay_period)) {
                            employee_info->pay_period_hours = (float)pay_period->valuedouble;
                        }
                    }

                    // Get vacation balance (if available)
                    cJSON *vacation = cJSON_GetObjectItem(response_json, "vacation_balance");
                    if (vacation != NULL && cJSON_IsNumber(vacation)) {
                        employee_info->vacation_balance = (float)vacation->valuedouble;
                    } else {
                        employee_info->vacation_balance = 0.0f;
                    }

                    ESP_LOGI(TAG, "Employee found: %s (ID: %s)",
                             employee_info->name, employee_info->employee_id);
                    ESP_LOGI(TAG, "   Today: %.1f hrs, Week: %.1f hrs, Pay Period: %.1f hrs",
                             employee_info->today_hours, employee_info->week_hours, employee_info->pay_period_hours);
                    ret = ESP_OK;
                } else {
                    ESP_LOGW(TAG, "Employee not found or unauthorized");
                    employee_info->is_authorized = false;
                }
                cJSON_Delete(response_json);
            }
        } else if (status_code != 200) {
            ESP_LOGW(TAG, "Employee lookup failed with status %d", status_code);
        }
    } else {
        ESP_LOGE(TAG, "HTTP request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    return ret;
}

api_config_t* api_get_config(void) {
    return &g_api_config;
}

sync_source_t api_get_sync_source(void) {
    return g_sync_source;
}

const time_sync_data_t* api_get_time_sync_data(void) {
    return &g_time_sync_data;
}

esp_err_t api_sync_time(time_sync_data_t *sync_data) {
    if (sync_data == NULL) {
        return ESP_ERR_INVALID_ARG;
    }

    memset(sync_data, 0, sizeof(time_sync_data_t));

    // Build URL with device_id so server can return device-specific settings
    char url[512];
    if (g_api_config.is_registered && strlen(g_api_config.device_id) > 0) {
        snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/time?device_id=%s",
                 g_api_config.server_host, g_api_config.server_port, g_api_config.device_id);
    } else {
        uint8_t mac[6];
        esp_read_mac(mac, ESP_MAC_BASE);
        snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/time?mac=%02X:%02X:%02X:%02X:%02X:%02X",
                 g_api_config.server_host, g_api_config.server_port,
                 mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
    }

    ESP_LOGI(TAG, "Time sync request: %s", url);

    // Clear response buffer
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 5000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    if (g_api_config.is_registered && strlen(g_api_config.api_token) > 0) {
        char auth_header[512];
        snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_config.api_token);
        esp_http_client_set_header(client, "Authorization", auth_header);
    }

    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        ESP_LOGI(TAG, "Time sync response: %d", status_code);

        if (status_code == 200 && response_buffer_len > 0) {
            ESP_LOGI(TAG, "Time response: %s", response_buffer);

            cJSON *response_json = cJSON_Parse(response_buffer);
            if (response_json != NULL) {
                // Get server_time
                cJSON *time_obj = cJSON_GetObjectItem(response_json, "server_time");
                if (time_obj && cJSON_IsString(time_obj)) {
                    strncpy(sync_data->server_time, time_obj->valuestring, sizeof(sync_data->server_time) - 1);
                    sync_data->valid = true;
                }

                // Get unix_timestamp
                cJSON *unix_ts = cJSON_GetObjectItem(response_json, "unix_timestamp");
                if (unix_ts && cJSON_IsNumber(unix_ts)) {
                    sync_data->unix_timestamp = (int64_t)unix_ts->valuedouble;
                }

                // Get timezone from server_timezone or device_timezone.timezone_name
                cJSON *server_tz = cJSON_GetObjectItem(response_json, "server_timezone");
                if (server_tz && cJSON_IsString(server_tz)) {
                    strncpy(sync_data->timezone, server_tz->valuestring, sizeof(sync_data->timezone) - 1);
                    ESP_LOGI(TAG, "Server timezone: %s", sync_data->timezone);
                }

                // Get device_timezone object for more details
                cJSON *device_tz = cJSON_GetObjectItem(response_json, "device_timezone");
                if (device_tz && cJSON_IsObject(device_tz)) {
                    // Get timezone_name if not already set
                    if (strlen(sync_data->timezone) == 0) {
                        cJSON *tz_name = cJSON_GetObjectItem(device_tz, "timezone_name");
                        if (tz_name && cJSON_IsString(tz_name)) {
                            strncpy(sync_data->timezone, tz_name->valuestring, sizeof(sync_data->timezone) - 1);
                        }
                    }

                    // Get current_offset (API returns HOURS, we store in SECONDS)
                    cJSON *offset = cJSON_GetObjectItem(device_tz, "current_offset");
                    if (offset && cJSON_IsNumber(offset)) {
                        sync_data->timezone_offset = (int)offset->valuedouble * 3600;  // Convert hours to seconds
                        ESP_LOGI(TAG, "Timezone offset: %d hours (%d seconds)",
                                 (int)offset->valuedouble, sync_data->timezone_offset);
                    }

                    // Log DST and abbreviation for debugging
                    cJSON *is_dst = cJSON_GetObjectItem(device_tz, "is_dst");
                    cJSON *tz_abbr = cJSON_GetObjectItem(device_tz, "timezone_abbr");
                    if (tz_abbr && cJSON_IsString(tz_abbr)) {
                        ESP_LOGI(TAG, "Timezone abbr: %s, DST: %s",
                                 tz_abbr->valuestring,
                                 (is_dst && cJSON_IsTrue(is_dst)) ? "Yes" : "No");
                    }
                }

                // Get NTP server if specified (may not be in current API)
                cJSON *ntp = cJSON_GetObjectItem(response_json, "ntp_server");
                if (ntp && cJSON_IsString(ntp)) {
                    strncpy(sync_data->ntp_server, ntp->valuestring, sizeof(sync_data->ntp_server) - 1);
                    ESP_LOGI(TAG, "NTP server: %s", sync_data->ntp_server);
                }

                // Check if server wants us to use server time (may not be in current API)
                cJSON *use_server = cJSON_GetObjectItem(response_json, "use_server_time");
                if (use_server && cJSON_IsBool(use_server)) {
                    sync_data->use_server_time = cJSON_IsTrue(use_server);
                } else {
                    sync_data->use_server_time = true;  // Default to using server time
                }

                if (sync_data->valid) {
                    // Copy to global for later retrieval
                    memcpy(&g_time_sync_data, sync_data, sizeof(time_sync_data_t));
                    g_sync_source = SYNC_SOURCE_SERVER;
                    ESP_LOGI(TAG, "Server time: %s (unix: %lld)", sync_data->server_time, sync_data->unix_timestamp);
                    ret = ESP_OK;
                }

                cJSON_Delete(response_json);
            } else {
                ESP_LOGE(TAG, "Failed to parse JSON response");
            }
        }
    } else {
        ESP_LOGE(TAG, "Time sync request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    return ret;
}

esp_err_t api_send_heartbeat(const char *ip_address) {
    if (!g_api_config.is_registered || strlen(g_api_config.api_token) == 0) {
        ESP_LOGW(TAG, "Device not registered, cannot send heartbeat");
        return ESP_ERR_INVALID_STATE;
    }

    // Build URL
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/heartbeat",
             g_api_config.server_host, g_api_config.server_port);

    // Build JSON payload
    cJSON *root = cJSON_CreateObject();
    cJSON_AddStringToObject(root, "device_id", g_api_config.device_id);
    if (ip_address && strlen(ip_address) > 0) {
        cJSON_AddStringToObject(root, "ip_address", ip_address);
    }

    // Add firmware version if available
    cJSON_AddStringToObject(root, "firmware_version", "1.0.0");

    // Add uptime
    cJSON_AddNumberToObject(root, "uptime_seconds", (double)(xTaskGetTickCount() / configTICK_RATE_HZ));

    char *json_data = cJSON_PrintUnformatted(root);

    ESP_LOGI(TAG, "Heartbeat to %s: %s", url, json_data);

    // Clear response buffer
    response_buffer_len = 0;
    response_buffer[0] = '\0';

    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 10000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    char auth_header[512];
    snprintf(auth_header, sizeof(auth_header), "Bearer %s", g_api_config.api_token);
    esp_http_client_set_header(client, "Authorization", auth_header);
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_method(client, HTTP_METHOD_POST);
    esp_http_client_set_post_field(client, json_data, strlen(json_data));

    esp_err_t err = esp_http_client_perform(client);
    esp_err_t ret = ESP_FAIL;

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        ESP_LOGI(TAG, "Heartbeat response: %d", status_code);
        if (status_code == 200) {
            ret = ESP_OK;
        }
    } else {
        ESP_LOGE(TAG, "Heartbeat failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(root);
    free(json_data);

    return ret;
}
