/**
 * MFRC522 RFID/NFC Driver for ESP32-P4
 * ESP-IDF Implementation
 */

#include "mfrc522.h"
#include "esp_log.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include <string.h>

static const char *TAG = "MFRC522";

// Forward declaration
static mfrc522_status_t
mfrc522_communicate_with_picc(mfrc522_handle_t *mfrc522, uint8_t command,
							  uint8_t *send_data, uint8_t send_len,
							  uint8_t *back_data, uint8_t *back_len);

// Initialize MFRC522
esp_err_t mfrc522_init(mfrc522_handle_t *mfrc522, spi_host_device_t host,
					   gpio_num_t cs_pin, gpio_num_t rst_pin) {
	esp_err_t ret;

	// Store pins
	mfrc522->cs_pin = cs_pin;
	mfrc522->rst_pin = rst_pin;

	// Configure CS pin
	gpio_config_t cs_config = {
		.pin_bit_mask = (1ULL << cs_pin),
		.mode = GPIO_MODE_OUTPUT,
		.pull_up_en = GPIO_PULLUP_DISABLE,
		.pull_down_en = GPIO_PULLDOWN_DISABLE,
		.intr_type = GPIO_INTR_DISABLE,
	};
	ret = gpio_config(&cs_config);
	if (ret != ESP_OK)
		return ret;

	// Configure RST pin
	gpio_config_t rst_config = {
		.pin_bit_mask = (1ULL << rst_pin),
		.mode = GPIO_MODE_OUTPUT,
		.pull_up_en = GPIO_PULLUP_DISABLE,
		.pull_down_en = GPIO_PULLDOWN_DISABLE,
		.intr_type = GPIO_INTR_DISABLE,
	};
	ret = gpio_config(&rst_config);
	if (ret != ESP_OK)
		return ret;

	// Set initial pin states
	gpio_set_level(cs_pin, 1);	// CS high (not selected)
	gpio_set_level(rst_pin, 1); // RST high (not in reset)

	// Configure SPI device
	spi_device_interface_config_t dev_config = {
		.clock_speed_hz = 4000000, // 4 MHz
		.mode = 0,				   // SPI mode 0
		.spics_io_num = cs_pin,
		.queue_size = 1,
		.flags = SPI_DEVICE_HALFDUPLEX,
	};

	ret = spi_bus_add_device(host, &dev_config, &mfrc522->spi_handle);
	if (ret != ESP_OK) {
		ESP_LOGE(TAG, "Failed to add SPI device: %s", esp_err_to_name(ret));
		return ret;
	}

	// Hardware reset
	gpio_set_level(rst_pin, 0);
	vTaskDelay(pdMS_TO_TICKS(10));
	gpio_set_level(rst_pin, 1);
	vTaskDelay(pdMS_TO_TICKS(100));

	// Soft reset
	mfrc522_write_register(mfrc522, MFRC522_REG_COMMAND,
						   MFRC522_CMD_SOFT_RESET);
	vTaskDelay(pdMS_TO_TICKS(50));

	// Check version register
	uint8_t version = mfrc522_read_register(mfrc522, MFRC522_REG_VERSION);
	ESP_LOGI(TAG, "MFRC522 version: 0x%02X", version);

	if (version == 0x91 || version == 0x92 || version == 0x88) {
		ESP_LOGI(TAG, "MFRC522 initialized successfully");

		// Configure for 14443A
		mfrc522_write_register(mfrc522, MFRC522_REG_T_MODE, 0x8D);
		mfrc522_write_register(mfrc522, MFRC522_REG_T_PRESCALER, 0x3E);
		mfrc522_write_register(mfrc522, MFRC522_REG_T_RELOAD_L, 30);
		mfrc522_write_register(mfrc522, MFRC522_REG_T_RELOAD_H, 0);

		mfrc522_write_register(mfrc522, MFRC522_REG_TX_ASK, 0x40);
		mfrc522_write_register(mfrc522, MFRC522_REG_MODE, 0x3D);

		// Turn on antenna
		uint8_t tx_control =
			mfrc522_read_register(mfrc522, MFRC522_REG_TX_CONTROL);
		if (!(tx_control & 0x03)) {
			mfrc522_write_register(mfrc522, MFRC522_REG_TX_CONTROL,
								   tx_control | 0x03);
		}

		return ESP_OK;
	} else {
		ESP_LOGE(TAG, "Invalid MFRC522 version: 0x%02X", version);
		return ESP_ERR_INVALID_RESPONSE;
	}
}

