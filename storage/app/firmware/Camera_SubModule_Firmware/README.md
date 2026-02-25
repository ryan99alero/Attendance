# ESP32-S3 Camera SubModule Firmware

SPI slave firmware for XIAO ESP32-S3 Sense camera module, designed to work with the ESP32-P4 main time clock.

## Features

- **OV5640 Camera** - 5MP camera sensor for facial recognition
- **SPI Slave Interface** - High-speed communication with P4 master
- **JPEG Compression** - Hardware-accelerated image encoding
- **Configurable Resolution** - From QQVGA (160x120) to 5MP (2592x1944)
- **READY Signal** - Interrupt-driven image ready notification

## Hardware

### XIAO ESP32-S3 Sense
- SoC: ESP32-S3 (Xtensa dual-core, 240MHz)
- Camera: OV5640 (5MP, JPEG output)
- Flash: 8MB
- PSRAM: 8MB (required for camera frame buffer)
- Interface: SPI slave to ESP32-P4

## Pin Configuration

### SPI Slave Interface (to ESP32-P4 Master)

| S3 GPIO | S3 Pin | Function | P4 GPIO | Notes |
|---------|--------|----------|---------|-------|
| 3 | D2 | SPI MOSI | GPIO 24 | P4 → S3 data |
| 4 | D3 | SPI MISO | GPIO 28 | S3 → P4 data |
| 5 | D4 | SPI SCLK | GPIO 29 | Clock from P4 |
| 6 | D5 | SPI CS | GPIO 30 | Chip select |
| 7 | D8 | READY | GPIO 33 | Image ready signal |
| - | 3V3 | VCC | 3.3V | Power |
| - | GND | GND | GND | Ground |

### Complete Wiring Diagram

```
XIAO ESP32-S3 Sense          ESP32-P4-Function-EV-Board
─────────────────────────────────────────────────────────
D2 (GPIO 3)  MOSI    ←────    GPIO 24  (P4 MOSI)
D3 (GPIO 4)  MISO    ────→    GPIO 28  (P4 MISO)
D4 (GPIO 5)  SCLK    ←────    GPIO 29  (P4 SCLK)
D5 (GPIO 6)  CS      ←────    GPIO 30  (P4 CS)
D8 (GPIO 7)  READY   ────→    GPIO 33  (P4 Interrupt)
3V3          VCC     ←────    3.3V
GND          GND     ─────    GND
```

### Onboard Components
- **LED:** GPIO 21 - Status indicator
- **Camera:** Internal I2C (GPIO 40 SDA, GPIO 39 SCL)

## SPI Protocol

### Communication Overview
- **Mode:** SPI Mode 0 (CPOL=0, CPHA=0)
- **Clock:** Up to 10 MHz
- **Chunk Size:** 4096 bytes max per transaction
- **Protocol:** Command-response with header

### Command Format (P4 → S3)
```c
typedef struct {
    uint8_t  command;       // Command code
    uint8_t  sequence;      // Sequence number
    uint16_t payload_len;   // Payload length
    // Variable payload follows...
} cam_spi_cmd_packet_t;
```

### Response Format (S3 → P4)
```c
typedef struct {
    uint8_t  status;        // Current status
    uint8_t  error_code;    // Error code (0 = OK)
    uint8_t  sequence;      // Echo sequence
    uint8_t  reserved;
    uint32_t data_len;      // Response data length
    // Variable data follows...
} spi_resp_header_t;
```

### Commands

| Command | Code | Description |
|---------|------|-------------|
| CAM_CMD_NOP | 0x00 | No operation (polling) |
| CAM_CMD_CAPTURE_PHOTO | 0x01 | Trigger camera capture |
| CAM_CMD_GET_STATUS | 0x02 | Query current status |
| CAM_CMD_GET_IMAGE_INFO | 0x03 | Get image size/dimensions |
| CAM_CMD_GET_IMAGE_DATA | 0x04 | Transfer image chunk |
| CAM_CMD_SET_RESOLUTION | 0x10 | Set capture resolution |
| CAM_CMD_SET_QUALITY | 0x11 | Set JPEG quality (1-100) |
| CAM_CMD_PING | 0xFE | Health check |
| CAM_CMD_RESET | 0xFF | Soft reset module |

