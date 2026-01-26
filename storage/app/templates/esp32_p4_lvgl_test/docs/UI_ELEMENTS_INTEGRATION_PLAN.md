# UI Elements Integration Plan

Complete mapping of SquareLine Studio UI elements to backend functionality

## Overview

This document maps every screen and UI element from SquareLine Studio to the required backend integration and explains the intended functionality based on naming conventions.

---

## Screen 1: Main Screen (ui_screen_mainscreen)

**Purpose**: Primary time clock interface - the screen users see when clocking in/out

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| `ui_mainscreen_label_neticonlabel` | Network status icon | Update with WiFi/Ethernet connection state (icon changes based on connectivity) |
| `ui_mainscreen_label_bluetoothiconlabel` | Bluetooth status icon | Show BLE status (if implementing mobile app sync) |
| `ui_mainscreen_label_machinenamelabel` | Display device name | Show device name from NVS config or API (e.g., "Front Desk Clock") |
| `ui_mainscreen_label_timelabel` | Current time display | Update every second with formatted time (HH:MM:SS or 12-hour format) |
| `ui_mainscreen_label_datelabel` | Current date display | Update daily with formatted date (e.g., "Monday, Jan 26, 2026") |
| `ui_mainscreen_button_settingsbutton` | Access admin panel | On long-press or specific gesture → navigate to Admin Login screen |

**Missing Elements** (Need to add to SquareLine):
- ✅ **Employee message area** - Show "Hello [Name]!" after successful punch
- ✅ **NFC status indicator** - "Present Card" or "Card Detected" message
- ✅ **Company logo** - Add branding image
- ⚠️ **Clock in/out status** - Visual indicator of current employee status

**Integration Notes**:
- Main screen should auto-refresh time via FreeRTOS task
- Network icon should update based on WiFi/Ethernet events
- Settings button could require **long press** (3+ seconds) to prevent accidental access

**Event Handlers Needed**:
```c
void ui_event_mainscreen_button_settingsbutton(lv_event_t * e) {
    // Long press detection → navigate to admin login
    if(lv_event_get_code(e) == LV_EVENT_LONG_PRESSED) {
        lv_scr_load(ui_screen_adminlogin);
    }
}

void ui_event_mainscreen_label_neticonlabel(lv_event_t * e) {
    // Click to show network details popup (optional)
    // Display IP address, signal strength, etc.
}
```

**Recommendation**: Add a central message area for:
- "Welcome! Please present your card"
- "Card detected..."
- "Hello John Doe! Time recorded at 8:30 AM"
- "Card not recognized"
- "System error - please try again"

---

## Screen 2: Admin Login (ui_screen_adminlogin)

**Purpose**: Authenticate admin users before accessing settings

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| (Password input field) | Admin password entry | Validate against stored admin password (NVS or hardcoded) |
| (Login button) | Submit password | Check password → navigate to Setup Configurations |
| (Cancel button) | Return to main | Navigate back to main screen |

**Note**: I don't see the specific textarea/button names in the header file excerpt. Let me check the actual implementation to verify element names.

**Integration**:
```c
void ui_event_adminlogin_button_login(lv_event_t * e) {
    const char *password = lv_textarea_get_text(ui_adminlogin_password_input);

    if(strcmp(password, ADMIN_PASSWORD) == 0) {
        // Success - navigate to setup
        lv_scr_load(ui_screen_setupconfigurations);
    } else {
        // Show error message
        lv_label_set_text(ui_adminlogin_error_label, "Invalid password");
    }
}
```

**Security Notes**:
- Password should be stored in NVS, not hardcoded
- Consider PIN pad instead of keyboard for faster entry
- Add login attempt throttling (3 failed attempts = 30 second lockout)

---

## Screen 3: Setup Configurations (ui_screen_setupconfigurations)

**Purpose**: Admin menu for device configuration

### UI Elements & Integration:

| Element | Purpose | Navigation Target |
|---------|---------|-------------------|
| `ui_setupconfigurations_button_wifinetworksettingsbutton` | WiFi configuration | → ui_screen_wifisetup |
| `ui_setupconfigurations_button_wirednetworksettingsbutton` | Ethernet configuration | → ui_screen_wiredsetup |
| `ui_setupconfigurations_button_deviceinfobutton` | Device details | → ui_screen_deviceinformation |
| `ui_setupconfigurations_button_timeinfobutton` | Time/timezone settings | → ui_screen_timeinformation |
| `ui_setupconfigurations_button_closebutton` | Exit admin mode | → ui_screen_mainscreen |

**All Elements Clear** ✅ - Naming is self-explanatory

**Event Handlers**:
```c
void ui_event_setupconfigurations_button_wifinetworksettingsbutton(lv_event_t * e) {
    if(lv_event_get_code(e) == LV_EVENT_CLICKED) {
        lv_scr_load(ui_screen_wifisetup);
    }
}

// Similar for other buttons...
```

