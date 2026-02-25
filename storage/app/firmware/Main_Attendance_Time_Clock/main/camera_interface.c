/**
 * Camera Interface Implementation
 * SPI Master communication with ESP32-S3 Camera SubModule
 */

#include "camera_interface.h"
#include "driver/spi_master.h"
#include "driver/gpio.h"
#include "esp_log.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/semphr.h"
#include <string.h>

static const char *TAG = "CAM_IF";

// SPI device handle
static spi_device_handle_t g_spi_handle = NULL;

// Semaphore for READY pin interrupt
static SemaphoreHandle_t g_ready_semaphore = NULL;

// Global state
static camera_module_state_t g_camera_state = {
    .initialized = false,
    .connected = false,
    .last_status = STATUS_IDLE,
    .last_error = ERR_NONE,
    .image_size = 0,
    .image_width = 0,
    .image_height = 0,
    .captures_requested = 0,
    .captures_completed = 0,
    .transfer_errors = 0,
};

// DMA-capable buffers
WORD_ALIGNED_ATTR static uint8_t spi_tx_buffer[SPI_CHUNK_SIZE + SPI_CMD_HEADER_SIZE];
WORD_ALIGNED_ATTR static uint8_t spi_rx_buffer[SPI_CHUNK_SIZE + SPI_RESP_HEADER_SIZE];

// ---------------------------------------------------------------------------
// Internal helper functions
// ---------------------------------------------------------------------------

static esp_err_t send_command(cam_spi_cmd_t cmd, const uint8_t *payload, uint16_t payload_len,
                               spi_resp_header_t *resp, uint8_t *resp_data, uint32_t resp_data_len) {
    if (!g_camera_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    // Prepare command packet
    cam_spi_cmd_packet_t *cmd_pkt = (cam_spi_cmd_packet_t *)spi_tx_buffer;
    cmd_pkt->command = cmd;
    cmd_pkt->sequence = (g_camera_state.captures_requested + g_camera_state.captures_completed) & 0xFF;
    cmd_pkt->payload_len = payload_len;

    if (payload && payload_len > 0) {
        memcpy(spi_tx_buffer + SPI_CMD_HEADER_SIZE, payload, payload_len);
    }

    // Clear RX buffer
    memset(spi_rx_buffer, 0, sizeof(spi_rx_buffer));

    // Calculate transaction size
    uint32_t tx_len = SPI_CMD_HEADER_SIZE + payload_len;
    uint32_t rx_len = SPI_RESP_HEADER_SIZE + resp_data_len;
    uint32_t trans_len = (tx_len > rx_len) ? tx_len : rx_len;

    // Perform SPI transaction
    spi_transaction_t trans = {
        .length = trans_len * 8,  // bits
        .tx_buffer = spi_tx_buffer,
        .rx_buffer = spi_rx_buffer,
    };

    esp_err_t err = spi_device_transmit(g_spi_handle, &trans);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "SPI transmit failed: %s", esp_err_to_name(err));
        return err;
    }

    // Parse response
    spi_resp_header_t *resp_hdr = (spi_resp_header_t *)spi_rx_buffer;

    if (resp) {
        memcpy(resp, resp_hdr, sizeof(spi_resp_header_t));
    }

    if (resp_data && resp_data_len > 0 && resp_hdr->data_len > 0) {
        uint32_t copy_len = (resp_hdr->data_len < resp_data_len) ? resp_hdr->data_len : resp_data_len;
        memcpy(resp_data, spi_rx_buffer + SPI_RESP_HEADER_SIZE, copy_len);
    }

    g_camera_state.last_status = (spi_status_t)resp_hdr->status;
    g_camera_state.last_error = (spi_error_t)resp_hdr->error_code;

    return ESP_OK;
}