### Status Codes

| Status | Code | Description |
|--------|------|-------------|
| STATUS_IDLE | 0x00 | Ready for commands |
| STATUS_CAPTURING | 0x01 | Capture in progress |
| STATUS_PROCESSING | 0x02 | JPEG encoding |
| STATUS_READY | 0x03 | Image ready for transfer |
| STATUS_TRANSFERRING | 0x04 | Transfer in progress |
| STATUS_ERROR | 0xFF | Error occurred |

### READY Pin Behavior
- **LOW:** Module busy or no image
- **HIGH:** Image captured and ready for transfer
- P4 should configure as interrupt (rising edge)

## Building

### Prerequisites
```bash
# ESP-IDF 5.5.1 or later
source ~/.espressif/frameworks/esp-idf-v5.5.1/export.sh
```

### Build Commands
```bash
cd Camera_SubModule_Firmware

# Set target
idf.py set-target esp32s3

# Build
idf.py build

# Flash and monitor
idf.py flash monitor
```

### Output
Build produces `esp32_s3_camera_module.bin` (~360KB)

## LED Status Patterns

| Pattern | Meaning |
|---------|---------|
| Slow blink (1Hz) | Idle, waiting for commands |
| Fast blink (5Hz) | Capturing photo |
| Solid ON | Image ready for transfer |
| Very fast blink | Transfer in progress |
| Double blink | Error state |

## Resolution Options

| Resolution | Size | Use Case |
|------------|------|----------|
| RES_QQVGA | 160x120 | Quick detection |
| RES_QVGA | 320x240 | Fast preview |
| RES_VGA | 640x480 | **Default** - Face recognition |
| RES_SVGA | 800x600 | Higher detail |
| RES_XGA | 1024x768 | High quality |
| RES_SXGA | 1280x1024 | Very high quality |
| RES_UXGA | 1600x1200 | Full sensor |
| RES_5MP | 2592x1944 | Maximum resolution |

## Typical Usage Flow

1. **Startup:** P4 sends `CAM_CMD_PING` to verify connection
2. **Configure:** P4 sends `CAM_CMD_SET_RESOLUTION` and `CAM_CMD_SET_QUALITY`
3. **Capture:** P4 sends `CAM_CMD_CAPTURE_PHOTO`
4. **Wait:** P4 monitors READY pin or polls `CAM_CMD_GET_STATUS`
5. **Info:** P4 sends `CAM_CMD_GET_IMAGE_INFO` to get size
6. **Transfer:** P4 sends multiple `CAM_CMD_GET_IMAGE_DATA` with offsets
7. **Process:** P4 processes JPEG for facial recognition

## Troubleshooting

### Camera Not Initializing
1. Check PSRAM is enabled in menuconfig
2. Verify camera module is properly seated
3. Check camera power (ensure 3.3V supply is stable)

### SPI Communication Fails
1. Verify all 5 SPI wires are connected
2. Check for loose connections on breadboard
3. Reduce SPI clock speed if needed
4. Verify P4 firmware is using correct pins

### Image Quality Issues
1. Increase JPEG quality setting
2. Use higher resolution
3. Ensure adequate lighting
4. Clean camera lens

### Build Errors
```bash
# Clean and rebuild
idf.py fullclean
idf.py build
```

## Memory Configuration

Requires PSRAM for camera frame buffer:
- **menuconfig → Component config → ESP PSRAM → Enable PSRAM**
- **PSRAM Mode:** Quad or Octal (board dependent)

## Version History

- **v1.0** - Initial release
  - OV5640 camera support
  - SPI slave interface
  - JPEG compression
  - Configurable resolution/quality

## License

MIT License
