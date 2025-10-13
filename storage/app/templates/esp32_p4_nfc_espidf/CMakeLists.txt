# ESP32-P4 NFC Reader Project
cmake_minimum_required(VERSION 3.16)

# Set the components to search for dependencies
set(EXTRA_COMPONENT_DIRS $ENV{IDF_PATH}/examples/common_components/protocol_examples_common)

include($ENV{IDF_PATH}/tools/cmake/project.cmake)
project(esp32_p4_nfc_reader)