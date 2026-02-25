/**
 * ESP32-P4 NFC Time Clock - NFC Card Reader with Display and API Integration
 */

// Feature configuration (MUST BE BEFORE OTHER INCLUDES)
#include "features.h"

#include <stdio.h>
#include <string.h>
#include <time.h>
#include <sys/time.h>
#include "driver/gpio.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_log.h"
#include "esp_system.h"
#include "esp_timer.h"
#include "esp_sntp.h"
#include "nvs_flash.h"
#include "firmware_info.h"
#include "time_settings.h"

// Board Support Package for ESP32-P4-Function-EV-Board
#include "bsp/esp32_p4_function_ev_board.h"
#include "esp_lvgl_port.h"
#include "lvgl.h"

// UI Manager
#if DISPLAY_ENABLED
#include "ui_manager.h"
#include "ui.h"  // SquareLine Studio UI
#include "ui_bridge.h"  // Bridge between SquareLine UI and backend
#include "ui_events.h"  // Event handlers with punch display helpers
#endif

// NFC driver - RYRR30D (Apple VAS / Google SmartTap / ISO14443)
#if NFC_ENABLED
#include "ryrr30d.h"
#endif

// Network manager (abstracts WiFi, Ethernet, Bluetooth)
#if WIFI_ENABLED || ETHERNET_ENABLED
#include "network_manager.h"
#endif

#if API_ENABLED
#include "api_client.h"
#include "punch_queue.h"
#include "esp_mac.h"
#endif

// WiFi Configuration (TODO: Make configurable via NVS or web interface)
#if WIFI_ENABLED
#define WIFI_SSID      "YOUR_WIFI_SSID"      // Change this
#define WIFI_PASSWORD  "YOUR_WIFI_PASSWORD"  // Change this
#endif

// API Configuration (TODO: Make configurable)
#if API_ENABLED
#define API_SERVER_HOST "192.168.29.25"  // Herd local server
#define API_SERVER_PORT 8000             // HTTP port
#define API_DEVICE_NAME "ESP32-P4-NFC-Clock-01"
#endif

// Pin definitions for RYRR30D NFC Module (UART mode)
// RYRR30D uses UART 115200 8N1 - supports Apple VAS, Google SmartTap, ISO14443A/B
#define NFC_UART_TX_PIN  GPIO_NUM_22  // P4 TX → Reader RX
#define NFC_UART_RX_PIN  GPIO_NUM_21  // Reader TX → P4 RX
#define NFC_RST_PIN      GPIO_NUM_32  // Optional reset pin (-1 if not used)

static const char *TAG = "NFC_TIMECLOCK";

// NFC global variables
#if NFC_ENABLED
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

