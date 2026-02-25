/**
 * SPI Communication Protocol
 * Shared between ESP32-P4 (Master) and XIAO ESP32-S3 (Slave)
 *
 * IMPORTANT: This file must be kept in sync between both projects.
 * Consider using a git submodule or copy script.
 */

#ifndef PROTOCOL_H
#define PROTOCOL_H

#include <stdint.h>

// Protocol version for compatibility checking
#define PROTOCOL_VERSION_MAJOR 1
#define PROTOCOL_VERSION_MINOR 0

// SPI Transfer Configuration
#define SPI_CHUNK_SIZE      4096    // Max bytes per SPI transaction (DMA buffer size)
#define SPI_MAX_IMAGE_SIZE  65536   // Max supported image size (64KB)

// ---------------------------------------------------------------------------
// Commands sent from P4 (Master) to S3 (Slave)
// Note: Type name prefixed with 'cam_' to avoid conflict with ESP-IDF's spi_command_t
// ---------------------------------------------------------------------------
typedef enum {
    CAM_CMD_NOP             = 0x00,  // No operation (used for polling)
    CAM_CMD_CAPTURE_PHOTO   = 0x01,  // Trigger camera capture
    CAM_CMD_GET_STATUS      = 0x02,  // Query slave status (ready, busy, error)
    CAM_CMD_GET_IMAGE_INFO  = 0x03,  // Get image size in bytes before transfer
    CAM_CMD_GET_IMAGE_DATA  = 0x04,  // Transfer image data (chunked)
    CAM_CMD_ABORT_TRANSFER  = 0x05,  // Abort current transfer

    // Configuration commands
    CAM_CMD_SET_RESOLUTION  = 0x10,  // Configure capture resolution
    CAM_CMD_SET_QUALITY     = 0x11,  // Configure JPEG quality
    CAM_CMD_SET_BRIGHTNESS  = 0x12,  // Adjust camera brightness
    CAM_CMD_SET_CONTRAST    = 0x13,  // Adjust camera contrast
    CAM_CMD_GET_CONFIG      = 0x14,  // Get current camera configuration

    // System commands
    CAM_CMD_PING            = 0xFE,  // Health check / keepalive
    CAM_CMD_RESET           = 0xFF,  // Soft reset camera module
} cam_spi_cmd_t;

// ---------------------------------------------------------------------------
// Status responses from S3 (Slave) to P4 (Master)
// ---------------------------------------------------------------------------
typedef enum {
    STATUS_IDLE         = 0x00,  // Ready for commands
    STATUS_CAPTURING    = 0x01,  // Camera capture in progress
    STATUS_PROCESSING   = 0x02,  // Processing image (JPEG encoding)
    STATUS_READY        = 0x03,  // Image captured and ready for transfer
    STATUS_TRANSFERRING = 0x04,  // Transfer in progress
    STATUS_BUSY         = 0x80,  // Generic busy state
    STATUS_ERROR        = 0xFF,  // Error occurred
} spi_status_t;

// ---------------------------------------------------------------------------
// Error codes
// ---------------------------------------------------------------------------
typedef enum {
    ERR_NONE            = 0x00,  // No error
    ERR_UNKNOWN_CMD     = 0x01,  // Unknown command received
    ERR_INVALID_PARAM   = 0x02,  // Invalid parameter value
    ERR_CAMERA_INIT     = 0x10,  // Camera initialization failed
    ERR_CAMERA_CAPTURE  = 0x11,  // Camera capture failed
    ERR_NO_IMAGE        = 0x12,  // No image available for transfer
    ERR_TRANSFER_ABORT  = 0x13,  // Transfer was aborted
    ERR_BUFFER_OVERFLOW = 0x20,  // Buffer overflow
    ERR_TIMEOUT         = 0x30,  // Operation timed out
    ERR_INTERNAL        = 0xFF,  // Internal error
} spi_error_t;

