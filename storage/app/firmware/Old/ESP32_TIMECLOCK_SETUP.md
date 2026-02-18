# ESP32 Time Clock Setup Guide

## Overview

This ESP32 Time Clock system provides a complete NFC/RFID-based employee time tracking solution that integrates with your Laravel attendance system. The device creates its own WiFi access point for initial configuration, then connects to your network and communicates with the server via RESTful APIs.

## Hardware Requirements

### Required Components
- **ESP32 Development Board** (DevKit V1 or similar)
- **MFRC522 NFC/RFID Module** (13.56MHz)
- **RFID Cards/Tags** (MIFARE Classic/Ultralight or NTAG213)

### Optional Components
- **RGB LED** (for visual status indication)
- **Buzzer** (for audio feedback)
- **OLED Display** (128x64 I2C)
- **Breadboard and jumper wires**
- **5V Power Supply** (for permanent installation)

## Pin Connections

### MFRC522 NFC Module
```
MFRC522    ESP32
VCC    →   3.3V
GND    →   GND
RST    →   GPIO 2
IRQ    →   Not Connected
MISO   →   GPIO 19
MOSI   →   GPIO 23
SCK    →   GPIO 18
SDA    →   GPIO 5
```

### RGB LED (Optional)
```
LED        ESP32
Red    →   GPIO 18
Green  →   GPIO 19
Blue   →   GPIO 21
GND    →   GND
```

### Buzzer (Optional)
```
Buzzer     ESP32
Positive → GPIO 22
Negative → GND
```

### OLED Display (Optional)
```
OLED       ESP32
VCC    →   3.3V
GND    →   GND
SDA    →   GPIO 21
SCL    →   GPIO 22
```

## Software Setup

### 1. Arduino IDE Configuration

1. **Install ESP32 Board Package:**
   - Open Arduino IDE
   - Go to File → Preferences
   - Add `https://dl.espressif.com/dl/package_esp32_index.json` to Additional Board Manager URLs
   - Go to Tools → Board → Boards Manager
   - Search for "ESP32" and install

2. **Select Board:**
   - Tools → Board → ESP32 Arduino → ESP32 Dev Module

3. **Install Required Libraries:**
   - Go to Sketch → Include Library → Manage Libraries
   - Install the following libraries:
     - `MFRC522` by GithubCommunity
     - `ArduinoJson` by Benoit Blanchon
     - `NTPClient` by Fabrice Weinberg

### 2. Firmware Installation

1. **Copy the firmware code:**
   - Copy the contents of `esp32_timeclock_firmware.ino` to a new Arduino sketch

2. **Upload the HTML interface to SPIFFS:**
   - Install the ESP32 SPIFFS upload tool
   - Create a `data` folder in your sketch directory
   - Copy `esp32_web_interface.html` to the `data` folder and rename it to `index.html`
   - Use Tools → ESP32 Sketch Data Upload to upload files to SPIFFS

3. **Upload the firmware:**
   - Connect ESP32 to your computer via USB
   - Select the correct COM port in Tools → Port
   - Click Upload

## Configuration Process

### Step 1: Initial Setup

1. **Power on the ESP32**
   - The device will create a WiFi access point named `ESP32-TimeClock`
   - Default password: `Configure123`

2. **Connect to the device:**
   - On your phone/computer, connect to the `ESP32-TimeClock` WiFi network
   - Open a web browser and navigate to `192.168.4.1`

### Step 2: Device Configuration

1. **Fill in the configuration form:**
   - **Device Name:** Unique name for this time clock (e.g., "Front Door Clock")
   - **Attendance System IP/FQDN:** IP address or domain of your Laravel server
   - **Server Port:** Usually 80 for HTTP or 443 for HTTPS
   - **WiFi Network (SSID):** Your company's WiFi network name
   - **WiFi Password:** Password for your WiFi network
   - **NTP Server:** Time synchronization server (default: pool.ntp.org)
   - **Timezone:** Select your local timezone

2. **Test the connection:**
   - Click "Test Connection" to verify the device can reach your server
   - Ensure your Laravel server is running and accessible

3. **Register the device:**
   - Click "Register Device" to register with your attendance system
   - The device will save configuration and attempt to connect to your WiFi
   - Registration status will show as "Pending" until approved by admin

### Step 3: Server-Side Approval

1. **Access your Laravel admin panel** (Filament)
2. **Navigate to Device Management**
3. **Find the newly registered device**
4. **Review device details and approve the registration**
5. **Assign the device to a department if needed**

## Operation

### Normal Operation Flow

