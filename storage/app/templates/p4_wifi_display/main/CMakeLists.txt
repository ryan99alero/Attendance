idf_component_register(
    SRCS "app_main.c"
    REQUIRES
        esp_event
        esp_netif
        nvs_flash
        esp_wifi
        esp_wifi_remote
        esp_timer
        esp_lcd
        esp_lvgl_port
        esp32_p4_function_ev_board
)

# Add ESP32-C6 as WiFi slave target
target_compile_definitions(${COMPONENT_LIB} PUBLIC CONFIG_SLAVE_IDF_TARGET_ESP32C6=1)
