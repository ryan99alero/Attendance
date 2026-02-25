# ESP32 Camera Capture Module — SPI Architecture

## Project Context

This document describes the camera capture subsystem of a **Time Attendance Solution** built on:

- **Backend**: PHP Laravel with Filament admin panel
- **Main Controller**: ESP32-P4 with 7" touchscreen display, card reader, and backend API communication
- **Camera Module**: XIAO ESP32-S3 Sense with OV5640 camera (5MP upgrade from stock OV2640)
- **Backend API**: REST endpoints on Laravel handling clock-in/out, employee management, photo storage
- **Firmware**: ESP-IDF (not Arduino framework)

The ESP32-P4 is the primary device running the time clock UI (LVGL-based), handling card swipes, and communicating with the Laravel backend over WiFi/Ethernet. The XIAO ESP32-S3 Sense is a **dedicated camera capture device** connected to the P4 via SPI bus.

---

## Hardware Connection: SPI Bus

### Why SPI Over Other Options

| Option | Speed | Complexity | Resilience | Verdict |
|--------|-------|-----------|------------|---------|
| UART | ~115KB/s at 921600 baud | 2 wires + GND | Simple but slow for images | Too slow |
| SPI | Multi-MB/s | 4-5 wires | Deterministic, no IP stack | **Selected** |
| ESP-NOW | ~125KB/s | Wireless | No router needed, but RF variability | Good if separated physically |
| WiFi AP | Variable | Full TCP/IP stack | Overkill, connection management overhead | Rejected |

SPI was chosen because both devices will be in the same enclosure or very close proximity. SPI provides deterministic, high-speed transfers with no networking overhead. A 30KB JPEG transfers in milliseconds.

### Pin Connections

The ESP32-P4 acts as **SPI Master**. The XIAO ESP32-S3 Sense acts as **SPI Slave**.

```
ESP32-P4 (Master)          XIAO ESP32-S3 Sense (Slave)
─────────────────          ──────────────────────────
MOSI  ──────────────────►  MOSI
MISO  ◄──────────────────  MISO
SCLK  ──────────────────►  SCLK
CS    ──────────────────►  CS
GND   ──────────────────►  GND
(optional) GPIO ◄─────────  READY/IRQ pin (slave signals data available)
```

> **Pin assignments TBD** — Must be selected to avoid conflicts with the P4's touchscreen display (SPI/RGB interface) and the S3's camera (DVP bus) and SD card (shared SPI). The S3 Sense camera uses specific GPIO pins for DVP; SPI slave pins must be chosen from remaining free GPIOs.

### SPI Configuration

```c
// Target SPI clock: 10-20 MHz (balance speed vs signal integrity on short wires)
// SPI Mode: 0 (CPOL=0, CPHA=0) — most common default
// Max transaction size: ~64KB per transfer (ESP-IDF SPI slave default DMA buffer)
// For images >64KB, implement chunked transfer protocol
```

---

## Communication Protocol

### Overview

The P4 (master) initiates all communication. The S3 (slave) only responds. An optional READY/IRQ GPIO pin allows the S3 to signal the P4 that a captured image is ready for retrieval, avoiding unnecessary polling.

### Command Structure

```c
// Commands sent from P4 (Master) to S3 (Slave)
typedef enum {
    CMD_CAPTURE_PHOTO   = 0x01,  // Trigger camera capture
    CMD_GET_STATUS      = 0x02,  // Query slave status (ready, busy, error)
    CMD_GET_IMAGE_INFO  = 0x03,  // Get image size in bytes before transfer
    CMD_GET_IMAGE_DATA  = 0x04,  // Transfer image data (chunked)
    CMD_SET_RESOLUTION  = 0x10,  // Configure capture resolution
    CMD_SET_QUALITY     = 0x11,  // Configure JPEG quality
    CMD_PING            = 0xFE,  // Health check
} spi_command_t;

// Status responses from S3 (Slave) to P4 (Master)
typedef enum {
    STATUS_IDLE         = 0x00,  // Ready for commands
    STATUS_CAPTURING    = 0x01,  // Camera capture in progress
    STATUS_READY        = 0x02,  // Image captured and ready for transfer
    STATUS_ERROR        = 0xFF,  // Error occurred
} spi_status_t;

// Command packet (Master → Slave)
typedef struct __attribute__((packed)) {
    uint8_t  command;       // spi_command_t
    uint8_t  reserved;      // Alignment
    uint16_t payload_len;   // Length of optional payload
    uint8_t  payload[];     // Variable-length payload
} spi_cmd_packet_t;

// Response header (Slave → Master)
typedef struct __attribute__((packed)) {
    uint8_t  status;        // spi_status_t
    uint8_t  error_code;    // 0 = no error
    uint32_t data_len;      // Length of response data following this header
} spi_resp_header_t;
```

