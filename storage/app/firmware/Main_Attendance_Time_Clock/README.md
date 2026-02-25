# ESP32-P4 NFC Time Clock - ESP-IDF Implementation

Pure ESP-IDF implementation for an attendance time clock with NFC/Apple Wallet support and camera-based facial recognition.

## Features

- **RYRR30D NFC Reader** - Apple VAS, Google SmartTap, ISO14443A/B, ISO15693, FeliCa
- **Camera SubModule** - ESP32-S3 with OV5640 for facial recognition (SPI slave)
- **7" MIPI Display** - 1024x600 touchscreen with LVGL UI
- **Dual Network** - WiFi (via ESP32-C6) and Ethernet (IP101GR RMII)
- **Pure ESP-IDF 5.x** - No Arduino dependencies

## Hardware

### ESP32-P4-Function-EV-Board v1.5.2
- Main SoC: ESP32-P4 (RISC-V dual-core, 400MHz)
- Display: 7" MIPI DSI (1024x600)
- WiFi/BT: ESP32-C6 companion chip (SDIO interface)
- Ethernet: IP101GR PHY (RMII interface)
- Flash: 16MB
- PSRAM: 32MB
- Audio: ES8311 codec

### NFC Reader: REYAX RYRR30D
- **Interface:** UART 115200 8N1
- **Voltage:** 3.3V
- **Supported Protocols:**
  - Apple VAS (Value Added Services) - Apple Wallet passes
  - Google SmartTap - Google Wallet passes
  - ISO14443A/B (MIFARE, NTAG, etc.)
  - ISO15693 (HF RFID tags)
  - FeliCa (Sony)

### Camera SubModule: XIAO ESP32-S3 Sense
- SoC: ESP32-S3 (Xtensa dual-core)
- Camera: OV5640 (5MP)
- Interface: SPI slave to P4 master
- Purpose: Facial recognition image capture

## Pin Configuration

### Complete GPIO Map (ESP32-P4-Function-EV-Board v1.5.2)

| GPIO | Function | Notes |
|------|----------|-------|
| 7 | I2C SDA | Touch & Audio codec |
| 8 | I2C SCL | Touch & Audio codec |
| 9-13 | Audio I2S | ES8311 codec |
| 14 | WiFi SDIO D0 | ESP32-C6 |
| 15 | WiFi SDIO D1 | ESP32-C6 |
| 16 | WiFi SDIO D2 | ESP32-C6 |
| 17 | WiFi SDIO D3 | ESP32-C6 |
| 18 | WiFi SDIO CLK | ESP32-C6 |
| 19 | WiFi SDIO CMD | ESP32-C6 |
| 20 | USB D+ | USB interface |
| 21 | **NFC UART RX** | RYRR30D TX → P4 RX |
| 22 | **NFC UART TX** | P4 TX → RYRR30D RX |
| 23 | Display Backlight | PWM control |
| 24 | **Camera SPI MOSI** | P4 → S3 data |
| 25 | Buzzer | Audio feedback |
| 26 | Display GPIO | MIPI DSI |
| 27 | Display GPIO | MIPI DSI |
| 28 | **Camera SPI MISO** | S3 → P4 data |
| 29 | **Camera SPI SCLK** | SPI clock |
| 30 | **Camera SPI CS** | Chip select |
| 31 | Ethernet MDC | IP101GR management |
| 32 | **NFC Reset** | RYRR30D reset (optional) |
| 33 | **Camera READY** | S3 ready signal (interrupt) |
| 34 | (Available) | Reserved |
| 35-36 | (AVOID) | Bootloader mode pins |
| 37-38 | (AVOID) | Console UART |
| 39-44 | SD Card | MMC mode |
| 45 | Red LED | Status indicator |
| 46 | Green LED | Status indicator |
| 48 | Blue LED | Status indicator |
| 50 | Ethernet RMII CLK | IP101GR reference clock |
| 51 | Ethernet PHY RST | IP101GR reset |
| 52 | Ethernet MDIO | IP101GR management data |
| 53 | Power Amplifier | Audio output |
| 54 | WiFi/C6 Reset | ESP32-C6 reset |

### RYRR30D NFC Reader Wiring

