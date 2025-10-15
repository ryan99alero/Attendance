/**
 * API Client Implementation for Time Attendance System
 */

#include "api_client.h"
#include "esp_http_client.h"
#include "esp_log.h"
#include "cJSON.h"
#include <string.h>

static const char *TAG = "API_CLIENT";

// Global API configuration
static api_config_t g_api_config = {0};

// HTTP event handler
static esp_err_t http_event_handler(esp_http_client_event_t *evt) {
    switch (evt->event_id) {
        case HTTP_EVENT_ERROR:
            ESP_LOGE(TAG, "HTTP_EVENT_ERROR");
            break;
        case HTTP_EVENT_ON_CONNECTED:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_CONNECTED");
            break;
        case HTTP_EVENT_HEADER_SENT:
            ESP_LOGD(TAG, "HTTP_EVENT_HEADER_SENT");
            break;
        case HTTP_EVENT_ON_HEADER:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_HEADER, key=%s, value=%s", evt->header_key, evt->header_value);
            break;
        case HTTP_EVENT_ON_DATA:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_DATA, len=%d", evt->data_len);
            break;
        case HTTP_EVENT_ON_FINISH:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_FINISH");
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

esp_err_t api_register_device(const char *mac_address, const char *device_name) {
    esp_err_t ret = ESP_FAIL;

    ESP_LOGI(TAG, "Registering device: %s (MAC: %s)", device_name, mac_address);

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
    ESP_LOGI(TAG, "Data: %s", json_data);

    // Configure HTTP client
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
        .timeout_ms = 10000,
    };

    esp_http_client_handle_t client = esp_http_client_init(&config);

    // Set headers and POST data
    esp_http_client_set_method(client, HTTP_METHOD_POST);
    esp_http_client_set_header(client, "Content-Type", "application/json");
    esp_http_client_set_post_field(client, json_data, strlen(json_data));

    // Perform request
    esp_err_t err = esp_http_client_perform(client);

    if (err == ESP_OK) {
        int status_code = esp_http_client_get_status_code(client);
        int content_length = esp_http_client_get_content_length(client);

        ESP_LOGI(TAG, "HTTP Status = %d, content_length = %d", status_code, content_length);

        if (status_code == 200 || status_code == 201) {
            // Get response data
            char response_buffer[1024];
            int data_read = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
            if (data_read > 0) {
                response_buffer[data_read] = '\0';
                ESP_LOGI(TAG, "Response: %s", response_buffer);

                // Parse JSON response
                cJSON *response_json = cJSON_Parse(response_buffer);
                if (response_json != NULL) {
                    // Try nested data structure first
                    cJSON *data = cJSON_GetObjectItem(response_json, "data");
                    if (data != NULL) {
                        cJSON *token = cJSON_GetObjectItem(data, "api_token");
                        cJSON *device_id = cJSON_GetObjectItem(data, "device_id");

                        if (token != NULL && device_id != NULL) {
                            strncpy(g_api_config.api_token, token->valuestring, sizeof(g_api_config.api_token) - 1);
                            strncpy(g_api_config.device_id, device_id->valuestring, sizeof(g_api_config.device_id) - 1);
                            g_api_config.is_registered = true;

                            ESP_LOGI(TAG, "✅ Registration successful!");
                            ESP_LOGI(TAG, "Device ID: %s", g_api_config.device_id);
                            ESP_LOGI(TAG, "API Token: %s...", strndup(g_api_config.api_token, 8));

                            ret = ESP_OK;
                        }
                    }
                    cJSON_Delete(response_json);
                }
            }
        } else {
            ESP_LOGE(TAG, "Registration failed with status %d", status_code);
        }
    } else {
        ESP_LOGE(TAG, "HTTP request failed: %s", esp_err_to_name(err));
    }

    esp_http_client_cleanup(client);
    cJSON_Delete(root);
    free(json_data);

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

        if (status_code == 200) {
            char response_buffer[512];
            int data_read = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
            if (data_read > 0) {
                response_buffer[data_read] = '\0';

                // Parse response
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

    // Build URL - using status endpoint which should return server time
    char url[256];
    snprintf(url, sizeof(url), "http://%s:%d/api/v1/timeclock/time",
             g_api_config.server_host, g_api_config.server_port);

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
            char response_buffer[512];
            int data_read = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
            if (data_read > 0) {
                response_buffer[data_read] = '\0';

                // Parse response
                cJSON *response_json = cJSON_Parse(response_buffer);
                if (response_json != NULL) {
                    cJSON *time = cJSON_GetObjectItem(response_json, "server_time");
                    if (time != NULL && cJSON_IsString(time)) {
                        strncpy(time_str, time->valuestring, time_str_size - 1);
                        time_str[time_str_size - 1] = '\0';
                        ESP_LOGI(TAG, "Server time: %s", time_str);
                        ret = ESP_OK;
                    }
                    cJSON_Delete(response_json);
                }
            }
        }
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
            char response_buffer[1024];
            int data_read = esp_http_client_read(client, response_buffer, sizeof(response_buffer) - 1);
            if (data_read > 0) {
                response_buffer[data_read] = '\0';
                ESP_LOGD(TAG, "Response: %s", response_buffer);

                // Parse response
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

                        ESP_LOGI(TAG, "✅ Employee found: %s (ID: %s)",
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
            }
        } else {
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
