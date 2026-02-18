/**
 * Punch Queue Implementation - Offline buffering for time clock punches
 */

#include "punch_queue.h"
#include "api_client.h"
#include "ui_events.h"
#include "esp_log.h"
#include "nvs_flash.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/semphr.h"
#include <string.h>
#include <time.h>

// Track server connectivity status
static bool s_server_online = true;

static const char *TAG = "PUNCH_QUEUE";

// NVS namespace and keys
#define NVS_NAMESPACE "punch_queue"
#define NVS_KEY_COUNT "count"
#define NVS_KEY_QUEUE "queue"

// Queue storage
static queued_punch_t s_queue[PUNCH_QUEUE_MAX_SIZE];
static uint32_t s_queue_count = 0;
static SemaphoreHandle_t s_queue_mutex = NULL;
static TaskHandle_t s_sync_task_handle = NULL;
static bool s_sync_task_running = false;
static uint32_t s_sync_interval_ms = 30000;  // Default 30 seconds

// Forward declarations
static void sync_task(void *pvParameters);
static uint32_t generate_punch_id(void);

esp_err_t punch_queue_init(void) {
    ESP_LOGI(TAG, "Initializing punch queue...");

    // Create mutex for thread safety
    if (s_queue_mutex == NULL) {
        s_queue_mutex = xSemaphoreCreateMutex();
        if (s_queue_mutex == NULL) {
            ESP_LOGE(TAG, "Failed to create mutex");
            return ESP_ERR_NO_MEM;
        }
    }

    // Clear queue memory
    memset(s_queue, 0, sizeof(s_queue));
    s_queue_count = 0;

    // Load from NVS
    esp_err_t ret = punch_queue_load();
    if (ret == ESP_OK) {
        ESP_LOGI(TAG, "Loaded %lu punches from NVS", (unsigned long)s_queue_count);
    } else if (ret == ESP_ERR_NVS_NOT_FOUND) {
        ESP_LOGI(TAG, "No saved queue found (first boot)");
        ret = ESP_OK;
    } else {
        ESP_LOGW(TAG, "Failed to load queue from NVS: %s", esp_err_to_name(ret));
    }

    // Log pending count
    uint32_t pending = punch_queue_pending_count();
    if (pending > 0) {
        ESP_LOGW(TAG, "*** %lu punches pending sync ***", (unsigned long)pending);
    }

    return ret;
}

esp_err_t punch_queue_add(
    const char *credential_value,
    const char *credential_kind,
    const char *event_time,
    const char *device_id,
    int8_t timezone_offset
) {
    if (!credential_value || !event_time || !device_id) {
        return ESP_ERR_INVALID_ARG;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        ESP_LOGE(TAG, "Failed to acquire mutex");
        return ESP_ERR_TIMEOUT;
    }

    esp_err_t ret = ESP_OK;

    // Check if queue is full
    if (s_queue_count >= PUNCH_QUEUE_MAX_SIZE) {
        ESP_LOGE(TAG, "Queue full! Cannot add punch. Count: %lu", (unsigned long)s_queue_count);
        ret = ESP_ERR_NO_MEM;
        goto exit;
    }

    // Find empty slot or append
    uint32_t slot = s_queue_count;
    for (uint32_t i = 0; i < s_queue_count; i++) {
        if (s_queue[i].sync_status == PUNCH_STATUS_SYNCED) {
            // Reuse synced slot
            slot = i;
            break;
        }
    }

    // If no synced slot found, use next available
    if (slot == s_queue_count && s_queue_count < PUNCH_QUEUE_MAX_SIZE) {
        s_queue_count++;
    }

    // Populate punch data
    queued_punch_t *punch = &s_queue[slot];
    memset(punch, 0, sizeof(queued_punch_t));

    punch->id = generate_punch_id();
    strncpy(punch->credential_value, credential_value, sizeof(punch->credential_value) - 1);
    strncpy(punch->credential_kind, credential_kind ? credential_kind : "unknown", sizeof(punch->credential_kind) - 1);
    strncpy(punch->event_time, event_time, sizeof(punch->event_time) - 1);
    strncpy(punch->device_id, device_id, sizeof(punch->device_id) - 1);
    punch->timezone_offset = timezone_offset;
    punch->sync_status = PUNCH_STATUS_PENDING;
    punch->retry_count = 0;
    punch->last_retry = 0;

    ESP_LOGI(TAG, "Punch queued: ID=%lu, Card=%s, Time=%s",
             (unsigned long)punch->id, punch->credential_value, punch->event_time);

    // Save to NVS
    ret = punch_queue_save();
    if (ret != ESP_OK) {
        ESP_LOGW(TAG, "Failed to save queue to NVS (punch still in memory)");
        ret = ESP_OK;  // Don't fail the add operation
    }

exit:
    xSemaphoreGive(s_queue_mutex);
    return ret;
}

