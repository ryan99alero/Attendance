idf_component_register(SRCS "main.c"
                            "nfc_reader.c"
                            "pn532.c"
                            "pn532_driver.c"
                            "pn532_driver_spi.c"
                            "pn532_driver_i2c.c"
                            "pn532_driver_hsu.c"
                            "ui_manager.c"
                            "wifi_manager.c"
                            "ethernet_manager.c"
                            "network_manager.c"
                            # "api_client.c"  # Enable when API_ENABLED=1
                   INCLUDE_DIRS "."
                   REQUIRES driver esp_timer nvs_flash esp_wifi esp_netif esp_eth lwip
                            espressif__esp32_p4_function_ev_board
                            espressif__esp_lvgl_port)