#if API_ENABLED
// Connectivity monitor task - checks server health every 30 seconds
// Gets device status (approved/pending/suspended) and handles reboot commands
static void connectivity_monitor_task(void *pvParameters) {
	const TickType_t check_interval = pdMS_TO_TICKS(30000);  // 30 seconds
	const int failure_threshold = 3;  // Show alert after 3 consecutive failures
	int consecutive_failures = 0;

	// Initial delay to let network stabilize
	vTaskDelay(pdMS_TO_TICKS(15000));  // 15 sec after boot

	while (1) {
		// Only check if network is connected
		if (network_manager_is_connected()) {
			api_config_t *cfg = api_get_config();
			health_check_result_t health_result = {0};

			esp_err_t ret = api_health_check_full(&health_result);

			if (ret == ESP_OK && health_result.server_reachable) {
				// Server is reachable
				if (consecutive_failures > 0) {
					ESP_LOGI(TAG, "Server back online after %d failures", consecutive_failures);
				}
				consecutive_failures = 0;

				// Only clear server offline status, preserve authorization status
				clock_status_t current_status = ui_get_clock_status();
				if (current_status == CLOCK_STATUS_SERVER_OFFLINE) {
					// Server is back - now check device status
					if (health_result.device_found) {
						if (health_result.is_approved) {
							ui_set_clock_status(CLOCK_STATUS_OK, NULL);
						} else {
							// Not approved - show appropriate status
							if (strcmp(health_result.registration_status, "suspended") == 0) {
								ui_set_clock_status(CLOCK_STATUS_SUSPENDED, cfg->device_name);
							} else {
								ui_set_clock_status(CLOCK_STATUS_NOT_AUTHORIZED, cfg->device_name);
							}
						}
					} else {
						// Device not found on server
						api_clear_registration();
						ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
					}
				}

				// If device is registered, check authorization status from health response
				if (cfg->is_registered && health_result.device_found) {
					// Update UI based on current authorization status
					if (health_result.is_approved) {
						// Device is approved - clear any non-OK status
						if (current_status != CLOCK_STATUS_OK) {
							ui_set_clock_status(CLOCK_STATUS_OK, NULL);
							ESP_LOGI(TAG, "Device approved - status cleared");
						}
					} else {
						// Device not approved - show status
						if (strcmp(health_result.registration_status, "suspended") == 0) {
							if (current_status != CLOCK_STATUS_SUSPENDED) {
								ui_set_clock_status(CLOCK_STATUS_SUSPENDED, cfg->device_name);
								ESP_LOGW(TAG, "Device suspended - showing status");
							}
						} else if (strcmp(health_result.registration_status, "rejected") == 0) {
							if (current_status != CLOCK_STATUS_NOT_AUTHORIZED) {
								ui_set_clock_status(CLOCK_STATUS_NOT_AUTHORIZED, cfg->device_name);
								ESP_LOGW(TAG, "Device rejected - showing status");
							}
						} else {
							// pending or other
							if (current_status != CLOCK_STATUS_NOT_AUTHORIZED) {
								ui_set_clock_status(CLOCK_STATUS_NOT_AUTHORIZED, cfg->device_name);
								ESP_LOGW(TAG, "Device pending approval - showing status");
							}
						}
					}

					// Handle reboot command from server
					if (health_result.reboot_requested) {
						ESP_LOGW(TAG, "🔄 Reboot requested by server - rebooting in 3 seconds...");
						vTaskDelay(pdMS_TO_TICKS(3000));
						esp_restart();
					}

					// Handle token expiration - auto re-authenticate
					if (health_result.needs_reauth && strlen(cfg->mac_address) > 0) {
						ESP_LOGW(TAG, "🔑 Token expired - auto re-authenticating...");
						esp_err_t reauth_result = api_register_device(cfg->mac_address, cfg->device_name);
						if (reauth_result == ESP_OK) {
							ESP_LOGI(TAG, "✅ Re-authentication successful");
							api_save_config();
						} else {
							ESP_LOGE(TAG, "❌ Re-authentication failed: %s", esp_err_to_name(reauth_result));
						}
					}

					// Update machine name label if display_name changed
					if (strlen(health_result.display_name) > 0) {
						api_save_config();  // Save updated display_name to NVS
						// Update the machine name label on UI
						if (ui_mainscreen_label_machinenamelabel) {
							const char *name = (cfg->display_name[0] != '\0') ? cfg->display_name : cfg->device_name;
							lv_label_set_text(ui_mainscreen_label_machinenamelabel, name);
							ESP_LOGI(TAG, "Updated machine name label: %s", name);
						}
					}
				} else if (cfg->is_registered && !health_result.device_found) {
					// Device was deleted from server
					ESP_LOGW(TAG, "Device deleted from server - clearing registration");
					api_clear_registration();
					ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
				}
			} else if (ret == ESP_ERR_NOT_FOUND) {
				// Device not found on server
				consecutive_failures = 0;  // Server is reachable
				ESP_LOGW(TAG, "Device not found on server - clearing registration");
				api_clear_registration();
				ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
			} else {
				// Health check failed - server unreachable
				consecutive_failures++;
				ESP_LOGW(TAG, "Health check failed (%d/%d)", consecutive_failures, failure_threshold);

				if (consecutive_failures >= failure_threshold) {
					ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
				}
			}
		} else {
			// Network not connected
			consecutive_failures++;
			if (consecutive_failures >= failure_threshold) {
				ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
			}
		}

		vTaskDelay(check_interval);
	}
}