### Image Transfer Flow

```
P4 (Master)                          S3 (Slave)
    │                                     │
    │── CMD_CAPTURE_PHOTO ──────────────►│
    │                                     │── Triggers OV5640 capture
    │                                     │── Encodes JPEG to RAM buffer
    │                                     │── Asserts READY pin (optional)
    │                                     │
    │── CMD_GET_IMAGE_INFO ─────────────►│
    │◄── {status: READY, data_len: N} ───│
    │                                     │
    │── CMD_GET_IMAGE_DATA (offset=0) ──►│
    │◄── [chunk 0: up to 4096 bytes] ────│
    │                                     │
    │── CMD_GET_IMAGE_DATA (offset=4096)►│
    │◄── [chunk 1: up to 4096 bytes] ────│
    │                                     │
    │   ... repeat until all N bytes .... │
    │                                     │
    │   (P4 now has full JPEG in RAM)     │
    │   (P4 POSTs to Laravel API)         │
```

### Chunked Transfer Detail

Images will typically be 15-40KB (640×480 JPEG at 70-80% quality). ESP-IDF SPI DMA buffers are commonly configured at 4096 bytes. The transfer is chunked:

```c
// On P4 (Master) side — pseudocode
esp_err_t retrieve_image(uint8_t *image_buf, uint32_t image_size) {
    uint32_t offset = 0;
    uint32_t chunk_size = 4096;

    while (offset < image_size) {
        uint32_t remaining = image_size - offset;
        uint32_t this_chunk = (remaining < chunk_size) ? remaining : chunk_size;

        // Send GET_IMAGE_DATA command with offset
        spi_cmd_packet_t cmd = {
            .command = CMD_GET_IMAGE_DATA,
            .payload_len = sizeof(uint32_t),
        };
        // payload contains offset

        spi_transaction_t trans = {
            .tx_buffer = &cmd,
            .rx_buffer = image_buf + offset,
            .length = this_chunk * 8,  // bits
        };

        spi_device_transmit(spi_handle, &trans);
        offset += this_chunk;
    }
    return ESP_OK;
}
```

---

## Camera Capture Settings

### Resolution & Compression Strategy

Facial recognition models internally resize to 160×160 or 224×224 pixels. Higher resolution does not improve recognition accuracy — clean, low-artifact images do.

| Setting | Value | Rationale |
|---------|-------|-----------|
| Resolution | 640×480 (VGA) | Sufficient for facial recognition, small file size |
| JPEG Quality | 70-80% | Clean features, ~25-40KB file size |
| Color | RGB (not grayscale) | Some models use color channel info |

### OV5640 Configuration on S3

```c
// Camera configuration for XIAO ESP32-S3 Sense with OV5640
camera_config_t camera_config = {
    .pin_pwdn  = -1,
    .pin_reset = -1,
    .pin_xclk  = CAM_PIN_XCLK,     // XIAO S3 Sense specific pins
    .pin_sccb_sda = CAM_PIN_SIOD,
    .pin_sccb_scl = CAM_PIN_SIOC,
    .pin_d7 = CAM_PIN_D7,
    .pin_d6 = CAM_PIN_D6,
    .pin_d5 = CAM_PIN_D5,
    .pin_d4 = CAM_PIN_D4,
    .pin_d3 = CAM_PIN_D3,
    .pin_d2 = CAM_PIN_D2,
    .pin_d1 = CAM_PIN_D1,
    .pin_d0 = CAM_PIN_D0,
    .pin_vsync = CAM_PIN_VSYNC,
    .pin_href  = CAM_PIN_HREF,
    .pin_pclk  = CAM_PIN_PCLK,

    .xclk_freq_hz = 20000000,       // 20MHz XCLK
    .ledc_timer   = LEDC_TIMER_0,
    .ledc_channel = LEDC_CHANNEL_0,

    .pixel_format = PIXFORMAT_JPEG,
    .frame_size   = FRAMESIZE_VGA,   // 640x480
    .jpeg_quality = 20,              // 1-63, lower = better quality. 20 ≈ 70-80% quality
    .fb_count     = 2,               // Double buffer for capture while transferring
    .grab_mode    = CAMERA_GRAB_LATEST,
};
```

