#include <string.h>
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_event.h"
#include "esp_log.h"
#include "esp_timer.h"
#include "nvs_flash.h"
#include "esp_netif.h"
#include "esp_wifi.h"

#include "bsp/esp32_p4_function_ev_board.h"   // Board BSP
#include "esp_lvgl_port.h"
#include "lvgl.h"

static const char *TAG = "P4_WIFI_DISPLAY";

/* Configure these in menuconfig → Example Configuration */
#define WIFI_SSID   CONFIG_EXAMPLE_WIFI_SSID
#define WIFI_PASS   CONFIG_EXAMPLE_WIFI_PASS

static void wifi_evt(void *arg, esp_event_base_t base, int32_t id, void *data)
{
    if (base == WIFI_EVENT && id == WIFI_EVENT_STA_START) {
        esp_wifi_connect();
    } else if (base == WIFI_EVENT && id == WIFI_EVENT_STA_DISCONNECTED) {
        ESP_LOGW(TAG, "Wi-Fi disconnected, retrying…");
        esp_wifi_connect();
    } else if (base == IP_EVENT && id == IP_EVENT_STA_GOT_IP) {
        ip_event_got_ip_t *e = (ip_event_got_ip_t *)data;
        ESP_LOGI(TAG, "Got IP: " IPSTR, IP2STR(&e->ip_info.ip));
    }
}

static void ui_make_screen(void)
{
    lv_obj_t *scr = lv_scr_act();

    lv_obj_t *label = lv_label_create(scr);
    lv_label_set_text(label, "Hello Wi-Fi (ESP32-P4 + C6)");
    lv_obj_center(label);
}

void app_main(void)
{
    ESP_LOGI(TAG, "Booting…");

    // --- NVS (Wi-Fi stores calibration/creds here)
    esp_err_t err = nvs_flash_init();
    if (err == ESP_ERR_NVS_NO_FREE_PAGES || err == ESP_ERR_NVS_NEW_VERSION_FOUND) {
        ESP_ERROR_CHECK(nvs_flash_erase());
        ESP_ERROR_CHECK(nvs_flash_init());
    } else {
        ESP_ERROR_CHECK(err);
    }

    // --- Display (via BSP + LVGL)
    // The BSP takes care of the display panel/IO clocks/pins for this board
    lv_display_t *disp = bsp_display_start();             // init display + backlight
    if (!disp) {
        ESP_LOGE("main", "Failed to start display");
        return;
    }
    const lvgl_port_cfg_t lvgl_cfg = ESP_LVGL_PORT_INIT_CONFIG();
    ESP_ERROR_CHECK(lvgl_port_init(&lvgl_cfg));           // start LVGL task/tick
    ui_make_screen();

    // --- Network stack
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    ESP_ERROR_CHECK(esp_netif_init());
    esp_netif_create_default_wifi_sta();                  // STA netif

    // --- Wi-Fi init (REMOTE -> routed to ESP32-C6 over SDIO)
    wifi_init_config_t wcfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&wcfg));

    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, ESP_EVENT_ANY_ID, &wifi_evt, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(IP_EVENT,   IP_EVENT_STA_GOT_IP, &wifi_evt, NULL));

    wifi_config_t scfg = { 0 };
    strncpy((char *)scfg.sta.ssid, WIFI_SSID, sizeof(scfg.sta.ssid));
    strncpy((char *)scfg.sta.password, WIFI_PASS, sizeof(scfg.sta.password));
    scfg.sta.threshold.authmode = WIFI_AUTH_WPA2_PSK;

    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &scfg));
    ESP_ERROR_CHECK(esp_wifi_start());

    ESP_LOGI(TAG, "Wi-Fi starting (remote over SDIO)…");

    // Keep LVGL alive (BSP port runs its own task, but a feed loop is fine)
    while (1) {
        vTaskDelay(pdMS_TO_TICKS(1000));
    }
}