esp_err_t punch_queue_get_next_pending(queued_punch_t **punch) {
    if (!punch) {
        return ESP_ERR_INVALID_ARG;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return ESP_ERR_TIMEOUT;
    }

    esp_err_t ret = ESP_ERR_NOT_FOUND;
    *punch = NULL;

    // Find oldest pending punch
    uint32_t oldest_id = UINT32_MAX;
    for (uint32_t i = 0; i < s_queue_count; i++) {
        if (s_queue[i].sync_status == PUNCH_STATUS_PENDING ||
            s_queue[i].sync_status == PUNCH_STATUS_FAILED) {
            if (s_queue[i].id < oldest_id) {
                oldest_id = s_queue[i].id;
                *punch = &s_queue[i];
                ret = ESP_OK;
            }
        }
    }

    xSemaphoreGive(s_queue_mutex);
    return ret;
}

esp_err_t punch_queue_mark_synced(uint32_t id) {
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return ESP_ERR_TIMEOUT;
    }

    esp_err_t ret = ESP_ERR_NOT_FOUND;

    for (uint32_t i = 0; i < s_queue_count; i++) {
        if (s_queue[i].id == id) {
            s_queue[i].sync_status = PUNCH_STATUS_SYNCED;
            ESP_LOGI(TAG, "Punch %lu marked as synced", (unsigned long)id);
            punch_queue_save();
            ret = ESP_OK;
            break;
        }
    }

    xSemaphoreGive(s_queue_mutex);
    return ret;
}

esp_err_t punch_queue_mark_failed(uint32_t id) {
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return ESP_ERR_TIMEOUT;
    }

    esp_err_t ret = ESP_ERR_NOT_FOUND;

    for (uint32_t i = 0; i < s_queue_count; i++) {
        if (s_queue[i].id == id) {
            s_queue[i].sync_status = PUNCH_STATUS_FAILED;
            s_queue[i].retry_count++;
            s_queue[i].last_retry = (uint32_t)time(NULL);
            ESP_LOGW(TAG, "Punch %lu marked as failed (retry #%d)",
                     (unsigned long)id, s_queue[i].retry_count);
            punch_queue_save();
            ret = ESP_OK;
            break;
        }
    }

    xSemaphoreGive(s_queue_mutex);
    return ret;
}

uint32_t punch_queue_cleanup_synced(void) {
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return 0;
    }

    uint32_t removed = 0;

    // Compact the queue by removing synced entries
    uint32_t write_idx = 0;
    for (uint32_t read_idx = 0; read_idx < s_queue_count; read_idx++) {
        if (s_queue[read_idx].sync_status != PUNCH_STATUS_SYNCED) {
            if (write_idx != read_idx) {
                memcpy(&s_queue[write_idx], &s_queue[read_idx], sizeof(queued_punch_t));
            }
            write_idx++;
        } else {
            removed++;
        }
    }

    // Clear remaining slots
    for (uint32_t i = write_idx; i < s_queue_count; i++) {
        memset(&s_queue[i], 0, sizeof(queued_punch_t));
    }

    s_queue_count = write_idx;

    if (removed > 0) {
        ESP_LOGI(TAG, "Cleaned up %lu synced punches, %lu remaining",
                 (unsigned long)removed, (unsigned long)s_queue_count);
        punch_queue_save();
    }

    xSemaphoreGive(s_queue_mutex);
    return removed;
}