---

## S3 Slave Firmware — High Level Structure

```
main/
├── main.c                  // App entry, init SPI slave + camera
├── spi_slave_handler.c     // SPI slave ISR and command processing
├── spi_slave_handler.h
├── camera_capture.c        // OV5640 init, capture, buffer management
├── camera_capture.h
├── protocol.h              // Shared command/status definitions (copy to P4 project too)
└── led_status.c            // Optional: onboard LED for status indication
```

### S3 Main Loop Logic

```c
void app_main(void) {
    // 1. Initialize camera (OV5640)
    camera_init(&camera_config);

    // 2. Initialize SPI slave on selected pins
    spi_slave_init();

    // 3. Optionally configure READY/IRQ GPIO as output
    gpio_set_direction(READY_PIN, GPIO_MODE_OUTPUT);
    gpio_set_level(READY_PIN, 0);  // Not ready

    // 4. Main loop: wait for SPI commands from P4
    while (1) {
        spi_cmd_packet_t cmd;
        spi_slave_receive(&cmd);  // Blocking wait for master transaction

        switch (cmd.command) {
            case CMD_CAPTURE_PHOTO:
                // Capture frame from OV5640 into internal buffer
                capture_photo();
                gpio_set_level(READY_PIN, 1);  // Signal P4
                break;

            case CMD_GET_STATUS:
                send_status_response();
                break;

            case CMD_GET_IMAGE_INFO:
                send_image_info();  // Responds with image size in bytes
                break;

            case CMD_GET_IMAGE_DATA:
                // Extract offset from cmd payload
                uint32_t offset = *(uint32_t*)cmd.payload;
                send_image_chunk(offset, CHUNK_SIZE);
                break;

            case CMD_PING:
                send_pong();
                break;

            default:
                send_error(ERR_UNKNOWN_CMD);
                break;
        }
    }
}
```

---

## P4 Master Side — Integration Points

On the ESP32-P4, the camera module is accessed as a peripheral. The P4 firmware needs:

### SPI Master Initialization

```c
// Initialize SPI master bus (separate from display SPI if display uses SPI)
spi_bus_config_t bus_cfg = {
    .mosi_io_num = P4_CAM_MOSI_PIN,
    .miso_io_num = P4_CAM_MISO_PIN,
    .sclk_io_num = P4_CAM_SCLK_PIN,
    .max_transfer_sz = 4096,
};

spi_device_interface_config_t dev_cfg = {
    .clock_speed_hz = 10 * 1000 * 1000,  // 10 MHz
    .mode = 0,
    .spics_io_num = P4_CAM_CS_PIN,
    .queue_size = 1,
};

spi_bus_initialize(SPI3_HOST, &bus_cfg, SPI_DMA_CH_AUTO);
spi_bus_add_device(SPI3_HOST, &dev_cfg, &cam_spi_handle);
```

### Capture & Upload Flow (P4 Side)

This is triggered by a card swipe event on the P4:

```c
// Called when employee swipes badge
void on_badge_swipe(uint32_t employee_id) {
    // 1. Command S3 to capture photo
    spi_send_command(cam_spi_handle, CMD_CAPTURE_PHOTO, NULL, 0);

    // 2. Wait for READY pin or poll status
    while (!gpio_get_level(CAM_READY_PIN)) {
        vTaskDelay(pdMS_TO_TICKS(10));
    }

    // 3. Get image size
    spi_resp_header_t info;
    spi_send_command(cam_spi_handle, CMD_GET_IMAGE_INFO, NULL, 0);
    spi_read_response(cam_spi_handle, &info, sizeof(info));

    // 4. Allocate buffer and retrieve image
    uint8_t *jpeg_buf = heap_caps_malloc(info.data_len, MALLOC_CAP_DMA);
    retrieve_image(jpeg_buf, info.data_len);

    // 5. POST to Laravel API (multipart form data)
    // POST /api/attendance/clock-in
    // Fields: employee_id, device_id, timestamp
    // File: photo (JPEG binary)
    http_post_clock_in(employee_id, jpeg_buf, info.data_len);

    free(jpeg_buf);
}
```

