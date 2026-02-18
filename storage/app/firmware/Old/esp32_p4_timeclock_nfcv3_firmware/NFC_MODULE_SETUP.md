# NFC Module Setup Guide
## ESP32-P4 TimeClock Firmware

This firmware supports **TWO** NFC module types with the **SAME wiring**:
- **PN532** (currently configured)
- **PN5180** (backup option)

---

## Quick Switch Instructions

### 1. Choose Your Module
Edit `esp32_p4_timeclock_nfcv3_firmware.ino` line 69-70:

**For PN532 (current):**
```cpp
#define USE_PN532       // PN532 NFC Module (currently installed)
// #define USE_PN5180   // PN5180 NFC Module (backup)
```

**For PN5180:**
```cpp
// #define USE_PN532    // PN532 NFC Module (currently installed)
#define USE_PN5180      // PN5180 NFC Module (backup)
```

### 2. Install Required Library

**Arduino IDE → Tools → Manage Libraries...**

**For PN532:**
- Search: "Adafruit PN532"
- Install: "Adafruit PN532" by Adafruit

**For PN5180:**
- Search: "PN5180"
- Install: "PN5180" by ATrappmann OR "PN5180-Library" by tueddy

### 3. Wiring (SAME FOR BOTH!)

```
NFC Module    →    ESP32-P4 (J1 Header)
─────────────────────────────────────────
VCC           →    3.3V  (CRITICAL: NOT 5V!)
GND           →    GND
MOSI          →    GPIO3
MISO          →    GPIO4
SCK           →    GPIO5
SS/NSS        →    GPIO33
RST/RSTO      →    GPIO46
IRQ (optional)→    GPIO47
```

### 4. Compile and Upload

**Arduino IDE:**
1. Open `esp32_p4_timeclock_nfcv3_firmware.ino`
2. Select **Tools → Board → ESP32 Arduino → ESP32P4 Dev Module**
3. Select your serial port
4. Click **Upload**

No rewiring needed - just recompile and upload!

---

## Pin Conflict Notes

**AVOID these GPIOs** (already used by board):
- GPIO7/8: I2C for GT911 touch controller
- GPIO18/19: SDIO for WiFi module
- GPIO24/25: USB-JTAG debugging
- GPIO26: Display backlight PWM
- GPIO27: Display reset

**Safe GPIOs used** for NFC: 3, 4, 5, 33, 46, 47

---

## Troubleshooting

### PN532 Not Detected
1. Check 3.3V power (NOT 5V!)
2. Verify wiring matches table above
3. Ensure PN532 is in **SPI mode** (check DIP switches on module)
4. Check SPI frequency (should be 1-5 MHz for PN532)

### PN5180 Not Detected
1. Check 3.3V power
2. Verify wiring
3. PN5180 requires **higher SPI speed** (up to 7 MHz)
4. Ensure proper decoupling capacitors on module

### "Unable to initialize I2C" Warning
This is **NORMAL** - it's from the GT911 touch controller, not your NFC module.
Ignore this warning, it doesn't affect NFC operation.

---

## Module Specifications

### PN532
- **Protocol**: ISO14443A/B, FeliCa
- **SPI Speed**: 1-5 MHz
- **Range**: ~5cm
- **Cards**: MIFARE, NTAG, FeliCa
- **Current**: ~100mA peak

### PN5180
- **Protocol**: ISO15693, ISO14443, ISO18092
- **SPI Speed**: Up to 7 MHz
- **Range**: ~10cm
- **Cards**: MIFARE, NTAG, ICODE, FeliCa
- **Current**: ~150mA peak

---

## Current Configuration

✅ **Currently set for: PN532**

Last updated: 2025-01-30
