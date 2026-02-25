/**
 * SPI Slave Handler Implementation
 * Handles SPI communication with ESP32-P4 master
 */

#include "spi_slave_handler.h"
#include "camera_capture.h"
#include "led_status.h"
#include "driver/spi_slave.h"
#include "driver/gpio.h"
#include "esp_log.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include <string.h>

static const char *TAG = "SPI";

// Global SPI slave state
static spi_slave_state_t g_spi_state = {
    .initialized = false,
    .status = STATUS_IDLE,
    .last_error = ERR_NONE,
    .sequence = 0,
    .transfer_active = false,
    .transfer_offset = 0,
    .transfer_remaining = 0,
    .commands_received = 0,
    .bytes_transferred = 0,
    .errors = 0,
};

// DMA-capable buffers for SPI transactions
WORD_ALIGNED_ATTR static uint8_t spi_rx_buffer[SPI_CHUNK_SIZE + SPI_CMD_HEADER_SIZE];
WORD_ALIGNED_ATTR static uint8_t spi_tx_buffer[SPI_CHUNK_SIZE + SPI_RESP_HEADER_SIZE];

// ---------------------------------------------------------------------------
// Internal helper functions
// ---------------------------------------------------------------------------

static void prepare_response(spi_status_t status, spi_error_t error, uint8_t seq, uint32_t data_len) {
    spi_resp_header_t *resp = (spi_resp_header_t *)spi_tx_buffer;
    resp->status = status;
    resp->error_code = error;
    resp->sequence = seq;
    resp->reserved = 0;
    resp->data_len = data_len;
}