esp_err_t punch_queue_get_stats(punch_queue_stats_t *stats) {
    if (!stats) {
        return ESP_ERR_INVALID_ARG;
    }

    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return ESP_ERR_TIMEOUT;
    }

    memset(stats, 0, sizeof(punch_queue_stats_t));
    stats->total_count = s_queue_count;
    stats->oldest_pending = UINT32_MAX;

    for (uint32_t i = 0; i < s_queue_count; i++) {
        switch (s_queue[i].sync_status) {
            case PUNCH_STATUS_PENDING:
                stats->pending_count++;
                if (s_queue[i].id < stats->oldest_pending) {
                    stats->oldest_pending = s_queue[i].id;
                }
                break;
            case PUNCH_STATUS_SYNCED:
                stats->synced_count++;
                break;
            case PUNCH_STATUS_FAILED:
                stats->failed_count++;
                if (s_queue[i].id < stats->oldest_pending) {
                    stats->oldest_pending = s_queue[i].id;
                }
                break;
        }
    }

    if (stats->oldest_pending == UINT32_MAX) {
        stats->oldest_pending = 0;
    }

    xSemaphoreGive(s_queue_mutex);
    return ESP_OK;
}

uint32_t punch_queue_pending_count(void) {
    punch_queue_stats_t stats;
    if (punch_queue_get_stats(&stats) == ESP_OK) {
        return stats.pending_count + stats.failed_count;
    }
    return 0;
}

esp_err_t punch_queue_save(void) {
    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READWRITE, &nvs_handle);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to open NVS: %s", esp_err_to_name(ret));
        return ret;
    }

    // Save count
    ret = nvs_set_u32(nvs_handle, NVS_KEY_COUNT, s_queue_count);
    if (ret != ESP_OK) {
        ESP_LOGE(TAG, "Failed to save count: %s", esp_err_to_name(ret));
        nvs_close(nvs_handle);
        return ret;
    }

    // Save queue data
    if (s_queue_count > 0) {
        size_t data_size = s_queue_count * sizeof(queued_punch_t);
        ret = nvs_set_blob(nvs_handle, NVS_KEY_QUEUE, s_queue, data_size);
        if (ret != ESP_OK) {
            ESP_LOGE(TAG, "Failed to save queue data: %s", esp_err_to_name(ret));
            nvs_close(nvs_handle);
            return ret;
        }
    }

    ret = nvs_commit(nvs_handle);
    nvs_close(nvs_handle);

    if (ret == ESP_OK) {
        ESP_LOGD(TAG, "Queue saved to NVS (%lu punches)", (unsigned long)s_queue_count);
    }

    return ret;
}

esp_err_t punch_queue_load(void) {
    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READONLY, &nvs_handle);
    if (ret != ESP_OK) {
        return ret;
    }

    // Load count
    uint32_t count = 0;
    ret = nvs_get_u32(nvs_handle, NVS_KEY_COUNT, &count);
    if (ret != ESP_OK) {
        nvs_close(nvs_handle);
        return ret;
    }

    if (count > PUNCH_QUEUE_MAX_SIZE) {
        ESP_LOGW(TAG, "Stored count (%lu) exceeds max, capping to %d",
                 (unsigned long)count, PUNCH_QUEUE_MAX_SIZE);
        count = PUNCH_QUEUE_MAX_SIZE;
    }

    // Load queue data
    if (count > 0) {
        size_t data_size = count * sizeof(queued_punch_t);
        ret = nvs_get_blob(nvs_handle, NVS_KEY_QUEUE, s_queue, &data_size);
        if (ret != ESP_OK) {
            ESP_LOGE(TAG, "Failed to load queue data: %s", esp_err_to_name(ret));
            nvs_close(nvs_handle);
            return ret;
        }
    }

    s_queue_count = count;
    nvs_close(nvs_handle);

    return ESP_OK;
}

