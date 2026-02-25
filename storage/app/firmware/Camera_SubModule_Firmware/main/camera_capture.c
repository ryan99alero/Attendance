/**
 * Camera Capture Module Implementation
 * Handles OV5640 camera on XIAO ESP32-S3 Sense
 */

#include "camera_capture.h"
#include "firmware_info.h"
#include "esp_camera.h"
#include "esp_log.h"
#include <string.h>

static const char *TAG = "CAM";

// Global camera state
static camera_state_t g_camera_state = {
    .initialized = false,
    .resolution = DEFAULT_RESOLUTION,
    .quality = DEFAULT_QUALITY,
    .brightness = 0,
    .contrast = 0,
    .image_buffer = NULL,
    .image_size = 0,
    .image_width = 0,
    .image_height = 0,
    .image_ready = false,
};

// Frame size mapping
static const framesize_t resolution_map[] = {
    [RES_QQVGA] = FRAMESIZE_QQVGA,  // 160x120
    [RES_QVGA]  = FRAMESIZE_QVGA,   // 320x240
    [RES_VGA]   = FRAMESIZE_VGA,    // 640x480
    [RES_SVGA]  = FRAMESIZE_SVGA,   // 800x600
    [RES_XGA]   = FRAMESIZE_XGA,    // 1024x768
    [RES_SXGA]  = FRAMESIZE_SXGA,   // 1280x1024
    [RES_UXGA]  = FRAMESIZE_UXGA,   // 1600x1200
    [RES_QXGA]  = FRAMESIZE_QXGA,   // 2048x1536
    [RES_5MP]   = FRAMESIZE_QSXGA,  // 2592x1944
};

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

static uint16_t calculate_crc16(const uint8_t *data, uint32_t length) {
    uint16_t crc = 0xFFFF;
    for (uint32_t i = 0; i < length; i++) {
        crc ^= data[i];
        for (int j = 0; j < 8; j++) {
            if (crc & 1) {
                crc = (crc >> 1) ^ 0xA001;
            } else {
                crc >>= 1;
            }
        }
    }
    return crc;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

esp_err_t camera_init(void) {
    if (g_camera_state.initialized) {
        ESP_LOGW(TAG, "Camera already initialized");
        return ESP_OK;
    }

    ESP_LOGI(TAG, "Initializing camera...");
    ESP_LOGI(TAG, "Device: %s, Firmware: %s", DEVICE_MODEL, FIRMWARE_VERSION);

    camera_config_t config = {
        .pin_pwdn = CAM_PIN_PWDN,
        .pin_reset = CAM_PIN_RESET,
        .pin_xclk = CAM_PIN_XCLK,
        .pin_sccb_sda = CAM_PIN_SIOD,
        .pin_sccb_scl = CAM_PIN_SIOC,
        .pin_d7 = CAM_PIN_D7,
        .pin_d6 = CAM_PIN_D6,
        .pin_d5 = CAM_PIN_D5,
        .pin_d4 = CAM_PIN_D4,
        .pin_d3 = CAM_PIN_D3,
        .pin_d2 = CAM_PIN_D2,
        .pin_d1 = CAM_PIN_D1,
        .pin_d0 = CAM_PIN_D0,
        .pin_vsync = CAM_PIN_VSYNC,
        .pin_href = CAM_PIN_HREF,
        .pin_pclk = CAM_PIN_PCLK,

        .xclk_freq_hz = 20000000,       // 20MHz XCLK
        .ledc_timer = LEDC_TIMER_0,
        .ledc_channel = LEDC_CHANNEL_0,

        .pixel_format = PIXFORMAT_JPEG,
        .frame_size = resolution_map[g_camera_state.resolution],
        .jpeg_quality = g_camera_state.quality,
        .fb_count = DEFAULT_FB_COUNT,
        .grab_mode = CAMERA_GRAB_LATEST,
        .fb_location = CAMERA_FB_IN_PSRAM,  // Use PSRAM for frame buffer
    };

    esp_err_t err = esp_camera_init(&config);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Camera init failed: %s", esp_err_to_name(err));
        return err;
    }

    // Apply default settings
    sensor_t *sensor = esp_camera_sensor_get();
    if (sensor) {
        sensor->set_brightness(sensor, g_camera_state.brightness);
        sensor->set_contrast(sensor, g_camera_state.contrast);

        // OV5640 specific optimizations for face capture
        sensor->set_whitebal(sensor, 1);    // Enable auto white balance
        sensor->set_awb_gain(sensor, 1);    // Enable AWB gain
        sensor->set_exposure_ctrl(sensor, 1); // Enable auto exposure
        sensor->set_aec2(sensor, 1);        // Enable AEC DSP
        sensor->set_gain_ctrl(sensor, 1);   // Enable auto gain

        ESP_LOGI(TAG, "Camera sensor: %s", sensor->id.MIDH == 0x56 ? "OV5640" : "Unknown");
    }

    g_camera_state.initialized = true;
    ESP_LOGI(TAG, "Camera initialized successfully");
    ESP_LOGI(TAG, "Resolution: %d, Quality: %d", g_camera_state.resolution, g_camera_state.quality);

    return ESP_OK;
}

