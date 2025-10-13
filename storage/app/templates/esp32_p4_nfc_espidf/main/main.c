/**
 * ESP32-P4 NFC Time Clock - NFC Card Reader with Display and API Integration
 */

// Feature configuration (MUST BE BEFORE OTHER INCLUDES)
#include "features.h"

#include <stdio.h>
#include <string.h>
#include <time.h>
#include "driver/gpio.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_log.h"
#include "esp_system.h"
#include "esp_timer.h"
#include "nvs_flash.h"
#include "firmware_info.h"

// Board Support Package for ESP32-P4-Function-EV-Board
#include "bsp/esp32_p4_function_ev_board.h"
#include "esp_lvgl_port.h"
#include "lvgl.h"

// UI Manager
#if DISPLAY_ENABLED
#include "ui_manager.h"
#endif

// NFC driver - PN532 abstraction layer
#if NFC_ENABLED
#include "nfc_reader.h"
#endif

// Network manager (abstracts WiFi, Ethernet, Bluetooth)
#if WIFI_ENABLED || ETHERNET_ENABLED
#include "network_manager.h"
#endif

#if API_ENABLED
#include "api_client.h"
#include "esp_mac.h"
#endif

// WiFi Configuration (TODO: Make configurable via NVS or web interface)
#if WIFI_ENABLED
#define WIFI_SSID      "YOUR_WIFI_SSID"      // Change this
#define WIFI_PASSWORD  "YOUR_WIFI_PASSWORD"  // Change this
#endif

// API Configuration (TODO: Make configurable)
#if API_ENABLED
#define API_SERVER_HOST "192.168.1.100"  // Change to your Laravel server IP/hostname
#define API_SERVER_PORT 80               // Change if using different port
#define API_DEVICE_NAME "ESP32-P4-NFC-Clock-01"
#endif

// Pin definitions for PN532 NFC Module V3 (SPI mode)
#define NFC_SCK_PIN  GPIO_NUM_20
#define NFC_MISO_PIN GPIO_NUM_21
#define NFC_MOSI_PIN GPIO_NUM_22
#define NFC_CS_PIN   GPIO_NUM_23
#define NFC_RST_PIN  GPIO_NUM_32
#define NFC_IRQ_PIN  -1  // Not using IRQ

#define NFC_SPI_HOST SPI3_HOST  // Changed from SPI2_HOST to avoid conflict with WiFi transport

static const char *TAG = "NFC_TIMECLOCK";

// NFC global variables
#if NFC_ENABLED
static nfc_reader_handle_t nfc_reader = NULL;
static uint32_t card_count = 0;
static char last_card_uid[32] = {0};
static uint64_t last_card_time = 0;
#endif

// Display variables - removed (using UI manager)

#if DISPLAY_ENABLED
// Password validation callback for admin access
static void validate_admin_password(const char *password, bool *is_valid) {
	if (strcmp(password, DEFAULT_ADMIN_PASSWORD) == 0) {
		*is_valid = true;
		ESP_LOGI(TAG, "Admin password accepted");
	} else {
		*is_valid = false;
		ESP_LOGW(TAG, "Invalid admin password attempt");
	}
}

#endif

