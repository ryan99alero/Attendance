# ESP32-P4 PN532 + Display Panel Integration - Session Notes
**Date:** 2025-10-01
**Status:** BLOCKED - I2C Driver Conflict

## Problem Summary
Trying to use **Adafruit PN532 (SPI mode) + ESP32-P4 7" Display Panel** on same board.
Getting I2C driver conflict: `E (864) i2c: CONFLICT! driver_ng is not allowed to be used with this old driver`

## Root Cause
- ESP-IDF 5.x has TWO I2C drivers that CANNOT coexist:
  - **OLD driver:** `i2c_driver_install()` - used by Arduino Wire library
  - **NEW driver (driver_ng):** `i2c_new_master_bus()` - used by display panel

- Even though PN532 uses SPI (not I2C), the conflict occurs because:
  - Arduino core auto-creates global `TwoWire Wire` object
  - Display panel uses new driver_ng for touch/backlight I2C
  - Both drivers try to initialize, ESP-IDF aborts with conflict

## What We Tried (All Failed)

### Attempt 1: Patch Adafruit_PN532 library
- Location: `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_PN532/`
- Added `PN532_ENABLE_I2C` flag to disable I2C support
- Wrapped all I2C code in `#ifdef PN532_ENABLE_I2C`
- **Result:** FAILED - Wire library still loaded

### Attempt 2: Patch Adafruit_BusIO library
- Location: `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_BusIO/`
- Added `ADAFRUIT_BUSIO_NO_I2C` flag to disable I2C
- Wrapped `Adafruit_I2CDevice.h/.cpp` in `#ifndef ADAFRUIT_BUSIO_NO_I2C`
- **Result:** FAILED - Wire library still loaded from somewhere else

### Attempt 3: Don't include Wire.h
- Removed `#include <Wire.h>` from test file
- Added `#define ADAFRUIT_BUSIO_NO_I2C 1` before all includes
- **Result:** FAILED - Arduino core still initializes Wire globally

### Attempt 4: Disable display libraries temporarily
- Commented out all display panel includes
- **Result:** ✅ SUCCESS - PN532 works perfectly alone
- Firmware version: `1.0.1-no-display`
- Serial output shows PN532 initializes correctly on SPI

## Current Working State

### ✅ WORKING: PN532 SPI without display
**File:** `/Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_minimal_test/esp32_p4_minimal_test.ino`
- Firmware Version: `1.0.1-no-display`
- Display libraries: DISABLED (commented out)
- PN532 Configuration:
  - Mode: SPI
  - DIP Switches: SEL0=1, SEL1=0
  - Pins: SCK=20, MISO=21, MOSI=22, SS=23, RST=32
  - Uses Adafruit_PN532 library
- **Status:** Compiles and runs perfectly, reads NFC cards

### ❌ BROKEN: PN532 SPI with display enabled
- Firmware Version: `1.0.3-busio-fix` (attempted)
- Display libraries: ENABLED
- **Status:** Aborts with I2C conflict at boot

## Library Patches Made (For Future Reference)

### Adafruit_PN532 (Patched)
Location: `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_PN532/`

**Adafruit_PN532.h:**
```cpp
// Line 18-24: Conditional I2C include
#ifndef PN532_ENABLE_I2C
#include <Adafruit_I2CDevice.h>
#endif

// Line 152-155: Conditional I2C constructor
#ifdef PN532_ENABLE_I2C
Adafruit_PN532(uint8_t irq, uint8_t reset, TwoWire *theWire = &Wire);
#endif

// Line 225-227: Conditional I2C device
#ifdef PN532_ENABLE_I2C
Adafruit_I2CDevice *i2c_dev = NULL;
#endif
```

**Adafruit_PN532.cpp:**
- Lines 108-115: I2C constructor wrapped in `#ifdef PN532_ENABLE_I2C`
- Lines 159-167: I2C begin() wrapped
- Lines 335-339: I2C slowdown check wrapped
- Lines 1547-1551: I2C readack() wrapped
- Lines 1572-1579: I2C isready() wrapped
- Lines 1628-1637: I2C readdata() wrapped
- Lines 1810-1842: I2C writecommand() wrapped

### Adafruit_BusIO (Patched)
Location: `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_BusIO/`

