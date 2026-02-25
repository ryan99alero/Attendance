/**
 * LED Status Indicator
 * Uses onboard LED for status indication
 */

#ifndef LED_STATUS_H
#define LED_STATUS_H

#include <stdbool.h>
#include "esp_err.h"

// XIAO ESP32-S3 Sense onboard LED
#define LED_PIN     21      // Onboard LED GPIO

// LED modes
typedef enum {
    LED_MODE_OFF,           // LED off
    LED_MODE_IDLE,          // Slow blink - ready and waiting
    LED_MODE_CAPTURING,     // Fast blink - capturing photo
    LED_MODE_READY,         // Solid on - image ready
    LED_MODE_TRANSFERRING,  // Very fast blink - transferring
    LED_MODE_ERROR,         // Double blink pattern - error state
} led_mode_t;

/**
 * Initialize LED GPIO
 * @return ESP_OK on success
 */
esp_err_t led_init(void);

/**
 * Set LED mode
 * @param mode LED display mode
 */
void led_set_mode(led_mode_t mode);

/**
 * Get current LED mode
 * @return Current mode
 */
led_mode_t led_get_mode(void);

/**
 * LED task - runs the blink patterns
 * Call from a dedicated FreeRTOS task
 */
void led_task(void *pvParameters);

/**
 * Turn LED on/off directly (bypasses mode)
 * @param on true = LED on
 */
void led_set(bool on);

#endif // LED_STATUS_H
