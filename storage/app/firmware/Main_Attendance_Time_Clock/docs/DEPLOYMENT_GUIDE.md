# Deployment and Testing Guide

Complete guide for building, flashing, and deploying ESP32-P4 Time Clock firmware

## Table of Contents
- [Prerequisites](#prerequisites)
- [Environment Setup](#environment-setup)
- [Building Firmware](#building-firmware)
- [Flashing Device](#flashing-device)
- [Testing](#testing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Maintenance](#maintenance)

## Prerequisites

### Hardware Requirements

- **ESP32-P4-Function-EV-Board v1.5.2** with:
  - 7" MIPI-DSI LCD display (1024x600)
  - GT911 capacitive touch controller
  - USB-C cable for programming
  - Power supply (5V, 2A minimum)

- **PN532 NFC Module V3** (if using NFC features):
  - Connected via SPI
  - Properly wired according to pinout

- **Development Computer**:
  - macOS, Linux, or Windows
  - USB port for programming
  - 8GB RAM minimum
  - 10GB free disk space

### Software Requirements

- **ESP-IDF v5.5.1** - Espressif IoT Development Framework
- **Python 3.8+** - For ESP-IDF tools
- **Git** - Version control
- **SquareLine Studio 1.6.0+** - UI designer (optional, for UI changes)
- **Serial Terminal** - For monitoring (built into ESP-IDF)

## Environment Setup

### 1. Install ESP-IDF

**macOS/Linux**:
```bash
# Create ESP directory
mkdir -p ~/esp
cd ~/esp

# Clone ESP-IDF v5.5.1
git clone -b v5.5.1 --recursive https://github.com/espressif/esp-idf.git

# Install ESP-IDF
cd esp-idf
./install.sh esp32p4

# Set up environment variables
. ./export.sh
```

**Windows**:
Download and run the ESP-IDF installer:
https://dl.espressif.com/dl/esp-idf/

### 2. Verify Installation

```bash
# Check IDF version
idf.py --version

# Should show: ESP-IDF v5.5.1
```

### 3. Set Up Project Path

```bash
# Navigate to project
cd /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test

# Or your equivalent path
```

### 4. Export IDF Environment (Required Each Session)

**Add to ~/.bashrc or ~/.zshrc** for convenience:
```bash
alias get_idf='. $HOME/esp/esp-idf/export.sh'
```

Then run:
```bash
get_idf
```

Or manually each time:
```bash
. $HOME/.espressif/frameworks/esp-idf-v5.5.1/export.sh
```

## Building Firmware

### Project Structure Review

```
esp32_p4_lvgl_test/
├── CMakeLists.txt              # Root build config
├── sdkconfig.defaults          # Default configuration
├── partitions.csv              # Flash partition table
├── main/
│   ├── CMakeLists.txt          # Main component build
│   ├── idf_component.yml       # Component dependencies
│   └── main.c                  # Application entry point
├── ui/                         # SquareLine Studio export
│   ├── CMakeLists.txt          # UI build config
│   ├── ui.c/h                  # UI initialization
│   ├── ui_events.c/h           # Event handlers
│   └── ui_screen_*.c/h         # Screen definitions
└── docs/                       # Documentation
```

### Configure Project (First Time Only)

```bash
# Set target to ESP32-P4
idf.py set-target esp32p4

# Optional: Configure project settings
idf.py menuconfig
```

**Important menuconfig settings**:
- `Component config → LVGL configuration`
  - Enable desired features
  - Set color depth (16-bit)
  - Configure memory (heap size)
- `Component config → ESP32P4-Specific`
  - Set CPU frequency (240 MHz)
  - Enable PSRAM if available

### Full Build

```bash
# Clean build (recommended for first build or after major changes)
idf.py fullclean build

# Regular build (after code changes)
idf.py build
```

**Build time**: First build ~5-10 minutes, subsequent builds ~30 seconds

### Build Output

Successful build produces:
```
build/
├── bootloader/bootloader.bin     # Bootloader
├── partition_table/              # Partition table
└── esp32_p4_nfc_reader.bin       # Main application
```

## Flashing Device

### 1. Connect Device

1. Connect ESP32-P4 to computer via USB-C
2. Device should appear as `/dev/cu.usbmodem*` (macOS) or `COM*` (Windows)

**Check port**:
```bash
# macOS/Linux
ls /dev/cu.usbmodem*

# Should show: /dev/cu.usbmodem14201 (or similar)
```

### 2. Put Device in Download Mode

**ESP32-P4-Function-EV-Board**:
1. Hold **BOOT** button
2. Press **RST** button briefly
3. Release **BOOT** button

Device is now in download mode (ready to flash).

### 3. Flash Firmware

**Option 1: Flash Everything** (first time, or after partition changes):
```bash
idf.py flash
```

This flashes:
- Bootloader
- Partition table
- Application

**Option 2: Flash App Only** (faster for code-only changes):
```bash
idf.py app-flash
```

**Option 3: Specify Port** (if auto-detection fails):
```bash
idf.py -p /dev/cu.usbmodem14201 flash
```

### 4. Monitor Output

After flashing, monitor serial output:
```bash
idf.py monitor

# Or combined flash + monitor
idf.py flash monitor
```

**Exit monitor**: Press `Ctrl+]`

### Expected Boot Output

```
ESP-ROM:esp32p4-20230921
Build:Sep 21 2023
rst:0x1 (POWERON),boot:0x0 (SPI_FAST_FLASH_BOOT)
...

=== ESP32-P4 NFC TIME CLOCK ===
Firmware Version: 1.0.0
Build Date: 2026-01-26

I (1234) NFC_TIMECLOCK: Starting ESP32-P4 LVGL SquareLine Studio Test
I (1245) LVGL_TEST: Initializing BSP...
I (2345) LVGL_TEST: BSP initialized
I (2350) LVGL_TEST: Turning on display backlight
I (2360) LVGL_TEST: Initializing SquareLine Studio UI...
I (2500) LVGL_TEST: UI initialized
I (2510) LVGL_TEST: LVGL test running. UI should be visible on display.
```

## Testing

### Pre-Deployment Testing Checklist

#### 1. Display Testing

- [ ] Display turns on and shows UI
- [ ] Colors render correctly
- [ ] Text is readable
- [ ] No flickering or artifacts
- [ ] Backlight brightness is appropriate

**Test Command** (adjust brightness):
```c
// In code, or via REPL
bsp_display_brightness_set(80); // 0-100
```

#### 2. Touch Testing

- [ ] Touch responds to finger press
- [ ] Touch coordinates are accurate
- [ ] No phantom touches
- [ ] Multi-touch works (if needed)
- [ ] Touch works across entire screen

**Test**: Tap each corner and center of screen.

#### 3. UI Navigation Testing

- [ ] All buttons respond to touch
- [ ] Screen transitions work smoothly
- [ ] Back buttons return to correct screen
- [ ] No UI freezes or hangs
- [ ] Text input works (keyboard appears)

**Test each screen**:
- Main screen
- Admin login
- Setup configurations
- WiFi setup
- Device information
- Time settings

#### 4. Network Testing

**WiFi**:
```bash
# Check logs for WiFi connection
I (5000) WIFI: Connecting to SSID: YourNetwork
I (7000) WIFI: Connected, IP: 192.168.1.100
```

Test:
- [ ] WiFi connects successfully
- [ ] Reconnects after disconnect
- [ ] Shows correct IP address
- [ ] Can reach API server

**Test Command** (from device):
```c
// Ping API server
esp_http_client_get("http://attend.test/api/v1/timeclock/health");
```

#### 5. NFC Testing (if applicable)

- [ ] NFC reader initializes
- [ ] Detects cards reliably
- [ ] Reads UID correctly
- [ ] No false positives
- [ ] Works with different card types

**Test with serial monitor**:
```
I (10000) NFC: Card detected: 04A1B2C3D4E5F6
I (10050) API: Sending punch...
I (10200) API: Punch recorded successfully
```

#### 6. API Integration Testing

- [ ] Device authenticates with API
- [ ] Stores and uses API token
- [ ] Sends punch events successfully
- [ ] Receives employee information
- [ ] Handles API errors gracefully
- [ ] Shows correct messages on display

**Test with cURL** (see API_INTEGRATION.md):
```bash
# Test from API server side
curl http://attend.test/api/v1/timeclock/health
```

#### 7. Error Handling Testing

**Test scenarios**:
- [ ] Wrong WiFi password
- [ ] Network disconnection during operation
- [ ] API server unreachable
- [ ] Invalid admin password
- [ ] Unrecognized NFC card
- [ ] Timeout conditions

#### 8. Performance Testing

- [ ] UI responds within 100ms
- [ ] Screen transitions are smooth (>30fps)
- [ ] No memory leaks (check heap over time)
- [ ] Device doesn't overheat
- [ ] Stable over extended operation (24+ hours)

**Monitor heap usage**:
```bash
# In monitor, check for:
I (60000) main: Free heap: 245678 bytes
I (120000) main: Free heap: 245234 bytes  # Should not decrease significantly
```

### Automated Testing

**Create test script**:
```python
# test_timeclock.py
import serial
import time

ser = serial.Serial('/dev/cu.usbmodem14201', 115200)

def test_boot():
    """Test device boots successfully"""
    # Reset device
    ser.write(b'\x03')  # Ctrl+C
    time.sleep(2)

    output = ser.read(ser.in_waiting).decode()
    assert "UI initialized" in output

def test_wifi_connect():
    """Test WiFi connection"""
    # Send WiFi credentials via REPL
    pass

def test_nfc_read():
    """Test NFC card reading"""
    # Simulate card presence
    pass

# Run tests
test_boot()
test_wifi_connect()
test_nfc_read()

print("All tests passed!")
```

## Deployment

### Single Device Deployment

1. **Prepare device**:
   - Flash latest firmware
   - Verify all tests pass
   - Configure device-specific settings (WiFi, timezone)

2. **Mount device**:
   - Install in secure enclosure
   - Mount on wall or desk stand
   - Connect power supply
   - Connect NFC reader (if external)

3. **Initial setup**:
   - Power on device
   - Wait for boot (UI should appear)
   - Enter admin mode (default password)
   - Configure WiFi
   - Verify API connection
   - Exit admin mode

4. **Verify operation**:
   - Test with employee card
   - Verify punch recorded in backend
   - Check display shows correct info

### Mass Deployment (Multiple Devices)

**Preparation**:

1. **Create deployment package**:
   ```bash
   mkdir esp32_timeclock_deployment
   cd esp32_timeclock_deployment

   # Copy firmware binaries
   cp build/bootloader/bootloader.bin .
   cp build/partition_table/partition-table.bin .
   cp build/esp32_p4_nfc_reader.bin .

   # Create flash script
   cat > flash.sh << 'EOF'
   #!/bin/bash
   PORT=$1
   if [ -z "$PORT" ]; then
       echo "Usage: ./flash.sh /dev/cu.usbmodem14201"
       exit 1
   fi

   esptool.py -p $PORT -b 460800 \
       --before default_reset --after hard_reset \
       --chip esp32p4 write_flash \
       0x0 bootloader.bin \
       0x8000 partition-table.bin \
       0x10000 esp32_p4_nfc_reader.bin

   echo "Flash complete!"
   EOF

   chmod +x flash.sh
   ```

2. **Create deployment checklist**:
   ```markdown
   ## Device Deployment Checklist

   Device ID: _________
   Location: __________
   Date: ______________

   - [ ] Flash firmware
   - [ ] Verify boot
   - [ ] Configure WiFi
   - [ ] Test API connection
   - [ ] Register in backend (admin panel)
   - [ ] Test with sample card
   - [ ] Mount device
   - [ ] Label with device ID
   - [ ] Document location
   - [ ] Take photo of installation
   ```

3. **Deployment process**:

   For each device:
   ```bash
   # 1. Connect device
   # 2. Flash firmware
   ./flash.sh /dev/cu.usbmodem14201

   # 3. Monitor boot
   idf.py -p /dev/cu.usbmodem14201 monitor

   # 4. Note MAC address from boot logs
   # 5. Configure via UI
   # 6. Test
   # 7. Deploy
   ```

### Configuration Management

**Use NVS for device-specific config**:

```c
// config_manager.c
typedef struct {
    char wifi_ssid[32];
    char wifi_password[64];
    char device_name[64];
    char timezone[32];
    int8_t utc_offset;
} device_config_t;

void config_manager_save(device_config_t *config)
{
    nvs_handle_t handle;
    nvs_open("config", NVS_READWRITE, &handle);

    nvs_set_str(handle, "wifi_ssid", config->wifi_ssid);
    nvs_set_str(handle, "wifi_pass", config->wifi_password);
    nvs_set_str(handle, "device_name", config->device_name);
    nvs_set_str(handle, "timezone", config->timezone);
    nvs_set_i8(handle, "utc_offset", config->utc_offset);

    nvs_commit(handle);
    nvs_close(handle);
}

void config_manager_load(device_config_t *config)
{
    nvs_handle_t handle;
    nvs_open("config", NVS_READONLY, &handle);

    size_t len;
    len = sizeof(config->wifi_ssid);
    nvs_get_str(handle, "wifi_ssid", config->wifi_ssid, &len);

    // ... load other fields ...

    nvs_close(handle);
}
```

## Troubleshooting

### Common Issues

#### Flash Fails

**Error**: `Failed to connect to ESP32`

**Solutions**:
1. Put device in download mode (hold BOOT, press RST)
2. Check USB cable (use data cable, not charge-only)
3. Try different USB port
4. Reduce baud rate: `idf.py -p PORT -b 115200 flash`
5. Check drivers (Windows: install USB-to-UART drivers)

#### Display Not Working

**Symptoms**: Black screen, no backlight

**Solutions**:
1. Check display cable connection
2. Verify backlight is enabled in code
3. Check power supply (needs 2A minimum)
4. Test with demo firmware
5. Check LCD configuration in menuconfig

#### Touch Not Responding

**Symptoms**: Touch doesn't register, wrong coordinates

**Solutions**:
1. Check touch controller I2C connection
2. Verify GT911 address in code
3. Calibrate touch (if supported)
4. Check for loose cables
5. Update touch driver

#### WiFi Won't Connect

**Symptoms**: Connection timeout, wrong password error

**Solutions**:
1. Verify SSID and password (case-sensitive)
2. Check WiFi is 2.4GHz (ESP32 doesn't support 5GHz)
3. Move closer to router
4. Check router logs for MAC filtering
5. Try different WiFi network

#### NFC Not Reading Cards

**Symptoms**: No card detection, timeout errors

**Solutions**:
1. Check PN532 SPI wiring
2. Verify SPI pins in code match hardware
3. Test PN532 with Arduino first
4. Check card type compatibility
5. Adjust antenna position

#### API Calls Fail

**Symptoms**: 401 Unauthorized, 500 Server Error

**Solutions**:
1. Check network connectivity (ping server)
2. Verify API token is valid
3. Check server logs for errors
4. Test API with cURL
5. Verify server is running

### Debug Tools

**Enable debug logging**:
```bash
idf.py menuconfig

# Component config → Log output
# Set default log level to Debug
```

**Monitor heap usage**:
```c
#include "esp_heap_caps.h"

void print_heap_info(void)
{
    ESP_LOGI(TAG, "Free heap: %d bytes", esp_get_free_heap_size());
    ESP_LOGI(TAG, "Largest free block: %d bytes",
        heap_caps_get_largest_free_block(MALLOC_CAP_8BIT));
}
```

**CPU usage**:
```c
#include "freertos/task.h"

void print_task_stats(void)
{
    char stats_buffer[1024];
    vTaskGetRunTimeStats(stats_buffer);
    ESP_LOGI(TAG, "Task stats:\n%s", stats_buffer);
}
```

## Maintenance

### Regular Maintenance

**Weekly**:
- Check device is online and responding
- Verify punches are being recorded
- Check for firmware updates

**Monthly**:
- Review error logs
- Check system health metrics
- Clean display screen
- Verify all features working

**Quarterly**:
- Update firmware to latest stable version
- Review and update configuration
- Replace any worn components
- Test disaster recovery

### Firmware Updates

**OTA (Over-The-Air) Update** (implement if needed):

```c
// ota_manager.c
#include "esp_ota_ops.h"
#include "esp_http_client.h"

esp_err_t ota_manager_check_update(void)
{
    // Check server for new firmware version
    // Compare with current version
    // Return ESP_OK if update available
}

esp_err_t ota_manager_perform_update(const char *url)
{
    esp_http_client_config_t config = {
        .url = url,
    };

    esp_err_t ret = esp_https_ota(&config);
    if(ret == ESP_OK) {
        ESP_LOGI(TAG, "OTA successful, rebooting...");
        esp_restart();
    } else {
        ESP_LOGE(TAG, "OTA failed: %s", esp_err_to_name(ret));
    }

    return ret;
}
```

**Manual Update**:
1. Build new firmware
2. Copy .bin file to USB drive
3. Flash each device via USB

### Backup and Recovery

**Backup device configuration**:
```bash
# Read NVS partition
esptool.py -p /dev/cu.usbmodem14201 read_flash 0x9000 0x6000 nvs_backup.bin

# Save for recovery
cp nvs_backup.bin "device_$(date +%Y%m%d).bin"
```

**Restore configuration**:
```bash
esptool.py -p /dev/cu.usbmodem14201 write_flash 0x9000 nvs_backup.bin
```

### Monitoring

**Set up monitoring** (backend):

```php
// Laravel - Monitor device health
class DeviceHealthCheck extends Command
{
    public function handle()
    {
        $devices = Device::where('is_active', true)->get();

        foreach($devices as $device) {
            $lastSeen = $device->last_seen_at;

            if($lastSeen < now()->subMinutes(15)) {
                // Device offline - send alert
                $this->sendAlert($device, 'Device offline for 15+ minutes');
            }
        }
    }
}
```

## Best Practices

1. **Version control everything** - Code, configs, documentation
2. **Test before deploying** - Never deploy untested firmware
3. **Document changes** - Keep changelog updated
4. **Backup before updates** - Always backup NVS before flashing
5. **Monitor logs** - Set up centralized logging
6. **Plan for failure** - Have rollback plan
7. **Label devices** - Physical labels with device ID
8. **Keep spares** - Extra devices for quick replacement
9. **Train staff** - Admin training for troubleshooting
10. **Document deployments** - Location, date, config

## Useful Commands Reference

```bash
# Build
idf.py build                    # Build firmware
idf.py fullclean build          # Clean build

# Flash
idf.py flash                    # Flash all
idf.py app-flash                # Flash app only
idf.py -p PORT flash            # Flash to specific port

# Monitor
idf.py monitor                  # Serial monitor
idf.py flash monitor            # Flash and monitor

# Info
idf.py size                     # Show size info
idf.py size-components          # Component sizes

# Clean
idf.py clean                    # Clean build files
idf.py fullclean                # Full clean including config
```

## Next Steps

- Deploy first device for testing
- Collect feedback from users
- Iterate on design/functionality
- Deploy remaining devices
- Set up monitoring and maintenance schedule

---

Last Updated: 2026-01-26
ESP-IDF Version: v5.5.1