// Daily sync task - syncs time, timezone, and sends heartbeat every 24 hours
// Also runs 30 seconds after boot to sync settings from server
static void daily_sync_task(void *pvParameters) {
	const TickType_t initial_delay = pdMS_TO_TICKS(30000);  // 30 sec after boot
	const TickType_t sync_interval = pdMS_TO_TICKS(24 * 60 * 60 * 1000);  // 24 hours

	// Initial delay to let network stabilize
	vTaskDelay(initial_delay);

	while (1) {
		ESP_LOGI(TAG, "Daily sync starting...");

		// Only sync if network is connected and device is registered
		if (network_manager_is_connected()) {
			api_config_t *config = api_get_config();

			// Sync time and timezone from server
			time_sync_data_t sync_data;
			esp_err_t ret = api_sync_time(&sync_data);
			if (ret == ESP_OK && sync_data.valid) {
				// Apply timezone FIRST (before setting time)
				if (strlen(sync_data.timezone) > 0) {
					bool tz_applied = time_settings_apply_by_name(sync_data.timezone);
					if (tz_applied) {
						ESP_LOGI(TAG, "Timezone applied from server: %s", sync_data.timezone);
					} else {
						// Fallback to offset-based timezone
						char tz_str[64];
						if (time_settings_get_posix_for_offset(sync_data.timezone_offset, tz_str, sizeof(tz_str))) {
							setenv("TZ", tz_str, 1);
							tzset();
							ESP_LOGI(TAG, "Timezone set from offset: %s", tz_str);
						}
					}
				}

				// Set system time from unix timestamp
				if (sync_data.unix_timestamp > 0) {
					struct timeval tv;
					tv.tv_sec = (time_t)sync_data.unix_timestamp;
					tv.tv_usec = 0;
					settimeofday(&tv, NULL);
					ESP_LOGI(TAG, "System time synced: %lld", (long long)sync_data.unix_timestamp);
				}

				// Update display with new time
				time_t now;
				struct tm timeinfo;
				time(&now);
				localtime_r(&now, &timeinfo);

				if (lvgl_port_lock(100)) {
					// Update main screen time display
					if (ui_mainscreen_label_timelabel) {
						char time_str[16];
						strftime(time_str, sizeof(time_str), "%I:%M %p", &timeinfo);
						lv_label_set_text(ui_mainscreen_label_timelabel, time_str);
					}
					if (ui_mainscreen_label_datelabel) {
						char date_str[16];
						strftime(date_str, sizeof(date_str), "%m/%d/%Y", &timeinfo);
						lv_label_set_text(ui_mainscreen_label_datelabel, date_str);
					}
					lvgl_port_unlock();
				}

				ESP_LOGI(TAG, "Time and timezone synced from server");
			}

			// Send heartbeat with IP address if registered
			if (config->is_registered) {
				char ip_str[16] = {0};
				network_manager_get_ip_string(ip_str, sizeof(ip_str));
				ret = api_send_heartbeat(ip_str);
				if (ret == ESP_OK) {
					ESP_LOGI(TAG, "Heartbeat sent with IP: %s", ip_str);
				}
			}
		}

		ESP_LOGI(TAG, "Daily sync complete, next sync in 24 hours");
		vTaskDelay(sync_interval);
	}
}
#endif

