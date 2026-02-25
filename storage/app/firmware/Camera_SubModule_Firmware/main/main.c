/**
 * ESP32-S3 Camera SubModule Firmware
 * Main entry point
 *
 * XIAO ESP32-S3 Sense with OV5640 camera
 * SPI slave device for Time Clock P4 Main Controller
 */

#include <stdio.h>
#include <string.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_system.h"
#include "esp_log.h"
#include "nvs_flash.h"

#include "firmware_info.h"
#include "protocol.h"
#include "camera_capture.h"
#include "spi_slave_handler.h"
#include "led_status.h"

static const char *TAG = "MAIN";

// Task stack sizes
#define SPI_TASK_STACK_SIZE     4096
#define LED_TASK_STACK_SIZE     2048

// Task priorities
#define SPI_TASK_PRIORITY       10
#define LED_TASK_PRIORITY       5

// Task handles
static TaskHandle_t spi_task_handle = NULL;
static TaskHandle_t led_task_handle = NULL;

/**
 * Print startup banner
 */
static void print_banner(void) {
    ESP_LOGI(TAG, "=====================================");
    ESP_LOGI(TAG, "  ESP32-S3 Camera SubModule");
    ESP_LOGI(TAG, "  Device: %s", DEVICE_MODEL);
    ESP_LOGI(TAG, "  Firmware: %s", FIRMWARE_VERSION_FULL);
    ESP_LOGI(TAG, "  Protocol: v%d.%d", PROTOCOL_VERSION_MAJOR, PROTOCOL_VERSION_MINOR);
    ESP_LOGI(TAG, "=====================================");
}

/**
 * Print system info
 */
static void print_system_info(void) {
    ESP_LOGI(TAG, "System Info:");
    ESP_LOGI(TAG, "  Chip: %s", CONFIG_IDF_TARGET);
    ESP_LOGI(TAG, "  Free heap: %lu bytes", (unsigned long)esp_get_free_heap_size());

    // Check PSRAM
    size_t psram_size = heap_caps_get_total_size(MALLOC_CAP_SPIRAM);
    if (psram_size > 0) {
        ESP_LOGI(TAG, "  PSRAM: %lu bytes", (unsigned long)psram_size);
        ESP_LOGI(TAG, "  PSRAM free: %lu bytes",
                 (unsigned long)heap_caps_get_free_size(MALLOC_CAP_SPIRAM));
    } else {
        ESP_LOGW(TAG, "  PSRAM: Not available");
    }
}

/**
 * Initialize NVS
 */
static esp_err_t init_nvs(void) {
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_LOGW(TAG, "NVS partition was truncated, erasing...");
        ESP_ERROR_CHECK(nvs_flash_erase());
        err = nvs_flash_init();
    }
    return err;
}

/**
 * Create FreeRTOS tasks
 */
static void create_tasks(void) {
    // Create SPI slave task (high priority)
    BaseType_t ret = xTaskCreate(
        spi_slave_task,
        "spi_slave",
        SPI_TASK_STACK_SIZE,
        NULL,
        SPI_TASK_PRIORITY,
        &spi_task_handle
    );

    if (ret != pdPASS) {
        ESP_LOGE(TAG, "Failed to create SPI task");
    } else {
        ESP_LOGI(TAG, "SPI task created");
    }

    // Create LED task (lower priority)
    ret = xTaskCreate(
        led_task,
        "led_status",
        LED_TASK_STACK_SIZE,
        NULL,
        LED_TASK_PRIORITY,
        &led_task_handle
    );

    if (ret != pdPASS) {
        ESP_LOGE(TAG, "Failed to create LED task");
    } else {
        ESP_LOGI(TAG, "LED task created");
    }
}

/**
 * Main application entry point
 */
void app_main(void) {
    // Print startup banner
    print_banner();

    // Initialize NVS
    ESP_ERROR_CHECK(init_nvs());
    ESP_LOGI(TAG, "NVS initialized");

    // Print system info
    print_system_info();

    // Initialize LED
    ESP_ERROR_CHECK(led_init());
    led_set_mode(LED_MODE_CAPTURING);  // Show we're starting up

    // Initialize camera
    ESP_LOGI(TAG, "Initializing camera...");
    esp_err_t err = camera_init();
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Camera initialization failed!");
        led_set_mode(LED_MODE_ERROR);
        // Continue anyway - SPI slave should still work
    } else {
        ESP_LOGI(TAG, "Camera ready");
    }

    // Initialize SPI slave
    ESP_LOGI(TAG, "Initializing SPI slave...");
    err = spi_slave_init();
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "SPI slave initialization failed!");
        led_set_mode(LED_MODE_ERROR);
        // This is fatal - we can't communicate without SPI
        while (1) {
            vTaskDelay(pdMS_TO_TICKS(1000));
        }
    }
    ESP_LOGI(TAG, "SPI slave ready");

    // Create tasks
    create_tasks();

    // Set LED to idle - ready for commands
    led_set_mode(LED_MODE_IDLE);

    ESP_LOGI(TAG, "=====================================");
    ESP_LOGI(TAG, "  Camera module ready");
    ESP_LOGI(TAG, "  Waiting for commands from P4...");
    ESP_LOGI(TAG, "=====================================");

    // Main loop - just monitor system health
    while (1) {
        // Log stats periodically
        const spi_slave_state_t *spi_state = spi_get_state();
        const camera_state_t *cam_state = camera_get_state();

        ESP_LOGD(TAG, "Stats: cmds=%lu, bytes=%lu, errors=%lu, heap=%lu",
                 (unsigned long)spi_state->commands_received,
                 (unsigned long)spi_state->bytes_transferred,
                 (unsigned long)spi_state->errors,
                 (unsigned long)esp_get_free_heap_size());

        // Sleep for 10 seconds
        vTaskDelay(pdMS_TO_TICKS(10000));
    }
}
