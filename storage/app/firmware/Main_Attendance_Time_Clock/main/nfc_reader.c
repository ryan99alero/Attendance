/**
 * NFC Reader Abstraction Layer - Implementation
 *
 * Currently supports PN532, designed for easy PN5180 integration
 */

#include "nfc_reader.h"
#include "esp_log.h"
#include "pn532.h"
#include "pn532_driver.h"
#include "pn532_driver_spi.h"
#include <string.h>
#include <stdlib.h>

static const char *TAG = "NFC_READER";

// Internal reader structure
typedef struct {
	nfc_reader_type_t type;
	union {
		struct pn532_io_t pn532; // PN532 driver IO handle
		// Future: pn5180_t pn5180;
	} driver;
} nfc_reader_t;

esp_err_t nfc_reader_init(const nfc_reader_config_t *config,
						  nfc_reader_handle_t *out_handle) {
	if (!config || !out_handle) {
		return ESP_ERR_INVALID_ARG;
	}

	// Allocate reader structure
	nfc_reader_t *reader = (nfc_reader_t *)calloc(1, sizeof(nfc_reader_t));
	if (!reader) {
		return ESP_ERR_NO_MEM;
	}

	reader->type = config->type;

	esp_err_t ret = ESP_OK;

	switch (config->type) {
	case NFC_READER_PN532: {
		ESP_LOGI(TAG, "Initializing PN532 reader");
		ESP_LOGI(TAG, "  SPI Host: %d", config->spi_host);
		ESP_LOGI(TAG, "  MISO Pin: %d", config->miso_pin);
		ESP_LOGI(TAG, "  MOSI Pin: %d", config->mosi_pin);
		ESP_LOGI(TAG, "  SCK Pin: %d", config->sck_pin);
		ESP_LOGI(TAG, "  CS Pin: %d", config->cs_pin);
		ESP_LOGI(TAG, "  RST Pin: %d", config->rst_pin);
		ESP_LOGI(TAG, "  IRQ Pin: %d", config->irq_pin);

		// Get SPI speed (default 5 MHz for PN532)
		uint32_t spi_speed =
			config->spi_speed_hz > 0 ? config->spi_speed_hz : 5000000;

		// Initialize PN532 SPI driver using Garag library API
		// Pass all GPIO pins - the library will handle SPI bus initialization
		ret = pn532_new_driver_spi(
			config->miso_pin,      // MISO pin
			config->mosi_pin,      // MOSI pin
			config->sck_pin,       // SCK pin
			config->cs_pin,        // CS pin
			config->rst_pin,       // Reset pin
			config->irq_pin,       // IRQ pin
			config->spi_host,      // SPI host
			spi_speed,             // Clock frequency
			&reader->driver.pn532  // IO handle
		);

		if (ret != ESP_OK) {
			ESP_LOGE(TAG, "PN532 driver init failed: %s", esp_err_to_name(ret));
			free(reader);
			return ret;
		}

		// Initialize PN532 hardware (reset, SAM config, etc.)
		ret = pn532_init(&reader->driver.pn532);
		if (ret != ESP_OK) {
			ESP_LOGE(TAG, "PN532 init failed: %s", esp_err_to_name(ret));
			pn532_delete_driver(&reader->driver.pn532);
			free(reader);
			return ret;
		}

		ESP_LOGI(TAG, "PN532 initialized successfully");
		break;
	}

	case NFC_READER_PN5180:
		ESP_LOGE(TAG, "PN5180 not yet implemented");
		free(reader);
		return ESP_ERR_NOT_SUPPORTED;

	default:
		ESP_LOGE(TAG, "Unknown reader type: %d", config->type);
		free(reader);
		return ESP_ERR_INVALID_ARG;
	}

	*out_handle = reader;
	return ESP_OK;
}

esp_err_t nfc_reader_deinit(nfc_reader_handle_t handle) {
	if (!handle) {
		return ESP_ERR_INVALID_ARG;
	}

	nfc_reader_t *reader = (nfc_reader_t *)handle;

	// Deinitialize based on type
	switch (reader->type) {
	case NFC_READER_PN532:
		pn532_release(&reader->driver.pn532);
		pn532_delete_driver(&reader->driver.pn532);
		break;

	case NFC_READER_PN5180:
		// Future: PN5180 cleanup
		break;

	default:
		break;
	}

	free(reader);
	return ESP_OK;
}

esp_err_t nfc_reader_get_firmware_version(nfc_reader_handle_t handle,
										  uint32_t *version) {
	if (!handle || !version) {
		return ESP_ERR_INVALID_ARG;
	}

	nfc_reader_t *reader = (nfc_reader_t *)handle;

	switch (reader->type) {
	case NFC_READER_PN532:
		// Use the correct Garag library API
		return pn532_get_firmware_version(&reader->driver.pn532, version);

	case NFC_READER_PN5180:
		return ESP_ERR_NOT_SUPPORTED;

	default:
		return ESP_ERR_INVALID_ARG;
	}
}