// Deinitialize MFRC522
esp_err_t mfrc522_deinit(mfrc522_handle_t *mfrc522) {
	return spi_bus_remove_device(mfrc522->spi_handle);
}

// Read register
uint8_t mfrc522_read_register(mfrc522_handle_t *mfrc522, uint8_t reg) {
	spi_transaction_t trans = {
		.flags = SPI_TRANS_USE_TXDATA,
		.length = 16,
		.tx_data = {(reg << 1) | 0x80, 0x00}, // Read command
		.rx_buffer = NULL,
		.rxlength = 16,
	};

	uint8_t rx_data[2];
	trans.rx_buffer = rx_data;

	esp_err_t ret = spi_device_transmit(mfrc522->spi_handle, &trans);
	if (ret != ESP_OK) {
		ESP_LOGE(TAG, "SPI read failed: %s", esp_err_to_name(ret));
		return 0;
	}

	return rx_data[1];
}

// Write register
esp_err_t mfrc522_write_register(mfrc522_handle_t *mfrc522, uint8_t reg,
								 uint8_t value) {
	spi_transaction_t trans = {
		.flags = SPI_TRANS_USE_TXDATA,
		.length = 16,
		.tx_data = {(reg << 1) & 0x7E, value}, // Write command
	};

	return spi_device_transmit(mfrc522->spi_handle, &trans);
}

// Check if new card is present
bool mfrc522_is_new_card_present(mfrc522_handle_t *mfrc522) {
	uint8_t buffer[2];
	uint8_t buffer_size = 2;

	mfrc522_write_register(mfrc522, MFRC522_REG_BIT_FRAMING, 0x07);

	// Send REQA command
	buffer[0] = PICC_CMD_REQA;
	mfrc522_status_t status = mfrc522_communicate_with_picc(
		mfrc522, MFRC522_CMD_TRANSCEIVE, buffer, 1, buffer, &buffer_size);

	return (status == STATUS_OK || status == STATUS_COLLISION);
}

