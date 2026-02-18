# ESP32-P4 NFC Time Clock - ESP-IDF Implementation

Pure ESP-IDF implementation using PN532 NFC reader with abstraction layer for future PN5180 support.

## Features

- ✅ **PN532 NFC Reader** via SPI (ESP-IDF 5.x native driver)
- ✅ **Abstraction Layer** - Easy to switch to PN5180
- ✅ **No Arduino Dependencies** - Pure ESP-IDF 5.x
- ✅ **No I2C Driver Conflicts** - Compatible with display panel
- ✅ **7" Display Support** - Ready for LVGL integration
- ✅ **Verified Hardware** - Tested pins and DIP switch settings

## Hardware

### ESP32-P4-Function-EV-Board v1.5.2
- Main SoC: ESP32-P4 (RISC-V dual-core)
- Display: 7" MIPI DSI (1024x600)
- WiFi/BT: ESP32-C6 companion chip
- Flash: 16MB
- Audio: ES8311 codec

### NFC Reader: Elechouse PN532
- **Mode:** SPI (fastest, most reliable)
- **DIP Switches:** SEL0=1, SEL1=0 (for SPI mode)
- **Warning:** Board labels are INCORRECT - use settings above

## Pin Configuration

### PN532 SPI Connections (VERIFIED WORKING)
```
PN532 Pin    →  ESP32-P4 GPIO
---------------------------------
VCC (3.3V)   →  3.3V
GND          →  GND
SCK          →  GPIO 20
MISO         →  GPIO 21
MOSI         →  GPIO 22
SS (CS)      →  GPIO 23
RST          →  GPIO 32
IRQ          →  Not connected
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
. $HOME/.espressif/frameworks/esp-idf-v5.5.1/export.sh

# Or if using different version
. $IDF_PATH/export.sh
```

### Build Commands
```bash
cd /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_nfc_espidf

# Set target
idf.py set-target esp32p4

# Configure (optional)
idf.py menuconfig

# Build
idf.py build

# Flash and monitor
idf.py flash monitor
```

### Monitor Only (after flashing)
```bash
idf.py monitor
```

## NFC Reader Abstraction API

### Initialize Reader
```c
nfc_reader_config_t config = {
    .type = NFC_READER_PN532,  // or NFC_READER_PN5180
    .spi_host = SPI2_HOST,
    .cs_pin = 23,
    .rst_pin = 32,
    .irq_pin = -1,  // Optional
    .spi_speed_hz = 5000000,  // 5MHz for PN532, 7MHz for PN5180
};

nfc_reader_handle_t reader;
esp_err_t ret = nfc_reader_init(&config, &reader);
```

### Read Card
```c
nfc_card_uid_t uid;
if (nfc_reader_read_card_uid(reader, &uid) == ESP_OK) {
    char uid_str[32];
    nfc_reader_uid_to_string(&uid, uid_str, sizeof(uid_str));
    printf("Card UID: %s\n", uid_str);

    nfc_card_type_t type = nfc_reader_get_card_type(&uid);
    printf("Type: %s\n", nfc_reader_get_card_type_name(type));
}
```

## Switching to PN5180

To switch from PN532 to PN5180:

1. Change reader type in config:
   ```c
   .type = NFC_READER_PN5180,
   .spi_speed_hz = 7000000,  // PN5180 supports 7MHz
   ```

2. Adjust DIP switches on reader module

3. Update pins if needed (PN5180 has same SPI interface)

4. Implement PN5180 driver in `nfc_reader.c` (marked with TODO)

## PN532 vs PN5180 Comparison

| Feature         | PN532          | PN5180         |
|-----------------|----------------|----------------|
| Speed           | Up to 424 kbps | Up to 848 kbps |
| Range           | ~5cm           | ~10cm          |
| SPI Speed       | 5 MHz          | 7 MHz          |
| Protocols       | 14443A/B, 18092| + ISO15693     |
| EMC/ESD         | Good           | Better         |
| **Recommended** | MIFARE badges  | Long range     |

## Troubleshooting

### PN532 Not Detected
1. Check DIP switches: SEL0=1, SEL1=0 (ignore board labels!)
2. Verify 3.3V power (NOT 5V)
3. Check SPI wiring against pinout above
4. Ensure RST pin is connected

### Build Errors
```bash
# Clean build
idf.py fullclean
idf.py build
```

### I2C Driver Conflicts
This project uses pure ESP-IDF - no Arduino Wire library, so NO conflicts with display panel's I2C driver_ng.

## Future Enhancements

- [ ] PN5180 driver implementation
- [ ] LVGL GUI integration
- [ ] Touch screen support
- [ ] Audio feedback (ES8311)
- [ ] Network time sync
- [ ] Employee database
- [ ] Web interface

## Version History

- **v1.0** - Initial ESP-IDF implementation with PN532
- Abstraction layer for PN5180
- Verified working with ESP32-P4 + 7" display

## License

MIT License - See original component licenses