```
RYRR30D Pin  →  ESP32-P4 GPIO
─────────────────────────────────
VCC          →  3.3V
GND          →  GND
TX           →  GPIO 21 (P4 RX)
RX           →  GPIO 22 (P4 TX)
RST          →  GPIO 32 (optional)
```

### Camera SubModule (ESP32-S3) Wiring

```
S3 Pin       →  ESP32-P4 GPIO
─────────────────────────────────
MOSI         →  GPIO 24
MISO         →  GPIO 28
SCLK         →  GPIO 29
CS           →  GPIO 30
READY        →  GPIO 33
VCC          →  3.3V
GND          →  GND
```

### Status LEDs
- Red LED: GPIO 45
- Green LED: GPIO 46
- Blue LED: GPIO 48
- Buzzer: GPIO 25

## Building

### Prerequisites
```bash
# ESP-IDF 5.5.1 or later
source ~/.espressif/frameworks/esp-idf-v5.5.1/export.sh
```

### Build Commands
```bash
# Set target
idf.py set-target esp32p4

# Build
idf.py build

# Flash and monitor
idf.py flash monitor
```

## NFC Reader API (RYRR30D)

### Initialize Reader
```c
ryrr30d_config_t config = {
    .uart_num = UART_NUM_1,
    .tx_pin = GPIO_NUM_22,
    .rx_pin = GPIO_NUM_21,
    .rst_pin = GPIO_NUM_32,
    .apple_enabled = false,    // Configure with credentials
    .google_enabled = false,   // Configure with credentials
};

esp_err_t ret = ryrr30d_init(&config);
```

### Configure Apple VAS (when credentials available)
```c
// Pass Type ID hash and private key from Apple Developer account
ryrr30d_configure_apple("pass_type_id_hash_hex", "private_key_hex");
```

### Configure Google SmartTap
```c
// Collector ID and private key from Google Wallet API
ryrr30d_configure_google("collector_id_hex", "private_key_hex");
```

### Poll for Cards/Passes
```c
ryrr30d_card_info_t card_info;
if (ryrr30d_poll_card(&card_info) == ESP_OK) {
    const char *type = ryrr30d_card_type_to_string(card_info.type);

    if (card_info.type == RYRR30D_CARD_APPLE_VAS) {
        printf("Apple Wallet: %s\n", card_info.pass_data);
    } else {
        char uid_str[32];
        ryrr30d_uid_to_string(&card_info, uid_str, sizeof(uid_str));
        printf("Card UID: %s (%s)\n", uid_str, type);
    }
}
```

## Camera SubModule API

### Initialize Camera Interface
```c
esp_err_t ret = camera_interface_init();
if (ret == ESP_OK && camera_is_connected()) {
    printf("Camera module ready\n");
}
```

### Capture and Transfer Image
```c
uint8_t *image_buffer = malloc(65536);  // 64KB max
uint32_t image_size;

if (camera_capture_and_get(image_buffer, 65536, &image_size) == ESP_OK) {
    printf("Captured %lu bytes\n", image_size);
    // Process JPEG image for facial recognition
}
```

## Supported Card Types

| Type | Description |
|------|-------------|
| Apple VAS | Apple Wallet passes (employee badges, access cards) |
| Google SmartTap | Google Wallet passes |
| ISO14443A | MIFARE Classic, MIFARE Ultralight, NTAG |
| ISO14443B | Various NFC-B cards |
| ISO15693 | HF RFID tags (vicinity cards) |
| FeliCa | Sony FeliCa cards (transit, payment) |

## Troubleshooting

### RYRR30D Not Responding
1. Check UART wiring (TX/RX may need to be swapped)
2. Verify 3.3V power supply
3. Check baud rate is 115200
4. Try hardware reset via RST pin

### Camera Not Detected
1. Check SPI wiring (all 5 signals)
2. Verify S3 firmware is flashed and running
3. Check READY pin connection
4. Monitor S3 serial output for errors

### Build Errors
```bash
# Clean build
idf.py fullclean
idf.py build
```

## Version History

- **v2.0** - RYRR30D NFC reader + Camera SubModule
  - Apple Wallet / Google SmartTap support
  - ESP32-S3 camera for facial recognition
  - Updated pin assignments for P4-Function-EV-Board v1.5.2

- **v1.0** - Initial ESP-IDF implementation with PN532
  - Basic NFC card reading
  - LVGL display integration

## License

MIT License - See original component licenses