**Possible Additions**:
- ⚠️ **NFC Settings** - Enable/disable NFC, test card reading
- ⚠️ **Display Settings** - Brightness, sleep timeout
- ⚠️ **API Settings** - Server URL, test connection
- ⚠️ **Restart/Factory Reset** - System maintenance options

---

## Screen 4: WiFi Setup (ui_screen_wifisetup)

**Purpose**: Configure WiFi connection with DHCP or static IP

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| `ui_wifisetup_label_wifidisconnected` | Connection status | Update with "Connected" / "Disconnected" / "Connecting..." |
| `ui_wifisetup_textarea_ssidinput` | WiFi network name | User enters SSID |
| `ui_wifisetup_textarea_passwordinput` | WiFi password | User enters password (should mask characters) |
| `ui_wifisetup_textarea_hostnameinput` | Device hostname | Set mDNS hostname (e.g., "timeclock-01.local") |
| `ui_wifisetup_switch_dhcpswitch` | DHCP toggle | ON = DHCP, OFF = show manual IP fields |
| `ui_wifisetup_textarea_ipaddressinput` | Static IP address | Only visible when DHCP OFF |
| `ui_wifisetup_textarea_gatewayinput` | Gateway address | Only visible when DHCP OFF |
| `ui_wifisetup_textarea_netmaskinput` | Subnet mask | Only visible when DHCP OFF |
| `ui_wifisetup_textarea_dnsinput` | Primary DNS | Only visible when DHCP OFF |
| `ui_wifisetup_textarea_dns2input` | Secondary DNS | Only visible when DHCP OFF |
| `ui_wifisetup_keyboard_networkkeyboard` | On-screen keyboard | For text input |
| `ui_wifisetup_button_cancelbutton` | Cancel changes | Return to setup menu without saving |
| `ui_wifisetup_button_okbutton` | Save and connect | Validate inputs → connect to WiFi → save to NVS |

**All Elements Clear** ✅ - Comprehensive WiFi setup

**Integration Logic**:
```c
void ui_event_wifisetup_switch_dhcpswitch(lv_event_t * e) {
    bool dhcp_enabled = lv_obj_has_state(ui_wifisetup_switch_dhcpswitch, LV_STATE_CHECKED);

    if(dhcp_enabled) {
        // Hide manual IP fields
        lv_obj_add_flag(ui_wifisetup_container_manualipsettingscontainer, LV_OBJ_FLAG_HIDDEN);
    } else {
        // Show manual IP fields
        lv_obj_clear_flag(ui_wifisetup_container_manualipsettingscontainer, LV_OBJ_FLAG_HIDDEN);
    }
}

void ui_event_wifisetup_button_okbutton(lv_event_t * e) {
    // 1. Validate all inputs
    // 2. Show "Connecting..." message
    // 3. Call network_manager_connect_wifi()
    // 4. On success: save to NVS, show "Connected!", return to setup menu
    // 5. On failure: show error, stay on screen
}
```

**Validation Needed**:
- ✅ SSID: 1-32 characters
- ✅ Password: 8-63 characters (WPA2)
- ✅ IP address: Valid IPv4 format (e.g., 192.168.1.100)
- ✅ Netmask: Valid format (e.g., 255.255.255.0)
- ✅ Gateway: Valid IPv4
- ✅ DNS: Valid IPv4

**UX Improvements**:
- Show WiFi signal strength indicator
- Add "Scan Networks" button to list available SSIDs
- Show password characters temporarily on tap (for debugging)

---

## Screen 5: Wired Setup (ui_screen_wiredsetup)

**Purpose**: Configure Ethernet connection (similar to WiFi but no SSID/password)

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| `ui_wiredsetup_label_wifidisconnected` | Connection status | Update with Ethernet link status |
| `ui_wiredsetup_textarea_hostnameinput` | Device hostname | Set mDNS hostname |
| `ui_wiredsetup_switch_dhcpswitch` | DHCP toggle | Same as WiFi |
| `ui_wiredsetup_textarea_ipaddressinput` | Static IP | Manual IP configuration |
| `ui_wiredsetup_textarea_gatewayinput` | Gateway | Manual config |
| `ui_wiredsetup_textarea_netmaskinput` | Netmask | Manual config |
| `ui_wiredsetup_textarea_dnsinput` | Primary DNS | Manual config |
| `ui_wiredsetup_textarea_dns2input` | Secondary DNS | Manual config |
| `ui_wiredsetup_button_okbutton` | Save and apply | Configure Ethernet interface |
| `ui_wiredsetup_button_cancelbutton` | Cancel | Return without saving |

**All Elements Clear** ✅ - Same pattern as WiFi setup

**Note**: Label says "wifidisconnected" but should probably be "Connection Status" for Ethernet

