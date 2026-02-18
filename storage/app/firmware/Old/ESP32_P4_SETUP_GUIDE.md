Shou# ESP32-P4-Function-EV-Board Time Clock Setup Guide

## Hardware Overview

The ESP32-P4-Function-EV-Board v1.5.2 with 7" display provides a modern touchscreen time clock solution with advanced NFC capabilities.

### Key Components
- **ESP32-P4-Function-EV-Board v1.5.2**: Main controller board
- **7" Display**: Connected via MIPI DSI port with PWM backlight control
- **PN5180 NFC Module**: Advanced NFC/RFID reader supporting ISO15693 and ISO14443

## Hardware Connections

### 7" Display Connection
- **Primary Connection**: 7" Display → MIPI DSI Port (J6 on ESP32-P4 board)
- **Backlight Control**: Use DuPont wire to connect:
  - PWM pin from J6 header → GPIO26 pin on J1 header
  - Default PWM configuration: GPIO26, 5kHz, 8-bit resolution

### PN5180 NFC Module Wiring

The PN5180 is connected using available GPIO pins from the ESP32-P4-Function-EV-Board v1.5.2:

```
PN5180 Pin    ESP32-P4 Pin    Function
---------     ------------    --------
3.3V          3.3V            Power (use 3.3V pin, NOT 5V!)
GND           GND             Ground
MOSI          GPIO6           Data Out (software SPI)
MISO          GPIO7           Data In (software SPI)
SCK           GPIO8           Clock (software SPI)
NSS           GPIO5           Chip Select
RST           GPIO22          Reset Control
REQ           GPIO23          RF Field Enable/Request
BUSY          GPIO21          Status Signal
IRQ           GPIO47          Interrupt (optional)
```

**📌 Pin Grouping for Easy Wiring:**
- **SPI Group**: GPIO5, GPIO6, GPIO7, GPIO8 (consecutive pins for clean wiring)
- **Control Group**: GPIO21, GPIO22, GPIO23 (consecutive pins)
- **Optional**: GPIO47 (IRQ)

**⚠️ CRITICAL POWER CONNECTION:**
- **ESP32-P4 has separate 3.3V and 5V pins side by side**
- **Always connect PN5180 to the 3.3V pin - NOT the 5V pin!**
- **5V will permanently damage the PN5180 module**
- The 3.3V pin provides adequate power for PN5180 operation

**⚠️ IMPORTANT WIRING NOTES:**
- Use only 3.3V for PN5180 - 5V will damage the module
- Keep wires short (< 10cm) to minimize signal interference
- Use quality DuPont wires with solid connections
- Double-check all connections before powering on
- **REQ pin controls RF field - essential for proper operation**

## Software Requirements

### Arduino IDE Libraries
Install these libraries through the Library Manager:

1. **PN5180 Libraries** (required):
   ```
   PN5180 by Andreas Trappmann
   ```

2. **Standard ESP32 Libraries** (usually pre-installed):
   ```
   WiFi
   WebServer
   ArduinoJson
   HTTPClient
   SPIFFS
   NTPClient
   ```

