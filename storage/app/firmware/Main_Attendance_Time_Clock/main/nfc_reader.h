/**
 * NFC Reader Abstraction Layer
 *
 * Provides unified interface for PN532 and PN5180 NFC readers
 * Allows switching between reader types without changing application code
 */

#ifndef NFC_READER_H
#define NFC_READER_H

#include "driver/spi_master.h"
#include "esp_err.h"
#include <stdbool.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

// NFC reader types
typedef enum {
	NFC_READER_PN532,  // NXP PN532 - NFC Forum certified, good for MIFARE
	NFC_READER_PN5180, // NXP PN5180 - Higher performance, better range
} nfc_reader_type_t;

// Card UID structure (compatible with both readers)
#define NFC_MAX_UID_LENGTH 10

typedef struct {
	uint8_t uid[NFC_MAX_UID_LENGTH]; // UID bytes
	uint8_t size;					 // UID size (4, 7, or 10 bytes)
	uint8_t sak;					 // Select acknowledge (card type indicator)
	uint16_t atqa;					 // Answer to request (card capability)
} nfc_card_uid_t;

// Card types (ISO14443A)
typedef enum {
	NFC_CARD_TYPE_UNKNOWN = 0,
	NFC_CARD_TYPE_MIFARE_MINI,
	NFC_CARD_TYPE_MIFARE_1K,
	NFC_CARD_TYPE_MIFARE_4K,
	NFC_CARD_TYPE_MIFARE_UL,
	NFC_CARD_TYPE_MIFARE_PLUS,
	NFC_CARD_TYPE_MIFARE_DESFIRE,
	NFC_CARD_TYPE_NTAG_213,
	NFC_CARD_TYPE_NTAG_215,
	NFC_CARD_TYPE_NTAG_216,
} nfc_card_type_t;

// NFC reader configuration
typedef struct {
	nfc_reader_type_t type;		// Reader chip type
	spi_host_device_t spi_host; // SPI host (SPI2_HOST or SPI3_HOST)
	int miso_pin;				// MISO GPIO
	int mosi_pin;				// MOSI GPIO
	int sck_pin;				// SCK GPIO
	int cs_pin;					// Chip select GPIO
	int rst_pin;				// Reset GPIO
	int irq_pin;				// Interrupt GPIO (optional, -1 if not used)
	uint32_t spi_speed_hz;		// SPI clock speed (default: 5000000 for PN532,
								// 7000000 for PN5180)
} nfc_reader_config_t;

// NFC reader handle
typedef void *nfc_reader_handle_t;

/**
 * @brief Initialize NFC reader
 *
 * @param config Reader configuration
 * @param out_handle Output handle for the reader
 * @return ESP_OK on success
 */
esp_err_t nfc_reader_init(const nfc_reader_config_t *config,
						  nfc_reader_handle_t *out_handle);

/**
 * @brief Deinitialize NFC reader
 *
 * @param handle Reader handle
 * @return ESP_OK on success
 */
esp_err_t nfc_reader_deinit(nfc_reader_handle_t handle);

/**
 * @brief Get firmware version
 *
 * @param handle Reader handle
 * @param version Output firmware version (32-bit value)
 * @return ESP_OK on success
 */
esp_err_t nfc_reader_get_firmware_version(nfc_reader_handle_t handle,
										  uint32_t *version);

/**
 * @brief Check if a card is present in the field
 *
 * @param handle Reader handle
 * @return true if card detected
 */
bool nfc_reader_is_card_present(nfc_reader_handle_t handle);

/**
 * @brief Read card UID
 *
 * @param handle Reader handle
 * @param uid Output UID structure
 * @return ESP_OK on success
 */
esp_err_t nfc_reader_read_card_uid(nfc_reader_handle_t handle,
								   nfc_card_uid_t *uid);

/**
 * @brief Get card type from UID
 *
 * @param uid Card UID structure
 * @return Card type
 */
nfc_card_type_t nfc_reader_get_card_type(const nfc_card_uid_t *uid);

/**
 * @brief Get card type name
 *
 * @param type Card type
 * @return String name of card type
 */
const char *nfc_reader_get_card_type_name(nfc_card_type_t type);

/**
 * @brief Halt the current card
 *
 * @param handle Reader handle
 * @return ESP_OK on success
 */
esp_err_t nfc_reader_halt_card(nfc_reader_handle_t handle);

/**
 * @brief Format UID as hex string (e.g. "04:5A:B2:C3")
 *
 * @param uid Card UID structure
 * @param str Output string buffer
 * @param str_size Size of output buffer
 */
void nfc_reader_uid_to_string(const nfc_card_uid_t *uid, char *str,
							  size_t str_size);

#ifdef __cplusplus
}
#endif

#endif // NFC_READER_H
