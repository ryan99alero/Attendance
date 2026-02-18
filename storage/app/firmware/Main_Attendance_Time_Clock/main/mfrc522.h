/**
 * MFRC522 RFID/NFC Driver for ESP32-P4
 * ESP-IDF Implementation
 */

#ifndef MFRC522_H
#define MFRC522_H

#include "driver/gpio.h"
#include "driver/spi_master.h"
#include <stdbool.h>
#include <stdint.h>

// MFRC522 Registers
#define MFRC522_REG_COMMAND 0x01
#define MFRC522_REG_COM_IRQ 0x04
#define MFRC522_REG_DIV_IRQ 0x05
#define MFRC522_REG_ERROR 0x06
#define MFRC522_REG_STATUS1 0x07
#define MFRC522_REG_STATUS2 0x08
#define MFRC522_REG_FIFO_DATA 0x09
#define MFRC522_REG_FIFO_LEVEL 0x0A
#define MFRC522_REG_CONTROL 0x0C
#define MFRC522_REG_BIT_FRAMING 0x0D
#define MFRC522_REG_MODE 0x11
#define MFRC522_REG_TX_CONTROL 0x14
#define MFRC522_REG_TX_ASK 0x15
#define MFRC522_REG_CRC_RESULT_H 0x21
#define MFRC522_REG_CRC_RESULT_L 0x22
#define MFRC522_REG_MOD_WIDTH 0x24
#define MFRC522_REG_RF_CFG 0x26
#define MFRC522_REG_GS_N 0x27
#define MFRC522_REG_CW_GS_P 0x28
#define MFRC522_REG_MOD_GS_P 0x29
#define MFRC522_REG_T_MODE 0x2A
#define MFRC522_REG_T_PRESCALER 0x2B
#define MFRC522_REG_T_RELOAD_H 0x2C
#define MFRC522_REG_T_RELOAD_L 0x2D
#define MFRC522_REG_VERSION 0x37

// MFRC522 Commands
#define MFRC522_CMD_IDLE 0x00
#define MFRC522_CMD_MEM 0x01
#define MFRC522_CMD_GENERATE_RANDOM_ID 0x02
#define MFRC522_CMD_CALC_CRC 0x03
#define MFRC522_CMD_TRANSMIT 0x04
#define MFRC522_CMD_NO_CMD_CHANGE 0x07
#define MFRC522_CMD_RECEIVE 0x08
#define MFRC522_CMD_TRANSCEIVE 0x0C
#define MFRC522_CMD_MF_AUTHENT 0x0E
#define MFRC522_CMD_SOFT_RESET 0x0F

// PICC Commands
#define PICC_CMD_REQA 0x26
#define PICC_CMD_WUPA 0x52
#define PICC_CMD_CT 0x88
#define PICC_CMD_SEL_CL1 0x93
#define PICC_CMD_SEL_CL2 0x95
#define PICC_CMD_SEL_CL3 0x97
#define PICC_CMD_HLTA 0x50
#define PICC_CMD_RATS 0xE0

// Card types
typedef enum {
	PICC_TYPE_UNKNOWN,
	PICC_TYPE_ISO_14443_4,
	PICC_TYPE_ISO_18092,
	PICC_TYPE_MIFARE_MINI,
	PICC_TYPE_MIFARE_1K,
	PICC_TYPE_MIFARE_4K,
	PICC_TYPE_MIFARE_UL,
	PICC_TYPE_MIFARE_PLUS,
	PICC_TYPE_MIFARE_DESFIRE,
	PICC_TYPE_TNP3XXX,
	PICC_TYPE_NOT_COMPLETE
} picc_type_t;

// Status codes
typedef enum {
	STATUS_OK,
	STATUS_ERROR,
	STATUS_COLLISION,
	STATUS_TIMEOUT,
	STATUS_NO_ROOM,
	STATUS_INTERNAL_ERROR,
	STATUS_INVALID,
	STATUS_CRC_WRONG,
	STATUS_MIFARE_NACK
} mfrc522_status_t;

// UID structure
typedef struct {
	uint8_t uidByte[10];
	uint8_t size;
	uint8_t sak;
} uid_t;

// MFRC522 Handle
typedef struct {
	spi_device_handle_t spi_handle;
	gpio_num_t cs_pin;
	gpio_num_t rst_pin;
} mfrc522_handle_t;

// Function prototypes
esp_err_t mfrc522_init(mfrc522_handle_t *mfrc522, spi_host_device_t host,
					   gpio_num_t cs_pin, gpio_num_t rst_pin);
esp_err_t mfrc522_deinit(mfrc522_handle_t *mfrc522);

uint8_t mfrc522_read_register(mfrc522_handle_t *mfrc522, uint8_t reg);
esp_err_t mfrc522_write_register(mfrc522_handle_t *mfrc522, uint8_t reg,
								 uint8_t value);

mfrc522_status_t mfrc522_self_test(mfrc522_handle_t *mfrc522);
bool mfrc522_is_new_card_present(mfrc522_handle_t *mfrc522);
mfrc522_status_t mfrc522_read_card_serial(mfrc522_handle_t *mfrc522,
										  uid_t *uid);
void mfrc522_picc_halt_a(mfrc522_handle_t *mfrc522);

picc_type_t mfrc522_picc_get_type(uint8_t sak);
const char *mfrc522_picc_get_type_name(picc_type_t type);

#endif // MFRC522_H