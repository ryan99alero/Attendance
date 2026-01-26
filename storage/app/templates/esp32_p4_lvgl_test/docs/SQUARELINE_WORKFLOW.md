# SquareLine Studio Workflow Guide

Complete guide for designing and exporting UI for ESP32-P4 Time Clock

## Table of Contents
- [Overview](#overview)
- [SquareLine Studio Setup](#squareline-studio-setup)
- [Project Configuration](#project-configuration)
- [Design Workflow](#design-workflow)
- [Export Process](#export-process)
- [Troubleshooting](#troubleshooting)

## Overview

This time clock system uses SquareLine Studio to design the touch screen interface, which is then exported as LVGL code and integrated into the ESP32-P4 firmware.

### Key Components
- **SquareLine Studio**: Visual UI designer (v1.6.0+)
- **LVGL**: Graphics library (v9.2.2)
- **ESP32-P4**: Microcontroller with 7" touch display (1024x600)
- **Export Target**: C code for ESP-IDF integration

## SquareLine Studio Setup

### Installation
1. Download SquareLine Studio from https://squareline.io/
2. Install the application (macOS, Windows, or Linux)
3. Ensure you have SquareLine Studio v1.6.0 or later

### Opening the Project
```bash
# Project file location
/Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/SquareLineConsole/SquareLine_TimeClock_Rand.spj
```

1. Launch SquareLine Studio
2. File → Open Project
3. Navigate to the `.spj` file above
4. Click Open

## Project Configuration

### Current Configuration
- **Board**: Espressif ESP32-P4-Function-EV-Board v1.5.2
- **Display**: 7" MIPI-DSI LCD
- **Resolution**: 1024x600 pixels
- **Color Depth**: 16-bit (RGB565)
- **LVGL Version**: 9.2.2
- **Touch**: Capacitive (GT911)

### Project Settings

To verify or modify settings:
1. File → Project Settings
2. Check the following:

```
Board: Espressif/esp32_p4_function_ev_board_v2_0_0
Screen Width: 1024
Screen Height: 600
Color Depth: 16 bit
LVGL Version: 9.2.2
```

### Export Configuration

**Critical**: Set the correct export path:

1. File → Project Settings
2. Export Settings tab
3. Set **Export Path**:
   ```
   /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/ui
   ```
4. Set **Export Template**: LVGL
5. Set **Export Format**: C files
6. Enable **Flat File Export** (all files in ui/ directory)

## Design Workflow

### Screen Structure

The current time clock UI has the following screens:

1. **ui_screen_mainscreen** - Main clock-in interface
   - Display current time and date
   - Show "Present Card" or "Tap to Clock" message
   - Display last action feedback

2. **ui_screen_adminlogin** - Admin authentication
   - Password input field
   - Login button
   - Back button

3. **ui_screen_setupconfigurations** - Configuration menu
   - WiFi Setup button
   - Wired Setup button
   - Device Information button
   - Time Settings button

4. **ui_screen_wifisetup** - WiFi configuration
   - SSID input
   - Password input
   - Connect button
   - Status display

5. **ui_screen_wiredsetup** - Ethernet configuration
   - DHCP/Static IP selection
   - IP configuration fields
   - Apply button

6. **ui_screen_deviceinformation** - Device details
   - MAC address display
   - IP address display
   - Firmware version
   - Connection status

7. **ui_screen_timeinformation** - Time/timezone setup
   - Timezone selection
   - NTP server configuration
   - Manual time set option

### Components

Custom components (reusable UI elements):

- **ui_comp_backgroundcontainer** - Consistent background styling
- **ui_comp_hook** - Custom event hooks

### Design Best Practices

#### 1. Screen Navigation
- Always provide a way to return to the main screen
- Use consistent navigation patterns (e.g., back buttons in the same position)
- Consider timeout to auto-return to main screen

#### 2. Touch Targets
- Minimum button size: 80x80 pixels (easier for users with gloves)
- Adequate spacing between interactive elements (20+ pixels)
- Visual feedback on button press (color change, animation)

#### 3. Text Readability
- Use high contrast colors (black text on white, or vice versa)
- Minimum font size: 16pt for body text, 24pt for important info
- Avoid thin fonts that are hard to read from a distance

#### 4. Feedback Messages
- Use large, clear text for status messages
- Show success/error with visual indicators (green check, red X)
- Auto-dismiss messages after 3-5 seconds

#### 5. Color Scheme
- Primary: Use company brand colors
- Success: Green (#28A745)
- Error: Red (#DC3545)
- Warning: Yellow/Orange (#FFC107)
- Info: Blue (#17A2B8)

### Adding New Screens

1. **Create Screen**:
   - Hierarchy panel → Right-click → Add Screen
   - Name it with `ui_screen_` prefix (e.g., `ui_screen_reports`)

2. **Design Layout**:
   - Drag widgets from the left panel onto your screen
   - Use containers for grouping related elements
   - Apply styles consistently

3. **Add Navigation**:
   - Select a button → Events → Clicked
   - Action: Change Screen
   - Target: Your new screen

4. **Add Event Handlers** (for custom logic):
   - Select widget → Events → Add Event
   - Event: Clicked (or other)
   - Action: Call Function
   - Function name: `ui_event_your_function_name`

### Modifying Existing Screens

1. **Select Screen**: Click screen name in Hierarchy
2. **Edit Widgets**: Click to select, modify properties in Inspector
3. **Test Layout**: Use Simulator (Play button) to test interactions
4. **Save Changes**: Ctrl+S (Cmd+S on macOS)

### Custom Fonts

To use custom fonts or icons:

1. **Add Font File**:
   - Assets panel → Fonts → Add Font
   - Upload TTF/OTF file
   - Set size and range (ASCII, Unicode blocks, etc.)

2. **Current Custom Fonts**:
   - `ui_font_Icons` - Icon font for symbols
   - `ui_font_Icon2` - Additional icons

3. **Apply Font**:
   - Select text widget
   - Inspector → Font → Choose custom font

### Images and Assets

Current assets location:
```
SquareLineConsole/assets/
```

To add new images:
1. Assets panel → Images → Add Image
2. Upload PNG/JPG (prefer PNG for transparency)
3. SquareLine will convert to C array format on export

**Optimization**:
- Use indexed color (PNG-8) where possible to reduce size
- Compress images before importing
- Consider using symbols/fonts instead of multiple icon images

## Export Process

### Step-by-Step Export

1. **Save Your Work**:
   ```
   File → Save (Ctrl+S / Cmd+S)
   ```

2. **Verify Export Settings**:
   ```
   File → Project Settings → Export Settings
   Export Path: /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/ui
   ```

3. **Export UI Files**:
   ```
   File → Export UI Files (or press F5)
   ```

4. **Verify Export**:
   - Check the ui/ directory for updated files
   - Look for timestamp update in export log
   - Verify no error messages in the console

### Generated Files Structure

After export, the ui/ directory will contain:

```
ui/
├── ui.c                              # Main UI initialization
├── ui.h                              # Main UI header
├── ui_helpers.c/h                    # Helper functions
├── ui_events.c/h                     # Event handlers (YOU EDIT THIS)
├── ui_comp.c/h                       # Component definitions
├── ui_comp_backgroundcontainer.c/h   # Background component
├── ui_comp_hook.c/h                  # Custom hooks
├── ui_screen_mainscreen.c/h          # Main screen
├── ui_screen_adminlogin.c/h          # Admin screen
├── ui_screen_setupconfigurations.c/h # Setup menu
├── ui_screen_wifisetup.c/h           # WiFi setup
├── ui_screen_wiredsetup.c/h          # Ethernet setup
├── ui_screen_deviceinformation.c/h   # Device info
├── ui_screen_timeinformation.c/h     # Time settings
├── ui_font_Icons.c                   # Icon font data
├── ui_font_Icon2.c                   # Additional icons
└── ui_img_*.c                        # Image assets
```

### Important Notes

**DO NOT EDIT** these generated files directly:
- `ui.c/h`
- `ui_screen_*.c/h` (except for adding custom logic)
- `ui_font_*.c`
- `ui_img_*.c`

**YOU SHOULD EDIT**:
- `ui_events.c` - Add your custom event handler code here
- `ui_comp_hook.c` - Add custom component behavior

Any changes made directly to generated files will be **overwritten** on the next export!

## Troubleshooting

### Export Issues

**Problem**: Export fails with path error
- **Solution**: Verify the export path exists and has write permissions
- Check: `ls -la /Users/ryangoff/Herd/Attend/storage/app/templates/esp32_p4_lvgl_test/ui`

**Problem**: Files not updating after export
- **Solution**:
  1. Check console output for errors
  2. Try File → Clean Export (removes old files first)
  3. Restart SquareLine Studio

**Problem**: LVGL version mismatch errors during build
- **Solution**: Verify Project Settings → LVGL Version matches your firmware (9.2.2)

### Design Issues

**Problem**: Text appears cut off or misaligned
- **Solution**: Increase widget size or check alignment settings
- Enable "Content Fit" in widget properties

**Problem**: Touch doesn't register on buttons
- **Solution**:
  1. Check that widget is set to "Clickable"
  2. Verify no transparent overlay blocking touches
  3. Ensure button is visible (not hidden behind other widgets)

**Problem**: Colors look different on actual hardware
- **Solution**:
  1. Hardware uses RGB565 (16-bit color) - some color loss is normal
  2. Test with high-contrast colors
  3. Use simulator to preview approximate color rendering

### Integration Issues

**Problem**: Compile errors after export
- **Solution**:
  1. Check that CMakeLists.txt includes the ui/ directory
  2. Verify all exported files are present
  3. Run `idf.py fullclean` then rebuild

**Problem**: Events not triggering
- **Solution**: Check `ui_events.c` has your handler functions implemented

## Best Practices Summary

1. **Always Save** before exporting
2. **Test in Simulator** before exporting to hardware
3. **Version Control**: Commit both .spj and exported files together
4. **Backup**: Keep backups in `SquareLineConsole/backup/` directory (automatic)
5. **Documentation**: Document custom events in code comments
6. **Naming**: Use consistent naming with `ui_` prefix
7. **File Management**: Keep assets organized in assets/ directory

## Next Steps

After exporting UI files:
1. Build firmware: See [FIRMWARE_INTEGRATION.md](FIRMWARE_INTEGRATION.md)
2. Flash to ESP32: See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)
3. Test on hardware
4. Iterate on design as needed

## Resources

- SquareLine Studio Docs: https://docs.squareline.io/
- LVGL Documentation: https://docs.lvgl.io/
- ESP32-P4 BSP: https://components.espressif.com/
- Project GitHub: (your repository URL)

## Support

For questions or issues:
1. Check this documentation first
2. Review SquareLine Studio documentation
3. Check ESP-IDF forums for hardware-specific issues
4. Contact your development team

---

Last Updated: 2026-01-26
SquareLine Studio Version: 1.6.0
LVGL Version: 9.2.2