void app_main(void) {
	ESP_EARLY_LOGI("MAIN", ">>> app_main ENTERED <<<");
	ESP_EARLY_LOGI("MAIN", "About to print banner...");
	printf("\n\n=== ESP32-P4 NFC TIME CLOCK ===\n");
	ESP_EARLY_LOGI("MAIN", "Banner printed, about to print version...");
	printf("Firmware Version: %s\n", FIRMWARE_VERSION);
	printf("Build Date: %s\n", FIRMWARE_BUILD_DATE);
	printf("Build Time: %s\n\n", FIRMWARE_BUILD_TIME);
	ESP_EARLY_LOGI("MAIN", "Version info printed, initializing NVS...");

	// Initialize NVS (required for WiFi)
	esp_err_t ret = nvs_flash_init();
	ESP_EARLY_LOGI("MAIN", "NVS init returned: %d", ret);
	if (ret == ESP_ERR_NVS_NO_FREE_PAGES || ret == ESP_ERR_NVS_NEW_VERSION_FOUND) {
		ESP_ERROR_CHECK(nvs_flash_erase());
		ret = nvs_flash_init();
	}
	ESP_ERROR_CHECK(ret);
	printf("NVS initialized\n\n");

	// Restore time settings (timezone, NTP server) from NVS
	// This must be called AFTER nvs_flash_init()
	time_settings_init_nvs();
	printf("Time settings restored from NVS\n");
	printf("   Timezone: %s\n", getenv("TZ") ? getenv("TZ") : "(default)");
	printf("   NTP Server: %s\n\n", time_settings_get_ntp_server());

#if DISPLAY_ENABLED
	// Initialize display FIRST to claim frame buffer memory before WiFi/NFC
	ESP_EARLY_LOGI("MAIN", "About to call bsp_display_start()...");

	// DEBUG: Check GPIO 26 state before display init
	ESP_EARLY_LOGI("MAIN", "Checking GPIO 26 state before display init...");
	gpio_reset_pin(GPIO_NUM_26);
	ESP_EARLY_LOGI("MAIN", "GPIO 26 reset, now calling bsp_display_start()...");

	lv_display_t *disp = bsp_display_start();
	ESP_EARLY_LOGI("MAIN", "bsp_display_start() returned: %p", disp);

	if (disp == NULL) {
		ESP_EARLY_LOGE("MAIN", "Display init FAILED - disp is NULL!");
	}

	ESP_EARLY_LOGI("MAIN", "About to call bsp_display_backlight_on()...");
	bsp_display_backlight_on();
	ESP_EARLY_LOGI("MAIN", "bsp_display_backlight_on() returned");

	// Initialize SquareLine Studio UI
	ESP_EARLY_LOGI("MAIN", "About to call lvgl_port_lock()...");
	lvgl_port_lock(0);
	ESP_EARLY_LOGI("MAIN", "lvgl_port_lock() returned, calling ui_init()...");
	ui_init();  // Create all SquareLine screens
	ESP_EARLY_LOGI("MAIN", "ui_init() returned, calling ui_bridge_init()...");
	ui_bridge_init();  // Connect SquareLine UI to backend managers
	ESP_EARLY_LOGI("MAIN", "ui_bridge_init() returned, calling lvgl_port_unlock()...");
	lvgl_port_unlock();
	ESP_EARLY_LOGI("MAIN", "Display init complete");

	printf("Display initialized with SquareLine UI and backend bridge!\n");
	printf("Free heap after display: %lu bytes\n\n", (unsigned long)esp_get_free_heap_size());
#else
	ESP_EARLY_LOGI("MAIN", "Display disabled");
#endif

#if WIFI_ENABLED || ETHERNET_ENABLED
	// Initialize network manager (handles WiFi, Ethernet, Bluetooth)
	ESP_EARLY_LOGI("MAIN", "About to init network manager...");
	if (network_manager_init() == ESP_OK) {
		ESP_EARLY_LOGI("MAIN", "Network manager init OK");
		printf("✅ Network manager initialized\n");
		printf("   Configure networks through Setup menu\n\n");

		// Try to load and connect to saved configurations
		ESP_EARLY_LOGI("MAIN", "About to call load_and_connect...");
		network_manager_load_and_connect();
		ESP_EARLY_LOGI("MAIN", "load_and_connect returned");

		// Initialize SNTP with saved NTP server once network is connected
		if (network_manager_is_connected()) {
			const char *ntp_server = time_settings_get_ntp_server();
			ESP_LOGI(TAG, "Starting SNTP with server: %s", ntp_server);

			// Stop any existing SNTP
			if (esp_sntp_enabled()) {
				esp_sntp_stop();
			}

			// Configure and start SNTP
			esp_sntp_setoperatingmode(SNTP_OPMODE_POLL);
			esp_sntp_setservername(0, ntp_server);
			esp_sntp_init();

			printf("✅ SNTP initialized with server: %s\n", ntp_server);
		}

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
			.mac_address = "",  // Will be populated below
			.is_registered = false,
			.is_approved = false
		};
		strcpy(api_config.device_name, API_DEVICE_NAME);
		strncpy(api_config.mac_address, mac_str, sizeof(api_config.mac_address) - 1);

		api_client_init(&api_config);

		// Load saved registration state from NVS (persists across reboots)
		if (api_load_config() == ESP_OK) {
			api_config_t *saved_config = api_get_config();
			if (saved_config->is_registered) {
				printf("✅ Restored registration from NVS\n");
				printf("   Device ID: %s\n", saved_config->device_id);
				printf("   Server: %s:%d\n\n", saved_config->server_host, saved_config->server_port);
			}

			// Ensure MAC address is populated (for devices registered before this update)
			if (strlen(saved_config->mac_address) == 0) {
				strncpy(saved_config->mac_address, mac_str, sizeof(saved_config->mac_address) - 1);
				printf("   MAC address populated: %s\n", mac_str);
				api_save_config();
			}
		}

		// Test server health (but don't auto-register - let user register via ServerSetup)
		if (api_health_check() == ESP_OK) {
			printf("✅ Server is reachable\n");
			api_config_t *cfg = api_get_config();
			if (cfg->is_registered) {
				// Verify registration is still valid on server
				printf("Verifying registration with server...\n");
				esp_err_t verify_result = api_verify_registration();

				if (verify_result == ESP_OK) {
					printf("✅ Registration verified - device exists on server\n");
					if (cfg->is_approved) {
						printf("✅ Device is approved\n\n");
						// Clear any previous status (device is good)
						ui_set_clock_status(CLOCK_STATUS_OK, NULL);
					} else {
						printf("⚠️  Device pending approval\n\n");
						// Show not authorized status on display
						ui_set_clock_status(CLOCK_STATUS_NOT_AUTHORIZED, cfg->device_name);
					}
				} else if (verify_result == ESP_ERR_NOT_FOUND) {
					printf("❌ Device was deleted from server - registration cleared\n");
					printf("   Use ServerSetup screen to re-register\n\n");
					// Show not registered status on display
					ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
				} else {
					// Other error but server was reachable - keep cached
					printf("⚠️  Could not verify registration - using cached state\n\n");
				}
			} else {
				printf("Use ServerSetup screen to register device\n\n");
				// Device not registered - show status
				ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
			}
		} else {
			printf("❌ Cannot reach server at %s:%d\n", API_SERVER_HOST, API_SERVER_PORT);
			api_config_t *cfg = api_get_config();
			if (cfg->is_registered) {
				printf("   Using cached registration (offline mode)\n\n");
				// Server offline but we have cached registration
				ui_set_clock_status(CLOCK_STATUS_SERVER_OFFLINE, NULL);
			} else {
				printf("   Device not registered\n\n");
				// Not registered and can't reach server
				ui_set_clock_status(CLOCK_STATUS_NOT_REGISTERED, NULL);
			}
		}
	}

	// Start daily sync task (time sync + heartbeat every 24h, first sync 30s after boot)
	xTaskCreate(daily_sync_task, "daily_sync", 8192, NULL, 3, NULL);
	printf("✅ Daily sync task started (first sync in 30s)\n");

	// Start connectivity monitor task (health check every 30s, alert after 3 failures)
	xTaskCreate(connectivity_monitor_task, "conn_monitor", 4096, NULL, 3, NULL);
	printf("✅ Connectivity monitor started (checks every 30s)\n\n");

	// Initialize punch queue for offline buffering
	if (punch_queue_init() == ESP_OK) {
		printf("✅ Punch queue initialized\n");
		uint32_t pending = punch_queue_pending_count();
		if (pending > 0) {
			printf("   ⚠️  %lu punches pending sync\n", (unsigned long)pending);
		}
		// Start background sync task (every 30 seconds)
		punch_queue_start_sync_task(30000);
		printf("✅ Punch sync task started\n\n");
	} else {
		printf("❌ Failed to initialize punch queue\n\n");
	}