// GPIO ISR handler
static void IRAM_ATTR ready_gpio_isr_handler(void *arg) {
    BaseType_t xHigherPriorityTaskWoken = pdFALSE;
    xSemaphoreGiveFromISR(g_ready_semaphore, &xHigherPriorityTaskWoken);
    if (xHigherPriorityTaskWoken) {
        portYIELD_FROM_ISR();
    }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

esp_err_t camera_interface_init(void) {
    if (g_camera_state.initialized) {
        ESP_LOGW(TAG, "Camera interface already initialized");
        return ESP_OK;
    }

    ESP_LOGI(TAG, "Initializing camera interface...");

    // Create READY semaphore
    g_ready_semaphore = xSemaphoreCreateBinary();
    if (!g_ready_semaphore) {
        ESP_LOGE(TAG, "Failed to create semaphore");
        return ESP_ERR_NO_MEM;
    }

    // Configure READY pin as input with interrupt
    gpio_config_t ready_conf = {
        .pin_bit_mask = (1ULL << CAM_READY_PIN),
        .mode = GPIO_MODE_INPUT,
        .pull_up_en = GPIO_PULLUP_DISABLE,
        .pull_down_en = GPIO_PULLDOWN_ENABLE,
        .intr_type = GPIO_INTR_POSEDGE,  // Interrupt on rising edge
    };
    gpio_config(&ready_conf);

    // Install GPIO ISR service if not already installed
    gpio_install_isr_service(0);
    gpio_isr_handler_add(CAM_READY_PIN, ready_gpio_isr_handler, NULL);

    // Configure SPI bus
    spi_bus_config_t bus_cfg = {
        .mosi_io_num = CAM_SPI_MOSI_PIN,
        .miso_io_num = CAM_SPI_MISO_PIN,
        .sclk_io_num = CAM_SPI_SCLK_PIN,
        .quadwp_io_num = -1,
        .quadhd_io_num = -1,
        .max_transfer_sz = SPI_CHUNK_SIZE + SPI_RESP_HEADER_SIZE,
    };

    esp_err_t err = spi_bus_initialize(CAM_SPI_HOST, &bus_cfg, SPI_DMA_CH_AUTO);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "SPI bus init failed: %s", esp_err_to_name(err));
        return err;
    }

    // Add SPI device
    spi_device_interface_config_t dev_cfg = {
        .clock_speed_hz = CAM_SPI_CLOCK_HZ,
        .mode = CAM_SPI_MODE,
        .spics_io_num = CAM_SPI_CS_PIN,
        .queue_size = 1,
        .pre_cb = NULL,
        .post_cb = NULL,
    };

    err = spi_bus_add_device(CAM_SPI_HOST, &dev_cfg, &g_spi_handle);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "SPI device add failed: %s", esp_err_to_name(err));
        spi_bus_free(CAM_SPI_HOST);
        return err;
    }

    g_camera_state.initialized = true;

    ESP_LOGI(TAG, "Camera interface initialized");
    ESP_LOGI(TAG, "  MOSI: GPIO%d, MISO: GPIO%d, SCLK: GPIO%d, CS: GPIO%d",
             CAM_SPI_MOSI_PIN, CAM_SPI_MISO_PIN, CAM_SPI_SCLK_PIN, CAM_SPI_CS_PIN);
    ESP_LOGI(TAG, "  READY: GPIO%d, Clock: %d Hz", CAM_READY_PIN, CAM_SPI_CLOCK_HZ);

    // Test connection
    if (camera_ping() == ESP_OK) {
        g_camera_state.connected = true;
        ESP_LOGI(TAG, "Camera module connected");
    } else {
        ESP_LOGW(TAG, "Camera module not responding (will retry later)");
    }

    return ESP_OK;
}

esp_err_t camera_interface_deinit(void) {
    if (!g_camera_state.initialized) {
        return ESP_OK;
    }

    gpio_isr_handler_remove(CAM_READY_PIN);

    if (g_spi_handle) {
        spi_bus_remove_device(g_spi_handle);
        g_spi_handle = NULL;
    }

    spi_bus_free(CAM_SPI_HOST);

    if (g_ready_semaphore) {
        vSemaphoreDelete(g_ready_semaphore);
        g_ready_semaphore = NULL;
    }

    g_camera_state.initialized = false;
    g_camera_state.connected = false;

    ESP_LOGI(TAG, "Camera interface deinitialized");
    return ESP_OK;
}

bool camera_is_connected(void) {
    return g_camera_state.initialized && g_camera_state.connected;
}

esp_err_t camera_ping(void) {
    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_PING, NULL, 0, &resp, NULL, 0);

    if (err == ESP_OK && resp.error_code == ERR_NONE) {
        g_camera_state.connected = true;
        return ESP_OK;
    }

    g_camera_state.connected = false;
    return ESP_FAIL;
}

esp_err_t camera_get_status(spi_status_t *status) {
    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_GET_STATUS, NULL, 0, &resp, NULL, 0);

    if (err == ESP_OK && status) {
        *status = (spi_status_t)resp.status;
    }

    return err;
}

esp_err_t camera_capture_photo(void) {
    ESP_LOGI(TAG, "Requesting photo capture...");
    g_camera_state.captures_requested++;

    // Clear any pending READY semaphore
    xSemaphoreTake(g_ready_semaphore, 0);

    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_CAPTURE_PHOTO, NULL, 0, &resp, NULL, 0);

    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Capture command failed");
        return err;
    }

    if (resp.error_code != ERR_NONE) {
        ESP_LOGE(TAG, "Capture error: %d", resp.error_code);
        return ESP_FAIL;
    }

    return ESP_OK;
}

esp_err_t camera_wait_ready(uint32_t timeout_ms) {
    // First check READY pin directly
    if (gpio_get_level(CAM_READY_PIN)) {
        return ESP_OK;
    }

    // Wait for READY pin interrupt
    if (xSemaphoreTake(g_ready_semaphore, pdMS_TO_TICKS(timeout_ms)) == pdTRUE) {
        return ESP_OK;
    }

    // Timeout - check status via SPI
    spi_status_t status;
    if (camera_get_status(&status) == ESP_OK && status == STATUS_READY) {
        return ESP_OK;
    }

    ESP_LOGW(TAG, "Timeout waiting for camera ready");
    return ESP_ERR_TIMEOUT;
}