esp_err_t punch_queue_clear(void) {
    if (xSemaphoreTake(s_queue_mutex, pdMS_TO_TICKS(1000)) != pdTRUE) {
        return ESP_ERR_TIMEOUT;
    }

    memset(s_queue, 0, sizeof(s_queue));
    s_queue_count = 0;

    // Clear NVS
    nvs_handle_t nvs_handle;
    esp_err_t ret = nvs_open(NVS_NAMESPACE, NVS_READWRITE, &nvs_handle);
    if (ret == ESP_OK) {
        nvs_erase_all(nvs_handle);
        nvs_commit(nvs_handle);
        nvs_close(nvs_handle);
    }

    ESP_LOGW(TAG, "Queue cleared");

    xSemaphoreGive(s_queue_mutex);
    return ESP_OK;
}

esp_err_t punch_queue_start_sync_task(uint32_t sync_interval_ms) {
    if (s_sync_task_running) {
        ESP_LOGW(TAG, "Sync task already running");
        return ESP_OK;
    }

    s_sync_interval_ms = sync_interval_ms > 0 ? sync_interval_ms : 30000;
    s_sync_task_running = true;

    BaseType_t result = xTaskCreate(
        sync_task,
        "punch_sync",
        4096,
        NULL,
        5,  // Priority
        &s_sync_task_handle
    );

    if (result != pdPASS) {
        s_sync_task_running = false;
        ESP_LOGE(TAG, "Failed to create sync task");
        return ESP_FAIL;
    }

    ESP_LOGI(TAG, "Sync task started (interval: %lu ms)", (unsigned long)s_sync_interval_ms);
    return ESP_OK;
}

void punch_queue_stop_sync_task(void) {
    if (s_sync_task_running && s_sync_task_handle != NULL) {
        s_sync_task_running = false;
        // Task will exit on next iteration
        ESP_LOGI(TAG, "Sync task stop requested");
    }
}

void punch_queue_trigger_sync(void) {
    if (s_sync_task_handle != NULL) {
        xTaskNotifyGive(s_sync_task_handle);
        ESP_LOGI(TAG, "Sync triggered");
    }
}