#else
	printf("API disabled\n\n");
#endif

#if NFC_ENABLED
	printf("Initializing RYRR30D NFC Module...\n");
	printf("Supports: Apple VAS, Google SmartTap, ISO14443A/B, ISO15693, FeliCa\n\n");

	// Configure RYRR30D
	ryrr30d_config_t nfc_config = {
		.uart_num = UART_NUM_1,
		.tx_pin = NFC_UART_TX_PIN,
		.rx_pin = NFC_UART_RX_PIN,
		.rst_pin = NFC_RST_PIN,
		.apple_enabled = false,     // Configure later with keys
		.google_enabled = false,    // Configure later with keys
	};

	// Initialize RYRR30D
	ret = ryrr30d_init(&nfc_config);
	if (ret != ESP_OK) {
		printf("❌ RYRR30D initialization FAILED: %s\n", esp_err_to_name(ret));
		printf("\nCheck wiring:\n");
		printf("  VCC  → 3.3V\n");
		printf("  GND  → GND\n");
		printf("  TX   → GPIO%d (Reader TX → P4 RX)\n", NFC_UART_RX_PIN);
		printf("  RX   → GPIO%d (P4 TX → Reader RX)\n", NFC_UART_TX_PIN);
		printf("  RST  → GPIO%d (optional)\n", NFC_RST_PIN);

		// Continue with heartbeat even if NFC fails
		printf("\nContinuing with heartbeat only...\n\n");
		int counter = 0;
		while (1) {
			printf("Heartbeat %d (NFC disabled)\n", counter++);
			vTaskDelay(pdMS_TO_TICKS(2000));
		}
	}

	// Get firmware version
	char version[64];
	if (ryrr30d_get_version(version, sizeof(version)) == ESP_OK) {
		printf("✅ RYRR30D initialized successfully!\n");
		printf("   Firmware: %s\n\n", version);
	} else {
		printf("✅ RYRR30D initialized (version unknown)\n\n");
	}

	// Configure card types to scan
	ryrr30d_configure_card_types(true, true, true, true);  // ISO14443A, B, 15693, FeliCa
	printf("Card types enabled: ISO14443A, ISO14443B, ISO15693, FeliCa\n");

	// Start continuous polling mode
	ret = ryrr30d_start_polling();
	if (ret == ESP_OK) {
		printf("✅ Reader polling started\n");
	} else {
		printf("⚠️  Could not start polling (reader may auto-poll): %s\n", esp_err_to_name(ret));
	}

	// TODO: Configure Apple VAS when credentials are available
	// ryrr30d_configure_apple("pass_type_id_hash", "private_key_hex");

	// TODO: Configure Google SmartTap when credentials are available
	// ryrr30d_configure_google("collector_id_hex", "private_key_hex");

	printf("Ready to read cards/passes. Present card or phone...\n\n");