// Internal function to communicate with PICC
static mfrc522_status_t
mfrc522_communicate_with_picc(mfrc522_handle_t *mfrc522, uint8_t command,
							  uint8_t *send_data, uint8_t send_len,
							  uint8_t *back_data, uint8_t *back_len) {
	uint8_t irq_wait = 0x00;
	uint8_t last_bits = 0;

	switch (command) {
	case MFRC522_CMD_MF_AUTHENT:
		irq_wait = 0x10;
		break;
	case MFRC522_CMD_TRANSCEIVE:
		irq_wait = 0x30;
		break;
	default:
		break;
	}

	// Clear interrupts
	mfrc522_write_register(mfrc522, MFRC522_REG_COM_IRQ, 0x7F);
	mfrc522_write_register(mfrc522, MFRC522_REG_FIFO_LEVEL, 0x80);

	// Write data to FIFO
	for (uint8_t i = 0; i < send_len; i++) {
		mfrc522_write_register(mfrc522, MFRC522_REG_FIFO_DATA, send_data[i]);
	}

	// Execute command
	mfrc522_write_register(mfrc522, MFRC522_REG_COMMAND, command);

	if (command == MFRC522_CMD_TRANSCEIVE) {
		uint8_t bit_framing =
			mfrc522_read_register(mfrc522, MFRC522_REG_BIT_FRAMING);
		mfrc522_write_register(mfrc522, MFRC522_REG_BIT_FRAMING,
							   bit_framing | 0x80);
	}

	// Wait for command to complete
	int timeout = 2000;
	uint8_t n;
	do {
		n = mfrc522_read_register(mfrc522, MFRC522_REG_COM_IRQ);
		timeout--;
		if (timeout == 0) {
			return STATUS_TIMEOUT;
		}
	} while (!(n & 0x01) && !(n & irq_wait));

	uint8_t error_reg_value = mfrc522_read_register(mfrc522, MFRC522_REG_ERROR);

	if (error_reg_value & 0x13) {
		return STATUS_ERROR;
	}

	mfrc522_status_t status = STATUS_OK;

	if (n & irq_wait & 0x01) {
		status = STATUS_TIMEOUT;
	}

	if (n & 0x08) {
		status = STATUS_COLLISION;
	}

	if (status == STATUS_OK) {
		uint8_t fifo_level =
			mfrc522_read_register(mfrc522, MFRC522_REG_FIFO_LEVEL);
		if (fifo_level > *back_len) {
			return STATUS_NO_ROOM;
		}
		*back_len = fifo_level;

		// Read data from FIFO
		for (uint8_t i = 0; i < fifo_level; i++) {
			back_data[i] =
				mfrc522_read_register(mfrc522, MFRC522_REG_FIFO_DATA);
		}

		uint8_t control_reg =
			mfrc522_read_register(mfrc522, MFRC522_REG_CONTROL);
		last_bits = control_reg & 0x07;
	}

	// Suppress unused variable warning
	(void)last_bits;

	return status;
}

// Get PICC type from SAK
picc_type_t mfrc522_picc_get_type(uint8_t sak) {
	if (sak & 0x04) {
		return PICC_TYPE_NOT_COMPLETE;
	}

	switch (sak) {
	case 0x09:
		return PICC_TYPE_MIFARE_MINI;
	case 0x08:
		return PICC_TYPE_MIFARE_1K;
	case 0x18:
		return PICC_TYPE_MIFARE_4K;
	case 0x00:
		return PICC_TYPE_MIFARE_UL;
	case 0x10:
	case 0x11:
		return PICC_TYPE_MIFARE_PLUS;
	case 0x01:
		return PICC_TYPE_TNP3XXX;
	case 0x20:
		return PICC_TYPE_ISO_14443_4;
	case 0x40:
		return PICC_TYPE_ISO_18092;
	default:
		return PICC_TYPE_UNKNOWN;
	}
}

// Get PICC type name
const char *mfrc522_picc_get_type_name(picc_type_t type) {
	switch (type) {
	case PICC_TYPE_ISO_14443_4:
		return "ISO 14443-4";
	case PICC_TYPE_ISO_18092:
		return "ISO 18092 (NFC)";
	case PICC_TYPE_MIFARE_MINI:
		return "MIFARE Mini";
	case PICC_TYPE_MIFARE_1K:
		return "MIFARE 1KB";
	case PICC_TYPE_MIFARE_4K:
		return "MIFARE 4KB";
	case PICC_TYPE_MIFARE_UL:
		return "MIFARE Ultralight";
	case PICC_TYPE_MIFARE_PLUS:
		return "MIFARE Plus";
	case PICC_TYPE_MIFARE_DESFIRE:
		return "MIFARE DESFire";
	case PICC_TYPE_TNP3XXX:
		return "TNP3XXX";
	case PICC_TYPE_NOT_COMPLETE:
		return "SAK incomplete";
	case PICC_TYPE_UNKNOWN:
	default:
		return "Unknown";
	}
}

// Halt PICC
void mfrc522_picc_halt_a(mfrc522_handle_t *mfrc522) {
	uint8_t buffer[4];
	buffer[0] = PICC_CMD_HLTA;
	buffer[1] = 0;

	// Calculate CRC
	uint8_t buffer_size = 4;
	mfrc522_communicate_with_picc(mfrc522, MFRC522_CMD_CALC_CRC, buffer, 2,
								  &buffer[2], &buffer_size);

	// Send command
	buffer_size = 4;
	mfrc522_communicate_with_picc(mfrc522, MFRC522_CMD_TRANSCEIVE, buffer, 4,
								  NULL, &buffer_size);
}