// Background sync task
static void sync_task(void *pvParameters) {
    ESP_LOGI(TAG, "Sync task started");

    while (s_sync_task_running) {
        // Wait for interval or notification
        ulTaskNotifyTake(pdTRUE, pdMS_TO_TICKS(s_sync_interval_ms));

        if (!s_sync_task_running) {
            break;
        }

        uint32_t pending = punch_queue_pending_count();

        // Periodic health check even if no pending punches
        // This allows detecting when server comes back online
        if (pending == 0) {
            // Do a health check if we think server is offline
            if (!s_server_online) {
                api_config_t *cfg = api_get_config();
                if (cfg->is_registered && api_health_check() == ESP_OK) {
                    s_server_online = true;
                    ui_set_clock_status(CLOCK_STATUS_OK, NULL);
                    ESP_LOGI(TAG, "Server back online (health check) - status cleared");
                }
            }
            continue;
        }

        ESP_LOGI(TAG, "Syncing %lu pending punches...", (unsigned long)pending);

        // Check if API is available
        api_config_t *api_cfg = api_get_config();
        if (!api_cfg->is_registered) {
            ESP_LOGW(TAG, "Device not registered, skipping sync");
            continue;
        }

        // Process pending punches
        queued_punch_t *punch = NULL;
        uint32_t synced = 0;
        uint32_t failed = 0;

        while (punch_queue_get_next_pending(&punch) == ESP_OK && punch != NULL) {
            // Build punch data for API
            punch_data_t punch_data = {0};
            strncpy(punch_data.device_id, punch->device_id, sizeof(punch_data.device_id) - 1);
            strncpy(punch_data.credential_value, punch->credential_value, sizeof(punch_data.credential_value) - 1);
            strncpy(punch_data.credential_kind, punch->credential_kind, sizeof(punch_data.credential_kind) - 1);
            strncpy(punch_data.event_time, punch->event_time, sizeof(punch_data.event_time) - 1);
            strncpy(punch_data.event_type, "unknown", sizeof(punch_data.event_type) - 1);
            punch_data.confidence = 100;
            punch_data.timezone_offset = punch->timezone_offset;

            ESP_LOGI(TAG, "Syncing punch ID=%lu: %s @ %s",
                     (unsigned long)punch->id, punch->credential_value, punch->event_time);

            // Send to API
            esp_err_t punch_result = api_send_punch(&punch_data);

            if (punch_result == ESP_OK) {
                punch_queue_mark_synced(punch->id);
                synced++;

                // Server is reachable and clock is authorized - clear any status
                if (!s_server_online) {
                    s_server_online = true;
                }
                ui_set_clock_status(CLOCK_STATUS_OK, NULL);
                ESP_LOGI(TAG, "Punch synced successfully - status cleared");
            } else {
                punch_queue_mark_failed(punch->id);
                failed++;

                // Handle different error types with specific UI messages
                if (punch_result == ESP_ERR_TIMEOUT) {
                    // Server completely unreachable (network error)
                    if (s_server_online) {
                        s_server_online = false;
                        ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
                        ESP_LOGW(TAG, "Server offline - showing 'Server Offline' alert");
                    }
                } else if (punch_result == ESP_ERR_NOT_FOUND) {
                    // Device was deleted from server (404/401)
                    s_server_online = true;  // Server IS reachable, just device gone
                    api_clear_registration();
                    ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
                    ESP_LOGW(TAG, "Device deleted - showing 'Not Registered' alert");
                    // Stop trying - device needs to re-register
                    break;
                } else if (punch_result == ESP_ERR_INVALID_STATE) {
                    // Device not authorized (403 - pending/rejected/suspended)
                    s_server_online = true;  // Server IS reachable
                    api_config_t *cfg = api_get_config();
                    ui_set_clock_status(CLOCK_STATUS_NOT_AUTHORIZED, cfg->device_name);
                    ESP_LOGW(TAG, "Device not authorized - showing 'Not Authorized' alert");
                    // Don't clear registration, just stop syncing
                    break;
                } else {
                    // Generic failure - treat as server issue
                    if (s_server_online) {
                        s_server_online = false;
                        ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
                        ESP_LOGW(TAG, "Unknown error - showing 'Server Offline' alert");
                    }
                }

                // If too many failures, back off
                if (failed >= 3) {
                    ESP_LOGW(TAG, "Too many failures, backing off");
                    break;
                }
            }

            // Small delay between punches
            vTaskDelay(pdMS_TO_TICKS(100));
        }

        // Cleanup synced punches
        if (synced > 0) {
            punch_queue_cleanup_synced();
            ESP_LOGI(TAG, "Sync complete: %lu synced, %lu failed",
                     (unsigned long)synced, (unsigned long)failed);
        }
    }

    ESP_LOGI(TAG, "Sync task exiting");
    s_sync_task_handle = NULL;
    vTaskDelete(NULL);
}

// Generate unique punch ID (timestamp-based)
static uint32_t generate_punch_id(void) {
    static uint32_t s_last_id = 0;
    uint32_t id = (uint32_t)time(NULL);

    // Ensure uniqueness if multiple punches in same second
    if (id <= s_last_id) {
        id = s_last_id + 1;
    }
    s_last_id = id;

    return id;
}

// Check if server is online
bool punch_queue_is_server_online(void) {
    return s_server_online;
}

// Set server status and update UI
void punch_queue_set_server_status(bool online) {
    if (online != s_server_online) {
        s_server_online = online;
        if (online) {
            // Only clear if current status is server offline
            // Don't clear NOT_REGISTERED or NOT_AUTHORIZED statuses
            clock_status_t current = ui_get_clock_status();
            if (current == CLOCK_STATUS_SERVER_OFFLINE) {
                ui_set_clock_status(CLOCK_STATUS_OK, NULL);
                ESP_LOGI(TAG, "Server marked online - status cleared");
            }
        } else {
            ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
            ESP_LOGW(TAG, "Server marked offline - showing 'Server Offline' alert");
        }
    }
}
