/**
 * Punch Queue - Offline buffering for time clock punches
 *
 * Stores punches locally when server is unavailable and syncs when connection restored.
 * Uses NVS for persistent storage across reboots.
 */

#ifndef PUNCH_QUEUE_H
#define PUNCH_QUEUE_H

#include "esp_err.h"
#include <stdint.h>
#include <stdbool.h>

#ifdef __cplusplus
extern "C" {
#endif

// Maximum number of punches to buffer
#define PUNCH_QUEUE_MAX_SIZE 100

// Sync status
typedef enum {
    PUNCH_STATUS_PENDING = 0,   // Not yet sent to server
    PUNCH_STATUS_SYNCED = 1,    // Successfully sent to server
    PUNCH_STATUS_FAILED = 2,    // Failed to send (will retry)
} punch_sync_status_t;

// Buffered punch record
typedef struct {
    uint32_t id;                    // Unique ID (timestamp-based)
    char credential_value[64];      // Card UID (normalized, no colons)
    char credential_kind[32];       // "MIFARE Ultralight", "NFC", etc.
    char event_time[32];            // ISO 8601: "2026-01-29T23:26:28"
    char device_id[64];             // Device identifier
    int8_t timezone_offset;         // Hours from UTC (-12 to +14)
    uint8_t sync_status;            // punch_sync_status_t
    uint8_t retry_count;            // Number of sync attempts
    uint32_t last_retry;            // Timestamp of last retry attempt
} queued_punch_t;

// Queue statistics
typedef struct {
    uint32_t total_count;           // Total punches in queue
    uint32_t pending_count;         // Punches waiting to sync
    uint32_t synced_count;          // Successfully synced (to be cleaned)
    uint32_t failed_count;          // Failed attempts
    uint32_t oldest_pending;        // Timestamp of oldest pending punch
} punch_queue_stats_t;

/**
 * Initialize the punch queue system
 * Must be called after NVS is initialized
 * @return ESP_OK on success
 */
esp_err_t punch_queue_init(void);

/**
 * Add a punch to the queue
 * @param credential_value Card UID
 * @param credential_kind Card type (e.g., "MIFARE Ultralight")
 * @param event_time ISO 8601 timestamp from device clock
 * @param device_id Device identifier
 * @param timezone_offset Hours from UTC
 * @return ESP_OK on success, ESP_ERR_NO_MEM if queue is full
 */
esp_err_t punch_queue_add(
    const char *credential_value,
    const char *credential_kind,
    const char *event_time,
    const char *device_id,
    int8_t timezone_offset
);

/**
 * Get the next pending punch to sync
 * @param punch Output: punch data (caller must not free)
 * @return ESP_OK if punch found, ESP_ERR_NOT_FOUND if no pending punches
 */
esp_err_t punch_queue_get_next_pending(queued_punch_t **punch);

/**
 * Mark a punch as synced (will be removed on next cleanup)
 * @param id Punch ID
 * @return ESP_OK on success
 */
esp_err_t punch_queue_mark_synced(uint32_t id);

/**
 * Mark a punch as failed (will be retried)
 * @param id Punch ID
 * @return ESP_OK on success
 */
esp_err_t punch_queue_mark_failed(uint32_t id);

/**
 * Remove synced punches from queue (cleanup)
 * @return Number of punches removed
 */
uint32_t punch_queue_cleanup_synced(void);

/**
 * Get queue statistics
 * @param stats Output: queue statistics
 * @return ESP_OK on success
 */
esp_err_t punch_queue_get_stats(punch_queue_stats_t *stats);

/**
 * Get count of pending punches
 * @return Number of punches waiting to sync
 */
uint32_t punch_queue_pending_count(void);

/**
 * Save queue to NVS (persist across reboot)
 * Called automatically on add/update, but can be called manually
 * @return ESP_OK on success
 */
esp_err_t punch_queue_save(void);

/**
 * Load queue from NVS (called during init)
 * @return ESP_OK on success
 */
esp_err_t punch_queue_load(void);

/**
 * Clear all punches from queue (use with caution!)
 * @return ESP_OK on success
 */
esp_err_t punch_queue_clear(void);

/**
 * Start background sync task
 * Periodically attempts to sync pending punches
 * @param sync_interval_ms Interval between sync attempts (default: 30000ms)
 * @return ESP_OK on success
 */
esp_err_t punch_queue_start_sync_task(uint32_t sync_interval_ms);

/**
 * Stop background sync task
 */
void punch_queue_stop_sync_task(void);

/**
 * Trigger immediate sync attempt (non-blocking)
 * Useful when network connection is restored
 */
void punch_queue_trigger_sync(void);

/**
 * Check if server is currently reachable
 * @return true if last communication was successful
 */
bool punch_queue_is_server_online(void);

/**
 * Set server online/offline status and update UI alert
 * @param online true if server is reachable
 */
void punch_queue_set_server_status(bool online);

#ifdef __cplusplus
}
#endif

#endif // PUNCH_QUEUE_H