esp_err_t camera_deinit(void) {
    if (!g_camera_state.initialized) {
        return ESP_OK;
    }

    esp_err_t err = esp_camera_deinit();
    if (err == ESP_OK) {
        g_camera_state.initialized = false;
        g_camera_state.image_buffer = NULL;
        g_camera_state.image_size = 0;
        g_camera_state.image_ready = false;
        ESP_LOGI(TAG, "Camera deinitialized");
    }

    return err;
}

bool camera_is_initialized(void) {
    return g_camera_state.initialized;
}

esp_err_t camera_capture(void) {
    if (!g_camera_state.initialized) {
        ESP_LOGE(TAG, "Camera not initialized");
        return ESP_ERR_INVALID_STATE;
    }

    // Clear previous image
    camera_clear_image();

    ESP_LOGI(TAG, "Capturing photo...");

    // Capture frame
    camera_fb_t *fb = esp_camera_fb_get();
    if (!fb) {
        ESP_LOGE(TAG, "Camera capture failed");
        return ESP_FAIL;
    }

    // Verify we got JPEG data
    if (fb->format != PIXFORMAT_JPEG) {
        ESP_LOGE(TAG, "Invalid image format: %d", fb->format);
        esp_camera_fb_return(fb);
        return ESP_FAIL;
    }

    // Store image info
    g_camera_state.image_buffer = fb->buf;
    g_camera_state.image_size = fb->len;
    g_camera_state.image_width = fb->width;
    g_camera_state.image_height = fb->height;
    g_camera_state.image_ready = true;

    ESP_LOGI(TAG, "Photo captured: %dx%d, %lu bytes",
             fb->width, fb->height, (unsigned long)fb->len);

    // Note: We keep the frame buffer until transfer is complete
    // Call camera_clear_image() when done

    return ESP_OK;
}

const uint8_t* camera_get_image(uint32_t *size) {
    if (!g_camera_state.image_ready || !g_camera_state.image_buffer) {
        if (size) *size = 0;
        return NULL;
    }

    if (size) {
        *size = g_camera_state.image_size;
    }

    return g_camera_state.image_buffer;
}

esp_err_t camera_get_info(spi_image_info_t *info) {
    if (!info) {
        return ESP_ERR_INVALID_ARG;
    }

    if (!g_camera_state.image_ready) {
        memset(info, 0, sizeof(spi_image_info_t));
        return ESP_ERR_NOT_FOUND;
    }

    info->image_size = g_camera_state.image_size;
    info->width = g_camera_state.image_width;
    info->height = g_camera_state.image_height;
    info->format = 0;  // JPEG
    info->quality = g_camera_state.quality;
    info->checksum = calculate_crc16(g_camera_state.image_buffer, g_camera_state.image_size);

    return ESP_OK;
}