bool nfc_reader_is_card_present(nfc_reader_handle_t handle) {
	if (!handle) {
		return false;
	}

	nfc_reader_t *reader = (nfc_reader_t *)handle;

	switch (reader->type) {
	case NFC_READER_PN532: {
		uint8_t uid[10];
		uint8_t uid_len;
		// Use correct Garag library API: pn532_read_passive_target_id
		return pn532_read_passive_target_id(&reader->driver.pn532,
											PN532_BRTY_ISO14443A_106KBPS,
											uid, &uid_len, 100) == ESP_OK;
	}

	case NFC_READER_PN5180:
		return false; // Not implemented

	default:
		return false;
	}
}

esp_err_t nfc_reader_read_card_uid(nfc_reader_handle_t handle,
								   nfc_card_uid_t *uid) {
	if (!handle || !uid) {
		return ESP_ERR_INVALID_ARG;
	}

	nfc_reader_t *reader = (nfc_reader_t *)handle;

	switch (reader->type) {
	case NFC_READER_PN532: {
		uint8_t temp_uid[10];
		uint8_t uid_len;

		// Use correct Garag library API: pn532_read_passive_target_id
		esp_err_t ret = pn532_read_passive_target_id(
			&reader->driver.pn532,
			PN532_BRTY_ISO14443A_106KBPS,  // ISO14443A at 106 kbps
			temp_uid,
			&uid_len,
			1000  // 1 second timeout
		);

		if (ret == ESP_OK) {
			memcpy(uid->uid, temp_uid, uid_len);
			uid->size = uid_len;
			// TODO: Get SAK and ATQA from PN532 response buffer
			// For now, set to 0 - we can extract these later if needed
			uid->sak = 0;
			uid->atqa = 0;
		}
		return ret;
	}

	case NFC_READER_PN5180:
		return ESP_ERR_NOT_SUPPORTED;

	default:
		return ESP_ERR_INVALID_ARG;
	}
}

nfc_card_type_t nfc_reader_get_card_type(const nfc_card_uid_t *uid) {
	if (!uid) {
		return NFC_CARD_TYPE_UNKNOWN;
	}

	// Determine card type based on SAK (Select Acknowledge)
	switch (uid->sak) {
	case 0x09:
		return NFC_CARD_TYPE_MIFARE_MINI;
	case 0x08:
		return NFC_CARD_TYPE_MIFARE_1K;
	case 0x18:
		return NFC_CARD_TYPE_MIFARE_4K;
	case 0x00:
		return NFC_CARD_TYPE_MIFARE_UL;
	case 0x10:
	case 0x11:
		return NFC_CARD_TYPE_MIFARE_PLUS;
	case 0x20:
		return NFC_CARD_TYPE_MIFARE_DESFIRE;
	case 0x44:
		// Could be NTAG - check UID size
		if (uid->size == 7) {
			return NFC_CARD_TYPE_NTAG_213; // Could also be 215/216
		}
		return NFC_CARD_TYPE_UNKNOWN;
	default:
		return NFC_CARD_TYPE_UNKNOWN;
	}
}

const char *nfc_reader_get_card_type_name(nfc_card_type_t type) {
	switch (type) {
	case NFC_CARD_TYPE_MIFARE_MINI:
		return "MIFARE Mini";
	case NFC_CARD_TYPE_MIFARE_1K:
		return "MIFARE Classic 1K";
	case NFC_CARD_TYPE_MIFARE_4K:
		return "MIFARE Classic 4K";
	case NFC_CARD_TYPE_MIFARE_UL:
		return "MIFARE Ultralight";
	case NFC_CARD_TYPE_MIFARE_PLUS:
		return "MIFARE Plus";
	case NFC_CARD_TYPE_MIFARE_DESFIRE:
		return "MIFARE DESFire";
	case NFC_CARD_TYPE_NTAG_213:
		return "NTAG213";
	case NFC_CARD_TYPE_NTAG_215:
		return "NTAG215";
	case NFC_CARD_TYPE_NTAG_216:
		return "NTAG216";
	default:
		return "Unknown";
	}
}

esp_err_t nfc_reader_halt_card(nfc_reader_handle_t handle) {
	if (!handle) {
		return ESP_ERR_INVALID_ARG;
	}

	nfc_reader_t *reader = (nfc_reader_t *)handle;

	switch (reader->type) {
	case NFC_READER_PN532:
		// PN532 automatically halts after read timeout
		return ESP_OK;

	case NFC_READER_PN5180:
		return ESP_ERR_NOT_SUPPORTED;

	default:
		return ESP_ERR_INVALID_ARG;
	}
}

void nfc_reader_uid_to_string(const nfc_card_uid_t *uid, char *str,
							  size_t str_size) {
	if (!uid || !str || str_size == 0) {
		return;
	}

	str[0] = '\0'; // Clear string

	for (int i = 0; i < uid->size && i < NFC_MAX_UID_LENGTH; i++) {
		char hex_byte[4];
		snprintf(hex_byte, sizeof(hex_byte), "%02X", uid->uid[i]);

		if (i > 0) {
			strncat(str, ":", str_size - strlen(str) - 1);
		}
		strncat(str, hex_byte, str_size - strlen(str) - 1);
	}
}