static void handle_command(cam_spi_cmd_t cmd, uint8_t seq, const uint8_t *payload, uint16_t payload_len) {
    ESP_LOGD(TAG, "Command: 0x%02X, seq: %d, payload: %d bytes", cmd, seq, payload_len);

    g_spi_state.commands_received++;
    g_spi_state.sequence = seq;

    switch (cmd) {
        case CAM_CMD_NOP:
            // No operation - just respond with current status
            prepare_response(g_spi_state.status, ERR_NONE, seq, 0);
            break;

        case CAM_CMD_PING:
            // Health check - respond with status
            ESP_LOGI(TAG, "PING received");
            prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            break;

        case CAM_CMD_CAPTURE_PHOTO:
            ESP_LOGI(TAG, "Capture photo command");
            g_spi_state.status = STATUS_CAPTURING;
            spi_set_ready_pin(false);
            led_set_mode(LED_MODE_CAPTURING);

            esp_err_t err = camera_capture();
            if (err == ESP_OK) {
                g_spi_state.status = STATUS_READY;
                spi_set_ready_pin(true);
                led_set_mode(LED_MODE_READY);
                prepare_response(STATUS_READY, ERR_NONE, seq, 0);
                ESP_LOGI(TAG, "Capture complete, image ready");
            } else {
                g_spi_state.status = STATUS_ERROR;
                g_spi_state.last_error = ERR_CAMERA_CAPTURE;
                g_spi_state.errors++;
                led_set_mode(LED_MODE_ERROR);
                prepare_response(STATUS_ERROR, ERR_CAMERA_CAPTURE, seq, 0);
                ESP_LOGE(TAG, "Capture failed");
            }
            break;

        case CAM_CMD_GET_STATUS:
            prepare_response(g_spi_state.status, g_spi_state.last_error, seq, 0);
            break;

        case CAM_CMD_GET_IMAGE_INFO: {
            spi_image_info_t info;
            if (camera_get_info(&info) == ESP_OK) {
                prepare_response(STATUS_READY, ERR_NONE, seq, sizeof(spi_image_info_t));
                memcpy(spi_tx_buffer + SPI_RESP_HEADER_SIZE, &info, sizeof(info));
                ESP_LOGI(TAG, "Image info: %lu bytes, %dx%d",
                         (unsigned long)info.image_size, info.width, info.height);
            } else {
                prepare_response(STATUS_ERROR, ERR_NO_IMAGE, seq, 0);
            }
            break;
        }

        case CAM_CMD_GET_IMAGE_DATA: {
            if (!camera_image_ready()) {
                prepare_response(STATUS_ERROR, ERR_NO_IMAGE, seq, 0);
                break;
            }

            // Parse request
            if (payload_len < sizeof(spi_get_image_req_t)) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }

            const spi_get_image_req_t *req = (const spi_get_image_req_t *)payload;
            uint32_t image_size;
            const uint8_t *image_data = camera_get_image(&image_size);

            if (!image_data || req->offset >= image_size) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }

            // Calculate chunk size
            uint32_t remaining = image_size - req->offset;
            uint32_t chunk_size = (req->length > 0 && req->length < remaining) ?
                                   req->length : remaining;
            if (chunk_size > SPI_CHUNK_SIZE) {
                chunk_size = SPI_CHUNK_SIZE;
            }

            // Prepare response with image chunk
            g_spi_state.status = STATUS_TRANSFERRING;
            prepare_response(STATUS_TRANSFERRING, ERR_NONE, seq, chunk_size);
            memcpy(spi_tx_buffer + SPI_RESP_HEADER_SIZE, image_data + req->offset, chunk_size);

            g_spi_state.bytes_transferred += chunk_size;

            // Check if transfer is complete
            if (req->offset + chunk_size >= image_size) {
                ESP_LOGI(TAG, "Transfer complete: %lu bytes", (unsigned long)image_size);
                g_spi_state.status = STATUS_IDLE;
                camera_clear_image();
                spi_set_ready_pin(false);
                led_set_mode(LED_MODE_IDLE);
            }
            break;
        }

        case CAM_CMD_ABORT_TRANSFER:
            ESP_LOGW(TAG, "Transfer aborted");
            camera_clear_image();
            spi_set_ready_pin(false);
            g_spi_state.status = STATUS_IDLE;
            g_spi_state.transfer_active = false;
            led_set_mode(LED_MODE_IDLE);
            prepare_response(STATUS_IDLE, ERR_TRANSFER_ABORT, seq, 0);
            break;

        case CAM_CMD_SET_RESOLUTION: {
            if (payload_len < 1) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }
            camera_resolution_t res = (camera_resolution_t)payload[0];
            if (camera_set_resolution(res) == ESP_OK) {
                prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            } else {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
            }
            break;
        }

        case CAM_CMD_SET_QUALITY: {
            if (payload_len < 1) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }
            if (camera_set_quality(payload[0]) == ESP_OK) {
                prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            } else {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
            }
            break;
        }

        case CAM_CMD_SET_BRIGHTNESS: {
            if (payload_len < 1) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }
            if (camera_set_brightness((int8_t)payload[0]) == ESP_OK) {
                prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            } else {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
            }
            break;
        }

        case CAM_CMD_SET_CONTRAST: {
            if (payload_len < 1) {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
                break;
            }
            if (camera_set_contrast((int8_t)payload[0]) == ESP_OK) {
                prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            } else {
                prepare_response(STATUS_ERROR, ERR_INVALID_PARAM, seq, 0);
            }
            break;
        }

        case CAM_CMD_GET_CONFIG: {
            spi_config_info_t config;
            camera_get_config(&config);
            prepare_response(STATUS_IDLE, ERR_NONE, seq, sizeof(spi_config_info_t));
            memcpy(spi_tx_buffer + SPI_RESP_HEADER_SIZE, &config, sizeof(config));
            break;
        }

        case CAM_CMD_RESET:
            ESP_LOGW(TAG, "Reset command received");
            camera_clear_image();
            spi_set_ready_pin(false);
            g_spi_state.status = STATUS_IDLE;
            g_spi_state.last_error = ERR_NONE;
            g_spi_state.transfer_active = false;
            led_set_mode(LED_MODE_IDLE);
            prepare_response(STATUS_IDLE, ERR_NONE, seq, 0);
            // Note: Could trigger actual hardware reset here if needed
            break;

        default:
            ESP_LOGW(TAG, "Unknown command: 0x%02X", cmd);
            g_spi_state.errors++;
            prepare_response(STATUS_ERROR, ERR_UNKNOWN_CMD, seq, 0);
            break;
    }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

