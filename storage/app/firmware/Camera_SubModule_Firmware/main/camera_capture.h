/**
 * Camera Capture Module
 * Handles OV5640 camera initialization and image capture
 */

#ifndef CAMERA_CAPTURE_H
#define CAMERA_CAPTURE_H

#include <stdint.h>
#include <stdbool.h>
#include "esp_err.h"
#include "protocol.h"

// ---------------------------------------------------------------------------
// XIAO ESP32-S3 Sense Camera Pin Definitions
// These are specific to the Seeed XIAO ESP32-S3 Sense board
// ---------------------------------------------------------------------------

// Camera data pins (directly connected to camera FPC)
#define CAM_PIN_PWDN    -1      // Power down (not connected on XIAO)
#define CAM_PIN_RESET   -1      // Reset (not connected on XIAO)
#define CAM_PIN_XCLK    10      // External clock
#define CAM_PIN_SIOD    40      // SCCB Data (I2C SDA)
#define CAM_PIN_SIOC    39      // SCCB Clock (I2C SCL)

#define CAM_PIN_D7      48      // Data bit 7
#define CAM_PIN_D6      11      // Data bit 6
#define CAM_PIN_D5      12      // Data bit 5
#define CAM_PIN_D4      14      // Data bit 4
#define CAM_PIN_D3      16      // Data bit 3
#define CAM_PIN_D2      18      // Data bit 2
#define CAM_PIN_D1      17      // Data bit 1
#define CAM_PIN_D0      15      // Data bit 0

#define CAM_PIN_VSYNC   38      // Vertical sync
#define CAM_PIN_HREF    47      // Horizontal reference
#define CAM_PIN_PCLK    13      // Pixel clock

// ---------------------------------------------------------------------------
// Camera configuration defaults
// ---------------------------------------------------------------------------
#define DEFAULT_RESOLUTION  RES_VGA     // 640x480
#define DEFAULT_QUALITY     20          // JPEG quality (1-63, lower = better)
#define DEFAULT_FB_COUNT    2           // Double buffer

// ---------------------------------------------------------------------------
// Camera state
// ---------------------------------------------------------------------------
typedef struct {
    bool initialized;
    camera_resolution_t resolution;
    uint8_t quality;            // JPEG quality (1-63)
    int8_t brightness;          // -2 to +2
    int8_t contrast;            // -2 to +2

    // Current capture state
    uint8_t *image_buffer;      // Pointer to captured JPEG data
    uint32_t image_size;        // Size of captured image
    uint16_t image_width;
    uint16_t image_height;
    bool image_ready;           // True when image is ready for transfer
} camera_state_t;

// ---------------------------------------------------------------------------
// Function prototypes
// ---------------------------------------------------------------------------

/**
 * Initialize the camera hardware
 * @return ESP_OK on success, error code otherwise
 */
esp_err_t camera_init(void);

/**
 * Deinitialize the camera hardware
 * @return ESP_OK on success
 */
esp_err_t camera_deinit(void);

/**
 * Check if camera is initialized
 * @return true if initialized
 */
bool camera_is_initialized(void);

/**
 * Capture a photo
 * Captures a JPEG image and stores it in the internal buffer
 * @return ESP_OK on success, error code otherwise
 */
esp_err_t camera_capture(void);

/**
 * Get pointer to captured image data
 * @param[out] size Pointer to store image size
 * @return Pointer to image buffer, or NULL if no image
 */
const uint8_t* camera_get_image(uint32_t *size);

/**
 * Get image information
 * @param[out] info Pointer to store image info
 * @return ESP_OK on success
 */
esp_err_t camera_get_info(spi_image_info_t *info);

/**
 * Check if image is ready for transfer
 * @return true if image is ready
 */
bool camera_image_ready(void);

/**
 * Clear the current image buffer
 */
void camera_clear_image(void);

/**
 * Set camera resolution
 * @param resolution New resolution setting
 * @return ESP_OK on success
 */
esp_err_t camera_set_resolution(camera_resolution_t resolution);

/**
 * Set JPEG quality
 * @param quality Quality value (1-100, higher = better)
 * @return ESP_OK on success
 */
esp_err_t camera_set_quality(uint8_t quality);

/**
 * Set brightness
 * @param brightness Brightness value (-2 to +2)
 * @return ESP_OK on success
 */
esp_err_t camera_set_brightness(int8_t brightness);

/**
 * Set contrast
 * @param contrast Contrast value (-2 to +2)
 * @return ESP_OK on success
 */
esp_err_t camera_set_contrast(int8_t contrast);

/**
 * Get current camera configuration
 * @param[out] config Pointer to store configuration
 * @return ESP_OK on success
 */
esp_err_t camera_get_config(spi_config_info_t *config);

/**
 * Get camera state (for debugging)
 * @return Pointer to camera state
 */
const camera_state_t* camera_get_state(void);

#endif // CAMERA_CAPTURE_H