void app_main(void) {
	printf("\n\n=== ESP32-P4 NFC TIME CLOCK ===\n");
	printf("Firmware Version: %s\n", FIRMWARE_VERSION);
	printf("Build Date: %s\n", FIRMWARE_BUILD_DATE);
	printf("Build Time: %s\n\n", FIRMWARE_BUILD_TIME);

	// Initialize NVS (required for WiFi)
	esp_err_t ret = nvs_flash_init();
	if (ret == ESP_ERR_NVS_NO_FREE_PAGES || ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
		ESP_ERROR_CHECK(nvs_flash_erase());
		ret = nvs_flash_init();
	}
	ESP_ERROR_CHECK(ret);
	printf("NVS initialized\n\n");

#if DISPLAY_ENABLED
	// Initialize display FIRST to claim frame buffer memory before WiFi/NFC
	printf("Initializing display (allocating frame buffer)...\n");
	bsp_display_start();
	bsp_display_backlight_on();

	// Initialize UI manager
	lvgl_port_lock(0);
	ui_manager_init(DEVICE_NAME);
	ui_set_setup_callback(validate_admin_password);  // Set password validation callback
	ui_update_status(NET_STATUS_DISCONNECTED, NFC_STATUS_DISABLED);
	ui_update_time("--:--", "---");
	ui_show_ready_screen("Initializing...");
	lvgl_port_unlock();

	printf("Display initialized!\n");
	printf("Free heap after display: %lu bytes\n\n", (unsigned long)esp_get_free_heap_size());
#else
	printf("Display disabled\n\n");
#endif

#if WIFI_ENABLED || ETHERNET_ENABLED
	// Initialize network manager (handles WiFi, Ethernet, Bluetooth)
	printf("Initializing network manager...\n");
	if (network_manager_init() == ESP_OK) {
		printf("✅ Network manager initialized\n");
		printf("   Configure networks through Setup menu\n\n");

		// Try to load and connect to saved configurations
		network_manager_load_and_connect();

#if DISPLAY_ENABLED
		// Start network monitoring task
		printf("Starting network monitoring...\n");
		network_manager_start_monitoring();
		printf("✅ Network monitoring started\n\n");
#endif
	} else {
		printf("❌ Failed to initialize network manager\n\n");
	}
#else
	printf("All networks disabled (enable via WIFI_ENABLED or ETHERNET_ENABLED in main.c)\n\n");
#endif

#if API_ENABLED
	// Initialize API client
	if (network_manager_is_connected()) {
		printf("Initializing API client...\n");

		// Get MAC address for device registration
		uint8_t mac[6];
		esp_read_mac(mac, ESP_MAC_WIFI_STA);
		char mac_str[18];
		snprintf(mac_str, sizeof(mac_str), "%02X:%02X:%02X:%02X:%02X:%02X",
		         mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

		// Configure API client
		api_config_t api_config = {
			.server_host = API_SERVER_HOST,
			.server_port = API_SERVER_PORT,
			.is_registered = false,
			.is_approved = false
		};
		strcpy(api_config.device_name, API_DEVICE_NAME);

		api_client_init(&api_config);

		// Test server health
		if (api_health_check() == ESP_OK) {
			printf("✅ Server is reachable\n");

			// Register device (if not already registered - TODO: save to NVS)
			printf("Registering device...\n");
			if (api_register_device(mac_str, API_DEVICE_NAME) == ESP_OK) {
				printf("✅ Device registered successfully!\n\n");
			} else {
				printf("❌ Device registration failed\n\n");
			}
		} else {
			printf("❌ Cannot reach server at %s:%d\n\n",
			       API_SERVER_HOST, API_SERVER_PORT);
		}
	}
#else
	printf("API disabled\n\n");
#endif

#if NFC_ENABLED
	printf("Initializing PN532 NFC Module V3...\n");
	printf("DIP Switches: SEL0=1, SEL1=0 (SPI mode)\n\n");

	// Configure PN532
	nfc_reader_config_t nfc_config = {
		.type = NFC_READER_PN532,
		.spi_host = NFC_SPI_HOST,
		.miso_pin = NFC_MISO_PIN,
		.mosi_pin = NFC_MOSI_PIN,
		.sck_pin = NFC_SCK_PIN,
		.cs_pin = NFC_CS_PIN,
		.rst_pin = NFC_RST_PIN,
		.irq_pin = NFC_IRQ_PIN,
		.spi_speed_hz = 5000000,  // 5 MHz
	};

	// Initialize PN532
	ret = nfc_reader_init(&nfc_config, &nfc_reader);
	if (ret != ESP_OK) {
		printf("❌ PN532 initialization FAILED: %s\n", esp_err_to_name(ret));
		printf("\nCheck wiring:\n");
		printf("  VCC  → 3.3V\n");
		printf("  GND  → GND\n");
		printf("  MOSI → GPIO%d\n", NFC_MOSI_PIN);
		printf("  MISO → GPIO%d\n", NFC_MISO_PIN);
		printf("  SCK  → GPIO%d\n", NFC_SCK_PIN);
		printf("  SS   → GPIO%d\n", NFC_CS_PIN);
		printf("  RST  → GPIO%d\n", NFC_RST_PIN);
		printf("  DIP: SEL0=1, SEL1=0\n");

		// Continue with heartbeat even if NFC fails
		printf("\nContinuing with heartbeat only...\n\n");
		int counter = 0;
		while (1) {
			printf("Heartbeat %d (NFC disabled)\n", counter++);
			vTaskDelay(pdMS_TO_TICKS(2000));
		}
	}

	// Get firmware version
	uint32_t version;
	if (nfc_reader_get_firmware_version(nfc_reader, &version) == ESP_OK) {
		printf("✅ PN532 initialized successfully!\n");
		printf("   Firmware: PN5%02lX v%ld.%ld\n\n",
		       (unsigned long)((version >> 24) & 0xFF),
		       (unsigned long)((version >> 16) & 0xFF),
		       (unsigned long)((version >> 8) & 0xFF));
	}

	printf("Ready to read cards. Place a card near the reader...\n\n");

#if DISPLAY_ENABLED
	// Update UI - NFC is ready
	lvgl_port_lock(0);
	ui_update_status(NET_STATUS_DISCONNECTED, NFC_STATUS_READY);
	ui_show_ready_screen("Ready to scan cards");
	lvgl_port_unlock();
#endif
#else
	printf("NFC disabled for display testing\n\n");
#endif

#if NFC_ENABLED
	// Main loop - scan for cards
	nfc_card_uid_t uid;
	char uid_str[32];
	int heartbeat_counter = 0;

	while (1) {
		// Try to read card
		ret = nfc_reader_read_card_uid(nfc_reader, &uid);

		if (ret == ESP_OK) {
			// Convert UID to string
			nfc_reader_uid_to_string(&uid, uid_str, sizeof(uid_str));

			// Get current time
			uint64_t current_time = esp_timer_get_time() / 1000;

			// Debounce - ignore same card within 2 seconds
			if (strcmp(uid_str, last_card_uid) != 0 ||
			    (current_time - last_card_time) > 2000) {

				// Update last card info
				strncpy(last_card_uid, uid_str, sizeof(last_card_uid) - 1);
				last_card_time = current_time;
				card_count++;

				// Get card type
				nfc_card_type_t card_type = nfc_reader_get_card_type(&uid);
				const char *type_name = nfc_reader_get_card_type_name(card_type);

				// Print card info to console
				printf("\n🎫 === CARD DETECTED #%lu ===\n", (unsigned long)card_count);
				printf("   UID:  %s\n", uid_str);
				printf("   Type: %s\n", type_name);
				printf("   SAK:  0x%02X\n", uid.sak);
				printf("   Size: %d bytes\n", uid.size);
				printf("   Time: %llu ms\n\n", current_time);

#if API_ENABLED
				// Send punch data to server
				api_config_t *api_config = api_get_config();
				if (api_config->is_registered) {
					// Prepare punch data
					punch_data_t punch_data;
					strncpy(punch_data.device_id, api_config->device_id, sizeof(punch_data.device_id) - 1);
					strncpy(punch_data.credential_kind, type_name, sizeof(punch_data.credential_kind) - 1);
					strncpy(punch_data.credential_value, uid_str, sizeof(punch_data.credential_value) - 1);

					// Simple ISO 8601 timestamp (TODO: Add proper time sync)
					time_t now = time(NULL);
					struct tm timeinfo;
					localtime_r(&now, &timeinfo);
					strftime(punch_data.event_time, sizeof(punch_data.event_time),
					         "%Y-%m-%dT%H:%M:%S", &timeinfo);

					strncpy(punch_data.event_type, "unknown", sizeof(punch_data.event_type) - 1);
					punch_data.confidence = 100;
					punch_data.timezone_offset = -5;  // TODO: Make configurable

					// Send to API
					if (api_send_punch(&punch_data) == ESP_OK) {
						printf("✅ Punch sent to server\n\n");
					} else {
						printf("❌ Failed to send punch to server\n\n");
					}
				} else {
					printf("⚠️  Device not registered, punch not sent to server\n\n");
				}
#endif

#if DISPLAY_ENABLED
				// Update display with new UI manager
				card_scan_result_t scan_result = {0};
				strncpy(scan_result.card_uid, uid_str, sizeof(scan_result.card_uid) - 1);
				strncpy(scan_result.card_type, type_name, sizeof(scan_result.card_type) - 1);

				// TODO: Lookup employee info from API
				strncpy(scan_result.employee.name, "Unknown Employee", sizeof(scan_result.employee.name) - 1);
				strncpy(scan_result.employee.employee_id, "---", sizeof(scan_result.employee.employee_id) - 1);
				strncpy(scan_result.employee.department, "Not Enrolled", sizeof(scan_result.employee.department) - 1);
				scan_result.employee.is_authorized = false;

				// Format timestamp
				time_t now = time(NULL);
				struct tm timeinfo;
				localtime_r(&now, &timeinfo);
				strftime(scan_result.timestamp, sizeof(scan_result.timestamp),
				         "%Y-%m-%d %H:%M:%S", &timeinfo);

				scan_result.success = true;
				snprintf(scan_result.message, sizeof(scan_result.message),
				         "Card #%lu scanned", (unsigned long)card_count);

				lvgl_port_lock(0);
				ui_show_card_scan(&scan_result, 3000);  // Show for 3 seconds
				lvgl_port_unlock();
#endif
			}

			// Halt the card
			nfc_reader_halt_card(nfc_reader);
		}

		// Heartbeat every 10 seconds
		if (heartbeat_counter++ % 100 == 0) {
			printf("💓 Heartbeat - Cards read: %lu, Heap: %lu bytes\n",
			       (unsigned long)card_count,
			       (unsigned long)esp_get_free_heap_size());
		}

		// Small delay
		vTaskDelay(pdMS_TO_TICKS(100));
	}
#else
	// Display-only mode - just keep running
	printf("Display test mode - system running\n");
	while (1) {
		vTaskDelay(pdMS_TO_TICKS(5000));
		printf("Display heartbeat...\n");
	}
#endif
}