esp_err_t spi_slave_init(void) {
    if (g_spi_state.initialized) {
        ESP_LOGW(TAG, "SPI slave already initialized");
        return ESP_OK;
    }

    ESP_LOGI(TAG, "Initializing SPI slave...");

    // Configure READY pin
    gpio_config_t ready_conf = {
        .pin_bit_mask = (1ULL << READY_PIN),
        .mode = GPIO_MODE_OUTPUT,
        .pull_up_en = GPIO_PULLUP_DISABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };
    gpio_config(&ready_conf);
    gpio_set_level(READY_PIN, READY_PIN_LOW);

    // Configure SPI slave
    spi_bus_config_t bus_cfg = {
        .mosi_io_num = SPI_SLAVE_MOSI_PIN,
        .miso_io_num = SPI_SLAVE_MISO_PIN,
        .sclk_io_num = SPI_SLAVE_SCLK_PIN,
        .quadwp_io_num = -1,
        .quadhd_io_num = -1,
        .max_transfer_sz = SPI_CHUNK_SIZE + SPI_RESP_HEADER_SIZE,
    };

    spi_slave_interface_config_t slave_cfg = {
        .mode = SPI_MODE,
        .spics_io_num = SPI_SLAVE_CS_PIN,
        .queue_size = SPI_QUEUE_SIZE,
        .flags = 0,
        .post_setup_cb = NULL,
        .post_trans_cb = NULL,
    };

    esp_err_t err = spi_slave_initialize(SPI_SLAVE_HOST, &bus_cfg, &slave_cfg, SPI_DMA_CHANNEL);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "SPI slave init failed: %s", esp_err_to_name(err));
        return err;
    }

    g_spi_state.initialized = true;
    g_spi_state.status = STATUS_IDLE;

    ESP_LOGI(TAG, "SPI slave initialized");
    ESP_LOGI(TAG, "  MOSI: GPIO%d, MISO: GPIO%d, SCLK: GPIO%d, CS: GPIO%d",
             SPI_SLAVE_MOSI_PIN, SPI_SLAVE_MISO_PIN, SPI_SLAVE_SCLK_PIN, SPI_SLAVE_CS_PIN);
    ESP_LOGI(TAG, "  READY: GPIO%d", READY_PIN);

    return ESP_OK;
}

esp_err_t spi_slave_deinit(void) {
    if (!g_spi_state.initialized) {
        return ESP_OK;
    }

    esp_err_t err = spi_slave_free(SPI_SLAVE_HOST);
    if (err == ESP_OK) {
        g_spi_state.initialized = false;
        ESP_LOGI(TAG, "SPI slave deinitialized");
    }

    return err;
}

void spi_slave_task(void *pvParameters) {
    ESP_LOGI(TAG, "SPI slave task started");
    led_set_mode(LED_MODE_IDLE);

    spi_slave_transaction_t trans;
    memset(&trans, 0, sizeof(trans));

    while (1) {
        // Prepare for receiving command
        memset(spi_rx_buffer, 0, sizeof(spi_rx_buffer));
        memset(spi_tx_buffer, 0, sizeof(spi_tx_buffer));

        // Pre-fill with idle status in case master reads before we process
        prepare_response(g_spi_state.status, g_spi_state.last_error, 0, 0);

        trans.length = (SPI_CHUNK_SIZE + SPI_RESP_HEADER_SIZE) * 8;  // bits
        trans.trans_len = 0;
        trans.tx_buffer = spi_tx_buffer;
        trans.rx_buffer = spi_rx_buffer;

        // Wait for transaction from master
        esp_err_t err = spi_slave_transmit(SPI_SLAVE_HOST, &trans, portMAX_DELAY);

        if (err != ESP_OK) {
            ESP_LOGE(TAG, "SPI transaction error: %s", esp_err_to_name(err));
            g_spi_state.errors++;
            continue;
        }

        // Check if we received data
        if (trans.trans_len == 0) {
            continue;
        }

        // Parse command packet
        if (trans.trans_len < SPI_CMD_HEADER_SIZE * 8) {
            ESP_LOGW(TAG, "Short packet: %d bits", (int)trans.trans_len);
            continue;
        }

        const cam_spi_cmd_packet_t *cmd = (const cam_spi_cmd_packet_t *)spi_rx_buffer;
        const uint8_t *payload = spi_rx_buffer + SPI_CMD_HEADER_SIZE;
        uint16_t payload_len = cmd->payload_len;

        // Validate payload length
        if (payload_len > SPI_CHUNK_SIZE) {
            ESP_LOGW(TAG, "Invalid payload length: %d", payload_len);
            continue;
        }

        // Process command
        handle_command((cam_spi_cmd_t)cmd->command, cmd->sequence, payload, payload_len);
    }
}

void spi_set_ready_pin(bool ready) {
    gpio_set_level(READY_PIN, ready ? READY_PIN_HIGH : READY_PIN_LOW);
    ESP_LOGD(TAG, "READY pin: %s", ready ? "HIGH" : "LOW");
}

const spi_slave_state_t* spi_get_state(void) {
    return &g_spi_state;
}

void spi_set_status(spi_status_t status) {
    g_spi_state.status = status;
}

void spi_set_error(spi_error_t error) {
    g_spi_state.last_error = error;
    if (error != ERR_NONE) {
        g_spi_state.errors++;
    }
}

void spi_clear_error(void) {
    g_spi_state.last_error = ERR_NONE;
}

bool spi_is_busy(void) {
    return g_spi_state.status == STATUS_CAPTURING ||
           g_spi_state.status == STATUS_PROCESSING ||
           g_spi_state.status == STATUS_TRANSFERRING ||
           g_spi_state.status == STATUS_BUSY;
}
