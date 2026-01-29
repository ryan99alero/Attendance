# ESP32-P4 Function EV Board - Complete Pinout Reference

This document provides a comprehensive pinout reference for the ESP32-P4-Function-EV-Board v1.5.2 and all connected peripherals including the PN532 NFC Module V3 and 7" MIPI-DSI display.

---

## Table of Contents

1. [Board Overview](#board-overview)
2. [PN532 NFC/RFID Module V3 (SPI)](#pn532-nfcrfid-module-v3-spi)
3. [Display Interface (MIPI-DSI)](#display-interface-mipi-dsi)
4. [Touch Controller (GT911 I2C)](#touch-controller-gt911-i2c)
5. [Ethernet PHY (IP101GR)](#ethernet-phy-ip101gr)
6. [SD Card Interface](#sd-card-interface)
7. [Audio Interface (ES8311)](#audio-interface-es8311)
8. [USB Interface](#usb-interface)
9. [I2C Bus](#i2c-bus)
10. [Status LEDs and Buzzer](#status-leds-and-buzzer)
11. [WiFi/BT Companion Chip (ESP32-C6)](#wifibt-companion-chip-esp32-c6)
12. [GPIO Summary Table](#gpio-summary-table)
13. [Important Notes](#important-notes)

---

## Board Overview

| Specification | Value |
|---------------|-------|
| **Main SoC** | ESP32-P4 (RISC-V dual-core) |
| **Board Version** | v1.5.2 |
| **Flash** | 16MB |
| **Display** | 7" MIPI DSI (1024x600) |
| **WiFi/BT** | ESP32-C6 companion chip (SDIO) |
| **Ethernet** | IP101GR PHY (RMII) |
| **Audio Codec** | ES8311 |
| **Total GPIOs** | 55 (GPIO 0-54) |

---

## PN532 NFC/RFID Module V3 (SPI)

### Wiring Connections

| PN532 Pin | ESP32-P4 GPIO | Description |
|-----------|---------------|-------------|
| VCC | 3.3V | Power supply (3.3V only!) |
| GND | GND | Ground |
| SCK | GPIO 20 | SPI Clock |
| MISO | GPIO 21 | SPI Master In Slave Out |
| MOSI | GPIO 22 | SPI Master Out Slave In |
| SS (CS) | GPIO 23 | SPI Chip Select |
| RST | GPIO 32 | Reset (active low) |
| IRQ | Not connected | Interrupt (optional) |

### SPI Configuration

| Parameter | Value |
|-----------|-------|
| **SPI Host** | SPI3_HOST |
| **SPI Speed** | 5 MHz (5,000,000 Hz) |
| **Mode** | SPI Mode 0 |

### DIP Switch Settings (CRITICAL)

| Switch | Position | Mode |
|--------|----------|------|
| SEL0 | 1 (ON) | SPI |
| SEL1 | 0 (OFF) | SPI |

> **WARNING:** The printed labels on the PN532 V3 board are INCORRECT. Use the settings above regardless of what the board silk screen says.

### Source File

Pin definitions: `main/main.c:59-68`

```c
#define NFC_SCK_PIN  GPIO_NUM_20
#define NFC_MISO_PIN GPIO_NUM_21
#define NFC_MOSI_PIN GPIO_NUM_22
#define NFC_CS_PIN   GPIO_NUM_23
#define NFC_RST_PIN  GPIO_NUM_32
#define NFC_IRQ_PIN  -1
#define NFC_SPI_HOST SPI3_HOST
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

> **IMPORTANT:** GPIO 50 is reserved by hardware for RMII_CLK. Previous documentation incorrectly showed GPIO 50 for MDC. The correct MDC pin is GPIO 31 (verified in v1.5.2 board revision).

### Source File

Pin definitions: `main/ethernet_manager.c:20-24`

```c
#define ETH_PHY_ADDR     1
#define ETH_MDC_GPIO     31   // Management Data Clock (corrected from 50)
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

> **Note:** GPIO 20 is shared with NFC SPI SCK. When using USB, NFC must use a different SPI configuration or be disabled.

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

Pin definitions: `README.md:45-48`

---

## WiFi/BT Companion Chip (ESP32-C6)

The ESP32-C6 companion chip provides WiFi and Bluetooth connectivity via SDIO interface.

### SDIO Interface

The ESP32-C6 uses GPIO 14-19 and GPIO 54 for SDIO communication. These pins are reserved for the companion chip and should not be used for other purposes.

| Function | GPIO Range |
|----------|------------|
| SDIO Data/Clock | GPIO 14-19, 54 |

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
| 14-19 | ESP32-C6 SDIO | - | WiFi/BT companion |
| 19 | USB- | ESP32-C6 | Shared |
| 20 | NFC SPI SCK | USB+ | Shared - choose one |
| 21 | NFC SPI MISO | - | NFC reader |
| 22 | NFC SPI MOSI | - | NFC reader |
| 23 | NFC SPI CS | LCD Backlight (1280x800) | Config dependent |
| 25 | Buzzer | - | Audio feedback |
| 26 | LCD Backlight | - | 1024x600 config |
| 27 | LCD Reset | - | 1024x600 config |
| 31 | Ethernet MDC | - | PHY management clock |
| 32 | NFC RST | - | NFC reader reset |
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
| 54 | ESP32-C6 SDIO | - | WiFi/BT companion |

---

## Important Notes

### Pin Conflicts

1. **GPIO 20**: Shared between NFC SPI SCK and USB+. Cannot use both simultaneously.

2. **GPIO 23**: Used for NFC SPI CS (1024x600 config) or LCD Backlight (1280x800 config). Choose based on display configuration.

3. **GPIO 50**: Hardware reserved for RMII reference clock. Do NOT use for any other purpose.

### SPI Bus Allocation

| SPI Host | Used By | Notes |
|----------|---------|-------|
| SPI2_HOST | WiFi Transport | Reserved for ESP32-C6 |
| SPI3_HOST | PN532 NFC | Available for user peripherals |

### Power Requirements

| Module | Voltage | Notes |
|--------|---------|-------|
| PN532 NFC | 3.3V | Do NOT use 5V - will damage module |
| ESP32-P4 | 3.3V | Core voltage |
| Display | 3.3V logic | Backlight may require higher voltage |

### Verified Working Configuration

This pinout has been verified working with:
- ESP32-P4-Function-EV-Board v1.5.2
- Elechouse PN532 NFC Module V3
- 7" MIPI-DSI Display (1024x600)
- ESP-IDF v5.5.1

---

## Revision History

| Date | Version | Changes |
|------|---------|---------|
| 2026-01-26 | 1.0 | Initial document |

---

*Generated from source code analysis of the ESP32-P4 NFC Time Clock project.*
