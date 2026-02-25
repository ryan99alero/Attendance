/**
 * RYRR30D NFC Reader Driver Implementation
 * REYAX Apple VAS & Google SmartTap NFC Module
 */

#include "ryrr30d.h"
#include "driver/gpio.h"
#include "esp_log.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include <string.h>
#include <stdio.h>
#include <stdlib.h>

static const char *TAG = "RYRR30D";

// ---------------------------------------------------------------------------
// Internal state
// ---------------------------------------------------------------------------
static ryrr30d_state_t g_state = {
    .initialized = false,
    .uart_num = UART_NUM_1,
    .rst_pin = -1,
    .apple_configured = false,
    .google_configured = false,
    .cards_read = 0,
    .errors = 0,
};

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

static esp_err_t uart_send(const char *data, size_t len) {
    int written = uart_write_bytes(g_state.uart_num, data, len);
    if (written < 0) {
        return ESP_FAIL;
    }
    return (written == len) ? ESP_OK : ESP_FAIL;
}

static int uart_receive(char *buffer, size_t buffer_size, uint32_t timeout_ms) {
    return uart_read_bytes(g_state.uart_num, (uint8_t *)buffer, buffer_size - 1,
                           pdMS_TO_TICKS(timeout_ms));
}

static esp_err_t send_at_command(const char *cmd, char *response,
                                  size_t response_size, uint32_t timeout_ms) {
    // Clear RX buffer
    uart_flush_input(g_state.uart_num);

    // Build command with AT+ prefix and \r\n suffix
    char full_cmd[256];
    snprintf(full_cmd, sizeof(full_cmd), "AT+%s\r\n", cmd);

    ESP_LOGD(TAG, "Sending: %s", cmd);

    // Send command
    esp_err_t err = uart_send(full_cmd, strlen(full_cmd));
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Failed to send command");
        return err;
    }

    // Wait for response
    if (response && response_size > 0) {
        memset(response, 0, response_size);
        int len = uart_receive(response, response_size, timeout_ms);

        if (len > 0) {
            response[len] = '\0';
            // Trim trailing \r\n
            while (len > 0 && (response[len-1] == '\r' || response[len-1] == '\n')) {
                response[--len] = '\0';
            }
            ESP_LOGD(TAG, "Response: %s", response);

            // Check for OK or ERROR
            if (strstr(response, "OK") != NULL) {
                return ESP_OK;
            } else if (strstr(response, "ERROR") != NULL) {
                return ESP_FAIL;
            }
            // Some responses don't have OK/ERROR, just data
            return ESP_OK;
        } else if (len == 0) {
            return ESP_ERR_TIMEOUT;
        } else {
            return ESP_FAIL;
        }
    }

    // Small delay for command processing
    vTaskDelay(pdMS_TO_TICKS(50));
    return ESP_OK;
}

static ryrr30d_card_type_t parse_card_type(const char *response) {
    if (strstr(response, "+APPLE=") != NULL) {
        return RYRR30D_CARD_APPLE_VAS;
    } else if (strstr(response, "+GOOGLE=") != NULL) {
        return RYRR30D_CARD_GOOGLE_SMARTTAP;
    } else if (strstr(response, "+ISO14443A=") != NULL ||
               strstr(response, "+MIFARE=") != NULL) {
        return RYRR30D_CARD_ISO14443A;
    } else if (strstr(response, "+ISO14443B=") != NULL) {
        return RYRR30D_CARD_ISO14443B;
    } else if (strstr(response, "+ISO15693=") != NULL) {
        return RYRR30D_CARD_ISO15693;
    } else if (strstr(response, "+FELICA=") != NULL) {
        return RYRR30D_CARD_FELICA;
    }
    return RYRR30D_CARD_UNKNOWN;
}

static void parse_apple_response(const char *response, ryrr30d_card_info_t *info) {
    // Format: +APPLE=1,<pass_data>
    const char *data_start = strstr(response, "+APPLE=");
    if (data_start) {
        data_start = strchr(data_start, ',');
        if (data_start) {
            data_start++; // Skip comma
            strncpy(info->pass_data, data_start, RYRR30D_MAX_PASS_DATA_LEN - 1);
            info->pass_data[RYRR30D_MAX_PASS_DATA_LEN - 1] = '\0';
            info->pass_data_len = strlen(info->pass_data);
        }
    }
}

static void parse_google_response(const char *response, ryrr30d_card_info_t *info) {
    // Format: +GOOGLE=1,<pass_data>
    const char *data_start = strstr(response, "+GOOGLE=");
    if (data_start) {
        data_start = strchr(data_start, ',');
        if (data_start) {
            data_start++; // Skip comma
            strncpy(info->pass_data, data_start, RYRR30D_MAX_PASS_DATA_LEN - 1);
            info->pass_data[RYRR30D_MAX_PASS_DATA_LEN - 1] = '\0';
            info->pass_data_len = strlen(info->pass_data);
        }
    }
}