**Integration**:
```c
void ui_event_wiredsetup_button_okbutton(lv_event_t * e) {
    // 1. Validate inputs (same as WiFi)
    // 2. Configure Ethernet MAC layer
    // 3. Apply IP settings (DHCP or static)
    // 4. Save to NVS
    // 5. Show status and return
}
```

---

## Screen 6: Device Information (ui_screen_deviceinformation)

**Purpose**: Display hardware and software information

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| `ui_deviceinformation_button_harwarebutton` | Toggle to hardware info | Show: MAC address, IP, Ethernet/WiFi status, NFC module info |
| `ui_deviceinformation_button_softwarebutton` | Toggle to software info | Show: Firmware version, ESP-IDF version, LVGL version, build date |
| `ui_deviceinformation_container_informationarea` | Display area | Dynamically populated labels showing device info |

**Integration**:
```c
void ui_event_deviceinformation_button_harwarebutton(lv_event_t * e) {
    // Clear information area
    lv_obj_clean(ui_deviceinformation_container_informationarea);

    // Add labels with hardware info
    add_info_label("MAC Address", get_mac_address());
    add_info_label("IP Address", get_ip_address());
    add_info_label("WiFi Status", get_wifi_status());
    add_info_label("Ethernet", get_ethernet_status());
    add_info_label("NFC Module", "PN532 (SPI)");
    add_info_label("Display", "7\" 1024x600");
}

void ui_event_deviceinformation_button_softwarebutton(lv_event_t * e) {
    // Show software information
    add_info_label("Firmware", FIRMWARE_VERSION);
    add_info_label("ESP-IDF", IDF_VER);
    add_info_label("LVGL", lv_version_info());
    add_info_label("Build Date", FIRMWARE_BUILD_DATE);
    add_info_label("Uptime", get_uptime());
}
```

**Suggested Info to Display**:

**Hardware Tab**:
- MAC Address (WiFi & Ethernet)
- IP Address (current)
- WiFi SSID & Signal Strength
- Ethernet Link Status
- NFC Module Status
- Free Heap Memory
- Flash Size

**Software Tab**:
- Firmware Version
- ESP-IDF Version
- LVGL Version
- Build Date & Time
- Device Uptime
- API Server URL
- Last Sync Time

---

## Screen 7: Time Information (ui_screen_timeinformation)

**Purpose**: Configure time, timezone, and NTP sync

### UI Elements & Integration:

| Element | Purpose | Backend Integration |
|---------|---------|---------------------|
| `ui_timeinformation_button_syncbutton` | Sync time with API | Call `/api/v1/timeclock/time` to get current server time |
| `ui_timeinformation_spinbox_timeinput` | Manual time entry | Set local time (if NTP unavailable) |
| `ui_timeinformation_dropdown_ampm` | AM/PM selector | 12-hour format toggle |
| `ui_timeinformation_dropdown_timezone` | Timezone selection | List of common timezones (America/Chicago, etc.) |
| `ui_timeinformation_textarea_ntpinput` | NTP server address | Custom NTP server (default: pool.ntp.org) |

**Integration Priority**:

1. **Sync Button** - Most important
   ```c
   void ui_event_timeinformation_button_syncbutton(lv_event_t * e) {
       // Show "Syncing..." message
       ui_manager_show_message("Syncing time...", true);

       // Call API to get server time
       api_client_get_server_time(on_time_sync_response);
   }

   void on_time_sync_response(api_response_t *response) {
       if(response->success) {
           // Parse timestamp and timezone offset
           time_t server_time = response->unix_timestamp;
           int8_t tz_offset = response->timezone_offset;

           // Set system time
           struct timeval tv = { .tv_sec = server_time };
           settimeofday(&tv, NULL);

           // Save timezone to NVS
           save_timezone_offset(tz_offset);

           ui_manager_show_message("Time synchronized!", true);
       } else {
           ui_manager_show_message("Sync failed", false);
       }
   }
   ```

2. **Timezone Dropdown** - Should list common zones
   ```c
   // Populate dropdown with timezone options
   const char *timezones[] = {
       "America/Chicago (CST/CDT)",
       "America/New_York (EST/EDT)",
       "America/Los_Angeles (PST/PDT)",
       "America/Denver (MST/MDT)",
       "America/Phoenix (MST - no DST)",
       "UTC"
   };

   // When selected, save to NVS and update API preference
   void ui_event_timezone_changed(lv_event_t * e) {
       uint16_t selected = lv_dropdown_get_selected(ui_timeinformation_dropdown_timezone);
       save_timezone_preference(timezones[selected]);
       api_client_update_device_timezone(timezones[selected]);
   }
   ```

