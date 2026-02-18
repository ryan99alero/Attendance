#!/bin/bash
# ESP-IDF Setup Script (Python 3.13)
# Usage: source ./setup_idf.sh

export IDF_PYTHON_ENV_PATH="$HOME/.espressif/python_env/idf5.5_py3.13_env"
. ~/.espressif/frameworks/esp-idf-v5.5.1/export.sh

echo ""
echo "✅ ESP-IDF v5.5.1 activated (Python 3.13)"
echo ""
echo "Available commands:"
echo "  idf.py build         - Build the firmware"
echo "  idf.py flash         - Flash to device"
echo "  idf.py monitor       - Monitor serial output"
echo "  idf.py flash monitor - Flash and monitor"
echo ""