// ---------------------------------------------------------------------------
// Resolution options
// ---------------------------------------------------------------------------
typedef enum {
    RES_QQVGA   = 0,    // 160x120
    RES_QVGA    = 1,    // 320x240
    RES_VGA     = 2,    // 640x480 (default for face recognition)
    RES_SVGA    = 3,    // 800x600
    RES_XGA     = 4,    // 1024x768
    RES_SXGA    = 5,    // 1280x1024
    RES_UXGA    = 6,    // 1600x1200
    RES_QXGA    = 7,    // 2048x1536
    RES_5MP     = 8,    // 2592x1944 (full OV5640 resolution)
} camera_resolution_t;

// ---------------------------------------------------------------------------
// Command packet (Master -> Slave)
// ---------------------------------------------------------------------------
typedef struct __attribute__((packed)) {
    uint8_t  command;       // cam_spi_cmd_t
    uint8_t  sequence;      // Sequence number for tracking
    uint16_t payload_len;   // Length of optional payload
    // Variable-length payload follows (if payload_len > 0)
} cam_spi_cmd_packet_t;

#define SPI_CMD_HEADER_SIZE sizeof(cam_spi_cmd_packet_t)

// ---------------------------------------------------------------------------
// Response header (Slave -> Master)
// ---------------------------------------------------------------------------
typedef struct __attribute__((packed)) {
    uint8_t  status;        // spi_status_t
    uint8_t  error_code;    // spi_error_t (0 = no error)
    uint8_t  sequence;      // Echo back sequence number
    uint8_t  reserved;      // Alignment padding
    uint32_t data_len;      // Length of response data following this header
} spi_resp_header_t;

#define SPI_RESP_HEADER_SIZE sizeof(spi_resp_header_t)

// ---------------------------------------------------------------------------
// Image info response (for CMD_GET_IMAGE_INFO)
// ---------------------------------------------------------------------------
typedef struct __attribute__((packed)) {
    uint32_t image_size;    // Total image size in bytes
    uint16_t width;         // Image width in pixels
    uint16_t height;        // Image height in pixels
    uint8_t  format;        // Image format (0 = JPEG)
    uint8_t  quality;       // JPEG quality (1-100)
    uint16_t checksum;      // CRC16 of image data
} spi_image_info_t;

// ---------------------------------------------------------------------------
// Configuration info (for CMD_GET_CONFIG)
// ---------------------------------------------------------------------------
typedef struct __attribute__((packed)) {
    uint8_t  resolution;    // camera_resolution_t
    uint8_t  quality;       // JPEG quality (1-100)
    int8_t   brightness;    // -2 to +2
    int8_t   contrast;      // -2 to +2
    uint8_t  protocol_major;// Protocol version major
    uint8_t  protocol_minor;// Protocol version minor
    uint16_t reserved;      // Future use
} spi_config_info_t;

// ---------------------------------------------------------------------------
// Get image data request payload
// ---------------------------------------------------------------------------
typedef struct __attribute__((packed)) {
    uint32_t offset;        // Byte offset to start reading from
    uint16_t length;        // Number of bytes to read (max SPI_CHUNK_SIZE)
    uint16_t reserved;      // Alignment
} spi_get_image_req_t;

// ---------------------------------------------------------------------------
// READY pin states
// ---------------------------------------------------------------------------
#define READY_PIN_LOW   0   // Not ready / busy
#define READY_PIN_HIGH  1   // Image ready for transfer

// ---------------------------------------------------------------------------
// Magic bytes for protocol validation
// ---------------------------------------------------------------------------
#define PROTOCOL_MAGIC_CMD  0xCA    // Command packet marker
#define PROTOCOL_MAGIC_RSP  0xAC    // Response packet marker

// ---------------------------------------------------------------------------
// Timeout values (milliseconds)
// ---------------------------------------------------------------------------
#define TIMEOUT_CAPTURE_MS      5000    // Max time to wait for capture
#define TIMEOUT_TRANSFER_MS     10000   // Max time for complete transfer
#define TIMEOUT_CHUNK_MS        100     // Max time per chunk transfer

#endif // PROTOCOL_H
