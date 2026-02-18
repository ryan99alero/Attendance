# ESP32 NFC Reader Troubleshooting Guide

## Problem: NFC Reader returning 0xFF (communication failure)

When you see "NFC Reader Version: 0xff" in the Serial Monitor, it indicates that the ESP32 cannot communicate with the PN532/MFRC522 module.

## Step-by-Step Troubleshooting

### 1. Verify Power Supply
- **Critical**: PN532 modules require **3.3V**, NOT 5V
- Connect VCC to 3.3V pin on ESP32 (NOT Vin or 5V)
- Ensure ground connection is solid (GND to GND)
- Check if your ESP32's 3.3V can supply enough current (~100-200mA)

### 2. Check Pin Conflicts

The 0xFF response often indicates GPIO pin conflicts. Try these configurations:

#### Configuration A (Default):
```
PN532 Module → ESP32
VCC → 3.3V
GND → GND
SCK → GPIO 18
MISO → GPIO 19
MOSI → GPIO 23
SS → GPIO 5
RST → GPIO 2
```

#### Configuration B (Alternative):
```
PN532 Module → ESP32
VCC → 3.3V
GND → GND
SCK → GPIO 18
MISO → GPIO 19
MOSI → GPIO 23
SS → GPIO 15    ← Changed
RST → GPIO 4    ← Changed
```

#### Configuration C (Safe pins):
```
PN532 Module → ESP32
VCC → 3.3V
GND → GND
SCK → GPIO 18
MISO → GPIO 19
MOSI → GPIO 23
SS → GPIO 21    ← Different again
RST → GPIO 22   ← Different again
```

### 3. Wiring Verification Checklist

1. **Double-check connections** with multimeter for continuity
2. **Check for loose breadboard connections**
3. **Verify ESP32 pin labels** (some boards have different labeling)
4. **Test with shorter jumper wires** (long wires can cause signal issues)
5. **Ensure no crossed wires**

### 4. ESP32 Pin Conflicts to Avoid

These ESP32 pins have special functions and may cause conflicts:

**Avoid these pins for NFC:**
- GPIO 0: Boot mode selection
- GPIO 1: UART TX (Serial)
- GPIO 3: UART RX (Serial)
- GPIO 6-11: Connected to flash memory
- GPIO 12: Boot mode, can cause issues
- GPIO 15: Boot mode, pull-down at boot

**Safe pins for SS/RST:**
- GPIO 4, 5, 13, 14, 15, 16, 17, 21, 22, 25, 26, 27

### 5. Hardware Debugging Steps

1. **Test the ESP32 SPI pins:**
   ```arduino
   void setup() {
     Serial.begin(115200);
     SPI.begin();
     Serial.println("SPI initialized");
   }
   ```

2. **Check if PN532 module is getting power:**
   - Some modules have power LEDs
   - Measure voltage at VCC pin (should be 3.3V)

3. **Try different PN532 modules** if available

4. **Test with minimal connections:**
   - Connect only VCC, GND, and one SPI pin at a time

### 6. Firmware Solutions

The updated firmware includes:
- Multiple initialization attempts
- Alternative pin configurations
- Enhanced diagnostics
- Self-test capabilities
- Slower SPI clock for debugging

### 7. Expected Working Output

When successful, you should see:
```
=== NFC Reader Initialization ===
📋 Pre-initialization pin status:
   SS (GPIO 5): LOW
   RST (GPIO 2): LOW
🔧 Initializing SPI...
🔧 Initializing MFRC522...
🔍 Testing NFC Reader communication...
   Attempt 1/3: Version = 0x91 ✅ SUCCESS!
✅ NFC Reader initialized successfully!
   Chip Version: 0x91
   Detected: MFRC522 v1.0/v2.0
🧪 Running self-test...
   Self-test: PASSED
=== NFC Initialization Complete ===
```

### 8. If Nothing Works

Try these advanced steps:

1. **Use an oscilloscope** to verify SPI signals
2. **Test with another ESP32 board**
3. **Check PN532 module datasheet** for specific requirements
4. **Try I2C mode instead of SPI** (different library/wiring)
5. **Contact module manufacturer** for support

### 9. Common PN532 Module Types

Different PN532 modules may have different interfaces:
- **SPI mode**: What we're using
- **I2C mode**: Alternative communication method
- **UART mode**: Serial communication

Ensure your module is configured for SPI mode (check jumpers/switches).

### 10. Power Supply Testing

If you suspect power issues:
```arduino
void setup() {
  Serial.begin(115200);
  Serial.print("3.3V rail voltage: ");
  Serial.println(analogRead(A0) * 3.3 / 4095.0); // Approximate
}
```

## Quick Pin Test Code

Use this minimal code to test just the NFC communication:

```arduino
#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN 5    // Try different values: 5, 15, 21
#define RST_PIN 2   // Try different values: 2, 4, 22

MFRC522 mfrc522(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(115200);
  SPI.begin();
  mfrc522.PCD_Init();

  byte version = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  Serial.print("Version: 0x");
  Serial.println(version, HEX);

  if (version == 0x91 || version == 0x92) {
    Serial.println("SUCCESS: MFRC522 detected!");
  } else {
    Serial.println("FAILED: No communication");
  }
}

void loop() {
  // Empty
}
```

Remember: The most common cause of 0xFF responses is incorrect power supply (using 5V instead of 3.3V) or pin conflicts.