#if DISPLAY_ENABLED
	// TODO: Update UI - NFC is ready (Phase 6)
	// lvgl_port_lock(0);
	// ui_update_status(NET_STATUS_DISCONNECTED, NFC_STATUS_READY);
	// ui_show_ready_screen("Ready to scan cards");
	// lvgl_port_unlock();
#endif
#else
	printf("NFC disabled for display testing\n\n");
#endif

#if NFC_ENABLED
	// Main loop - scan for cards/passes
	ryrr30d_card_info_t card_info;
	char uid_str[32];
	int heartbeat_counter = 0;

	while (1) {
		// Try to read card/pass (non-blocking poll)
		ret = ryrr30d_poll_card(&card_info);

		if (ret == ESP_OK) {
			// Convert UID/pass data to string for identification
			if (card_info.type == RYRR30D_CARD_APPLE_VAS ||
			    card_info.type == RYRR30D_CARD_GOOGLE_SMARTTAP) {
				// For wallet passes, use the pass data as identifier
				strncpy(uid_str, card_info.pass_data,
				        (card_info.pass_data_len < sizeof(uid_str) - 1) ?
				        card_info.pass_data_len : sizeof(uid_str) - 1);
				uid_str[sizeof(uid_str) - 1] = '\0';
			} else {
				// For physical cards, use UID
				ryrr30d_uid_to_string(&card_info, uid_str, sizeof(uid_str));
			}

			// Get current time
			uint64_t current_time = esp_timer_get_time() / 1000;

			// Debounce - ignore same card within 2 seconds
			if (strcmp(uid_str, last_card_uid) != 0 ||
			    (current_time - last_card_time) > 2000) {

				// Update last card info
				strncpy(last_card_uid, uid_str, sizeof(last_card_uid) - 1);
				last_card_time = current_time;
				card_count++;

				// Get card/pass type name
				const char *type_name = ryrr30d_card_type_to_string(card_info.type);

				// Print card/pass info to console
				printf("\n🎫 === CARD/PASS DETECTED #%lu ===\n", (unsigned long)card_count);
				printf("   ID:   %s\n", uid_str);
				printf("   Type: %s\n", type_name);
				if (card_info.type == RYRR30D_CARD_APPLE_VAS ||
				    card_info.type == RYRR30D_CARD_GOOGLE_SMARTTAP) {
					printf("   Pass Data Len: %d bytes\n", card_info.pass_data_len);
				} else {
					printf("   UID Len: %d bytes\n", card_info.uid_len);
				}
				printf("   Time: %llu ms\n\n", current_time);

				// Generate timestamp from device clock (shared by API and Display)
				time_t now = time(NULL);
				struct tm timeinfo;
				localtime_r(&now, &timeinfo);

#if API_ENABLED
				// Queue punch for sync (offline-first approach)
				api_config_t *api_config = api_get_config();

				// Format event time for API (ISO 8601)
				char event_time[32];
				strftime(event_time, sizeof(event_time), "%Y-%m-%dT%H:%M:%S", &timeinfo);

				// Always queue the punch first (survives network issues/reboots)
				esp_err_t queue_result = punch_queue_add(
					uid_str,                              // credential_value (card UID)
					type_name,                            // credential_kind (card type)
					event_time,                           // event_time (device local time)
					api_config->device_id,                // device_id
					-6                                    // timezone_offset (CST) TODO: Make configurable
				);

				if (queue_result == ESP_OK) {
					printf("✅ Punch queued (time: %s)\n", event_time);

					// Trigger immediate sync attempt if registered
					if (api_config->is_registered) {
						punch_queue_trigger_sync();
					} else {
						printf("⚠️  Device not registered - punch saved for later sync\n");
					}
				} else if (queue_result == ESP_ERR_NO_MEM) {
					printf("❌ Punch queue full! Cannot record punch\n");
				} else {
					printf("❌ Failed to queue punch: %s\n", esp_err_to_name(queue_result));
				}

				// Show queue status
				uint32_t pending = punch_queue_pending_count();
				if (pending > 1) {
					printf("   📋 %lu punches pending sync\n", (unsigned long)pending);
				}
				printf("\n");
#endif

#if DISPLAY_ENABLED
				// Update display with new UI manager
				card_scan_result_t scan_result = {0};
				strncpy(scan_result.card_uid, uid_str, sizeof(scan_result.card_uid) - 1);
				strncpy(scan_result.card_type, type_name, sizeof(scan_result.card_type) - 1);

				// Initialize default values
				strncpy(scan_result.employee.name, "Unknown Employee", sizeof(scan_result.employee.name) - 1);
				strncpy(scan_result.employee.employee_id, "---", sizeof(scan_result.employee.employee_id) - 1);
				strncpy(scan_result.employee.department, "Not Enrolled", sizeof(scan_result.employee.department) - 1);
				scan_result.employee.is_authorized = false;
				scan_result.employee.today_hours = 0.0f;
				scan_result.employee.week_hours = 0.0f;
				scan_result.employee.pay_period_hours = 0.0f;
				scan_result.employee.vacation_balance = 0.0f;

#if API_ENABLED
				// Fetch employee info and hours from API
				api_config_t *api_cfg = api_get_config();
				if (api_cfg->is_registered) {
					employee_info_t emp_info = {0};
					esp_err_t emp_result = api_get_employee_info(uid_str, &emp_info);
					if (emp_result == ESP_OK) {
						// Copy employee info to scan result
						strncpy(scan_result.employee.name, emp_info.name, sizeof(scan_result.employee.name) - 1);
						strncpy(scan_result.employee.employee_id, emp_info.employee_id, sizeof(scan_result.employee.employee_id) - 1);
						strncpy(scan_result.employee.department, emp_info.department, sizeof(scan_result.employee.department) - 1);
						scan_result.employee.is_authorized = emp_info.is_authorized;
						scan_result.employee.today_hours = emp_info.today_hours;
						scan_result.employee.week_hours = emp_info.week_hours;
						scan_result.employee.pay_period_hours = emp_info.pay_period_hours;
						scan_result.employee.vacation_balance = emp_info.vacation_balance;
						printf("✅ Employee info retrieved from API\n\n");

						// Server is reachable
						punch_queue_set_server_status(true);
					} else if (emp_result == ESP_ERR_NOT_FOUND) {
						printf("⚠️  Employee not found in system\n\n");
						// Server is reachable, just no employee record
						punch_queue_set_server_status(true);
					} else if (emp_result == ESP_ERR_TIMEOUT) {
						printf("❌ Server unreachable - punch queued for later sync\n\n");
						// Server is unreachable
						punch_queue_set_server_status(false);
					} else {
						printf("⚠️  Failed to fetch employee info (error: %s)\n\n", esp_err_to_name(emp_result));
						// Other error - assume server issue
						punch_queue_set_server_status(false);
					}
				}
#endif

				// Format timestamp for display
				strftime(scan_result.timestamp, sizeof(scan_result.timestamp),
				         "%Y-%m-%d %H:%M:%S", &timeinfo);

				scan_result.success = true;
				snprintf(scan_result.message, sizeof(scan_result.message),
				         "Card #%lu scanned", (unsigned long)card_count);

				// Show punch info on MainScreen UI
				ui_show_employee_info(scan_result.employee.name);

				// Format date and time for punch display
				char punch_date[16];
				char punch_time[16];
				strftime(punch_date, sizeof(punch_date), "%m/%d/%Y", &timeinfo);
				strftime(punch_time, sizeof(punch_time), "%I:%M %p", &timeinfo);
				ui_show_punch_info(punch_date, punch_time);
#endif
			}
			// Note: RYRR30D handles card state internally, no explicit halt needed
		}

		// Heartbeat every 10 seconds
		if (heartbeat_counter++ % 100 == 0) {
			const ryrr30d_state_t *nfc_state = ryrr30d_get_state();
			printf("💓 Heartbeat - Cards/Passes: %lu, Errors: %lu, Heap: %lu bytes\n",
			       (unsigned long)card_count,
			       (unsigned long)(nfc_state ? nfc_state->errors : 0),
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