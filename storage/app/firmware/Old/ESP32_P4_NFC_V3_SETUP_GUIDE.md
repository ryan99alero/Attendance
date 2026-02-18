
# ESP32-P4 Time Clock with NFC Module V3 Setup Guide

## Hardware Overview

The ESP32-P4-Function-EV-Board v1.5.2 with 7" display and NFC Module V3 provides a reliable, cost-effective time clock solution with proven MFRC522 NFC technology.

### Key Components
- **ESP32-P4-Function-EV-Board v1.5.2**: Main controller board
- **7" Touch Display**: Connected via MIPI DSI port with PWM backlight control
- **NFC Module V3**: MFRC522-based NFC/RFID reader (3.3V only)

## Hardware Connections

### 7" Display Connection
- **Primary Connection**: 7" Display → MIPI DSI Port (J6 on ESP32-P4 board)
- **Backlight Control**: Use DuPont wire to connect:
  - PWM pin from J6 header → GPIO26 pin on J1 header
  - Default PWM configuration: GPIO26, 5kHz, 8-bit resolution

### NFC Module V3 Wiring

The NFC Module V3 uses the MFRC522 chip and connects via available GPIO pins from the ESP32-P4-Function-EV-Board v1.5.2:

```
NFC Module V3     ESP32-P4 Pin    Function
Pin
---------         ------------    --------
VCC               3.3V            Power (use 3.3V pin, NOT 5V!)
GND               GND             Ground
MOSI              GPIO6           Data Out (software SPI)
MISO              GPIO7           Data In (software SPI)
SCK               GPIO8           Clock (software SPI)
SS                GPIO5           Chip Select
RSTO              GPIO22          Reset Control
IRQ               GPIO47          Interrupt (optional)
```

**📌 Pin Grouping for Easy Wiring:**
- **SPI Group**: GPIO5, GPIO6, GPIO7, GPIO8 (consecutive pins for clean wiring)
- **Control**: GPIO22 (reset)
- **Optional**: GPIO47 (IRQ)

**⚠️ CRITICAL POWER CONNECTION:**
- **ESP32-P4 has separate 3.3V and 5V pins side by side**
- **Always connect NFC Module V3 to the 3.3V pin - NOT the 5V pin!**
- **5V will permanently damage the MFRC522 module**
- The NFC Module V3 is designed for 3.3V operation only

**⚠️ IMPORTANT WIRING NOTES:**
- Use only 3.3V for NFC Module V3 - 5V will damage the module
- Keep wires short (< 10cm) to minimize signal interference
- Use quality DuPont wires with solid connections
- Double-check all connections before powering on
- IRQ pin connection is optional but recommended for advanced features

## Software Requirements

### Arduino IDE Libraries
Install these libraries through the Library Manager:

1. **MFRC522 Library** (required):
   ```
   MFRC522 by GithubCommunity
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
1. Navigate to `/storage/app/templates/esp32_p4_timeclock_nfcv3_firmware/`
2. Copy the entire folder to your Arduino sketches directory
3. Ensure folder name matches: `esp32_p4_timeclock_nfcv3_firmware`

### Step 2: Arduino IDE Setup
1. Open Arduino IDE
2. Go to **File → Open**
3. Navigate to the `esp32_p4_timeclock_nfcv3_firmware` folder
4. Open `esp32_p4_timeclock_nfcv3_firmware.ino`
5. Install all required libraries (see above)

### Step 3: Hardware Configuration
1. Connect ESP32-P4 board to computer via USB
2. Verify all hardware connections (display, NFC Module V3)
3. Select correct board and port in Arduino IDE

### Step 4: Upload Firmware
1. Click **Upload** button or use Ctrl+U
2. Wait for compilation and upload (may take 2-3 minutes)
3. Monitor Serial Console at 115200 baud for status messages

## Initial Configuration

### Step 1: Connect to Access Point
1. Power on the device
2. Look for WiFi network: **"ESP32P4-TimeClock-V3"**
3. Connect using password: **"Configure123"**
4. Open browser and go to: **192.168.4.1**

### Step 2: Device Setup
1. **Device Information**:
   - Device Name: Give it a descriptive name (e.g., "Reception Desk")
   - Device ID: Auto-generated from MAC address (includes "V3" identifier)

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
3. Find your new device (will show as "ESP32P4-V3-" + MAC address)
4. Approve the device
5. Device status should change to "Approved & Ready"

## Display Features

### 7" Touchscreen Interface
- **Resolution**: 800x480 pixels
- **Theme**: Dark theme for professional appearance
- **Title**: "ESP32-P4 Time Clock (NFC V3)" to identify the variant
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
The MFRC522 chip in NFC Module V3 supports:

1. **MIFARE Classic Series**:
   - MIFARE Classic 1K (most common employee cards)
   - MIFARE Classic 4K
   - MIFARE Mini

2. **MIFARE Ultralight Series**:
   - MIFARE Ultralight (NFC tags)
   - MIFARE Ultralight C

3. **Advanced MIFARE Cards**:
   - MIFARE Plus
   - MIFARE DESFire

4. **ISO14443 Compatible Cards**:
   - Most employee access cards
   - NFC-enabled credit cards
   - NFC tags and stickers

### Card Reading Process
1. Present card to reader (within 3cm of NFC Module V3 antenna)
2. Device reads card UID and determines card type automatically
3. Visual feedback on display (yellow highlight)
4. Audio feedback (short beep)
5. Data sent to Laravel server for processing
6. Card type automatically classified as "rfid" or "nfc"

## Troubleshooting

### NFC Module V3 Issues
1. **"MFRC522 Communication Failed"**:
   - ⚠️  **CRITICAL**: Verify you connected to 3.3V pin, NOT 5V pin!
   - Check all SPI connections (GPIO5,6,7,8 - consecutive pins)
   - Verify control pin connections (RSTO=GPIO22, IRQ=GPIO47)
   - Ensure no loose wires - MFRC522 is sensitive to connection quality
   - Try different DuPont wires

2. **"Card Detection Intermittent"**:
   - Check RSTO pin connection (GPIO22) - critical for reset functionality
   - Verify antenna connections on NFC Module V3
   - Clean card surface and antenna area
   - Ensure card is presented within 3cm of antenna
   - Try different card types to isolate issue

3. **"Version Reading Issues"**:
   - Expected versions: 0x91 (v1.0), 0x92 (v2.0), 0x88 (clone)
   - If reading 0x00: Check SPI wiring and power
   - If reading 0xFF: Check power supply and ground connections
   - Run self-test feature for hardware validation

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
   - MFRC522 diagnostics
   - System information

2. **Configuration Management**:
   - WiFi settings
   - Server configuration
   - Display brightness
   - Timezone settings

3. **Diagnostic Tools**:
   - MFRC522 reader test
   - Connection test
   - System restart
   - Recovery mode

### API Endpoints
The device provides several API endpoints for integration:

- `GET /api/status` - Device status information
- `POST /api/nfc/test` - Test MFRC522 functionality
- `POST /api/nfc/recover` - Recover MFRC522 connection
- `POST /api/restart` - Restart device
- `POST /api/config/update` - Update configuration

## Performance Characteristics

### MFRC522 vs PN5180 Comparison
| Feature | MFRC522 (NFC V3) | PN5180 |
|---------|------------------|---------|
| **Cost** | Lower | Higher |
| **Power Usage** | Lower | Higher |
| **Read Range** | 2-4cm | 5-10cm |
| **Card Types** | MIFARE, ISO14443A | ISO15693, ISO14443A/B |
| **Reliability** | Excellent | Excellent |
| **Setup Complexity** | Simple | Complex |

### Performance Optimizations
- CPU locked at 240MHz for consistent NFC performance
- Light sleep disabled to prevent SPI communication issues
- Automatic connection recovery for reliability
- Faster SPI frequency (4MHz vs 2MHz for PN5180)

### Memory Management
- LVGL buffer optimized for 800x480 display
- JSON documents sized appropriately for API communication
- SPIFFS used for configuration storage
- Lower memory usage than PN5180 variant

## Maintenance

### Regular Checks
1. **Monthly**:
   - Clean display surface and NFC antenna area
   - Check all wire connections
   - Verify device status in Laravel admin
   - Test with multiple card types

2. **Quarterly**:
   - Review device logs for errors
   - Update firmware if new version available
   - Clean NFC Module V3 antenna contacts

3. **Annually**:
   - Replace DuPont wires if showing wear
   - Backup device configuration
   - Performance review and optimization

### Firmware Updates
1. Download latest firmware from templates folder
2. Upload via Arduino IDE (same process as initial installation)
3. Device will retain configuration after update
4. Test all functionality after update

## Support

### Serial Console Debugging
Connect to device at 115200 baud to see detailed status messages:
- `✅ MFRC522 initialized successfully!` - NFC reader working
- `🌐 WiFi connected to: [SSID]` - Network connection established
- `📤 Sending punch data to server...` - Card read, sending to server
- `🔧 === MFRC522 CONNECTION CHECK ===` - Auto-recovery in progress

### Common Status Messages
- `MFRC522 v1.0 Ready` - Most common version detected
- `MFRC522 Clone Ready` - Compatible clone chip detected
- `Self-test: PASSED` - Hardware validation successful
- `Card: [UID]` - Card successfully read and processed

### Advantages of NFC Module V3
- **Proven Technology**: MFRC522 is battle-tested and reliable
- **Lower Cost**: More affordable than PN5180 solutions
- **Simpler Wiring**: No REQ pin needed - fewer connections
- **Lower Power**: Consumes less power than PN5180
- **Wide Compatibility**: Works with most employee access cards
- **Easier Troubleshooting**: Well-documented chip with extensive community support

For additional support, check the Laravel admin panel logs and device diagnostics through the web interface.