**Adafruit_I2CDevice.h:**
```cpp
// Line 4-6: Conditional compilation guard
#ifndef ADAFRUIT_BUSIO_NO_I2C
#include <Arduino.h>
#include <Wire.h>
// ... entire file ...
#endif // ADAFRUIT_BUSIO_NO_I2C (line 40)
```

**Adafruit_I2CDevice.cpp:**
```cpp
// Line 3: Start guard
#ifndef ADAFRUIT_BUSIO_NO_I2C
// ... entire file ...
#endif // ADAFRUIT_BUSIO_NO_I2C (line 324)
```

## Why Patches Didn't Work
The global Wire object is created in Arduino core:
```
/Users/ryangoff/Library/Arduino15/packages/esp32/hardware/esp32/3.3.1/libraries/Wire/src/Wire.cpp
Line: TwoWire Wire = TwoWire(0);
```

This gets linked into the binary even if we never explicitly use Wire, and it conflicts with display panel's driver_ng I2C.

## Hardware Configuration

### ESP32-P4-Function-EV-Board v1.5.2
- 7" RGB LCD display (uses driver_ng I2C for touch/backlight)
- Running Arduino-ESP32 v3.3.1 with ESP-IDF v5.5.1

### Elechouse PN532 NFC Reader
- Confirmed working in SPI mode
- Confirmed working in UART mode
- DIP switch labels on board are WRONG:
  - SPI mode: SEL0=1, SEL1=0 (not what board says)
  - UART mode: SEL0=1, SEL1=1 (not what board says)

## Possible Solutions (Not Yet Tried)

### Option A: Two ESP32-P4 Boards (SAFEST)
- Board 1: Display only
- Board 2: PN532 only
- Communicate via UART/SPI between boards
- **Pros:** Both work perfectly, no conflicts
- **Cons:** More expensive, more complex hardware

### Option B: Pure ESP-IDF (NO ARDUINO)
- Rewrite entire project using ESP-IDF APIs only
- Use ESP-IDF native PN532 driver (Garag library - has issues too)
- **Pros:** No Arduino/Wire conflicts
- **Cons:** HUGE rewrite, steep learning curve, Garag library also broken

### Option C: No Display (Current Workaround)
- Keep PN532 working on SPI
- Use LEDs/buzzer/network for employee feedback
- **Pros:** Works now
- **Cons:** No visual feedback

### Option D: Different NFC Hardware
- Find reader with pure ESP-IDF 5.x driver
- Or use PN532 UART-only module
- **Pros:** Might avoid conflicts
- **Cons:** New hardware purchase/testing

## Files to Check After Restart

### Main Test File
`/Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_minimal_test/esp32_p4_minimal_test.ino`
- Current version: 1.0.1-no-display (WORKING without display)
- Display libraries commented out lines 24-31

### Supporting Files
- `lvgl_v8_port.h` - LVGL port configuration
- `lv_conf.h` - LVGL configuration

### Libraries to Restore if Needed
If Arduino Library Manager updates Adafruit libraries, patches will be lost:
- `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_PN532/`
- `/Users/ryangoff/Documents/Arduino/libraries/Adafruit_BusIO/`

## Next Steps (User Needs to Decide)
1. **Quick fix:** Option A - Use two ESP32 boards
2. **Long-term:** Option B - Rewrite in pure ESP-IDF (weeks of work)
3. **Compromise:** Option C - Keep current working state, find alternative feedback

## Test Results Summary

### ✅ Working Tests
1. PN532 UART mode alone (firmware in `/storage/app/templates/adafruit_test/`)
2. PN532 SPI mode alone (firmware in `/storage/app/templates/adafruit_spi_test/`)
3. PN532 SPI mode without display (current state)

### ❌ Failed Tests
1. PN532 + Display with Adafruit libraries (I2C conflict)
2. Custom minimal drivers (communication failures)
3. Garag ESP-IDF library (compilation errors, I2C conflicts)

## Key Discoveries
1. DIP switch labels on Elechouse PN532 are incorrect/misleading
2. Arduino Wire auto-initialization cannot be prevented
3. ESP-IDF 5.x enforces I2C driver exclusivity at runtime
4. Patching libraries isn't enough - need to prevent Wire linking entirely

## User's Requirements
- Needs display for employee clock in/out feedback
- Needs PN532 for NFC card reading
- Single ESP32-P4 board preferred
- Timeline: ASAP

## Recommendation Status
**WAITING FOR USER DECISION** on which option to pursue.
