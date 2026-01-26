# ESP32-P4 Time Clock Documentation

Complete documentation for the ESP32-P4 Touch Screen Time Clock system with SquareLine Studio UI and Laravel backend integration.

## Overview

This is a professional time and attendance system built with:

- **Hardware**: ESP32-P4-Function-EV-Board with 7" touch display
- **UI Design**: SquareLine Studio (LVGL-based)
- **Firmware**: ESP-IDF (FreeRTOS)
- **Backend**: Laravel (FilamentPHP admin panel)
- **Features**: NFC card reading, WiFi connectivity, real-time API integration

## Documentation Index

### 📘 [1. SquareLine Studio Workflow](SQUARELINE_WORKFLOW.md)
**Learn how to design and export the touch screen UI**

- Opening and configuring the SquareLine project
- Design best practices for touch interfaces
- Adding and modifying screens
- Managing fonts, images, and assets
- Exporting UI files for ESP32 integration
- Troubleshooting design issues

**Start here if**: You need to modify the UI design, add new screens, or change the visual appearance.

---

### ⚙️ [2. Firmware Integration Guide](FIRMWARE_INTEGRATION.md)
**Connect the UI to firmware backend logic**

- Understanding the architecture (UI ↔ Firmware ↔ API)
- Implementing UI event handlers in `ui_events.c`
- Thread-safe display updates with LVGL
- Network and NFC integration patterns
- State management
- Complete code examples

**Start here if**: You need to add functionality, handle button clicks, or integrate new hardware features.

---

### 🌐 [3. API Integration Guide](API_INTEGRATION.md)
**ESP32-Laravel backend communication**

- API endpoint reference (authentication, punch, employee info)
- Request/response formats and examples
- Error handling strategies
- ESP32 HTTP client implementation
- Token management and storage
- Testing with cURL and Postman

**Start here if**: You need to understand the API, add new endpoints, or troubleshoot network issues.

---

### 🎨 [4. Customization Guide](CUSTOMIZATION_GUIDE.md)
**Adapt the system for specific use cases**

- Branding (logos, colors, company name)
- Adding features (department selection, break tracking, photos)
- Multi-language support
- Workflow customization
- Industry-specific examples (healthcare, manufacturing, retail)
- Accessibility improvements

**Start here if**: You need to customize the system for a specific customer, industry, or use case.

---

### 🚀 [5. Deployment Guide](DEPLOYMENT_GUIDE.md)
**Build, flash, and deploy firmware to devices**

- Environment setup (ESP-IDF installation)
- Building the firmware
- Flashing to hardware
- Testing checklist
- Mass deployment procedures
- Troubleshooting common issues
- Maintenance and updates

**Start here if**: You need to build the firmware, flash devices, or troubleshoot hardware issues.

---

## Quick Start

### For UI Designers