bool camera_image_ready(void) {
    return g_camera_state.image_ready;
}

void camera_clear_image(void) {
    if (g_camera_state.image_buffer) {
        // Return the frame buffer to the camera driver
        camera_fb_t fb = {
            .buf = g_camera_state.image_buffer,
            .len = g_camera_state.image_size,
            .width = g_camera_state.image_width,
            .height = g_camera_state.image_height,
            .format = PIXFORMAT_JPEG,
        };
        esp_camera_fb_return(&fb);
    }

    g_camera_state.image_buffer = NULL;
    g_camera_state.image_size = 0;
    g_camera_state.image_width = 0;
    g_camera_state.image_height = 0;
    g_camera_state.image_ready = false;
}

esp_err_t camera_set_resolution(camera_resolution_t resolution) {
    if (resolution > RES_5MP) {
        return ESP_ERR_INVALID_ARG;
    }

    sensor_t *sensor = esp_camera_sensor_get();
    if (!sensor) {
        return ESP_ERR_INVALID_STATE;
    }

    int ret = sensor->set_framesize(sensor, resolution_map[resolution]);
    if (ret == 0) {
        g_camera_state.resolution = resolution;
        ESP_LOGI(TAG, "Resolution set to: %d", resolution);
        return ESP_OK;
    }

    return ESP_FAIL;
}

esp_err_t camera_set_quality(uint8_t quality) {
    if (quality < 1 || quality > 100) {
        return ESP_ERR_INVALID_ARG;
    }

    sensor_t *sensor = esp_camera_sensor_get();
    if (!sensor) {
        return ESP_ERR_INVALID_STATE;
    }

    // Convert 1-100 to 1-63 (ESP32 camera quality range)
    // Lower value = better quality
    uint8_t esp_quality = 63 - ((quality - 1) * 62 / 99);

    int ret = sensor->set_quality(sensor, esp_quality);
    if (ret == 0) {
        g_camera_state.quality = esp_quality;
        ESP_LOGI(TAG, "Quality set to: %d (internal: %d)", quality, esp_quality);
        return ESP_OK;
    }

    return ESP_FAIL;
}

esp_err_t camera_set_brightness(int8_t brightness) {
    if (brightness < -2 || brightness > 2) {
        return ESP_ERR_INVALID_ARG;
    }

    sensor_t *sensor = esp_camera_sensor_get();
    if (!sensor) {
        return ESP_ERR_INVALID_STATE;
    }

    int ret = sensor->set_brightness(sensor, brightness);
    if (ret == 0) {
        g_camera_state.brightness = brightness;
        return ESP_OK;
    }

    return ESP_FAIL;
}

esp_err_t camera_set_contrast(int8_t contrast) {
    if (contrast < -2 || contrast > 2) {
        return ESP_ERR_INVALID_ARG;
    }

    sensor_t *sensor = esp_camera_sensor_get();
    if (!sensor) {
        return ESP_ERR_INVALID_STATE;
    }

    int ret = sensor->set_contrast(sensor, contrast);
    if (ret == 0) {
        g_camera_state.contrast = contrast;
        return ESP_OK;
    }

    return ESP_FAIL;
}

esp_err_t camera_get_config(spi_config_info_t *config) {
    if (!config) {
        return ESP_ERR_INVALID_ARG;
    }

    config->resolution = g_camera_state.resolution;
    config->quality = g_camera_state.quality;
    config->brightness = g_camera_state.brightness;
    config->contrast = g_camera_state.contrast;
    config->protocol_major = PROTOCOL_VERSION_MAJOR;
    config->protocol_minor = PROTOCOL_VERSION_MINOR;
    config->reserved = 0;

    return ESP_OK;
}

const camera_state_t* camera_get_state(void) {
    return &g_camera_state;
}
