idf_component_register(
    SRCS "main.c"
    INCLUDE_DIRS "."
    REQUIRES
        esp_lcd
        esp_lvgl_port
        lvgl__lvgl
        espressif__esp32_p4_function_ev_board
)
