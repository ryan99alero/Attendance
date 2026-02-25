/**
 * Camera Interface
 * SPI Master interface to communicate with ESP32-S3 Camera SubModule
 */

#ifndef CAMERA_INTERFACE_H
#define CAMERA_INTERFACE_H

#include <stdint.h>
#include <stdbool.h>
#include "esp_err.h"
#include "camera_protocol.h"

// ---------------------------------------------------------------------------
// ESP32-P4 SPI Master Pin Configuration for Camera Module
// Must not conflict with display, NFC, WiFi SDIO, or Ethernet
// ---------------------------------------------------------------------------

// ESP32-P4-Function-EV-Board v1.5.2 Pin Usage:
// - I2C (Touch/Audio): GPIO 7, 8
// - Audio I2S: GPIO 9-13
// - WiFi SDIO (to ESP32-C6): GPIO 14-19, 54 (OCCUPIED)
// - USB: GPIO 19, 20
// - NFC RYRR30D UART: GPIO 21 (RX), 22 (TX), 32 (RST)
// - Display: GPIO 23 (backlight), 26, 27
// - Buzzer: GPIO 25
// - Ethernet RMII: GPIO 31 (MDC), 50 (CLK), 51 (RST), 52 (MDIO)
// - SD Card: GPIO 39-44
// - Status LEDs: GPIO 45, 46, 48
// - Power Amp: GPIO 53
// - Bootloader (AVOID): GPIO 35, 36
// - Console UART (AVOID): GPIO 37, 38
//
// Available for Camera SPI: GPIO 24, 28, 29, 30, 33, 34

#define CAM_SPI_MOSI_PIN    24   // Master Out Slave In
#define CAM_SPI_MISO_PIN    28   // Master In Slave Out
#define CAM_SPI_SCLK_PIN    29   // Serial Clock
#define CAM_SPI_CS_PIN      30   // Chip Select
#define CAM_READY_PIN       33   // Input from S3 READY pin (interrupt capable)

// ---------------------------------------------------------------------------
// SPI Configuration
// ---------------------------------------------------------------------------
#define CAM_SPI_HOST        SPI3_HOST   // Use SPI3 (separate from display/NFC)
#define CAM_SPI_CLOCK_HZ    10000000    // 10 MHz
#define CAM_SPI_MODE        0           // CPOL=0, CPHA=0

// ---------------------------------------------------------------------------
// Timeouts (milliseconds)
// ---------------------------------------------------------------------------
#define CAM_TIMEOUT_CAPTURE_MS      5000
#define CAM_TIMEOUT_TRANSFER_MS     10000
#define CAM_TIMEOUT_COMMAND_MS      1000

// ---------------------------------------------------------------------------
// Camera module state
// ---------------------------------------------------------------------------
typedef struct {
    bool initialized;
    bool connected;
    spi_status_t last_status;
    spi_error_t last_error;

    // Last image info
    uint32_t image_size;
    uint16_t image_width;
    uint16_t image_height;

    // Statistics
    uint32_t captures_requested;
    uint32_t captures_completed;
    uint32_t transfer_errors;
} camera_module_state_t;

// ---------------------------------------------------------------------------
// Function prototypes
// ---------------------------------------------------------------------------

/**
 * Initialize camera interface (SPI master)
 * @return ESP_OK on success
 */
esp_err_t camera_interface_init(void);

/**
 * Deinitialize camera interface
 * @return ESP_OK on success
 */
esp_err_t camera_interface_deinit(void);

/**
 * Check if camera module is connected and responding
 * @return true if connected
 */
bool camera_is_connected(void);

/**
 * Ping camera module to check connectivity
 * @return ESP_OK if camera responds
 */
esp_err_t camera_ping(void);

/**
 * Get camera module status
 * @param[out] status Current status
 * @return ESP_OK on success
 */
esp_err_t camera_get_status(spi_status_t *status);

/**
 * Trigger photo capture
 * Non-blocking - use camera_wait_ready() or check READY pin
 * @return ESP_OK if command sent successfully
 */
esp_err_t camera_capture_photo(void);

/**
 * Wait for camera to be ready (image captured)
 * @param timeout_ms Maximum time to wait
 * @return ESP_OK if ready, ESP_ERR_TIMEOUT if timeout
 */
esp_err_t camera_wait_ready(uint32_t timeout_ms);

/**
 * Check if camera has image ready (non-blocking)
 * @return true if image ready for transfer
 */
bool camera_image_ready(void);

/**
 * Get image information
 * @param[out] info Image information
 * @return ESP_OK on success
 */
esp_err_t camera_get_image_info(spi_image_info_t *info);

/**
 * Retrieve image data
 * Transfers the complete image from camera module
 * @param[out] buffer Buffer to store image (must be large enough)
 * @param buffer_size Size of buffer
 * @param[out] image_size Actual image size transferred
 * @return ESP_OK on success
 */
esp_err_t camera_get_image(uint8_t *buffer, uint32_t buffer_size, uint32_t *image_size);

/**
 * Capture photo and retrieve image (blocking)
 * Combines capture_photo, wait_ready, get_image_info, and get_image
 * @param[out] buffer Buffer to store JPEG image
 * @param buffer_size Size of buffer
 * @param[out] image_size Actual image size
 * @return ESP_OK on success
 */
esp_err_t camera_capture_and_get(uint8_t *buffer, uint32_t buffer_size, uint32_t *image_size);

/**
 * Set camera resolution
 * @param resolution Resolution setting
 * @return ESP_OK on success
 */
esp_err_t camera_set_resolution(camera_resolution_t resolution);

/**
 * Set JPEG quality
 * @param quality Quality 1-100 (higher = better)
 * @return ESP_OK on success
 */
esp_err_t camera_set_quality(uint8_t quality);

/**
 * Get camera configuration
 * @param[out] config Configuration info
 * @return ESP_OK on success
 */
esp_err_t camera_get_config(spi_config_info_t *config);

/**
 * Reset camera module
 * @return ESP_OK on success
 */
esp_err_t camera_reset(void);

/**
 * Get camera module state
 * @return Pointer to state structure
 */
const camera_module_state_t* camera_get_state(void);

/**
 * READY pin interrupt handler
 * Call this from GPIO ISR when READY pin changes
 */
void camera_ready_isr_handler(void);

#endif // CAMERA_INTERFACE_H