3. **Display Libraries** (for 7" display support):
   ```
   lvgl (Light and Versatile Graphics Library)
   ```

### Board Configuration
1. **Board**: ESP32S3 Dev Module (closest compatible option until official P4 support)
2. **Flash Size**: 16MB (assuming your P4 board configuration)
3. **Partition Scheme**: Default 4MB with spiffs
4. **Core Debug Level**: None (for production) or Info (for debugging)

## Firmware Installation

### Step 1: Download Firmware
1. Navigate to `/storage/app/templates/esp32_p4_timeclock_firmware/`
2. Copy the entire folder to your Arduino sketches directory
3. Ensure folder name matches: `esp32_p4_timeclock_firmware`

### Step 2: Arduino IDE Setup
1. Open Arduino IDE
2. Go to **File → Open**
3. Navigate to the `esp32_p4_timeclock_firmware` folder
4. Open `esp32_p4_timeclock_firmware.ino`
5. Install all required libraries (see above)

### Step 3: Hardware Configuration
1. Connect ESP32-P4 board to computer via USB
2. Verify all hardware connections (display, PN5180)
3. Select correct board and port in Arduino IDE

### Step 4: Upload Firmware
1. Click **Upload** button or use Ctrl+U
2. Wait for compilation and upload (may take 2-3 minutes)
3. Monitor Serial Console at 115200 baud for status messages

## Initial Configuration

### Step 1: Connect to Access Point
1. Power on the device
2. Look for WiFi network: **"ESP32P4-TimeClock"**
3. Connect using password: **"Configure123"**
4. Open browser and go to: **192.168.4.1**

### Step 2: Device Setup
1. **Device Information**:
   - Device Name: Give it a descriptive name (e.g., "Main Entrance")
   - Device ID: Auto-generated from MAC address

2. **WiFi Configuration**:
   - Enter your corporate WiFi SSID
   - Enter WiFi password
   - Test connection before saving

3. **Server Configuration**:
   - Server Host: Your Laravel server IP/domain
   - Server Port: Usually 80 or 443
   - API Token: Generated from Laravel admin panel

### Step 3: Device Registration
1. After WiFi connection, device will auto-register with server
2. Go to Laravel admin: `/admin/devices`
3. Find your new device and approve it
4. Device status should change to "Approved & Ready"

## Display Features

### 7" Touchscreen Interface
- **Resolution**: 800x480 pixels
- **Theme**: Dark theme for professional appearance
- **Real-time Information**:
  - Current time and date
  - Device status
  - WiFi connection status
  - NFC reader status
  - Last card scanned

### Backlight Control
- **Automatic PWM Control**: Smooth brightness adjustment
- **Default Brightness**: 80% (configurable via web interface)
- **Power Saving**: Can dim or turn off during idle periods

## NFC Card Support

### Supported Card Types
The PN5180 module supports multiple card technologies:

1. **ISO15693 (RFID)**:
   - Classic RFID proximity cards
   - Long-range reading (up to 10cm)
   - Most common employee access cards

2. **ISO14443 Type A/B (NFC)**:
   - Modern NFC cards and tags
   - Smartphones with NFC capability
   - Credit card-style contactless cards

3. **Multiple Formats**:
   - MIFARE Classic 1K/4K
   - MIFARE Ultralight
   - MIFARE DESFire
   - NTAG series
   - And more

### Card Reading Process
1. REQ pin automatically enables RF field when card reading is active
2. Present card to reader (within 5cm of PN5180 antenna)
3. Device reads card UID and determines card type
4. Visual feedback on display (yellow highlight)
5. Audio feedback (short beep)
6. Data sent to Laravel server for processing
7. RF field disabled during idle periods for power saving

## Troubleshooting

### PN5180 Issues
1. **"PN5180 Communication Failed"**:
   - ⚠️  **CRITICAL**: Verify you connected to 3.3V pin, NOT 5V pin!
   - Check all SPI connections (GPIO5,6,7,8 - consecutive pins)
   - Verify control pin connections (RST=GPIO22, REQ=GPIO23, BUSY=GPIO21)
   - Ensure no loose wires
   - Try different DuPont wires

2. **"Card Detection Intermittent"**:
   - Check REQ pin connection (GPIO23) - controls RF field
   - Verify BUSY pin connection (GPIO21)
   - Check antenna connections on PN5180
   - Clean card surface and antenna area
   - Monitor serial console for RF field enable/disable messages

3. **"RF Field Issues"**:
   - REQ pin should show HIGH when cards are being read
   - REQ pin goes LOW during idle periods (power saving)
   - Check for "🔵 RF field enabled" and "🔴 RF field disabled" messages

### Display Issues
1. **"Display Not Initializing"**:
   - Verify MIPI DSI connection is secure
   - Check PWM backlight connection (GPIO26)
   - Ensure display power supply is adequate

2. **"Backlight Not Working"**:
   - Verify PWM wire: J6 header → GPIO26
   - Check LEDC configuration in firmware
   - Try different GPIO26 jumper wire

### WiFi Connection Problems
1. **"Cannot Connect to WiFi"**:
   - Verify SSID and password are correct
   - Check if network supports ESP32 devices
   - Try moving closer to WiFi router
   - Check for corporate firewall restrictions

2. **"Device Not Registering"**:
   - Verify server host/port configuration
   - Check API token in Laravel admin
   - Ensure firewall allows device communication
   - Check Laravel logs for registration errors

### Server Communication Issues
1. **"Punch Data Not Sending"**:
   - Verify device is approved in Laravel admin
   - Check WiFi connection status
   - Review API endpoint configuration
   - Monitor Laravel logs for API errors

2. **"Time Sync Problems"**:
   - Check NTP server accessibility
   - Verify timezone configuration
   - Ensure device has internet access

## Advanced Configuration

### Web Interface Features
Access via device IP address when connected to WiFi:

1. **Status Dashboard**:
   - Real-time device status
   - Network information
   - NFC reader diagnostics
   - System information

2. **Configuration Management**:
   - WiFi settings
   - Server configuration
   - Display brightness
   - Timezone settings

3. **Diagnostic Tools**:
   - NFC reader test
   - Connection test
   - System restart
   - Recovery mode

### API Endpoints
The device provides several API endpoints for integration:

- `GET /api/status` - Device status information
- `POST /api/nfc/test` - Test NFC reader functionality
- `POST /api/nfc/recover` - Recover NFC connection
- `POST /api/restart` - Restart device
- `POST /api/config/update` - Update configuration

## Performance Optimizations

### Power Management
- CPU locked at 240MHz for consistent NFC performance
- Light sleep disabled to prevent SPI communication issues
- Automatic connection recovery for reliability

### Memory Management
- LVGL buffer optimized for 800x480 display
- JSON documents sized appropriately for API communication
- SPIFFS used for configuration storage

### Network Efficiency
- Heartbeat every 60 seconds when approved
- Configuration polling every 5 minutes
- Automatic reconnection on WiFi loss

## Maintenance

### Regular Checks
1. **Monthly**:
   - Clean display surface
   - Check all wire connections
   - Verify device status in Laravel admin

2. **Quarterly**:
   - Test NFC reader with multiple card types
   - Review device logs for errors
   - Update firmware if new version available

3. **Annually**:
   - Replace DuPont wires if showing wear
   - Clean PN5180 antenna area
   - Backup device configuration

### Firmware Updates
1. Download latest firmware from templates folder
2. Upload via Arduino IDE (same process as initial installation)
3. Device will retain configuration after update
4. Test all functionality after update

## Support

### Serial Console Debugging
Connect to device at 115200 baud to see detailed status messages:
- Device initialization progress
- NFC reader communication status
- WiFi connection details
- Server communication logs
- Error messages and recovery attempts

### Common Status Messages
- `✅ PN5180 initialized successfully!` - NFC reader working
- `🌐 WiFi connected to: [SSID]` - Network connection established
- `📤 Sending punch data to server...` - Card read, sending to server
- `🔧 === PN5180 CONNECTION CHECK ===` - Auto-recovery in progress

For additional support, check the Laravel admin panel logs and device diagnostics through the web interface.