// Self test
mfrc522_status_t mfrc522_self_test(mfrc522_handle_t *mfrc522) {
	// Soft reset
	mfrc522_write_register(mfrc522, MFRC522_REG_COMMAND,
						   MFRC522_CMD_SOFT_RESET);
	vTaskDelay(pdMS_TO_TICKS(50));

	// Clear FIFO
	mfrc522_write_register(mfrc522, MFRC522_REG_FIFO_LEVEL, 0x80);

	// Write test data to FIFO
	const uint8_t test_data[] = {0x00, 0x00, 0x00, 0x00};
	for (int i = 0; i < 4; i++) {
		mfrc522_write_register(mfrc522, MFRC522_REG_FIFO_DATA, test_data[i]);
	}

	// Start self test
	mfrc522_write_register(mfrc522, MFRC522_REG_COMMAND, MFRC522_CMD_MEM);

	// Wait for self test to complete
	vTaskDelay(pdMS_TO_TICKS(25));

	// Read FIFO
	uint8_t result[64];
	for (int i = 0; i < 64; i++) {
		result[i] = mfrc522_read_register(mfrc522, MFRC522_REG_FIFO_DATA);
	}

	// Expected result for version 1.0
	const uint8_t expected_v1[] = {
		0x00, 0xC6, 0x37, 0xD5, 0x32, 0xB7, 0x57, 0x5C, 0xC2, 0xD8, 0x7C,
		0x4D, 0xD9, 0x70, 0xC7, 0x73, 0x10, 0xE6, 0xD2, 0xAA, 0x5E, 0xA1,
		0x3E, 0x5A, 0x14, 0xAF, 0x30, 0x61, 0xC9, 0x70, 0xDB, 0x2E, 0x64,
		0x22, 0x72, 0xB5, 0xBD, 0x65, 0xF4, 0xEC, 0x22, 0xBC, 0xD3, 0x72,
		0x35, 0xCD, 0xAA, 0x41, 0x1F, 0xA7, 0xF3, 0x53, 0x14, 0xDE, 0x7E,
		0x02, 0xD9, 0x0F, 0xB5, 0x5E, 0x25, 0x1D, 0x29, 0x79};

	// Compare results (first 64 bytes)
	bool self_test_passed = true;
	for (int i = 0; i < 64; i++) {
		if (result[i] != expected_v1[i]) {
			self_test_passed = false;
			break;
		}
	}

	// Re-initialize after self test
	mfrc522_write_register(mfrc522, MFRC522_REG_COMMAND,
						   MFRC522_CMD_SOFT_RESET);
	vTaskDelay(pdMS_TO_TICKS(50));

	return self_test_passed ? STATUS_OK : STATUS_ERROR;
}

// Read card serial (simplified version)
mfrc522_status_t mfrc522_read_card_serial(mfrc522_handle_t *mfrc522,
										  uid_t *uid) {
	// This is a simplified implementation
	// Full implementation would include collision resolution

	uint8_t buffer[9];
	uint8_t buffer_size = 9;

	// Send SELECT CL1
	buffer[0] = PICC_CMD_SEL_CL1;
	buffer[1] = 0x70;

	mfrc522_status_t status = mfrc522_communicate_with_picc(
		mfrc522, MFRC522_CMD_TRANSCEIVE, buffer, 2, buffer, &buffer_size);

	if (status != STATUS_OK) {
		return status;
	}

	// Extract UID (simplified - assumes 4-byte UID)
	if (buffer_size >= 5) {
		uid->size = 4;
		for (int i = 0; i < 4; i++) {
			uid->uidByte[i] = buffer[i];
		}
		uid->sak = buffer[4];
	}

	return STATUS_OK;
}