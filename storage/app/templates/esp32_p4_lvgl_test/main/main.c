/**
 * ESP32-P4 LVGL SquareLine Studio Test
 * Minimal firmware to test SquareLine Studio UI export
 */

#include <stdio.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_log.h"
#include "esp_err.h"
#include "bsp/esp32_p4_function_ev_board.h"
#include "bsp/display.h"
#include "lvgl.h"
#include "ui.h"

static const char *TAG = "LVGL_TEST";

void app_main(void)
{
    ESP_LOGI(TAG, "Starting ESP32-P4 LVGL SquareLine Studio Test");

    // Initialize BSP (display, touch, etc.)
    ESP_LOGI(TAG, "Initializing BSP...");
    lv_display_t *disp = bsp_display_start();
    if (disp == NULL) {
        ESP_LOGE(TAG, "Failed to initialize BSP display");
        return;
    }
    ESP_LOGI(TAG, "BSP initialized");

    // Turn on display backlight
    ESP_LOGI(TAG, "Turning on display backlight");
    ESP_ERROR_CHECK(bsp_display_backlight_on());

    // Lock LVGL mutex before UI operations
    bsp_display_lock(0);

    // Initialize SquareLine Studio UI
    ESP_LOGI(TAG, "Initializing SquareLine Studio UI...");
    ui_init();
    ESP_LOGI(TAG, "UI initialized");

    // Unlock LVGL mutex
    bsp_display_unlock();

    ESP_LOGI(TAG, "LVGL test running. UI should be visible on display.");

    // Main loop - just keep the app running
    while (1) {
        vTaskDelay(pdMS_TO_TICKS(1000));
    }
}