---

## Laravel API Endpoint (Backend)

The P4 sends a multipart POST to the Laravel backend:

```
POST /api/attendance/clock-in
Content-Type: multipart/form-data

Fields:
  - employee_id: integer
  - device_id: string (unique device identifier)
  - timestamp: ISO 8601 string (from P4's RTC)

File:
  - photo: JPEG binary (field name "photo")
```

### Server-Side Photo Processing

On the Laravel side, the uploaded photo is processed on ingest:

```
storage/app/attendance/
├── reference/{employee_id}.jpg              ← Enrolled headshots (uploaded via Filament admin)
├── clockins/2026/02/18/{attendance_id}_{employee_id}.jpg   ← Raw clock-in photos
├── clockins/2026/02/18/thumb_{attendance_id}.webp          ← 150×150 thumbnail for Filament lists
└── clockins/2026/02/18/recog_{attendance_id}.webp          ← 640×480 for facial recognition service
```

**Database stores**: `photo_path` (nullable string) + `face_embedding` (JSON/binary, 128-512 floats from recognition service).

**Retention policy**: Purge clock-in photos after configurable period (e.g., 90 days). Embeddings and attendance records are kept permanently.

### Facial Recognition Flow (Future Phase)

A Python microservice (face_recognition or DeepFace library) handles embeddings:

1. **Enrollment**: Employee reference photo uploaded via Filament → Python service computes embedding → stored in `employees.face_embedding`
2. **Verification (1-to-1)**: Clock-in photo arrives → Python service computes embedding → compared against employee's stored embedding → returns confidence score
3. **Identification (1-to-many, future)**: Face-only login without badge → compare against all stored embeddings → return best match above threshold

---

## V-Groove Measurement Notes (XPS Niche — Unrelated, Included for Context)

When computing interior measurements for V-groove folded pieces:

- V-groove width at surface: **7/8"** (45° bit, 7/16" deep, 1/16" skin)
- For a desired **interior** measurement of X inches between two folds: set distance between groove **centers** to `X + 7/16 + 7/16 = X + 7/8`
- Each fold consumes a half-groove-width (7/16") from the interior measurement

---

## Development Phases

1. **Phase 1**: SPI communication between P4 and S3 — ping/pong, basic command/response
2. **Phase 2**: Camera capture on S3 — trigger capture, retrieve JPEG over SPI
3. **Phase 3**: Integration with clock-in flow — badge swipe triggers capture + API upload
4. **Phase 4**: Admin panel photo review — display captured vs reference photos in Filament
5. **Phase 5**: Facial verification — Python microservice compares embeddings on clock-in
6. **Phase 6**: Face-only identification — no badge required, camera identifies employee

---

## Key Decisions & Rationale

| Decision | Choice | Why |
|----------|--------|-----|
| Connection type | SPI (wired) | Both devices in same housing, deterministic, no networking overhead |
| SPI roles | P4 = Master, S3 = Slave | P4 controls workflow, S3 is a peripheral |
| Camera resolution | 640×480 | Recognition models resize to 160-224px internally; higher res adds no accuracy |
| JPEG quality | 70-80% | Clean features for recognition, moderate compression avoids artifacts |
| Photo storage | Filesystem with DB path reference | Avoids DB bloat from blob storage |
| Chunked transfer | 4096 byte chunks over SPI | Fits ESP-IDF DMA buffer defaults |
| READY pin | GPIO interrupt from S3 → P4 | Avoids polling; P4 gets immediate notification when capture is complete |

---

## Files to Share Between Projects

The `protocol.h` file containing command enums, status codes, and packet structures must be identical in both the P4 and S3 firmware projects. Consider a shared git submodule or a simple copy script to keep them in sync.
