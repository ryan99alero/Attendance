/**
 * SPI Slave Handler
 * Handles SPI communication with ESP32-P4 master
 */

#ifndef SPI_SLAVE_HANDLER_H
#define SPI_SLAVE_HANDLER_H

#include <stdint.h>
#include <stdbool.h>
#include "esp_err.h"
#include "protocol.h"

// ---------------------------------------------------------------------------
// SPI Slave Pin Configuration (XIAO ESP32-S3 Sense)
// These pins must not conflict with camera DVP bus
// ---------------------------------------------------------------------------

// Available GPIO pins on XIAO ESP32-S3 Sense (after camera allocation):
// D0 (GPIO1), D1 (GPIO2), D2 (GPIO3), D3 (GPIO4), D4 (GPIO5), D5 (GPIO6)
// D6 (GPIO43/TX), D7 (GPIO44/RX), D8 (GPIO7), D9 (GPIO8), D10 (GPIO9)

#define SPI_SLAVE_MOSI_PIN  3   // D2 - Master Out Slave In
#define SPI_SLAVE_MISO_PIN  4   // D3 - Master In Slave Out
#define SPI_SLAVE_SCLK_PIN  5   // D4 - Serial Clock
#define SPI_SLAVE_CS_PIN    6   // D5 - Chip Select

// READY/IRQ pin to signal P4 when image is ready
#define READY_PIN           7   // D8 - Output to P4

// ---------------------------------------------------------------------------
// SPI Configuration
// ---------------------------------------------------------------------------
#define SPI_SLAVE_HOST      SPI2_HOST
#define SPI_DMA_CHANNEL     SPI_DMA_CH_AUTO
#define SPI_MODE            0       // CPOL=0, CPHA=0
#define SPI_QUEUE_SIZE      1

// ---------------------------------------------------------------------------
// Callback function type for command processing
// ---------------------------------------------------------------------------
typedef void (*spi_command_callback_t)(cam_spi_cmd_t cmd, const uint8_t *payload, uint16_t len);

// ---------------------------------------------------------------------------
// SPI Slave state
// ---------------------------------------------------------------------------
typedef struct {
    bool initialized;
    spi_status_t status;
    spi_error_t last_error;
    uint8_t sequence;

    // Transfer state
    bool transfer_active;
    uint32_t transfer_offset;
    uint32_t transfer_remaining;

    // Statistics
    uint32_t commands_received;
    uint32_t bytes_transferred;
    uint32_t errors;
} spi_slave_state_t;

// ---------------------------------------------------------------------------
// Function prototypes
// ---------------------------------------------------------------------------

/**
 * Initialize SPI slave interface
 * @return ESP_OK on success
 */
esp_err_t spi_slave_init(void);

/**
 * Deinitialize SPI slave interface
 * @return ESP_OK on success
 */
esp_err_t spi_slave_deinit(void);

/**
 * Start listening for SPI commands
 * This function blocks and processes commands in a loop
 * Call from a dedicated FreeRTOS task
 */
void spi_slave_task(void *pvParameters);

/**
 * Set the READY pin state
 * @param ready true = image ready, false = not ready
 */
void spi_set_ready_pin(bool ready);

/**
 * Get current SPI slave state
 * @return Pointer to state structure
 */
const spi_slave_state_t* spi_get_state(void);

/**
 * Set current status
 * @param status New status value
 */
void spi_set_status(spi_status_t status);

/**
 * Set error state
 * @param error Error code
 */
void spi_set_error(spi_error_t error);

/**
 * Clear error state
 */
void spi_clear_error(void);

/**
 * Check if SPI slave is busy
 * @return true if busy
 */
bool spi_is_busy(void);

#endif // SPI_SLAVE_HANDLER_H