static void parse_uid_response(const char *response, ryrr30d_card_info_t *info) {
    // Format varies by card type, typically: +ISO14443A=<UID_hex>
    // Example: +ISO14443A=04A5B2C3D4E5F6
    const char *uid_start = strchr(response, '=');
    if (uid_start) {
        uid_start++; // Skip '='

        // Parse hex string to bytes
        info->uid_len = 0;
        while (*uid_start && info->uid_len < RYRR30D_MAX_UID_LEN) {
            char hex_byte[3] = {0};
            if (uid_start[0] && uid_start[1]) {
                hex_byte[0] = uid_start[0];
                hex_byte[1] = uid_start[1];
                info->uid[info->uid_len++] = (uint8_t)strtol(hex_byte, NULL, 16);
                uid_start += 2;
            } else {
                break;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

esp_err_t ryrr30d_init(const ryrr30d_config_t *config) {
    if (!config) {
        return ESP_ERR_INVALID_ARG;
    }

    if (g_state.initialized) {
        ESP_LOGW(TAG, "Already initialized");
        return ESP_OK;
    }

    ESP_LOGI(TAG, "Initializing RYRR30D NFC reader...");

    g_state.uart_num = config->uart_num;
    g_state.rst_pin = config->rst_pin;

    // Configure UART
    uart_config_t uart_config = {
        .baud_rate = RYRR30D_UART_BAUD,
        .data_bits = UART_DATA_8_BITS,
        .parity = UART_PARITY_DISABLE,
        .stop_bits = UART_STOP_BITS_1,
        .flow_ctrl = UART_HW_FLOWCTRL_DISABLE,
        .source_clk = UART_SCLK_DEFAULT,
    };

    esp_err_t err = uart_param_config(config->uart_num, &uart_config);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "UART config failed: %s", esp_err_to_name(err));
        return err;
    }

    err = uart_set_pin(config->uart_num, config->tx_pin, config->rx_pin,
                       UART_PIN_NO_CHANGE, UART_PIN_NO_CHANGE);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "UART pin config failed: %s", esp_err_to_name(err));
        return err;
    }

    err = uart_driver_install(config->uart_num, RYRR30D_RX_BUF_SIZE,
                              RYRR30D_TX_BUF_SIZE, 0, NULL, 0);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "UART driver install failed: %s", esp_err_to_name(err));
        return err;
    }

    // Configure reset pin if provided
    if (config->rst_pin >= 0) {
        gpio_config_t rst_conf = {
            .pin_bit_mask = (1ULL << config->rst_pin),
            .mode = GPIO_MODE_OUTPUT,
            .pull_up_en = GPIO_PULLUP_DISABLE,
            .pull_down_en = GPIO_PULLDOWN_DISABLE,
            .intr_type = GPIO_INTR_DISABLE,
        };
        gpio_config(&rst_conf);
        gpio_set_level(config->rst_pin, 1);  // Active high (not in reset)
    }

    ESP_LOGI(TAG, "UART configured: %d baud, TX=GPIO%d, RX=GPIO%d",
             RYRR30D_UART_BAUD, config->tx_pin, config->rx_pin);

    // Small delay for reader to be ready
    vTaskDelay(pdMS_TO_TICKS(100));

    // Test communication with version query
    char version[64];
    err = ryrr30d_get_version(version, sizeof(version));
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "Reader version: %s", version);
    } else {
        ESP_LOGW(TAG, "Could not get version (reader may need reset)");
    }

    // Configure Apple VAS if provided
    if (config->apple_enabled && config->apple_pass_type_id[0] &&
        config->apple_private_key[0]) {
        err = ryrr30d_configure_apple(config->apple_pass_type_id,
                                       config->apple_private_key);
        if (err == ESP_OK) {
            g_state.apple_configured = true;
            ESP_LOGI(TAG, "Apple VAS configured");
        }
    }

    // Configure Google SmartTap if provided
    if (config->google_enabled && config->google_collector_id[0] &&
        config->google_private_key[0]) {
        err = ryrr30d_configure_google(config->google_collector_id,
                                        config->google_private_key);
        if (err == ESP_OK) {
            g_state.google_configured = true;
            ESP_LOGI(TAG, "Google SmartTap configured");
        }
    }

    g_state.initialized = true;
    ESP_LOGI(TAG, "RYRR30D initialized successfully");

    return ESP_OK;
}

esp_err_t ryrr30d_deinit(void) {
    if (!g_state.initialized) {
        return ESP_OK;
    }

    uart_driver_delete(g_state.uart_num);
    g_state.initialized = false;

    ESP_LOGI(TAG, "RYRR30D deinitialized");
    return ESP_OK;
}

