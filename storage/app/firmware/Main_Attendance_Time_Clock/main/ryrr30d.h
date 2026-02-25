/**
 * RYRR30D NFC Reader Driver
 * REYAX Apple VAS & Google SmartTap NFC Module
 *
 * Communication: UART 115200 8N1
 * Supports: Apple Wallet, Google SmartTap, ISO14443A/B, ISO15693, FeliCa
 */

#ifndef RYRR30D_H
#define RYRR30D_H

#include <stdint.h>
#include <stdbool.h>
#include "esp_err.h"
#include "driver/uart.h"

#ifdef __cplusplus
extern "C" {
#endif

// ---------------------------------------------------------------------------
// UART Configuration
// ---------------------------------------------------------------------------
#define RYRR30D_UART_NUM        UART_NUM_1      // Use UART1 (UART0 is console)
#define RYRR30D_UART_BAUD       115200
#define RYRR30D_UART_TX_PIN     GPIO_NUM_22     // P4 TX → Reader RX
#define RYRR30D_UART_RX_PIN     GPIO_NUM_21     // Reader TX → P4 RX
#define RYRR30D_RST_PIN         GPIO_NUM_32     // Optional reset pin

#define RYRR30D_RX_BUF_SIZE     1024
#define RYRR30D_TX_BUF_SIZE     256

// ---------------------------------------------------------------------------
// Response timeout
// ---------------------------------------------------------------------------
#define RYRR30D_CMD_TIMEOUT_MS      1000    // AT command response timeout
#define RYRR30D_READ_TIMEOUT_MS     100     // Card read polling timeout

// ---------------------------------------------------------------------------
// Card/Pass Types
// ---------------------------------------------------------------------------
typedef enum {
    RYRR30D_CARD_NONE = 0,
    RYRR30D_CARD_APPLE_VAS,         // Apple Wallet pass
    RYRR30D_CARD_GOOGLE_SMARTTAP,   // Google Wallet pass
    RYRR30D_CARD_ISO14443A,         // MIFARE, NTAG, etc.
    RYRR30D_CARD_ISO14443B,
    RYRR30D_CARD_ISO15693,          // HF RFID tags
    RYRR30D_CARD_FELICA,            // Sony FeliCa
    RYRR30D_CARD_UNKNOWN,
} ryrr30d_card_type_t;

// ---------------------------------------------------------------------------
// Card Read Result
// ---------------------------------------------------------------------------
#define RYRR30D_MAX_UID_LEN         10
#define RYRR30D_MAX_PASS_DATA_LEN   256

typedef struct {
    ryrr30d_card_type_t type;

    // For standard NFC cards (ISO14443A/B, etc.)
    uint8_t uid[RYRR30D_MAX_UID_LEN];
    uint8_t uid_len;

    // For Apple VAS / Google SmartTap passes
    char pass_data[RYRR30D_MAX_PASS_DATA_LEN];
    uint16_t pass_data_len;

    // Raw response from reader
    char raw_response[RYRR30D_MAX_PASS_DATA_LEN];
} ryrr30d_card_info_t;

// ---------------------------------------------------------------------------
// Reader Configuration
// ---------------------------------------------------------------------------
typedef struct {
    uart_port_t uart_num;
    int tx_pin;
    int rx_pin;
    int rst_pin;            // -1 if not used

    // Apple VAS configuration (optional)
    bool apple_enabled;
    char apple_pass_type_id[64];    // PassTypeID hash (hex string)
    char apple_private_key[128];    // Private key bytes (hex string)

    // Google SmartTap configuration (optional)
    bool google_enabled;
    char google_collector_id[64];   // Collector ID (hex string)
    char google_private_key[128];   // Private key bytes (hex string)
} ryrr30d_config_t;

// ---------------------------------------------------------------------------
// Reader State
// ---------------------------------------------------------------------------
typedef struct {
    bool initialized;
    uart_port_t uart_num;
    int rst_pin;
    bool apple_configured;
    bool google_configured;
    uint32_t cards_read;
    uint32_t errors;
} ryrr30d_state_t;

// ---------------------------------------------------------------------------
// API Functions
// ---------------------------------------------------------------------------

/**
 * Initialize RYRR30D reader
 * @param config Reader configuration
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_init(const ryrr30d_config_t *config);

/**
 * Deinitialize reader
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_deinit(void);

/**
 * Hardware reset the reader
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_reset(void);

/**
 * Send AT command and get response
 * @param cmd Command string (without AT+ prefix)
 * @param response Response buffer
 * @param response_size Response buffer size
 * @param timeout_ms Timeout in milliseconds
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_send_command(const char *cmd, char *response,
                                size_t response_size, uint32_t timeout_ms);

/**
 * Configure Apple VAS pass reading
 * @param pass_type_id PassTypeID hash (hex string)
 * @param private_key Private key bytes (hex string)
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_configure_apple(const char *pass_type_id, const char *private_key);

/**
 * Configure Google SmartTap pass reading
 * @param collector_id Collector ID (hex string)
 * @param private_key Private key bytes (hex string)
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_configure_google(const char *collector_id, const char *private_key);

/**
 * Configure which card types to scan for
 * @param iso14443a Enable ISO14443A (MIFARE)
 * @param iso14443b Enable ISO14443B
 * @param iso15693 Enable ISO15693
 * @param felica Enable FeliCa
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_configure_card_types(bool iso14443a, bool iso14443b,
                                        bool iso15693, bool felica);

/**
 * Start continuous polling for cards
 * Must be called after configure_card_types()
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_start_polling(void);

/**
 * Stop polling for cards
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_stop_polling(void);

/**
 * Poll for card/pass (non-blocking check)
 * @param card_info Output card information (if card detected)
 * @return ESP_OK if card detected, ESP_ERR_NOT_FOUND if no card
 */
esp_err_t ryrr30d_poll_card(ryrr30d_card_info_t *card_info);

/**
 * Wait for card/pass (blocking)
 * @param card_info Output card information
 * @param timeout_ms Maximum time to wait
 * @return ESP_OK if card detected, ESP_ERR_TIMEOUT on timeout
 */
esp_err_t ryrr30d_wait_for_card(ryrr30d_card_info_t *card_info, uint32_t timeout_ms);

/**
 * Get reader firmware version
 * @param version Output version string buffer
 * @param version_size Buffer size
 * @return ESP_OK on success
 */
esp_err_t ryrr30d_get_version(char *version, size_t version_size);

/**
 * Get reader state
 * @return Pointer to reader state
 */
const ryrr30d_state_t* ryrr30d_get_state(void);

/**
 * Convert card type to string
 * @param type Card type
 * @return String name
 */
const char* ryrr30d_card_type_to_string(ryrr30d_card_type_t type);

/**
 * Format UID as hex string (e.g., "04:5A:B2:C3")
 * @param card_info Card info with UID
 * @param str Output string buffer
 * @param str_size Buffer size
 */
void ryrr30d_uid_to_string(const ryrr30d_card_info_t *card_info,
                           char *str, size_t str_size);

#ifdef __cplusplus
}
#endif

#endif // RYRR30D_H
