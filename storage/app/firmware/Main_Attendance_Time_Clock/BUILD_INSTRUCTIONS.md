# Build Instructions for ESP32-P4 NFC Time Clock

## Quick Start

### 1. Set Up ESP-IDF Environment

```bash
cd /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_nfc_espidf

# Run setup script (use Python 3.13)
source ./setup_idf.sh
```

### 2. Build Firmware

```bash
# Full build
idf.py build

# Clean build (after major changes)
idf.py fullclean build
```

### 3. Flash to Device

```bash
# Flash and monitor
idf.py flash monitor

# Or separately:
idf.py flash
idf.py monitor
```

### 4. Exit Monitor

Press `Ctrl + ]` to exit the serial monitor.

---

## Python Version Issue (FIXED)

### Problem
- System has Python 3.14, but ESP-IDF v5.5.1 only supports up to Python 3.13
- Error: "ESP-IDF Python virtual environment not found"

### Solution Applied
✅ Python 3.13.7 is already installed at `/opt/homebrew/bin/python3.13`
✅ ESP-IDF Python environment reinstalled with Python 3.13
✅ setuptools downgraded to version 71.0.0 (compatible with ESP-IDF)
✅ Created `setup_idf.sh` script for easy activation

### Manual Setup (if needed)
```bash
# Set environment variable
export IDF_PYTHON_ENV_PATH="$HOME/.espressif/python_env/idf5.5_py3.13_env"

# Activate ESP-IDF
. ~/.espressif/frameworks/esp-idf-v5.5.1/export.sh
```

---

## Next Steps

Follow the **SQUARELINE_UI_MERGE_CHECKLIST.md** to integrate the UI:

1. ✅ Files already copied (Phase 1-2 complete)
2. ⏭️ Phase 3: Update build configuration
3. ⏭️ Phase 4: Update main.c
4. ⏭️ Phase 5-10: Implement integration

---

## Common Commands

```bash
# Check ESP-IDF version
idf.py --version

# Configure project (interactive menu)
idf.py menuconfig

# Set build target
idf.py set-target esp32p4

# Clean build artifacts
idf.py clean

# Full clean (including config)
idf.py fullclean

# Show binary size
idf.py size

# Show component sizes
idf.py size-components

# Monitor serial output only
idf.py monitor

# Specify serial port
idf.py -p /dev/cu.usbmodem14201 flash
```

---

## Troubleshooting

### Python Errors Return
If you see Python 3.14 errors again:
```bash
# Always use the setup script
source ./setup_idf.sh
```

### Build Fails
```bash
# Clean and rebuild
idf.py fullclean
idf.py build
```

### Can't Find Device
```bash
# List USB devices
ls /dev/cu.usbmodem*

# Or check system report
system_profiler SPUSBDataType
```

### Monitor Not Working
```bash
# Specify port explicitly
idf.py -p /dev/cu.usbmodem14201 monitor
```

---

## Environment Details

- **ESP-IDF Version**: v5.5.1
- **Python Version**: 3.13.7
- **Python Env Path**: `~/.espressif/python_env/idf5.5_py3.13_env`
- **IDF Path**: `~/.espressif/frameworks/esp-idf-v5.5.1`
- **Target**: ESP32-P4

---

Last Updated: 2026-01-26
