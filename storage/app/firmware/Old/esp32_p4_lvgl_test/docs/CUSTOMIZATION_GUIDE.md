# Touch Screen Customization Guide

Complete guide for customizing the ESP32-P4 Time Clock touch screen interface for specific deployment scenarios

## Table of Contents
- [Overview](#overview)
- [Common Customizations](#common-customizations)
- [Branding Customization](#branding-customization)
- [Feature Customization](#feature-customization)
- [Workflow Customization](#workflow-customization)
- [Advanced Customizations](#advanced-customizations)
- [Example Scenarios](#example-scenarios)

## Overview

This guide helps you customize the time clock interface for different use cases:
- Company branding (colors, logos)
- Different workflows (lunch break tracking, department selection)
- Language localization
- Accessibility requirements
- Industry-specific features

### Customization Approach

1. **Design Changes** → Edit in SquareLine Studio
2. **Behavior Changes** → Edit `ui_events.c` and firmware code
3. **API Integration** → Modify API client to match backend changes

## Common Customizations

### 1. Change Company Name/Logo

**SquareLine Studio**:
1. Open project in SquareLine
2. Select main screen (`ui_screen_mainscreen`)
3. Find company name label
4. Inspector → Text → Change to your company name
5. To add logo:
   - Assets → Images → Add Image
   - Upload your logo (PNG, max 200x80px recommended)
   - Drag image widget onto screen
   - Set image source to your logo

**Example**:
```
Before: "Time Clock System"
After:  "Acme Corporation Time Clock"
```

**Export and rebuild** after changes.

---

### 2. Change Color Scheme

**SquareLine Studio**:
1. Open Themes panel
2. Create new theme or edit existing
3. Set primary color (buttons, headers)
4. Set secondary color (backgrounds, accents)
5. Set text colors (ensure good contrast)

**Programmatic Color Changes** (for dynamic themes):

```c
// ui_manager.c
void ui_manager_apply_theme(theme_color_t primary, theme_color_t secondary)
{
    if(bsp_display_lock(0)) {
        // Apply primary color to buttons
        lv_obj_set_style_bg_color(ui_button_clockin,
            lv_color_hex(primary), LV_PART_MAIN);

        // Apply secondary color to background
        lv_obj_set_style_bg_color(ui_screen_mainscreen,
            lv_color_hex(secondary), LV_PART_MAIN);

        bsp_display_unlock();
    }
}

// Usage in main.c
void app_main(void)
{
    // ... initialization ...

    // Apply company colors
    ui_manager_apply_theme(0x1E3A8A, 0xF3F4F6); // Blue primary, light gray bg
}
```

**Color Palette Examples**:

```c
// Healthcare
#define THEME_HEALTHCARE_PRIMARY   0x0891B2  // Cyan
#define THEME_HEALTHCARE_SECONDARY 0xF0F9FF  // Light blue

// Manufacturing
#define THEME_MANUFACTURING_PRIMARY   0xEA580C  // Orange
#define THEME_MANUFACTURING_SECONDARY 0x292524  // Dark gray

// Retail
#define THEME_RETAIL_PRIMARY   0x7C3AED  // Purple
#define THEME_RETAIL_SECONDARY 0xFAF5FF  // Light purple

// Office
#define THEME_OFFICE_PRIMARY   0x2563EB  // Blue
#define THEME_OFFICE_SECONDARY 0xF8FAFC  // Gray
```

---

### 3. Change Welcome Message

**SquareLine Studio**:
1. Select main screen welcome label
2. Change text to your message

**Dynamic Message** (changes based on time of day):

```c
// ui_manager.c
void ui_manager_update_welcome_message(void)
{
    time_t now;
    struct tm timeinfo;
    time(&now);
    localtime_r(&now, &timeinfo);

    const char *message;
    int hour = timeinfo.tm_hour;

    if(hour < 12) {
        message = "Good Morning! Please present your card.";
    } else if(hour < 17) {
        message = "Good Afternoon! Please present your card.";
    } else {
        message = "Good Evening! Please present your card.";
    }

    if(bsp_display_lock(0)) {
        lv_label_set_text(ui_welcome_label, message);
        bsp_display_unlock();
    }
}

// Call from time update task every minute
```

---

### 4. Adjust Font Sizes

**For visually impaired or older workers**:

**SquareLine Studio**:
1. Select text widget
2. Inspector → Font → Size
3. Increase to 24pt (body text) or 36pt (headings)
4. Adjust widget size to fit larger text

**Recommended Sizes**:
- Heading: 36-48pt
- Body text: 20-24pt
- Small text: 16-18pt
- Button text: 24-28pt

---

### 5. Add Audio Feedback

**Play sound on successful/failed punch**:

```c
// audio_manager.c
#include "driver/i2s.h"

typedef enum {
    SOUND_SUCCESS,
    SOUND_ERROR,
    SOUND_WARNING
} sound_type_t;

void audio_manager_play_sound(sound_type_t type)
{
    switch(type) {
        case SOUND_SUCCESS:
            // Play "ding" sound
            play_wav_file("/spiffs/success.wav");
            break;
        case SOUND_ERROR:
            // Play "buzz" sound
            play_wav_file("/spiffs/error.wav");
            break;
        case SOUND_WARNING:
            // Play "beep" sound
            play_wav_file("/spiffs/warning.wav");
            break;
    }
}

// Usage in punch handler
void on_punch_response(api_response_t *response)
{
    if(response->success) {
        audio_manager_play_sound(SOUND_SUCCESS);
        ui_manager_show_message(response->display_message, true);
    } else {
        audio_manager_play_sound(SOUND_ERROR);
        ui_manager_show_message(response->display_message, false);
    }
}
```

## Branding Customization

### Full Branding Package

**Assets to prepare**:
1. Logo (PNG, transparent background)
   - Size: 200x80px or 400x160px @2x
   - Format: PNG-24 with transparency

2. Color palette:
   - Primary color (buttons, highlights)
   - Secondary color (backgrounds)
   - Text colors (dark/light variants)
   - Success/error/warning colors

3. Custom fonts (optional):
   - Company font (TTF/OTF)
   - Size variants needed

**Implementation Steps**:

1. **Add logo to SquareLine**:
   ```
   Assets → Images → Add → Select logo file
   ```

2. **Place logo on screens**:
   - Main screen: Top center or top left
   - Admin screens: Smaller version in header
   - About screen: Larger centered version

3. **Apply color theme** (see section 2 above)

4. **Add custom font**:
   ```
   Assets → Fonts → Add Font → Select TTF file
   Range: ASCII (0x20-0x7E) + extended if needed
   Sizes: 16, 20, 24, 32, 48
   ```

5. **Update all text** to use company terminology

## Feature Customization

### Scenario: Add Department Selection

**Use case**: Employees work in multiple departments and need to select which department they're clocking into.

**Step 1: Design UI in SquareLine**

1. Add new screen: `ui_screen_department_select`
2. Add title label: "Select Department"
3. Add dropdown widget: `ui_department_dropdown`
4. Add "Confirm" button: `ui_button_department_confirm`
5. Add "Cancel" button: `ui_button_department_cancel`

**Step 2: Modify Main Screen Flow**

Change navigation after card scan to go to department selection instead of directly recording punch.

**Step 3: Implement Event Handler**

```c
// ui_events.c
#include "ui_manager.h"

// Store card info temporarily
static char g_pending_card_uid[32] = {0};

void ui_event_card_scanned(const char *card_uid)
{
    // Save card UID
    strncpy(g_pending_card_uid, card_uid, sizeof(g_pending_card_uid));

    // Navigate to department selection
    if(bsp_display_lock(0)) {
        lv_scr_load(ui_screen_department_select);
        bsp_display_unlock();
    }
}

void ui_event_department_confirm_clicked(lv_event_t * e)
{
    if(lv_event_get_code(e) != LV_EVENT_CLICKED) return;

    // Get selected department
    uint16_t selected = lv_dropdown_get_selected(ui_department_dropdown);
    char dept_name[64];
    lv_dropdown_get_selected_str(ui_department_dropdown, dept_name, sizeof(dept_name));

    ESP_LOGI("UI", "Department selected: %s", dept_name);

    // Send punch with department info
    api_client_send_punch_with_dept(
        "nfc",
        g_pending_card_uid,
        dept_name
    );

    // Return to main screen
    if(bsp_display_lock(0)) {
        lv_scr_load(ui_screen_mainscreen);
        bsp_display_unlock();
    }
}
```

**Step 4: Update API Client**

```c
// api_client.c
void api_client_send_punch_with_dept(const char *kind, const char *value, const char *dept)
{
    cJSON *json = cJSON_CreateObject();
    cJSON_AddStringToObject(json, "device_id", g_device_id);
    cJSON_AddStringToObject(json, "credential_kind", kind);
    cJSON_AddStringToObject(json, "credential_value", value);
    // ... other fields ...

    // Add department to meta
    cJSON *meta = cJSON_CreateObject();
    cJSON_AddStringToObject(meta, "department", dept);
    cJSON_AddItemToObject(json, "meta", meta);

    // Send to API
    http_client_post("/api/v1/timeclock/punch", json, on_punch_response);
    cJSON_Delete(json);
}
```

**Step 5: Update Backend** (Laravel):

```php
// TimeClockController.php
public function recordPunch(Request $request)
{
    // ... existing validation ...

    // Extract department from meta
    $department = $request->input('meta.department');

    $clockEvent = ClockEvent::create([
        // ... existing fields ...
        'department' => $department,
    ]);
}
```

---

### Scenario: Add Break Time Tracking

**Use case**: Track when employees go on break and return.

**Step 1: Add Break Buttons to Main Screen**

In SquareLine:
1. Add "Start Break" button
2. Add "End Break" button
3. Show/hide based on current state

**Step 2: Implement State Machine**

```c
// punch_state.h
typedef enum {
    PUNCH_STATE_CLOCKED_OUT,
    PUNCH_STATE_CLOCKED_IN,
    PUNCH_STATE_ON_BREAK
} punch_state_t;

typedef struct {
    punch_state_t state;
    char employee_id[32];
    time_t clock_in_time;
    time_t break_start_time;
} punch_context_t;

extern punch_context_t g_punch_context;

// punch_state.c
void punch_state_update_ui(void)
{
    if(bsp_display_lock(0)) {
        switch(g_punch_context.state) {
            case PUNCH_STATE_CLOCKED_OUT:
                lv_obj_add_flag(ui_button_start_break, LV_OBJ_FLAG_HIDDEN);
                lv_obj_add_flag(ui_button_end_break, LV_OBJ_FLAG_HIDDEN);
                lv_label_set_text(ui_status_label, "Please clock in");
                break;

            case PUNCH_STATE_CLOCKED_IN:
                lv_obj_clear_flag(ui_button_start_break, LV_OBJ_FLAG_HIDDEN);
                lv_obj_add_flag(ui_button_end_break, LV_OBJ_FLAG_HIDDEN);
                lv_label_set_text(ui_status_label, "Clocked In");
                break;

            case PUNCH_STATE_ON_BREAK:
                lv_obj_add_flag(ui_button_start_break, LV_OBJ_FLAG_HIDDEN);
                lv_obj_clear_flag(ui_button_end_break, LV_OBJ_FLAG_HIDDEN);
                lv_label_set_text(ui_status_label, "On Break");
                break;
        }
        bsp_display_unlock();
    }
}
```

**Step 3: Handle Break Events**

```c
// ui_events.c
void ui_event_start_break_clicked(lv_event_t * e)
{
    if(lv_event_get_code(e) != LV_EVENT_CLICKED) return;

    // Send break start punch
    api_client_send_punch_with_type("nfc", g_current_card_uid, "break_in");

    // Update state
    g_punch_context.state = PUNCH_STATE_ON_BREAK;
    g_punch_context.break_start_time = time(NULL);
    punch_state_update_ui();
}

void ui_event_end_break_clicked(lv_event_t * e)
{
    if(lv_event_get_code(e) != LV_EVENT_CLICKED) return;

    // Calculate break duration
    time_t break_duration = time(NULL) - g_punch_context.break_start_time;
    ESP_LOGI("BREAK", "Break duration: %ld seconds", break_duration);

    // Send break end punch
    api_client_send_punch_with_type("nfc", g_current_card_uid, "break_out");

    // Update state
    g_punch_context.state = PUNCH_STATE_CLOCKED_IN;
    punch_state_update_ui();
}
```

---

### Scenario: Multi-Language Support

**Use case**: Support Spanish and English languages.

**Step 1: Define String Tables**

```c
// i18n.h
typedef enum {
    LANG_ENGLISH,
    LANG_SPANISH
} language_t;

typedef enum {
    STR_WELCOME,
    STR_PRESENT_CARD,
    STR_SUCCESS,
    STR_ERROR,
    STR_CLOCK_IN,
    STR_CLOCK_OUT,
    // ... add more ...
    STR_MAX
} string_id_t;

extern language_t g_current_language;

const char* i18n_get_string(string_id_t id);
void i18n_set_language(language_t lang);

// i18n.c
static const char* strings_en[] = {
    [STR_WELCOME] = "Welcome!",
    [STR_PRESENT_CARD] = "Please present your card",
    [STR_SUCCESS] = "Success!",
    [STR_ERROR] = "Error",
    [STR_CLOCK_IN] = "Clock In",
    [STR_CLOCK_OUT] = "Clock Out",
};

static const char* strings_es[] = {
    [STR_WELCOME] = "¡Bienvenido!",
    [STR_PRESENT_CARD] = "Por favor presente su tarjeta",
    [STR_SUCCESS] = "¡Éxito!",
    [STR_ERROR] = "Error",
    [STR_CLOCK_IN] = "Entrada",
    [STR_CLOCK_OUT] = "Salida",
};

language_t g_current_language = LANG_ENGLISH;

const char* i18n_get_string(string_id_t id)
{
    if(id >= STR_MAX) return "???";

    switch(g_current_language) {
        case LANG_SPANISH:
            return strings_es[id];
        default:
            return strings_en[id];
    }
}

void i18n_set_language(language_t lang)
{
    g_current_language = lang;
    // Update all UI labels
    ui_manager_refresh_all_text();
}
```

**Step 2: Add Language Toggle**

Add button on admin screen to toggle language:

```c
void ui_event_language_toggle_clicked(lv_event_t * e)
{
    if(g_current_language == LANG_ENGLISH) {
        i18n_set_language(LANG_SPANISH);
    } else {
        i18n_set_language(LANG_ENGLISH);
    }

    // Save preference to NVS
    save_language_preference(g_current_language);
}
```

**Step 3: Update UI Manager**

```c
// ui_manager.c
void ui_manager_refresh_all_text(void)
{
    if(bsp_display_lock(0)) {
        lv_label_set_text(ui_welcome_label, i18n_get_string(STR_WELCOME));
        lv_label_set_text(ui_instruction_label, i18n_get_string(STR_PRESENT_CARD));
        lv_label_set_text(ui_button_clockin_label, i18n_get_string(STR_CLOCK_IN));
        // ... update all labels ...
        bsp_display_unlock();
    }
}
```

## Workflow Customization

### Custom Workflow: Photo Capture on Punch

**Use case**: Take a photo when employee clocks in for security/verification.

**Hardware**: Add USB camera or use BSP camera module

**Step 1: Initialize Camera**

```c
// camera_manager.c
#include "esp_camera.h"

esp_err_t camera_manager_init(void)
{
    camera_config_t config = {
        .pin_d0 = CAM_PIN_D0,
        .pin_d1 = CAM_PIN_D1,
        // ... configure pins ...
        .frame_size = FRAMESIZE_VGA,
        .jpeg_quality = 12,
    };

    esp_err_t err = esp_camera_init(&config);
    if(err != ESP_OK) {
        ESP_LOGE(TAG, "Camera init failed: 0x%x", err);
        return err;
    }

    ESP_LOGI(TAG, "Camera initialized");
    return ESP_OK;
}

esp_err_t camera_manager_capture(uint8_t **out_buf, size_t *out_len)
{
    camera_fb_t *fb = esp_camera_fb_get();
    if(!fb) {
        ESP_LOGE(TAG, "Camera capture failed");
        return ESP_FAIL;
    }

    *out_buf = fb->buf;
    *out_len = fb->len;

    // Note: caller must call esp_camera_fb_return(fb) after use
    return ESP_OK;
}
```

**Step 2: Capture on Punch**

```c
// Modified punch handler
void on_card_scanned(const char *card_uid)
{
    // Show camera preview on screen
    ui_manager_show_camera_preview();

    // Wait 2 seconds for user to look at camera
    vTaskDelay(pdMS_TO_TICKS(2000));

    // Capture photo
    uint8_t *photo_buf;
    size_t photo_len;

    if(camera_manager_capture(&photo_buf, &photo_len) == ESP_OK) {
        ESP_LOGI(TAG, "Photo captured: %d bytes", photo_len);

        // Send punch with photo
        api_client_send_punch_with_photo(card_uid, photo_buf, photo_len);

        esp_camera_fb_return((camera_fb_t*)photo_buf);
    } else {
        // Send punch without photo
        api_client_send_punch("nfc", card_uid);
    }

    // Return to main screen
    ui_manager_show_main_screen();
}
```

**Step 3: Upload Photo to API**

```c
// api_client.c - Multipart form upload
void api_client_send_punch_with_photo(const char *card_uid, uint8_t *photo, size_t photo_len)
{
    // Build multipart/form-data request
    char boundary[] = "----ESP32Boundary";

    // Create payload with photo as base64 or binary
    // Send to API with photo attachment
    // Backend should save photo and associate with clock event
}
```

## Advanced Customizations

### Dynamic UI Loading

**Load different UI layouts based on department or role**:

```c
typedef enum {
    UI_LAYOUT_STANDARD,
    UI_LAYOUT_SIMPLIFIED,  // For warehouse workers with gloves
    UI_LAYOUT_KIOSK        // For public-facing kiosks
} ui_layout_t;

void ui_manager_load_layout(ui_layout_t layout)
{
    switch(layout) {
        case UI_LAYOUT_STANDARD:
            ui_init(); // Load standard UI
            break;
        case UI_LAYOUT_SIMPLIFIED:
            ui_simplified_init(); // Large buttons, minimal text
            break;
        case UI_LAYOUT_KIOSK:
            ui_kiosk_init(); // Read-only, no admin access
            break;
    }
}
```

### Gesture Support

**Add swipe gestures for navigation**:

```c
// Add gesture detector to screen
void ui_screen_add_gestures(lv_obj_t *screen)
{
    lv_obj_add_event_cb(screen, gesture_event_cb, LV_EVENT_GESTURE, NULL);
}

void gesture_event_cb(lv_event_t *e)
{
    lv_dir_t dir = lv_indev_get_gesture_dir(lv_indev_get_act());

    switch(dir) {
        case LV_DIR_LEFT:
            // Swipe left - next screen
            break;
        case LV_DIR_RIGHT:
            // Swipe right - previous screen
            break;
        case LV_DIR_TOP:
            // Swipe up - admin menu
            break;
        case LV_DIR_BOTTOM:
            // Swipe down - refresh
            break;
    }
}
```

## Example Scenarios

### Healthcare Facility

**Requirements**:
- Clean, professional interface
- Large text for easy reading
- Photo capture on clock-in
- Department selection
- Hand hygiene reminder

**Customizations**:
1. White/light blue color scheme
2. 24pt minimum font size
3. Camera integration
4. Department dropdown
5. Show "Please wash hands" message after clock-in

---

### Manufacturing Plant

**Requirements**:
- High contrast (for bright/dark areas)
- Touch works with gloves
- Break time tracking
- Shift information
- Loud audio feedback

**Customizations**:
1. Orange/black color scheme (high contrast)
2. Extra large buttons (100x100px minimum)
3. Break start/end buttons
4. Display current shift on main screen
5. Amplified speaker for beeps

---

### Retail Store

**Requirements**:
- Match store branding
- Employee discount code display
- Schedule display
- Quick clock-in (no extra steps)

**Customizations**:
1. Store brand colors
2. Show employee discount code after clock-in
3. Display today's schedule
4. Simple one-tap clock-in

## Testing Your Customizations

### Checklist

- [ ] All screens display correctly
- [ ] Touch targets are adequate size
- [ ] Text is readable from 3 feet away
- [ ] Colors have sufficient contrast (WCAG AA)
- [ ] Navigation is intuitive
- [ ] Error messages are clear
- [ ] Success feedback is obvious
- [ ] Language/terminology is appropriate
- [ ] Branding is consistent
- [ ] Performance is smooth (no lag on touch)

### User Acceptance Testing

1. Have actual users test the interface
2. Observe without instruction
3. Note points of confusion
4. Collect feedback on:
   - Ease of use
   - Clarity of instructions
   - Visual appeal
   - Speed of operation

## Best Practices

1. **Keep it simple** - Don't add complexity that users don't need
2. **Test with actual users** - What makes sense to you may confuse others
3. **Consider environment** - Bright? Dark? Dusty? Wet?
4. **Accessibility matters** - Consider vision, motor, cognitive impairments
5. **Consistent design** - Use same patterns throughout
6. **Provide feedback** - Always confirm user actions
7. **Handle errors gracefully** - Clear messages, easy recovery
8. **Document changes** - Keep notes on what you customized and why

## Getting Help

- Review SquareLine documentation for UI design
- Check ESP32 forums for hardware questions
- Refer to LVGL docs for advanced styling
- Contact your development team for API changes

## Next Steps

- Apply your customizations
- Test thoroughly
- Deploy to test device
- Collect user feedback
- Iterate and improve

---

Last Updated: 2026-01-26
For: ESP32-P4 Time Clock System