1. **Device Status Indicators:**
   - **Blue LED:** Device ready, waiting for card
   - **Yellow LED:** Device in configuration mode
   - **Green LED:** Successful punch recorded
   - **Red LED:** Error or failed operation
   - **Cyan LED:** Device registered but not approved

2. **Recording Time:**
   - Employee presents NFC card/tag to the reader
   - Device reads card UID and sends punch data to server
   - Green LED and double beep indicate success
   - Red LED and long beep indicate error

3. **Automatic Functions:**
   - Device sends heartbeat to server every minute
   - Time synchronization occurs every hour
   - WiFi reconnection attempts if connection is lost

### Card Management

1. **Registering Employee Cards:**
   - Use your Laravel admin panel to register employee NFC cards
   - Associate card UIDs with employee records
   - Cards can be MIFARE Classic, MIFARE Ultralight, or NTAG213

2. **Reading Card UIDs:**
   - Present card to reader when device shows blue LED
   - Card UID will be displayed in serial monitor for registration
   - UIDs are case-insensitive hexadecimal strings

## Troubleshooting

### Common Issues

**Device won't connect to WiFi:**
- Verify WiFi credentials are correct
- Ensure WiFi network is 2.4GHz (ESP32 doesn't support 5GHz)
- Check signal strength at device location

**Registration fails:**
- Verify server URL and port are correct
- Ensure Laravel server is running and accessible
- Check firewall settings on server

**Card not detected:**
- Verify MFRC522 module connections
- Ensure card is compatible (13.56MHz)
- Hold card close to reader (1-2cm distance)

**Time sync issues:**
- Verify NTP server is accessible
- Check timezone setting
- Ensure device has internet access

### LED Status Codes

| Color | Status |
|-------|--------|
| Blue | Ready for card reading |
| Yellow | Configuration mode |
| Green | Successful operation |
| Red | Error condition |
| Cyan | Registered, waiting approval |
| Magenta | Configuration saved |
| White | Hardware test mode |

### Serial Monitor Messages

Connect to the device via USB and open Serial Monitor at 115200 baud to see:
- Device initialization messages
- WiFi connection status
- Card reading events
- API communication logs
- Error messages and debugging info

## API Endpoints

The ESP32 device uses the following Laravel API endpoints:

- `POST /api/v1/timeclock/register` - Device registration
- `GET /api/v1/timeclock/status` - Check device approval status
- `POST /api/v1/timeclock/punch` - Record employee punch
- `GET /api/v1/timeclock/health` - Server health check

## Security Considerations

1. **WiFi Security:**
   - Use WPA2/WPA3 secured networks
   - Change default AP password for configuration

2. **API Security:**
   - Device uses unique API tokens for authentication
   - Tokens expire after 30 days and must be renewed
   - All API communication should use HTTPS in production

3. **Physical Security:**
   - Mount device securely to prevent tampering
   - Consider using enclosure to protect components
   - Limit physical access to configuration interface

## Maintenance

### Regular Tasks

1. **Monitor device status** in admin panel
2. **Check device logs** for errors or issues
3. **Update firmware** when new versions are available
4. **Test card reading** functionality periodically

### Firmware Updates

1. **Over-the-Air (OTA) updates** can be implemented
2. **Manual updates** via USB connection
3. **Configuration backup** before updates
4. **Test in development** before production deployment

## Advanced Configuration

### Custom Hardware Configuration

Modify the pin definitions in the firmware for custom hardware setups:

```cpp
#define RFID_SS_PIN     5    // SDA/CS pin for MFRC522
#define RFID_RST_PIN    2    // Reset pin for MFRC522
#define LED_RED_PIN     18   // Red LED pin
#define LED_GREEN_PIN   19   // Green LED pin
#define LED_BLUE_PIN    21   // Blue LED pin
#define BUZZER_PIN      22   // Buzzer pin
```

### Network Configuration

For enterprise networks, you may need to configure:
- Static IP addresses
- VLAN settings
- Proxy servers
- Custom DNS servers

### Integration with Other Systems

The device can be extended to integrate with:
- Access control systems
- HR management systems
- Payroll systems
- Building management systems

## Support

For technical support:
1. Check the serial monitor output for error messages
2. Review device logs in the Laravel admin panel
3. Verify network connectivity and server accessibility
4. Test with known working NFC cards

## File Structure

```
/storage/app/templates/
├── esp32_config_template.xml      # XML configuration template
├── esp32_web_interface.html       # Web configuration interface
├── esp32_timeclock_firmware.ino   # Arduino firmware code
└── ESP32_TIMECLOCK_SETUP.md      # This setup guide
```

This complete system provides a robust, scalable time clock solution that can be deployed across multiple locations while maintaining centralized management through your Laravel application.