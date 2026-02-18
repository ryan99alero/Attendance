# ESP32-P4 LVGL SquareLine Studio Test Firmware

Minimal test firmware for ESP32-P4-Function-EV-Board v1.5.2 with 7" display (1024x600).

## Purpose

Test SquareLine Studio exported UI designs without the complexity of the full NFC timeclock firmware.

## Hardware

- **Board**: ESP32-P4-Function-EV-Board v1.5.2
- **Display**: 7" MIPI-DSI LCD (1024x600)
- **Touch**: GT911 capacitive touch
- **NFC**: Connected but not used in this test

## Project Structure

```
esp32_p4_lvgl_test/
├── CMakeLists.txt          # Root CMake config
├── sdkconfig.defaults      # ESP-IDF config defaults
├── main/
│   ├── CMakeLists.txt      # Main component config
│   ├── idf_component.yml   # Managed components
│   └── main.c              # Minimal main program
└── ui/                     # SquareLine Studio export goes here
    ├── ui.c
    ├── ui.h
    ├── screens/
    ├── components/
    └── ...
```

## SquareLine Studio Export Instructions

1. In SquareLine Studio, go to **File → Project Settings**
2. Set **Export** path to: `/Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/ui`
3. Set **Export** template to: **LVGL**
4. Click **Export UI Files**

## Build Instructions

```bash
cd /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test

# Set up ESP-IDF environment
. $HOME/.espressif/frameworks/esp-idf-v5.5.1/export.sh

# Build
idf.py build

# Flash
idf.py flash

# Monitor
idf.py monitor
```

## What This Tests

- SquareLine Studio UI rendering
- LVGL event handling (button clicks, keyboard popup, etc.)
- Touch interactions
- Display performance

## Notes

- LVGL 9.3.0 is used (matches main firmware)
- BSP handles all display/touch initialization
- No WiFi, Ethernet, or NFC in this minimal test
- Main loop just keeps app running - all UI handled by LVGL task
