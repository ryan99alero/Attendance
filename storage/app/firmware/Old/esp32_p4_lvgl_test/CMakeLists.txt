# ESP32-P4 LVGL SquareLine Studio Test Firmware
cmake_minimum_required(VERSION 3.16)

# Set the components to include the UI folder (SquareLine Studio export)
set(EXTRA_COMPONENT_DIRS ui)

include($ENV{IDF_PATH}/tools/cmake/project.cmake)
project(esp32_p4_lvgl_test)