idf_component_register(SRCS "main.c"
                            "nfc_reader.c"
                            "pn532.c"
                            "pn532_driver.c"
                            "pn532_driver_spi.c"
                            "pn532_driver_i2c.c"
                            "pn532_driver_hsu.c"
                            "ui_manager.c"
                            "time_settings.c"
                            "wifi_manager.c"
                            "ethernet_manager.c"
                            "network_manager.c"
                            "api_client.c"
                   INCLUDE_DIRS "."
                   REQUIRES driver esp_timer nvs_flash esp_wifi esp_netif esp_eth lwip
                            spi_flash esp_partition esp_psram app_update
                            esp_http_client
                            json
                            espressif__esp32_p4_function_ev_board
                            espressif__esp_lvgl_port)