esp_err_t ryrr30d_reset(void) {
    if (g_state.rst_pin < 0) {
        ESP_LOGW(TAG, "Reset pin not configured");
        return ESP_ERR_NOT_SUPPORTED;
    }

    ESP_LOGI(TAG, "Resetting reader...");
    gpio_set_level(g_state.rst_pin, 0);  // Assert reset
    vTaskDelay(pdMS_TO_TICKS(100));
    gpio_set_level(g_state.rst_pin, 1);  // Release reset
    vTaskDelay(pdMS_TO_TICKS(500));      // Wait for boot

    return ESP_OK;
}

esp_err_t ryrr30d_send_command(const char *cmd, char *response,
                                size_t response_size, uint32_t timeout_ms) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    return send_at_command(cmd, response, response_size, timeout_ms);
}

esp_err_t ryrr30d_configure_apple(const char *pass_type_id, const char *private_key) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    char cmd[256];
    snprintf(cmd, sizeof(cmd), "APPLE=1,%s,%s", pass_type_id, private_key);

    char response[128];
    esp_err_t err = send_at_command(cmd, response, sizeof(response),
                                    RYRR30D_CMD_TIMEOUT_MS);

    if (err == ESP_OK) {
        g_state.apple_configured = true;
    }

    return err;
}

esp_err_t ryrr30d_configure_google(const char *collector_id, const char *private_key) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    char cmd[256];
    snprintf(cmd, sizeof(cmd), "GOOGLE=1,%s,%s", collector_id, private_key);

    char response[128];
    esp_err_t err = send_at_command(cmd, response, sizeof(response),
                                    RYRR30D_CMD_TIMEOUT_MS);

    if (err == ESP_OK) {
        g_state.google_configured = true;
    }

    return err;
}

esp_err_t ryrr30d_configure_card_types(bool iso14443a, bool iso14443b,
                                        bool iso15693, bool felica) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    // CTYPE format: 16-bit binary string (Bit15 to Bit0, left to right)
    // Per REYAX documentation:
    //   Bit0 = ISO15693
    //   Bit1 = ISO14443A  <-- MIFARE cards
    //   Bit2 = ISO14443B
    //   Bit3 = Felica
    //   Bit4-15 = Apple/Google wallet IDs
    char ctype[17] = "0000000000000000";
    //                 Bit15............Bit0

    // Set bits from the RIGHT side (Bit0 is rightmost)
    if (iso15693)  ctype[15] = '1';  // Bit0
    if (iso14443a) ctype[14] = '1';  // Bit1 - MIFARE/NFC-A
    if (iso14443b) ctype[13] = '1';  // Bit2
    if (felica)    ctype[12] = '1';  // Bit3

    char cmd[32];
    snprintf(cmd, sizeof(cmd), "CTYPE=%s", ctype);

    char response[128];
    esp_err_t err = send_at_command(cmd, response, sizeof(response), RYRR30D_CMD_TIMEOUT_MS);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Failed to set CTYPE: %s", response);
        return err;
    }

    ESP_LOGI(TAG, "Card types configured: %s", ctype);
    return ESP_OK;
}

esp_err_t ryrr30d_start_polling(void) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    char response[128];
    esp_err_t err;

    // Switch to Standalone Mode (MODE=2) for automatic card scanning
    // Per REYAX docs: MODE=2 auto-scans based on CTYPE settings and outputs:
    //   +ISO14443A=<UID> for MIFARE cards
    //   +GOOGLE=<num>,<data> for Google Wallet
    //   +APPLE=<num>,<data> for Apple Wallet
    err = send_at_command("MODE=2", response, sizeof(response), RYRR30D_CMD_TIMEOUT_MS);

    if (err == ESP_OK && strstr(response, "+OK") != NULL) {
        ESP_LOGI(TAG, "Standalone Mode enabled - reader auto-scanning");
        return ESP_OK;
    }

    ESP_LOGE(TAG, "Failed to enable Standalone Mode: %s", response);
    return ESP_FAIL;
}

esp_err_t ryrr30d_stop_polling(void) {
    if (!g_state.initialized) {
        return ESP_ERR_INVALID_STATE;
    }

    char response[128];
    esp_err_t err;

    // Switch back to Command Mode (MODE=1)
    err = send_at_command("MODE=1", response, sizeof(response), RYRR30D_CMD_TIMEOUT_MS);
    if (err == ESP_OK && strstr(response, "+OK") != NULL) {
        ESP_LOGI(TAG, "Switched back to Command Mode");
        return ESP_OK;
    }

    return ESP_ERR_NOT_SUPPORTED;
}

