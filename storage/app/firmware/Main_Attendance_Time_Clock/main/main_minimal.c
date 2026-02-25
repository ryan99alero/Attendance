/**
 * STEPPED DISPLAY TEST
 * Initialize display components one at a time to find failure point
 */

#include <stdio.h>
#include "esp_log.h"
#include "nvs_flash.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_system.h"

// BSP and display
#include "bsp/esp32_p4_function_ev_board.h"
#include "esp_lvgl_port.h"
#include "lvgl.h"

static const char *TAG = "DISP_TEST";

void app_main(void) {
    printf("\n\n");
    printf("########################################\n");
    printf("#   STEPPED DISPLAY INIT TEST         #\n");
    printf("########################################\n\n");

    // Step 1: NVS
    printf(">>> STEP 1: NVS init...\n");
    esp_err_t ret = nvs_flash_init();
    if (ret == ESP_ERR_NVS_NO_FREE_PAGES || ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        nvs_flash_erase();
        ret = nvs_flash_init();
    }
    printf("    NVS: OK\n\n");

    // Step 2: I2C (needed for touch)
    printf(">>> STEP 2: I2C init (bsp_i2c_init)...\n");
    fflush(stdout);
    ret = bsp_i2c_init();
    printf("    I2C: %s (0x%x)\n\n", ret == ESP_OK ? "OK" : "FAILED", ret);

    // Step 3: Display new (creates panel without LVGL)
    printf(">>> STEP 3: Display panel init (bsp_display_new)...\n");
    fflush(stdout);
    esp_lcd_panel_handle_t panel = NULL;
    esp_lcd_panel_io_handle_t io = NULL;
    bsp_display_config_t disp_cfg = {
        .max_transfer_sz = 640 * 480 * 2,  // Smaller buffer for test
    };
    ret = bsp_display_new(&disp_cfg, &panel, &io);
    printf("    Display panel: %s (0x%x), panel=%p\n\n", ret == ESP_OK ? "OK" : "FAILED", ret, panel);

    if (ret != ESP_OK) {
        printf("!!! DISPLAY PANEL INIT FAILED - stopping here\n");
        while(1) { vTaskDelay(pdMS_TO_TICKS(1000)); }
    }

    // Step 4: LVGL port init
    printf(">>> STEP 4: LVGL port init (lvgl_port_init)...\n");
    fflush(stdout);
    const lvgl_port_cfg_t lvgl_cfg = ESP_LVGL_PORT_INIT_CONFIG();
    ret = lvgl_port_init(&lvgl_cfg);
    printf("    LVGL port: %s (0x%x)\n\n", ret == ESP_OK ? "OK" : "FAILED", ret);

    // Step 5: Add display to LVGL
    printf(">>> STEP 5: Add display to LVGL (lvgl_port_add_disp)...\n");
    fflush(stdout);
    const lvgl_port_display_cfg_t disp_port_cfg = {
        .panel_handle = panel,
        .buffer_size = 640 * 100,
        .double_buffer = false,
        .hres = 1024,
        .vres = 600,
        .rotation = {
            .swap_xy = false,
            .mirror_x = false,
            .mirror_y = false,
        },
    };
    lv_display_t *disp = lvgl_port_add_disp(&disp_port_cfg);
    printf("    LVGL display: %p\n\n", disp);

    // Step 6: Touch init (this is where it likely hangs)
    printf(">>> STEP 6: Touch init (bsp_touch_new)...\n");
    printf("    NOTE: If it hangs here, the GT911 touch controller is the problem\n");
    fflush(stdout);
    esp_lcd_touch_handle_t touch = NULL;
    bsp_touch_config_t touch_cfg = {};
    ret = bsp_touch_new(&touch_cfg, &touch);
    printf("    Touch: %s (0x%x), handle=%p\n\n", ret == ESP_OK ? "OK" : "FAILED", ret, touch);

    // Step 7: Add touch to LVGL
    if (touch != NULL) {
        printf(">>> STEP 7: Add touch to LVGL (lvgl_port_add_touch)...\n");
        fflush(stdout);
        const lvgl_port_touch_cfg_t touch_port_cfg = {
            .disp = disp,
            .handle = touch,
        };
        lv_indev_t *indev = lvgl_port_add_touch(&touch_port_cfg);
        printf("    LVGL touch: %p\n\n", indev);
    }

    // Step 8: Backlight
    printf(">>> STEP 8: Backlight on...\n");
    fflush(stdout);
    bsp_display_backlight_on();
    printf("    Backlight: ON\n\n");

    printf("########################################\n");
    printf("#   ALL STEPS COMPLETED!              #\n");
    printf("########################################\n\n");

    // Create test screen
    lvgl_port_lock(0);
    lv_obj_t *label = lv_label_create(lv_scr_act());
    lv_label_set_text(label, "DISPLAY WORKING!");
    lv_obj_center(label);
    lvgl_port_unlock();

    // Heartbeat
    int counter = 0;
    while (1) {
        printf("Heartbeat %d\n", counter++);
        vTaskDelay(pdMS_TO_TICKS(2000));
    }
}