1. Install [SquareLine Studio](https://squareline.io/)
2. Open project: `SquareLineConsole/SquareLine_TimeClock_Rand.spj`
3. Make your changes
4. Export to: `ui/`
5. Hand off to firmware developer

→ See [SQUARELINE_WORKFLOW.md](SQUARELINE_WORKFLOW.md)

### For Firmware Developers

1. Install ESP-IDF v5.5.1
2. Clone this repository
3. Edit event handlers in `ui/ui_events.c`
4. Build: `idf.py build`
5. Flash: `idf.py flash monitor`

→ See [FIRMWARE_INTEGRATION.md](FIRMWARE_INTEGRATION.md) and [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

### For Backend Developers

1. Understand the API endpoints
2. Test with cURL or Postman
3. Modify `app/Http/Controllers/Api/TimeClockController.php`
4. Update API documentation

→ See [API_INTEGRATION.md](API_INTEGRATION.md)

### For System Integrators

1. Understand the overall architecture
2. Plan customizations for your deployment
3. Test with one device first
4. Deploy to all locations

→ See [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md) and [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Laravel Backend                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   Filament   │  │     API      │  │   Database   │     │
│  │ Admin Panel  │  │  Controller  │  │  (Employees, │     │
│  │              │  │              │  │  Attendance) │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           │ HTTP/JSON API
                           │ (/api/v1/timeclock/*)
                           │
┌──────────────────────────┴──────────────────────────────────┐
│                    ESP32-P4 Device                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Touch Screen UI (LVGL)                  │  │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐    │  │
│  │  │   Main     │  │   Admin    │  │   Setup    │    │  │
│  │  │   Screen   │  │   Login    │  │   Screens  │    │  │
│  │  └────────────┘  └────────────┘  └────────────┘    │  │
│  └──────────────────────────────────────────────────────┘  │
│                           │                                 │
│  ┌────────────────────────┴────────────────────────────┐   │
│  │          Firmware Logic (ESP-IDF/FreeRTOS)         │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐   │   │
│  │  │    API     │  │    NFC     │  │  Network   │   │   │
│  │  │   Client   │  │   Reader   │  │  Manager   │   │   │
│  │  └────────────┘  └────────────┘  └────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│  ┌────────────────────────┴────────────────────────────┐   │
│  │                     Hardware                        │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐   │   │
│  │  │   7" LCD   │  │    Touch   │  │  PN532 NFC │   │   │
│  │  │ 1024x600   │  │   GT911    │  │   Module   │   │   │
│  │  └────────────┘  └────────────┘  └────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## Project Structure

```
esp32_p4_lvgl_test/
├── docs/                           # 📚 Documentation (you are here)
│   ├── README.md                   # This file
│   ├── SQUARELINE_WORKFLOW.md      # UI design guide
│   ├── FIRMWARE_INTEGRATION.md     # Backend integration
│   ├── API_INTEGRATION.md          # API reference
│   ├── CUSTOMIZATION_GUIDE.md      # Customization examples
│   └── DEPLOYMENT_GUIDE.md         # Build and deploy
│
├── SquareLineConsole/              # 🎨 SquareLine Studio project
│   ├── SquareLine_TimeClock_Rand.spj  # Main project file
│   ├── assets/                     # Images, fonts
│   └── backup/                     # Auto-backups
│
├── ui/                             # 📱 Exported UI files (generated)
│   ├── ui.c/h                      # Main UI init
│   ├── ui_events.c/h               # Event handlers (EDIT THIS)
│   ├── ui_screen_*.c/h             # Screen definitions
│   └── ui_font_*.c                 # Custom fonts
│
├── main/                           # 💻 Firmware source code
│   ├── main.c                      # Application entry
│   ├── api_client.c/h              # API communication
│   ├── nfc_reader.c/h              # NFC card reading
│   ├── network_manager.c/h         # WiFi/Ethernet
│   ├── ui_manager.c/h              # UI updates (thread-safe)
│   └── idf_component.yml           # Dependencies
│
├── CMakeLists.txt                  # Build configuration
├── sdkconfig.defaults              # ESP-IDF config
├── partitions.csv                  # Flash layout
└── README.md                       # Main project README
```

## Key Features

### Current Implementation

- ✅ 7" touch screen interface with LVGL
- ✅ Multiple screens: main, admin, WiFi setup, device info
- ✅ NFC card reading (PN532)
- ✅ WiFi connectivity
- ✅ API integration with Laravel backend
- ✅ Device authentication and token management
- ✅ Real-time clock synchronization
- ✅ Employee punch recording
- ✅ Display employee information after punch

### Potential Enhancements

- [ ] Ethernet connectivity
- [ ] Break time tracking
- [ ] Department selection
- [ ] Photo capture on punch
- [ ] Multi-language support
- [ ] Offline punch storage and sync
- [ ] OTA firmware updates
- [ ] QR code support
- [ ] PIN code entry
- [ ] Voice feedback

See [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md) for implementation details.

## Development Workflow

### Typical Development Cycle

1. **Design UI** (UI Designer)
   - Open SquareLine Studio
   - Modify screens/widgets
   - Export UI files

2. **Implement Logic** (Firmware Developer)
   - Edit `ui/ui_events.c`
   - Add business logic
   - Test event handlers

3. **Integrate API** (Backend Developer)
   - Update Laravel controller
   - Test endpoints with cURL
   - Update API documentation

4. **Build and Test** (All)
   - Build firmware: `idf.py build`
   - Flash device: `idf.py flash`
   - Test on hardware
   - Iterate as needed

5. **Deploy** (System Integrator)
   - Flash all devices
   - Configure on-site
   - Verify operation

## Tech Stack

### Hardware
- **MCU**: ESP32-P4 (dual-core, 400MHz, 16MB Flash, 32MB PSRAM)
- **Display**: 7" MIPI-DSI LCD, 1024x600 resolution
- **Touch**: GT911 capacitive touch controller
- **NFC**: PN532 NFC module (SPI interface)

### Software
- **UI Framework**: LVGL 9.2.2
- **UI Designer**: SquareLine Studio 1.6.0
- **Firmware**: ESP-IDF v5.5.1 (FreeRTOS)
- **Language**: C99
- **Backend**: Laravel 10.x (PHP)
- **Database**: MySQL/PostgreSQL
- **Admin Panel**: FilamentPHP

## Getting Help

### Documentation
- Read the relevant guide from the index above
- Check the troubleshooting sections
- Review code comments and examples

### Resources
- [ESP-IDF Documentation](https://docs.espressif.com/projects/esp-idf/)
- [LVGL Documentation](https://docs.lvgl.io/)
- [SquareLine Studio Docs](https://docs.squareline.io/)
- [ESP32-P4 BSP](https://components.espressif.com/)

### Support
- **Hardware Issues**: Check [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) troubleshooting
- **UI Design**: See [SQUARELINE_WORKFLOW.md](SQUARELINE_WORKFLOW.md)
- **API Issues**: Review [API_INTEGRATION.md](API_INTEGRATION.md)
- **Customization**: Consult [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)

## Contributing

When contributing to this project:

1. **Document changes** - Update relevant guide
2. **Follow code style** - ESP-IDF style for C code
3. **Test thoroughly** - All features on hardware
4. **Update changelog** - Document what changed
5. **Commit properly** - Clear commit messages

## Version History

### v1.0.0 (2026-01-26)
- Initial release
- SquareLine Studio UI integration
- Complete API integration with Laravel backend
- NFC card reading
- WiFi connectivity
- Comprehensive documentation

## License

Proprietary - All rights reserved
For: Time Attendance System Project

## Credits

- **UI Design**: SquareLine Studio
- **Firmware**: ESP-IDF/Espressif
- **Graphics**: LVGL
- **Backend**: Laravel/FilamentPHP

---

## Next Steps

**New to the project?** Start with this README, then:

1. **UI Designer** → [SQUARELINE_WORKFLOW.md](SQUARELINE_WORKFLOW.md)
2. **Firmware Dev** → [FIRMWARE_INTEGRATION.md](FIRMWARE_INTEGRATION.md)
3. **Backend Dev** → [API_INTEGRATION.md](API_INTEGRATION.md)
4. **Customization** → [CUSTOMIZATION_GUIDE.md](CUSTOMIZATION_GUIDE.md)
5. **Deployment** → [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

**Questions?** Check the troubleshooting sections in each guide.

**Ready to build?** See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for setup instructions.

---

Last Updated: 2026-01-26
Documentation Version: 1.0
