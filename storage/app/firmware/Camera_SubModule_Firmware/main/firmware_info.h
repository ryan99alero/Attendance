/**
 * Firmware Information Header
 * Contains version and build information for Camera SubModule
 */

#ifndef FIRMWARE_INFO_H
#define FIRMWARE_INFO_H

// Device identification
#define DEVICE_TYPE         "ESP32-S3-CAM"
#define DEVICE_MODEL        "XIAO-SENSE-OV5640"

// Semantic version - update these when releasing new firmware
#define FIRMWARE_VERSION_MAJOR 1
#define FIRMWARE_VERSION_MINOR 0
#define FIRMWARE_VERSION_PATCH 0

// String version for reporting (e.g., "1.0.0")
#define STRINGIFY(x) #x
#define TOSTRING(x) STRINGIFY(x)
#define FIRMWARE_VERSION TOSTRING(FIRMWARE_VERSION_MAJOR) "." \
                         TOSTRING(FIRMWARE_VERSION_MINOR) "." \
                         TOSTRING(FIRMWARE_VERSION_PATCH)

// Build timestamp for debugging
#define FIRMWARE_BUILD_DATE __DATE__
#define FIRMWARE_BUILD_TIME __TIME__

// Full version string with build info (e.g., "1.0.0 (Feb 18 2026 14:30:00)")
#define FIRMWARE_VERSION_FULL FIRMWARE_VERSION " (" FIRMWARE_BUILD_DATE " " FIRMWARE_BUILD_TIME ")"

#endif // FIRMWARE_INFO_H