3. **Manual Time Entry** - Fallback if no network
   ```c
   void ui_event_time_manual_set(lv_event_t * e) {
       // Get time from spinbox
       int32_t time_value = lv_spinbox_get_value(ui_timeinformation_spinbox_timeinput);

       // Parse hours/minutes
       int hours = time_value / 100;
       int minutes = time_value % 100;

       // Check AM/PM
       bool is_pm = lv_dropdown_get_selected(ui_timeinformation_dropdown_ampm) == 1;
       if(is_pm && hours < 12) hours += 12;
       if(!is_pm && hours == 12) hours = 0;

       // Set system time
       set_system_time(hours, minutes, 0);
   }
   ```

**Recommendations**:
- Auto-sync time every 6-12 hours
- Show "Last synced: X minutes ago" status
- Validate NTP server address before attempting connection
- Support automatic DST adjustment (use timezone library)

---

## Missing/Unclear Elements

### Questions & Clarifications Needed:

1. **Main Screen - Employee Feedback Area**
   - ❓ **Question**: Where does the "Hello John Doe! Time recorded" message appear?
   - **Recommendation**: Add a large central label/panel on main screen for employee messages
   - This is **critical** for user experience

2. **Main Screen - NFC Card Prompt**
   - ❓ **Question**: Where's the "Please present your card" instruction?
   - **Recommendation**: Same area as employee feedback, changes based on state

3. **Main Screen - Company Logo/Branding**
   - ❓ **Question**: Where should company logo appear?
   - **Recommendation**: Top center or top left corner

4. **Admin Login Screen - Input Elements**
   - ❓ **Question**: What are the actual widget names for password input?
   - **Need**: Check SquareLine project for textarea and button names

5. **Time Information - Time Spinbox Format**
   - ❓ **Question**: How is the spinbox formatted? HHMM or separate H/M?
   - **Recommendation**: Consider separate hour/minute dropdowns for easier input

6. **Device Information - Dynamic Content**
   - ❓ **Question**: Does `informationarea` dynamically create labels, or are they pre-defined?
   - **Recommendation**: Container with vertical layout, dynamically add label pairs

7. **All Screens - Back/Close Buttons**
   - ✅ Most screens have Cancel/Close buttons - good!
   - Ensure all return to appropriate parent screen

8. **Bluetooth Icon**
   - ❓ **Question**: Is Bluetooth functionality planned?
   - If not needed, can hide this icon or repurpose for another indicator

---

## Recommended Additions to SquareLine Design

### Priority 1 (Critical):

1. **Main Screen - Message/Feedback Panel**
   ```
   Central area (300x200px) for:
   - Default: "Welcome! Present your card to clock in/out"
   - Active: "Card detected... please wait"
   - Success: "Hello [Name]! Time recorded at [Time]"
   - Error: "Card not recognized"
   ```

2. **Main Screen - Company Logo**
   ```
   Top center: Company logo image (200x80px)
   ```

### Priority 2 (Helpful):

3. **Setup Menu - Additional Buttons**
   - NFC Settings (test card reading, enable/disable)
   - Display Settings (brightness, sleep timeout)
   - System (restart, factory reset, logs)

4. **All Screens - Status Bar**
   - Consistent across all screens
   - Shows: Network status, Time, Battery (if using UPS)

5. **WiFi Setup - Signal Strength**
   - Show bars/percentage for currently connected network

6. **Device Info - Copy to Clipboard**
   - Long-press MAC/IP to copy (for sharing with IT)

### Priority 3 (Nice to Have):

7. **Main Screen - Recent Punches**
   - Small list showing last 5 clock events for transparency

8. **Time Settings - Visual Clock**
   - Analog or digital clock display showing current time being set

9. **Employee Hours Display**
   - After punch, show: "Today: 5.5 hrs | Week: 32 hrs"

---

## Integration Summary

### Elements That Are Perfect ✅:
- Setup menu navigation (all buttons clear)
- WiFi/Ethernet configuration (comprehensive)
- Time sync button
- Device info tabs (hardware/software)
- DHCP toggles

### Elements That Need Clarification ❓:
- Main screen message/feedback area **← MOST CRITICAL**
- Admin login input field names
- Time spinbox format
- Device info dynamic content structure

### Elements That Should Be Added ⚠️:
- Main screen: Employee message panel
- Main screen: Company logo/branding
- Main screen: "Present card" instruction
- Setup menu: NFC settings, Display settings, System options

---

## Next Steps

1. **Verify Main Screen Design** - Open SquareLine and check for message/feedback area
2. **Add Missing Elements** - Employee message panel, company logo
3. **Document Widget Names** - Especially for admin login password input
4. **Create Sample Event Handlers** - Based on this mapping
5. **Test Navigation Flow** - Ensure all screens connect properly

Would you like me to:
- Create a sample implementation for any specific screen?
- Generate complete event handler templates for all screens?
- Help redesign the main screen to add missing elements?

---

Last Updated: 2026-01-26