bool camera_image_ready(void) {
    return gpio_get_level(CAM_READY_PIN) || g_camera_state.last_status == STATUS_READY;
}

esp_err_t camera_get_image_info(spi_image_info_t *info) {
    if (!info) {
        return ESP_ERR_INVALID_ARG;
    }

    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_GET_IMAGE_INFO, NULL, 0, &resp, (uint8_t *)info, sizeof(spi_image_info_t));

    if (err == ESP_OK && resp.error_code == ERR_NONE) {
        g_camera_state.image_size = info->image_size;
        g_camera_state.image_width = info->width;
        g_camera_state.image_height = info->height;
        ESP_LOGI(TAG, "Image info: %lux%lu, %lu bytes",
                 (unsigned long)info->width, (unsigned long)info->height, (unsigned long)info->image_size);
    }

    return (err == ESP_OK && resp.error_code == ERR_NONE) ? ESP_OK : ESP_FAIL;
}

esp_err_t camera_get_image(uint8_t *buffer, uint32_t buffer_size, uint32_t *image_size) {
    if (!buffer || buffer_size == 0) {
        return ESP_ERR_INVALID_ARG;
    }

    // Get image info first
    spi_image_info_t info;
    esp_err_t err = camera_get_image_info(&info);
    if (err != ESP_OK) {
        return err;
    }

    if (info.image_size > buffer_size) {
        ESP_LOGE(TAG, "Buffer too small: %lu < %lu", (unsigned long)buffer_size, (unsigned long)info.image_size);
        return ESP_ERR_INVALID_SIZE;
    }

    // Transfer image in chunks
    uint32_t offset = 0;
    uint32_t remaining = info.image_size;

    ESP_LOGI(TAG, "Transferring %lu bytes...", (unsigned long)info.image_size);

    while (remaining > 0) {
        uint32_t chunk_size = (remaining < SPI_CHUNK_SIZE) ? remaining : SPI_CHUNK_SIZE;

        // Prepare request
        spi_get_image_req_t req = {
            .offset = offset,
            .length = (uint16_t)chunk_size,
            .reserved = 0,
        };

        spi_resp_header_t resp;
        err = send_command(CAM_CMD_GET_IMAGE_DATA, (uint8_t *)&req, sizeof(req),
                           &resp, buffer + offset, chunk_size);

        if (err != ESP_OK) {
            ESP_LOGE(TAG, "Transfer error at offset %lu", (unsigned long)offset);
            g_camera_state.transfer_errors++;
            return err;
        }

        if (resp.error_code != ERR_NONE) {
            ESP_LOGE(TAG, "Transfer error code: %d", resp.error_code);
            g_camera_state.transfer_errors++;
            return ESP_FAIL;
        }

        offset += resp.data_len;
        remaining -= resp.data_len;
    }

    if (image_size) {
        *image_size = info.image_size;
    }

    g_camera_state.captures_completed++;
    ESP_LOGI(TAG, "Transfer complete: %lu bytes", (unsigned long)info.image_size);

    return ESP_OK;
}

esp_err_t camera_capture_and_get(uint8_t *buffer, uint32_t buffer_size, uint32_t *image_size) {
    // Capture
    esp_err_t err = camera_capture_photo();
    if (err != ESP_OK) {
        return err;
    }

    // Wait for ready
    err = camera_wait_ready(CAM_TIMEOUT_CAPTURE_MS);
    if (err != ESP_OK) {
        return err;
    }

    // Get image
    return camera_get_image(buffer, buffer_size, image_size);
}

esp_err_t camera_set_resolution(camera_resolution_t resolution) {
    uint8_t payload = (uint8_t)resolution;
    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_SET_RESOLUTION, &payload, 1, &resp, NULL, 0);
    return (err == ESP_OK && resp.error_code == ERR_NONE) ? ESP_OK : ESP_FAIL;
}

esp_err_t camera_set_quality(uint8_t quality) {
    spi_resp_header_t resp;
    esp_err_t err = send_command(CAM_CMD_SET_QUALITY, &quality, 1, &resp, NULL, 0);
    return (err == ESP_OK && resp.error_code == ERR_NONE) ? ESP_OK : ESP_FAIL;
}

esp_err_t camera_get_config(spi_config_info_t *config) {
    if (!config) {
        return ESP_ERR_INVALID_ARG;
    }

    spi_resp_header_t resp;
    return send_command(CAM_CMD_GET_CONFIG, NULL, 0, &resp, (uint8_t *)config, sizeof(spi_config_info_t));
}

esp_err_t camera_reset(void) {
    ESP_LOGW(TAG, "Resetting camera module...");
    spi_resp_header_t resp;
    return send_command(CAM_CMD_RESET, NULL, 0, &resp, NULL, 0);
}

const camera_module_state_t* camera_get_state(void) {
    return &g_camera_state;
}

void camera_ready_isr_handler(void) {
    BaseType_t xHigherPriorityTaskWoken = pdFALSE;
    xSemaphoreGiveFromISR(g_ready_semaphore, &xHigherPriorityTaskWoken);
    portYIELD_FROM_ISR(xHigherPriorityTaskWoken);
}
