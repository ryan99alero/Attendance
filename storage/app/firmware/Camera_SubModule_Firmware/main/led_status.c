/**
 * LED Status Indicator Implementation
 */

#include "led_status.h"
#include "driver/gpio.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_log.h"

static const char *TAG = "LED";

static led_mode_t g_led_mode = LED_MODE_OFF;
static bool g_led_state = false;

// Blink timing (milliseconds)
#define BLINK_SLOW          1000    // Idle: 1 second cycle
#define BLINK_FAST          200     // Capturing: 200ms cycle
#define BLINK_VERY_FAST     50      // Transferring: 50ms cycle
#define BLINK_ERROR_ON      100     // Error: double blink
#define BLINK_ERROR_OFF     200
#define BLINK_ERROR_PAUSE   800

esp_err_t led_init(void) {
    gpio_config_t led_conf = {
        .pin_bit_mask = (1ULL << LED_PIN),
        .mode = GPIO_MODE_OUTPUT,
        .pull_up_en = GPIO_PULLUP_DISABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };

    esp_err_t err = gpio_config(&led_conf);
    if (err == ESP_OK) {
        gpio_set_level(LED_PIN, 0);
        ESP_LOGI(TAG, "LED initialized on GPIO%d", LED_PIN);
    }

    return err;
}

void led_set_mode(led_mode_t mode) {
    g_led_mode = mode;
    ESP_LOGD(TAG, "LED mode: %d", mode);
}

led_mode_t led_get_mode(void) {
    return g_led_mode;
}

void led_set(bool on) {
    g_led_state = on;
    gpio_set_level(LED_PIN, on ? 1 : 0);
}

void led_task(void *pvParameters) {
    ESP_LOGI(TAG, "LED task started");

    int error_blink_count = 0;

    while (1) {
        switch (g_led_mode) {
            case LED_MODE_OFF:
                led_set(false);
                vTaskDelay(pdMS_TO_TICKS(100));
                break;

            case LED_MODE_IDLE:
                // Slow breathing blink
                led_set(true);
                vTaskDelay(pdMS_TO_TICKS(BLINK_SLOW / 2));
                led_set(false);
                vTaskDelay(pdMS_TO_TICKS(BLINK_SLOW / 2));
                break;

            case LED_MODE_CAPTURING:
                // Fast blink while capturing
                led_set(!g_led_state);
                vTaskDelay(pdMS_TO_TICKS(BLINK_FAST / 2));
                break;

            case LED_MODE_READY:
                // Solid on when image ready
                led_set(true);
                vTaskDelay(pdMS_TO_TICKS(100));
                break;

            case LED_MODE_TRANSFERRING:
                // Very fast blink during transfer
                led_set(!g_led_state);
                vTaskDelay(pdMS_TO_TICKS(BLINK_VERY_FAST / 2));
                break;

            case LED_MODE_ERROR:
                // Double blink pattern
                if (error_blink_count < 2) {
                    led_set(true);
                    vTaskDelay(pdMS_TO_TICKS(BLINK_ERROR_ON));
                    led_set(false);
                    vTaskDelay(pdMS_TO_TICKS(BLINK_ERROR_OFF));
                    error_blink_count++;
                } else {
                    vTaskDelay(pdMS_TO_TICKS(BLINK_ERROR_PAUSE));
                    error_blink_count = 0;
                }
                break;

            default:
                vTaskDelay(pdMS_TO_TICKS(100));
                break;
        }
    }
}
