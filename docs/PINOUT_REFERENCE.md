# ESP32-P4 Function EV Board - Complete Pinout Reference

This document provides a comprehensive pinout reference for the ESP32-P4-Function-EV-Board v1.5.2 and all connected peripherals including the RYRR30D NFC Reader, ESP32-S3 Camera SubModule, and 7" MIPI-DSI display.

---

## Table of Contents

1. [Board Overview](#board-overview)
2. [RYRR30D NFC Reader (UART)](#ryrr30d-nfc-reader-uart)
3. [Camera SubModule - ESP32-S3 Sense (SPI)](#camera-submodule---esp32-s3-sense-spi)
4. [Display Interface (MIPI-DSI)](#display-interface-mipi-dsi)
5. [Touch Controller (GT911 I2C)](#touch-controller-gt911-i2c)
6. [Ethernet PHY (IP101GR)](#ethernet-phy-ip101gr)
7. [SD Card Interface](#sd-card-interface)
8. [Audio Interface (ES8311)](#audio-interface-es8311)
9. [USB Interface](#usb-interface)
10. [I2C Bus](#i2c-bus)
11. [Status LEDs and Buzzer](#status-leds-and-buzzer)
12. [WiFi/BT Companion Chip (ESP32-C6)](#wifibt-companion-chip-esp32-c6)
13. [GPIO Summary Table](#gpio-summary-table)
14. [Important Notes](#important-notes)

---

## Board Overview

| Specification | Value |
|---------------|-------|
| **Main SoC** | ESP32-P4 (RISC-V dual-core, 400MHz) |
| **Board Version** | v1.5.2 |
| **Flash** | 16MB |
| **PSRAM** | 32MB |
| **Display** | 7" MIPI DSI (1024x600) |
| **WiFi/BT** | ESP32-C6 companion chip (SDIO) - Built-in |
| **Ethernet** | IP101GR PHY (RMII) - Built-in |
| **Audio Codec** | ES8311 |
| **Total GPIOs** | 55 (GPIO 0-54) |

---

## RYRR30D NFC Reader (UART)

The REYAX RYRR30D is a multi-protocol NFC reader supporting Apple VAS (Value Added Services), Google SmartTap, ISO14443A/B, ISO15693, and FeliCa.

### Supported Protocols

| Protocol | Description |
|----------|-------------|
| **Apple VAS** | Apple Wallet passes (employee badges, access cards) |
| **Google SmartTap** | Google Wallet passes |
| **ISO14443A** | MIFARE Classic, MIFARE Ultralight, NTAG |
| **ISO14443B** | Various NFC-B cards |
| **ISO15693** | HF RFID tags (vicinity cards) |
| **FeliCa** | Sony FeliCa cards (transit, payment) |

### Wiring Connections

| RYRR30D Pin | ESP32-P4 GPIO | Description |
|-------------|---------------|-------------|
| VCC | 3.3V | Power supply (3.3V only!) |
| GND | GND | Ground |
| TX | GPIO 21 | Reader TX → P4 RX |
| RX | GPIO 22 | P4 TX → Reader RX |
| RST | GPIO 32 | Reset (active low, optional) |

### UART Configuration

| Parameter | Value |
|-----------|-------|
| **UART Port** | UART_NUM_1 |
| **Baud Rate** | 115200 |
| **Data Bits** | 8 |
| **Parity** | None |
| **Stop Bits** | 1 |

### AT Command Reference

| Command | Description |
|---------|-------------|
| `AT+APPLE=1,<hash>,<key>` | Enable Apple VAS with Pass Type ID hash and private key |
| `AT+GOOGLE=1,<id>,<key>` | Enable Google SmartTap with Collector ID and private key |
| `AT+MODE=2` | Set USB mode |
| `AT+POLL` | Poll for cards |

### Response Format

| Response | Description |
|----------|-------------|
| `+APPLE=1,<data>` | Apple Wallet pass data |
| `+GOOGLE=1,<data>` | Google SmartTap pass data |
| `+UID=<hex>` | Card UID for standard NFC cards |

### Source File

Pin definitions: `main/main.c`

```c
#define NFC_UART_TX_PIN  GPIO_NUM_22  // P4 TX → Reader RX
#define NFC_UART_RX_PIN  GPIO_NUM_21  // Reader TX → P4 RX
#define NFC_RST_PIN      GPIO_NUM_32  // Reset (optional)
```

---

## Camera SubModule - ESP32-S3 Sense (SPI)

The XIAO ESP32-S3 Sense with OV5640 camera operates as an SPI slave, providing image capture for facial recognition.

### SubModule Specifications

| Specification | Value |
|---------------|-------|
| **SoC** | ESP32-S3 (Xtensa dual-core, 240MHz) |
| **Camera Sensor** | OV5640 (5MP) |
| **Flash** | 8MB |
| **PSRAM** | 8MB |
| **Interface** | SPI Slave to ESP32-P4 Master |
| **Output** | JPEG compressed images |

### Wiring Connections

| S3 GPIO | S3 Pin | Function | P4 GPIO | Description |
|---------|--------|----------|---------|-------------|
| 3 | D2 | SPI MOSI | GPIO 24 | P4 → S3 data |
| 4 | D3 | SPI MISO | GPIO 28 | S3 → P4 data |
| 5 | D4 | SPI SCLK | GPIO 29 | Clock from P4 |
| 6 | D5 | SPI CS | GPIO 30 | Chip select |
| 7 | D8 | READY | GPIO 33 | Image ready signal |
| - | 3V3 | VCC | 3.3V | Power |
| - | GND | GND | GND | Ground |

### Wiring Diagram

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

### SPI Configuration

| Parameter | Value |
|-----------|-------|
| **SPI Host** | SPI3_HOST |
| **SPI Speed** | 10 MHz |
| **Mode** | SPI Mode 0 (CPOL=0, CPHA=0) |
| **Chunk Size** | 4096 bytes max per transaction |

### READY Pin Behavior

| State | Meaning |
|-------|---------|
| LOW | Module busy or no image |
| HIGH | Image captured and ready for transfer |

> **Note:** Configure P4 GPIO 33 as interrupt (rising edge) for optimal performance.

### Resolution Options

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

### Source File

Pin definitions: `main/camera_interface.h`

```c
#define CAM_SPI_MOSI_PIN    24   // Master Out Slave In
#define CAM_SPI_MISO_PIN    28   // Master In Slave Out
#define CAM_SPI_SCLK_PIN    29   // Serial Clock
#define CAM_SPI_CS_PIN      30   // Chip Select
#define CAM_READY_PIN       33   // Input from S3 READY pin
#define CAM_SPI_HOST        SPI3_HOST
#define CAM_SPI_CLOCK_HZ    10000000  // 10 MHz
```

---

## Display & Touch Panel Connector

The 7" display module has an 8-pin connector with integrated touch controller (GT911).

### Board Connector Pinout

| Board Label | ESP32-P4 Connection | Description |
|-------------|---------------------|-------------|
| **5V** | 5V | Display power supply |
| **GND** | GND | Ground |
| **UP/DN** | - | Display orientation control (directly wired on display PCB) |
| **STLR** | - | Left/Right control (directly wired on display PCB) |
| **PWM** | GPIO 26 | Backlight brightness (PWM controlled) |
| **RST_LCD** | GPIO 27 | LCD reset (active low) |
| **INT_TP** | NC | Touch panel interrupt (directly wired on display PCB) |
| **RST_TP** | NC | Touch panel reset (directly wired on display PCB) |

> **Note:** UP/DN and STLR pins may be directly connected on the display module PCB for default orientation. INT_TP and RST_TP are directly wired to the GT911 on the display PCB.

### Display Specifications

| Parameter | 1024x600 Config | 1280x800 Config |
|-----------|-----------------|-----------------|
| **Resolution** | 1024 x 600 | 800 x 1280 (portrait) |
| **Interface** | MIPI-DSI | MIPI-DSI |
| **Data Lanes** | 2 | 2 |
| **Lane Bitrate** | 1000 Mbps | 1000 Mbps |
| **Pixel Clock** | 80 MHz | 80 MHz |
| **Color Format** | RGB565 (16-bit) | RGB565 (16-bit) |

### Display Control Pins (by config)

| Board Label | 1024x600 GPIO | 1280x800 GPIO | Description |
|-------------|---------------|---------------|-------------|
| PWM | GPIO 26 | GPIO 23 | PWM-controlled backlight |
| RST_LCD | GPIO 27 | NC | Display reset (active low) |
| INT_TP | NC | NC | Touch interrupt (directly on display) |
| RST_TP | NC | NC | Touch reset (directly on display) |

### MIPI-DSI PHY Power

| Parameter | Value |
|-----------|-------|
| LDO Channel | LDO_VO3 |
| Voltage | 2500 mV |

### Source Files

- Pin definitions: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/esp32_p4_function_ev_board.h:88-98`
- Display config: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/display.h`

---

## Touch Controller (GT911 I2C)

The GT911 capacitive touch controller is integrated on the display module and communicates via I2C. The I2C bus is shared with the audio codec.

### I2C Wiring

| Function | ESP32-P4 GPIO | Description |
|----------|---------------|-------------|
| SCL | GPIO 8 | I2C Clock |
| SDA | GPIO 7 | I2C Data |

> **Note:** The touch I2C lines connect through the display's ribbon cable/connector, not through the 8-pin header.

### I2C Configuration

| Parameter | Value |
|-----------|-------|
| **I2C Port** | Configurable via CONFIG_BSP_I2C_NUM |
| **Speed** | 400 kHz (Fast Mode) |
| **GT911 Address** | 0x5D or 0x14 |

### Source File

Pin definitions: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/esp32_p4_function_ev_board.h:68-69`

```c
#define BSP_I2C_SCL (GPIO_NUM_8)
#define BSP_I2C_SDA (GPIO_NUM_7)
```

---

## Ethernet PHY (IP101GR)

The IP101GR Ethernet PHY is built into the ESP32-P4-Function-EV-Board and uses the RMII interface.

### RMII Interface Pins

| Function | GPIO | Description |
|----------|------|-------------|
| MDC | GPIO 31 | Management Data Clock |
| MDIO | GPIO 52 | Management Data I/O |
| PHY Reset | GPIO 51 | PHY Reset (active low) |
| RMII CLK | GPIO 50 | RMII Reference Clock (hardware reserved) |

### Configuration

| Parameter | Value |
|-----------|-------|
| **PHY Address** | 1 |
| **PHY Chip** | IP101GR |
| **Interface** | RMII |
| **Auto-negotiation** | Enabled |

> **IMPORTANT:** GPIO 50 is reserved by hardware for RMII_CLK. Do NOT use for any other purpose.

### Source File

Pin definitions: `main/ethernet_manager.c:20-24`

```c
#define ETH_PHY_ADDR     1
#define ETH_MDC_GPIO     31   // Management Data Clock
#define ETH_MDIO_GPIO    52   // Management Data I/O
#define ETH_PHY_RST_GPIO 51   // PHY Reset
```

---

## SD Card Interface

The SD card can be used in either MMC (4-bit) or SPI mode.

### MMC Mode (4-bit) - Recommended

| Function | GPIO | Description |
|----------|------|-------------|
| D0 | GPIO 39 | Data Line 0 |
| D1 | GPIO 40 | Data Line 1 |
| D2 | GPIO 41 | Data Line 2 |
| D3 | GPIO 42 | Data Line 3 |
| CMD | GPIO 44 | Command Line |
| CLK | GPIO 43 | Clock |

### SPI Mode (Alternative)

| Function | GPIO | Description |
|----------|------|-------------|
| MISO | GPIO 39 | SPI MISO (same as D0) |
| CS | GPIO 42 | SPI Chip Select (same as D3) |
| MOSI | GPIO 44 | SPI MOSI (same as CMD) |
| CLK | GPIO 43 | SPI Clock |

### Source File

Pin definitions: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/esp32_p4_function_ev_board.h:106-117`

```c
// MMC Mode
#define BSP_SD_D0  (GPIO_NUM_39)
#define BSP_SD_D1  (GPIO_NUM_40)
#define BSP_SD_D2  (GPIO_NUM_41)
#define BSP_SD_D3  (GPIO_NUM_42)
#define BSP_SD_CMD (GPIO_NUM_44)
#define BSP_SD_CLK (GPIO_NUM_43)

// SPI Mode
#define BSP_SD_SPI_MISO (GPIO_NUM_39)
#define BSP_SD_SPI_CS   (GPIO_NUM_42)
#define BSP_SD_SPI_MOSI (GPIO_NUM_44)
#define BSP_SD_SPI_CLK  (GPIO_NUM_43)
```

---

## Audio Interface (ES8311)

### I2S Pins

| Function | GPIO | Description |
|----------|------|-------------|
| SCLK | GPIO 12 | Serial Clock (Bit Clock) |
| MCLK | GPIO 13 | Master Clock |
| LCLK (WS) | GPIO 10 | Left/Right Clock (Word Select) |
| DOUT | GPIO 9 | Data Out (to Speaker) |
| DSIN | GPIO 11 | Data In (from Microphone) |

### Amplifier Control

| Function | GPIO | Description |
|----------|------|-------------|
| Power Amp | GPIO 53 | Power Amplifier Enable |

### Configuration

| Parameter | Value |
|-----------|-------|
| **Codec Chip** | ES8311 |
| **Control Interface** | I2C (shared bus) |
| **Audio Interface** | I2S Standard |
| **Sample Rate** | 22050 Hz (default) |
| **Bit Depth** | 16-bit |

### Source File

Pin definitions: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/esp32_p4_function_ev_board.h:76-81`

```c
#define BSP_I2S_SCLK     (GPIO_NUM_12)
#define BSP_I2S_MCLK     (GPIO_NUM_13)
#define BSP_I2S_LCLK     (GPIO_NUM_10)
#define BSP_I2S_DOUT     (GPIO_NUM_9)
#define BSP_I2S_DSIN     (GPIO_NUM_11)
#define BSP_POWER_AMP_IO (GPIO_NUM_53)
```

---

## USB Interface

### USB 2.0 Pins

| Function | GPIO | Description |
|----------|------|-------------|
| USB+ | GPIO 20 | USB Data Positive |
| USB- | GPIO 19 | USB Data Negative |

### Source File

Pin definitions: `managed_components/espressif__esp32_p4_function_ev_board/include/bsp/esp32_p4_function_ev_board.h:124-125`

```c
#define BSP_USB_POS (GPIO_NUM_20)
#define BSP_USB_NEG (GPIO_NUM_19)
```

---

## I2C Bus

The I2C bus is shared between multiple devices.

### Connected Devices

| Device | Address | Function |
|--------|---------|----------|
| GT911 Touch | 0x5D / 0x14 | Capacitive touch controller |
| ES8311 Codec | 0x18 | Audio codec configuration |

### I2C Pins

| Function | GPIO |
|----------|------|
| SCL | GPIO 8 |
| SDA | GPIO 7 |

---

## Status LEDs and Buzzer

### LED Indicators

| Color | GPIO | Description |
|-------|------|-------------|
| Red | GPIO 45 | Error/Alert indicator |
| Green | GPIO 46 | Success/Ready indicator |
| Blue | GPIO 48 | Status/Activity indicator |

### Audio Feedback

| Function | GPIO | Description |
|----------|------|-------------|
| Buzzer | GPIO 25 | Piezo buzzer for feedback |

### Source File

Pin definitions: `main/main.c`

---

## WiFi/BT Companion Chip (ESP32-C6)

The ESP32-C6 companion chip is built into the ESP32-P4-Function-EV-Board and provides WiFi 6 and Bluetooth 5.0 LE connectivity via SDIO interface.

### SDIO Interface

| Function | GPIO | Description |
|----------|------|-------------|
| SDIO D0 | GPIO 14 | Data Line 0 |
| SDIO D1 | GPIO 15 | Data Line 1 |
| SDIO D2 | GPIO 16 | Data Line 2 |
| SDIO D3 | GPIO 17 | Data Line 3 |
| SDIO CLK | GPIO 18 | Clock |
| SDIO CMD | GPIO 19 | Command |
| C6 Reset | GPIO 54 | ESP32-C6 reset control |

> **IMPORTANT:** GPIO 14-19 and GPIO 54 are reserved for the ESP32-C6 companion chip and should NOT be used for other purposes.

---

## GPIO Summary Table

### Complete GPIO Allocation

| GPIO | Primary Function | Secondary Function | Notes |
|------|------------------|-------------------|-------|
| 7 | I2C SDA | - | Touch + Audio codec |
| 8 | I2C SCL | - | Touch + Audio codec |
| 9 | I2S DOUT | - | Audio speaker output |
| 10 | I2S LCLK | - | Audio word select |
| 11 | I2S DSIN | - | Audio microphone input |
| 12 | I2S SCLK | - | Audio bit clock |
| 13 | I2S MCLK | - | Audio master clock |
| 14-19 | ESP32-C6 SDIO | - | WiFi/BT companion (Built-in) |
| 20 | USB+ | - | USB interface |
| 21 | **NFC UART RX** | - | RYRR30D TX → P4 |
| 22 | **NFC UART TX** | - | P4 → RYRR30D RX |
| 23 | LCD Backlight | - | 1024x600 config |
| 24 | **Camera SPI MOSI** | - | P4 → S3 data |
| 25 | Buzzer | - | Audio feedback |
| 26 | LCD Backlight | - | 1024x600 config |
| 27 | LCD Reset | - | 1024x600 config |
| 28 | **Camera SPI MISO** | - | S3 → P4 data |
| 29 | **Camera SPI SCLK** | - | SPI clock |
| 30 | **Camera SPI CS** | - | Chip select |
| 31 | Ethernet MDC | - | PHY management clock |
| 32 | **NFC RST** | - | RYRR30D reset (optional) |
| 33 | **Camera READY** | - | S3 ready signal (interrupt) |
| 34 | (Available) | - | Reserved for future use |
| 35-36 | (AVOID) | - | Bootloader mode pins |
| 37-38 | (AVOID) | - | Console UART |
| 39 | SD D0 / SPI MISO | - | SD card |
| 40 | SD D1 | - | SD card (MMC only) |
| 41 | SD D2 | - | SD card (MMC only) |
| 42 | SD D3 / SPI CS | - | SD card |
| 43 | SD CLK | - | SD card |
| 44 | SD CMD / SPI MOSI | - | SD card |
| 45 | Red LED | - | Status indicator |
| 46 | Green LED | - | Status indicator |
| 48 | Blue LED | - | Status indicator |
| 50 | RMII CLK | - | Hardware reserved |
| 51 | Ethernet PHY RST | - | PHY reset |
| 52 | Ethernet MDIO | - | PHY management data |
| 53 | Power Amp Enable | - | Audio amplifier |
| 54 | ESP32-C6 Reset | - | WiFi/BT companion (Built-in) |

---

## Important Notes

### Pins to Avoid

| GPIO | Reason |
|------|--------|
| 35-36 | Bootloader mode strapping pins |
| 37-38 | Console UART (debugging) |
| 50 | Hardware reserved for RMII CLK |

### SPI Bus Allocation

| SPI Host | Used By | Notes |
|----------|---------|-------|
| SPI2_HOST | (Available) | Can be used for additional peripherals |
| SPI3_HOST | Camera SubModule | ESP32-S3 SPI slave interface |

### Power Requirements

| Module | Voltage | Notes |
|--------|---------|-------|
| RYRR30D NFC | 3.3V | Do NOT use 5V - will damage module |
| Camera S3 | 3.3V | Power from P4 board |
| ESP32-P4 | 3.3V | Core voltage |
| Display | 3.3V logic | Backlight may require higher voltage |

### Verified Working Configuration

This pinout has been verified working with:
- ESP32-P4-Function-EV-Board v1.5.2
- REYAX RYRR30D NFC Reader
- XIAO ESP32-S3 Sense Camera Module
- 7" MIPI-DSI Display (1024x600)
- ESP-IDF v5.5.1

---

## Revision History

| Date | Version | Changes |
|------|---------|---------|
| 2026-02-18 | 2.0 | Replaced PN532 with RYRR30D NFC reader, added Camera SubModule |
| 2026-01-26 | 1.0 | Initial document |

---

*Generated from source code analysis of the ESP32-P4 NFC Time Clock project.*