esp_err_t ryrr30d_poll_card(ryrr30d_card_info_t *card_info) {
    if (!g_state.initialized || !card_info) {
        return ESP_ERR_INVALID_ARG;
    }

    // Clear output
    memset(card_info, 0, sizeof(ryrr30d_card_info_t));

    // PASSIVE LISTENING - Reader should auto-output when card detected
    // (Based on working sample code - no polling commands needed)

    // Check if data is available on UART
    size_t available = 0;
    uart_get_buffered_data_len(g_state.uart_num, &available);

    if (available == 0) {
        return ESP_ERR_NOT_FOUND;
    }

    // Read available data
    char buffer[RYRR30D_MAX_PASS_DATA_LEN];
    int len = uart_receive(buffer, sizeof(buffer), RYRR30D_READ_TIMEOUT_MS);

    if (len <= 0) {
        return ESP_ERR_NOT_FOUND;
    }

    buffer[len] = '\0';

    // Trim trailing whitespace
    while (len > 0 && (buffer[len-1] == '\r' || buffer[len-1] == '\n' || buffer[len-1] == ' ')) {
        buffer[--len] = '\0';
    }

    // Store raw response
    strncpy(card_info->raw_response, buffer, RYRR30D_MAX_PASS_DATA_LEN - 1);

    // Parse card type - format: +ISO14443A=<UID>, +GOOGLE=1,<data>, +APPLE=1,<data>
    card_info->type = parse_card_type(buffer);

    if (card_info->type == RYRR30D_CARD_NONE ||
        card_info->type == RYRR30D_CARD_UNKNOWN) {
        return ESP_ERR_NOT_FOUND;
    }

    // Parse specific data based on type
    switch (card_info->type) {
        case RYRR30D_CARD_APPLE_VAS:
            parse_apple_response(buffer, card_info);
            break;

        case RYRR30D_CARD_GOOGLE_SMARTTAP:
            parse_google_response(buffer, card_info);
            break;

        case RYRR30D_CARD_ISO14443A:
        case RYRR30D_CARD_ISO14443B:
        case RYRR30D_CARD_ISO15693:
        case RYRR30D_CARD_FELICA:
            parse_uid_response(buffer, card_info);
            break;

        default:
            break;
    }

    g_state.cards_read++;
    ESP_LOGI(TAG, "Card detected: %s", ryrr30d_card_type_to_string(card_info->type));

    return ESP_OK;
}

esp_err_t ryrr30d_wait_for_card(ryrr30d_card_info_t *card_info, uint32_t timeout_ms) {
    if (!g_state.initialized || !card_info) {
        return ESP_ERR_INVALID_ARG;
    }

    uint32_t start_time = xTaskGetTickCount() * portTICK_PERIOD_MS;

    while ((xTaskGetTickCount() * portTICK_PERIOD_MS - start_time) < timeout_ms) {
        esp_err_t err = ryrr30d_poll_card(card_info);
        if (err == ESP_OK) {
            return ESP_OK;
        }

        vTaskDelay(pdMS_TO_TICKS(50));  // Poll every 50ms
    }

    return ESP_ERR_TIMEOUT;
}

esp_err_t ryrr30d_get_version(char *version, size_t version_size) {
    if (!g_state.initialized || !version) {
        return ESP_ERR_INVALID_ARG;
    }

    return send_at_command("VER?", version, version_size, RYRR30D_CMD_TIMEOUT_MS);
}

const ryrr30d_state_t* ryrr30d_get_state(void) {
    return &g_state;
}

const char* ryrr30d_card_type_to_string(ryrr30d_card_type_t type) {
    switch (type) {
        case RYRR30D_CARD_NONE:           return "None";
        case RYRR30D_CARD_APPLE_VAS:      return "Apple Wallet";
        case RYRR30D_CARD_GOOGLE_SMARTTAP: return "Google Wallet";
        case RYRR30D_CARD_ISO14443A:      return "ISO14443A (MIFARE)";
        case RYRR30D_CARD_ISO14443B:      return "ISO14443B";
        case RYRR30D_CARD_ISO15693:       return "ISO15693";
        case RYRR30D_CARD_FELICA:         return "FeliCa";
        case RYRR30D_CARD_UNKNOWN:        return "Unknown";
        default:                          return "Invalid";
    }
}

void ryrr30d_uid_to_string(const ryrr30d_card_info_t *card_info,
                           char *str, size_t str_size) {
    if (!card_info || !str || str_size == 0) {
        return;
    }

    str[0] = '\0';

    for (int i = 0; i < card_info->uid_len && i < RYRR30D_MAX_UID_LEN; i++) {
        char hex_byte[4];
        snprintf(hex_byte, sizeof(hex_byte), "%02X", card_info->uid[i]);

        if (i > 0) {
            strncat(str, ":", str_size - strlen(str) - 1);
        }
        strncat(str, hex_byte, str_size - strlen(str) - 1);
    }
}
