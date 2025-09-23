/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `anniversary_vacation_status`;
/*!50001 DROP VIEW IF EXISTS `anniversary_vacation_status`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `anniversary_vacation_status` AS SELECT 
 1 AS `employee_id`,
 1 AS `first_name`,
 1 AS `last_name`,
 1 AS `date_of_hire`,
 1 AS `last_anniversary_date`,
 1 AS `next_anniversary_date`,
 1 AS `completed_years`,
 1 AS `current_year_accrued`,
 1 AS `current_year_used`,
 1 AS `policy_name`,
 1 AS `annual_entitlement`,
 1 AS `is_due_for_accrual`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `attendance_time_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_time_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the attendance_time_groups table',
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to employees table',
  `external_group_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_date` date DEFAULT NULL COMMENT 'The official workday this shift is assigned to',
  `shift_window_start` datetime DEFAULT NULL COMMENT 'Start of the work period for this shift group',
  `shift_window_end` datetime DEFAULT NULL COMMENT 'End of the work period for this shift group',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the record is archived',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_time_groups_external_group_id_unique` (`external_group_id`),
  KEY `idx_employee_shift_date` (`employee_id`,`shift_date`),
  KEY `idx_external_group_id` (`external_group_id`),
  KEY `idx_is_archived` (`is_archived`),
  CONSTRAINT `attendance_time_groups_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the attendances table',
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to employees table',
  `employee_external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'External ID of the employee for mapping',
  `punch_time` datetime DEFAULT NULL COMMENT 'Time of the punch event',
  `device_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to devices table',
  `punch_type_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to punch types table',
  `is_manual` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the attendance was manually recorded',
  `punch_state` enum('start','stop','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'Indicates whether the punch is a start or stop event',
  `external_group_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_date` date DEFAULT NULL COMMENT 'The assigned workday for this attendance record',
  `status` enum('Incomplete','Partial','Complete','Discrepancy','Migrated','Posted','NeedsReview') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Incomplete' COMMENT 'Processing status of the attendance record',
  `is_migrated` tinyint(1) DEFAULT NULL COMMENT 'Indicates if the attendance record is migrated',
  `is_posted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the pay period has been posted',
  `classification_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to classification table',
  `holiday_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to holidays table',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the record is archived',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `issue_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_punch_time` (`punch_time`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_employee_external_id` (`employee_external_id`),
  KEY `idx_external_group_id` (`external_group_id`),
  KEY `idx_shift_date` (`shift_date`),
  KEY `idx_is_archived` (`is_archived`),
  KEY `attendances_device_id_foreign` (`device_id`),
  KEY `attendances_punch_type_id_foreign` (`punch_type_id`),
  KEY `attendances_classification_id_foreign` (`classification_id`),
  KEY `attendances_holiday_id_foreign` (`holiday_id`),
  KEY `attendances_created_by_foreign` (`created_by`),
  KEY `attendances_updated_by_foreign` (`updated_by`),
  CONSTRAINT `attendances_classification_id_foreign` FOREIGN KEY (`classification_id`) REFERENCES `classifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `attendances_punch_type_id_foreign` FOREIGN KEY (`punch_type_id`) REFERENCES `punch_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `validate_punch_time` BEFORE INSERT ON `attendances` FOR EACH ROW BEGIN
                IF NEW.punch_time IS NULL THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Punch time cannot be NULL.';
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `set_employee_id_from_external_id` BEFORE INSERT ON `attendances` FOR EACH ROW BEGIN
                -- If employee_id is missing, find it using employee_external_id
                IF NEW.employee_id IS NULL AND NEW.employee_external_id IS NOT NULL THEN
                    SET NEW.employee_id = (
                        SELECT id
                        FROM employees
                        WHERE external_id = NEW.employee_external_id
                        LIMIT 1
                    );
                END IF;

                -- If employee_external_id is missing, find it using employee_id
                IF NEW.employee_external_id IS NULL AND NEW.employee_id IS NOT NULL THEN
                    SET NEW.employee_external_id = (
                        SELECT external_id
                        FROM employees
                        WHERE id = NEW.employee_id
                        LIMIT 1
                    );
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `set_status_default` BEFORE INSERT ON `attendances` FOR EACH ROW BEGIN
                IF NEW.status IS NULL OR NEW.status = '' THEN
                    SET NEW.status = 'Incomplete';
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `set_status_on_update` BEFORE UPDATE ON `attendances` FOR EACH ROW BEGIN
                IF NEW.status = 'Migrated' THEN
                    SET NEW.is_migrated = 1;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the classifications table',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the classification (e.g., Holiday, Vacation)',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique code identifier (e.g., HOLIDAY, VACATION)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Detailed description of the classification',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the classification is active',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classifications_name_unique` (`name`),
  UNIQUE KEY `classifications_code_unique` (`code`),
  KEY `classifications_created_by_foreign` (`created_by`),
  KEY `classifications_updated_by_foreign` (`updated_by`),
  CONSTRAINT `classifications_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classifications_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clock_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clock_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  `employee_id` bigint unsigned DEFAULT NULL,
  `device_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> devices.id (which device saw it)',
  `credential_id` bigint unsigned DEFAULT NULL COMMENT 'FK -> credentials.id (which credential was used, if any)',
  `event_time` datetime NOT NULL COMMENT 'Exact server-side timestamp the event was recorded',
  `shift_date` date DEFAULT NULL COMMENT 'Logical workday the event belongs to (app assigned)',
  `event_source` enum('device','api','backfill','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'device' COMMENT 'How this event was recorded',
  `location` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional freeform location label from device',
  `confidence` tinyint unsigned DEFAULT NULL COMMENT '0–100 confidence score (e.g., biometric/NFC quality)',
  `raw_payload` json DEFAULT NULL COMMENT 'Optional raw payload for audit/debug',
  `is_processed` tinyint(1) NOT NULL DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `attendance_id` bigint unsigned DEFAULT NULL,
  `batch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_error` text COLLATE utf8mb4_unicode_ci,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short operator/system note',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Users.id that created the record (if admin/API)',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Users.id that last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_event_device` (`employee_id`,`event_time`,`device_id`),
  KEY `idx_clock_events_employee_time` (`employee_id`,`event_time`),
  KEY `idx_clock_events_device_time` (`device_id`,`event_time`),
  KEY `idx_clock_events_shift_date` (`shift_date`),
  KEY `idx_clock_events_employee_shift_type` (`employee_id`,`shift_date`),
  KEY `idx_clock_events_credential_time` (`credential_id`,`event_time`),
  KEY `clock_events_attendance_id_foreign` (`attendance_id`),
  KEY `clock_events_is_processed_index` (`is_processed`),
  KEY `clock_events_employee_id_shift_date_is_processed_index` (`employee_id`,`shift_date`,`is_processed`),
  CONSTRAINT `clock_events_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clock_events_credential` FOREIGN KEY (`credential_id`) REFERENCES `credentials` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clock_events_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_clock_events_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_setup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_setup` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_flexibility_minutes` int NOT NULL DEFAULT '30' COMMENT 'Number of minutes allowed before/after a shift for attendance matching',
  `logging_level` enum('none','error','warning','info','debug') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'error' COMMENT 'Defines the level of logging in the system',
  `auto_adjust_punches` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether to automatically adjust punch types for incomplete records',
  `heuristic_min_punch_gap` int NOT NULL DEFAULT '6' COMMENT 'Minimum hours required between punches for auto-classification',
  `use_ml_for_punch_matching` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Enable ML-based punch classification',
  `enforce_shift_schedules` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Require employees to adhere to assigned shift schedules',
  `allow_manual_time_edits` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Allow admins to manually edit time records',
  `max_shift_length` int NOT NULL DEFAULT '12' COMMENT 'Maximum shift length in hours before requiring admin approval',
  `clock_event_sync_frequency` enum('real_time','every_minute','every_5_minutes','every_15_minutes','every_30_minutes','hourly','twice_daily','daily','manual_only') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'every_5_minutes',
  `clock_event_batch_size` int NOT NULL DEFAULT '100' COMMENT 'Number of events to process per batch',
  `clock_event_auto_retry_failed` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Automatically retry failed clock event processing',
  `clock_event_daily_sync_time` time NOT NULL DEFAULT '06:00:00' COMMENT 'Time of day for daily sync (when using daily frequency)',
  `debug_punch_assignment_mode` enum('heuristic','ml','consensus','all') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' COMMENT 'Controls which Punch Type Assignment service runs for debugging',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `config_poll_interval_minutes` int NOT NULL DEFAULT '5' COMMENT 'How often devices should poll for configuration updates (in minutes)',
  `firmware_check_interval_hours` int NOT NULL DEFAULT '24' COMMENT 'How often devices should check for firmware updates (in hours)',
  `allow_device_poll_override` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Allow individual devices to override company polling settings',
  `payroll_frequency_id` bigint unsigned DEFAULT NULL COMMENT 'Company-wide payroll frequency - all employees follow the same schedule',
  `payroll_start_date` date DEFAULT NULL COMMENT 'Date when the company started using the current payroll frequency (used for bi-weekly cycle calculations)',
  `vacation_accrual_method` enum('calendar_year','pay_period','anniversary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'anniversary' COMMENT 'Primary vacation accrual method',
  `allow_carryover` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Allow vacation carryover to next period',
  `max_carryover_hours` decimal(8,2) DEFAULT NULL COMMENT 'Maximum hours that can carry over',
  `max_accrual_balance` decimal(8,2) DEFAULT NULL COMMENT 'Maximum vacation balance cap',
  `prorate_new_hires` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Prorate vacation for mid-period hires',
  `calendar_year_award_date` date DEFAULT NULL COMMENT 'Date to award annual vacation (e.g., January 1st)',
  `calendar_year_prorate_partial` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Prorate vacation for partial year employment',
  `pay_period_hours_per_period` decimal(8,4) DEFAULT NULL COMMENT 'Hours accrued per pay period',
  `pay_period_accrue_immediately` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Start accruing from first pay period',
  `pay_period_waiting_periods` int NOT NULL DEFAULT '0' COMMENT 'Number of pay periods to wait before accruing',
  `anniversary_first_year_waiting_period` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Wait until first anniversary to award vacation',
  `anniversary_award_on_anniversary` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Award full year vacation on anniversary date',
  `anniversary_max_days_cap` int DEFAULT NULL COMMENT 'Maximum vacation days cap (leave null for policy-based cap)',
  `anniversary_allow_partial_year` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Allow partial year accrual in first year',
  PRIMARY KEY (`id`),
  KEY `company_setup_payroll_frequency_id_foreign` (`payroll_frequency_id`),
  CONSTRAINT `company_setup_payroll_frequency_id_foreign` FOREIGN KEY (`payroll_frequency_id`) REFERENCES `payroll_frequencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT 'Owner: employees.id',
  `kind` enum('rfid','nfc','magstripe','qrcode','barcode','ble','biometric','pin','mobile') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Credential technology / method',
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Raw identifier when safe to store (e.g., RFID/NFC UID, QR text). NULL for secrets like PIN/biometric.',
  `identifier_hash` varbinary(255) DEFAULT NULL COMMENT 'Hash of sensitive value (e.g., PIN). Use bcrypt/argon2. NULL when identifier is stored in plaintext.',
  `hash_algo` enum('bcrypt','argon2id','argon2i') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which algorithm produced identifier_hash (if used).',
  `template_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pointer to biometric template in secure store (do NOT store raw template here).',
  `template_hash` varbinary(255) DEFAULT NULL COMMENT 'Hash of the template bytes for integrity/versioning, not reversible.',
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Friendly display label, e.g., “Blue HID fob”',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=usable, 0=revoked/disabled',
  `issued_at` timestamp NULL DEFAULT NULL COMMENT 'When this credential was issued to the employee',
  `revoked_at` timestamp NULL DEFAULT NULL COMMENT 'When disabled; keep row for history linkage',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Most recent successful use',
  `metadata` json DEFAULT NULL COMMENT 'Optional extra fields from device/provisioning (e.g., ATR, facility code)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credentials_kind_identifier` (`kind`,`identifier`),
  UNIQUE KEY `uq_credentials_kind_identifier_hash` (`kind`,`identifier_hash`),
  KEY `idx_credentials_employee` (`employee_id`),
  CONSTRAINT `fk_credentials_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the departments table',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Department name',
  `external_department_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID from external Department systems',
  `manager_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Employees for department manager',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `departments_manager_id_foreign` (`manager_id`),
  KEY `departments_created_by_foreign` (`created_by`),
  KEY `departments_updated_by_foreign` (`updated_by`),
  CONSTRAINT `departments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `departments_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `departments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the devices table',
  `device_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unique identifier reported by device (e.g., ESP32-001)',
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the device',
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Human-friendly device name (e.g., Front Office Clock)',
  `mac_address` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Device MAC address',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the device',
  `last_seen_at` timestamp NULL DEFAULT NULL COMMENT 'Last heartbeat/check-in from device',
  `last_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last known IP (redundant log field)',
  `last_mac` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last known MAC address',
  `firmware_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Current firmware version on the device',
  `last_wakeup_at` timestamp NULL DEFAULT NULL COMMENT 'Last wake/sleep-cycle time reported by device',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the device is active',
  `device_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'esp32_timeclock' COMMENT 'Type of device (esp32_timeclock, etc.)',
  `device_config` json DEFAULT NULL COMMENT 'Device-specific configuration (NTP server, pins, etc.)',
  `timezone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Device timezone (e.g., America/Chicago, UTC-5)',
  `ntp_server` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NTP server for time synchronization (e.g., pool.ntp.org)',
  `config_updated_at` timestamp NULL DEFAULT NULL COMMENT 'When device configuration was last updated from server',
  `config_synced_at` timestamp NULL DEFAULT NULL COMMENT 'When device last synced configuration from server',
  `config_version` int NOT NULL DEFAULT '1' COMMENT 'Configuration version number for sync tracking',
  `api_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Authentication token for device API calls',
  `token_expires_at` timestamp NULL DEFAULT NULL COMMENT 'API token expiration time',
  `registration_status` enum('pending','registered','approved','rejected','disabled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `registration_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Notes about device registration/approval',
  `department_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Departments',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `devices_device_id_unique` (`device_id`),
  KEY `devices_department_id_foreign` (`department_id`),
  KEY `devices_created_by_foreign` (`created_by`),
  KEY `devices_updated_by_foreign` (`updated_by`),
  KEY `devices_mac_address_index` (`mac_address`),
  KEY `devices_last_seen_at_index` (`last_seen_at`),
  KEY `devices_registration_status_index` (`registration_status`),
  CONSTRAINT `devices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `devices_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `devices_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the employee_stats table',
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to Employees',
  `hours_worked` int NOT NULL DEFAULT '0' COMMENT 'Total hours worked',
  `overtime_hours` int NOT NULL DEFAULT '0' COMMENT 'Total overtime hours',
  `leave_days` int NOT NULL DEFAULT '0' COMMENT 'Total leave days',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_stats_employee_id_foreign` (`employee_id`),
  KEY `employee_stats_created_by_foreign` (`created_by`),
  KEY `employee_stats_updated_by_foreign` (`updated_by`),
  CONSTRAINT `employee_stats_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_stats_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_stats_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_vacation_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_vacation_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to employees',
  `vacation_policy_id` bigint unsigned NOT NULL COMMENT 'Foreign key to vacation_policies',
  `effective_date` date NOT NULL COMMENT 'Date this policy assignment became effective',
  `end_date` date DEFAULT NULL COMMENT 'Date this assignment ends (null = current)',
  `override_settings` json DEFAULT NULL COMMENT 'Employee-specific overrides to policy',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this assignment is active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_vacation_assignments_vacation_policy_id_foreign` (`vacation_policy_id`),
  KEY `employee_vacation_assignments_employee_id_effective_date_index` (`employee_id`,`effective_date`),
  CONSTRAINT `employee_vacation_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_vacation_assignments_vacation_policy_id_foreign` FOREIGN KEY (`vacation_policy_id`) REFERENCES `vacation_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_vacation_summary`;
/*!50001 DROP VIEW IF EXISTS `employee_vacation_summary`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `employee_vacation_summary` AS SELECT 
 1 AS `employee_id`,
 1 AS `first_name`,
 1 AS `last_name`,
 1 AS `date_of_hire`,
 1 AS `years_of_service`,
 1 AS `vacation_policy_id`,
 1 AS `policy_name`,
 1 AS `vacation_days_per_year`,
 1 AS `vacation_hours_per_year`,
 1 AS `total_accrued`,
 1 AS `total_used`,
 1 AS `total_adjustments`,
 1 AS `current_balance`,
 1 AS `vacation_accrual_method`,
 1 AS `max_accrual_balance`,
 1 AS `max_carryover_hours`,
 1 AS `last_transaction_date`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the employees table',
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'External system identifier for the employee',
  `department_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the departments table',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Employee email address',
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'First name of the employee',
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Last name of the employee',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Residential address of the employee',
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'City of residence of the employee',
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'State of residence of the employee',
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ZIP or postal code of the employee',
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Country of residence of the employee',
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Contact phone number of the employee',
  `shift_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the shifts table',
  `photograph` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path or URL of the employee photograph',
  `termination_date` date DEFAULT NULL COMMENT 'Date of termination, if applicable',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the employee is currently active',
  `full_time` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the employee is a full-time worker',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `full_names` varchar(101) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Concatenated full name of the employee',
  `shift_schedule_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the shift schedules table',
  `round_group_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the round_groups table',
  `date_of_hire` date DEFAULT NULL COMMENT 'Employee hire date for vacation accrual calculations',
  `seniority_date` date DEFAULT NULL COMMENT 'Date for calculating length of service (may differ from hire date)',
  `overtime_exempt` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True if employee is exempt from overtime (salary)',
  `overtime_rate` decimal(5,3) NOT NULL DEFAULT '1.500' COMMENT 'Overtime multiplier (e.g., 1.500 for time and a half)',
  `double_time_threshold` decimal(8,2) DEFAULT NULL COMMENT 'Hours threshold for double time (e.g., 12.00)',
  `pay_type` enum('hourly','salary','contract') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hourly' COMMENT 'Employee pay structure',
  `pay_rate` decimal(10,2) DEFAULT NULL COMMENT 'Hourly rate or annual salary',
  PRIMARY KEY (`id`),
  KEY `employees_shift_id_foreign` (`shift_id`),
  KEY `employees_created_by_foreign` (`created_by`),
  KEY `employees_updated_by_foreign` (`updated_by`),
  KEY `employees_shift_schedule_id_foreign` (`shift_schedule_id`),
  KEY `employees_round_group_id_foreign` (`round_group_id`),
  KEY `idx_employee_name` (`first_name`,`last_name`),
  KEY `idx_department_id` (`department_id`),
  CONSTRAINT `employees_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_round_group_id_foreign` FOREIGN KEY (`round_group_id`) REFERENCES `round_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `employees_shift_schedule_id_foreign` FOREIGN KEY (`shift_schedule_id`) REFERENCES `shift_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `insert_full_name` BEFORE INSERT ON `employees` FOR EACH ROW BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)), LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `update_full_name` BEFORE UPDATE ON `employees` FOR EACH ROW BEGIN
                SET NEW.full_names = CONCAT(
                    UCASE(LEFT(NEW.first_name, 1)), LCASE(SUBSTRING(NEW.first_name, 2)), ' ',
                    UCASE(LEFT(NEW.last_name, 1)), LCASE(SUBSTRING(NEW.last_name, 2))
                );
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holiday_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holiday_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('fixed_date','relative','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculation_rule` json NOT NULL,
  `auto_create_days_ahead` int NOT NULL DEFAULT '365',
  `applies_to_all_employees` tinyint(1) NOT NULL DEFAULT '1',
  `eligible_pay_types` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holiday_template_id` bigint unsigned NOT NULL COMMENT 'Foreign key to holiday_templates',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the holiday instance',
  `date` date NOT NULL COMMENT 'Specific date for this holiday occurrence',
  `year` year NOT NULL COMMENT 'Year this holiday instance is for',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional description',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this holiday is active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_template_year` (`holiday_template_id`,`year`),
  KEY `holidays_date_is_active_index` (`date`,`is_active`),
  KEY `holidays_year_is_active_index` (`year`,`is_active`),
  CONSTRAINT `holidays_holiday_template_id_foreign` FOREIGN KEY (`holiday_template_id`) REFERENCES `holiday_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `overtime_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `overtime_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the overtime_rules table',
  `rule_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the overtime rule',
  `hours_threshold` int NOT NULL DEFAULT '40' COMMENT 'Minimum hours worked per week to trigger overtime calculation',
  `multiplier` decimal(5,2) NOT NULL DEFAULT '1.50' COMMENT 'Multiplier for overtime pay',
  `shift_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to shifts table, representing the shift this rule applies to',
  `consecutive_days_threshold` int DEFAULT NULL COMMENT 'Number of consecutive days required to trigger this rule',
  `applies_on_weekends` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the rule applies to work done on weekends',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hours_threshold` (`hours_threshold`),
  KEY `idx_shift_id` (`shift_id`),
  KEY `overtime_rules_created_by_foreign` (`created_by`),
  KEY `overtime_rules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `overtime_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `overtime_rules_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `overtime_rules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pay_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL COMMENT 'Start date of the pay period',
  `end_date` date NOT NULL COMMENT 'End date of the pay period',
  `is_posted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the pay period has been posted',
  `processed_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for processor',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `is_processed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the pay period has been processed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pay_periods_processed_by_foreign` (`processed_by`),
  KEY `pay_periods_created_by_foreign` (`created_by`),
  KEY `pay_periods_updated_by_foreign` (`updated_by`),
  CONSTRAINT `pay_periods_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pay_periods_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pay_periods_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `frequency_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Display name (e.g., "Bi-Weekly", "Semi-Monthly")',
  `frequency_type` enum('weekly','biweekly','semimonthly','monthly','quarterly','annually') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'System type for calculation logic',
  `reference_start_date` date DEFAULT NULL COMMENT 'Starting date for bi-weekly cycles or other recurring calculations',
  `weekly_day` int DEFAULT NULL COMMENT 'Day of week for weekly/bi-weekly pay (0=Sunday, 6=Saturday)',
  `start_of_week` int NOT NULL DEFAULT '0' COMMENT 'Start of work week (0=Sunday, 1=Monday, 2=Tuesday, etc.)',
  `first_pay_day` int DEFAULT NULL COMMENT 'First pay day of month (1-31, or special values: 99=last_day)',
  `second_pay_day` int DEFAULT NULL COMMENT 'Second pay day of month for semi-monthly (1-31, or special values: 99=last_day)',
  `month_end_handling` enum('exact_day','last_day_of_month','first_day_next_month') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exact_day' COMMENT 'How to handle when pay day > days in month',
  `weekend_adjustment` enum('none','previous_friday','next_monday','closest_weekday') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'How to adjust pay dates that fall on weekends',
  `skip_holidays` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether to adjust pay dates that fall on company holidays',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Human readable description of this frequency configuration',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this frequency is available for selection',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_frequencies_created_by_foreign` (`created_by`),
  KEY `payroll_frequencies_updated_by_foreign` (`updated_by`),
  KEY `payroll_frequencies_frequency_type_index` (`frequency_type`),
  KEY `payroll_frequencies_is_active_index` (`is_active`),
  CONSTRAINT `payroll_frequencies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_frequencies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `punch_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `punch_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the punch_types table',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the punch type (e.g., Clock In, Clock Out)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Detailed description of the punch type',
  `schedule_reference` enum('start_time','lunch_start','lunch_stop','stop_time','break_start','break_stop','manual','jury_duty','bereavement','flexible','passthrough') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference to a schedule event associated with this punch type',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the punch type is currently active',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `punch_types_created_by_foreign` (`created_by`),
  KEY `punch_types_updated_by_foreign` (`updated_by`),
  CONSTRAINT `punch_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punch_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `punches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `punches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the punches table',
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the employee who made the punch',
  `external_group_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shift_date` date DEFAULT NULL COMMENT 'The assigned workday for this punch record',
  `device_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the device used for the punch',
  `punch_type_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the type of punch (e.g., Clock In, Clock Out)',
  `punch_time` datetime NOT NULL COMMENT 'Exact time of the punch',
  `is_altered` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the punch was manually altered after recording',
  `is_late` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the punch is considered late',
  `punch_state` enum('start','stop','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'Indicates whether the punch is a start or stop event',
  `pay_period_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the associated pay period',
  `attendance_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the associated attendance record',
  `classification_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the classifications table',
  `is_posted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the punch has been posted in the system',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the record is archived',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_punch_time` (`punch_time`),
  KEY `idx_external_group_id` (`external_group_id`),
  KEY `idx_shift_date` (`shift_date`),
  KEY `idx_is_archived` (`is_archived`),
  KEY `punches_device_id_foreign` (`device_id`),
  KEY `punches_punch_type_id_foreign` (`punch_type_id`),
  KEY `punches_pay_period_id_foreign` (`pay_period_id`),
  KEY `punches_attendance_id_foreign` (`attendance_id`),
  KEY `punches_classification_id_foreign` (`classification_id`),
  KEY `punches_created_by_foreign` (`created_by`),
  KEY `punches_updated_by_foreign` (`updated_by`),
  CONSTRAINT `punches_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_classification_id_foreign` FOREIGN KEY (`classification_id`) REFERENCES `classifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `punches_pay_period_id_foreign` FOREIGN KEY (`pay_period_id`) REFERENCES `pay_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_punch_type_id_foreign` FOREIGN KEY (`punch_type_id`) REFERENCES `punch_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `punches_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_punch_time_update` BEFORE UPDATE ON `punches` FOR EACH ROW SET NEW.is_altered = IF(NEW.punch_time <> OLD.punch_time, 1, NEW.is_altered) */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `round_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `round_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the round_groups table',
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of the rounding group (e.g., 5_Minute)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `round_groups_group_name_unique` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rounding_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rounding_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the rounding_rules table',
  `round_group_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the round_groups table',
  `minute_min` int NOT NULL COMMENT 'Minimum minute value for the rounding range',
  `minute_max` int NOT NULL COMMENT 'Maximum minute value for the rounding range',
  `new_minute` int NOT NULL COMMENT 'New minute value after rounding',
  `new_minute_decimal` decimal(5,2) NOT NULL COMMENT 'Decimal equivalent of the rounded minute value',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rounding_range` (`minute_min`,`minute_max`),
  KEY `idx_rounding_group` (`round_group_id`),
  CONSTRAINT `rounding_rules_round_group_id_foreign` FOREIGN KEY (`round_group_id`) REFERENCES `round_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_insert_rounding_rules` BEFORE INSERT ON `rounding_rules` FOR EACH ROW BEGIN
                IF NEW.minute_min > NEW.minute_max THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'minute_min cannot be greater than minute_max';
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shift_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shift_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the schedules table',
  `schedule_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the schedule',
  `start_time` time NOT NULL COMMENT 'Scheduled start time',
  `lunch_start_time` time NOT NULL COMMENT 'Scheduled lunch start time',
  `lunch_stop_time` time NOT NULL COMMENT 'Scheduled stop time',
  `lunch_duration` int NOT NULL DEFAULT '60' COMMENT 'Lunch duration in minutes',
  `daily_hours` int NOT NULL COMMENT 'Standard hours worked per day',
  `end_time` time NOT NULL COMMENT 'Scheduled end time',
  `grace_period` int NOT NULL DEFAULT '15' COMMENT 'Allowed grace period in minutes for lateness',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the schedule is active',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional notes for the schedule',
  `shift_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  PRIMARY KEY (`id`),
  KEY `shift_schedules_created_by_foreign` (`created_by`),
  KEY `shift_schedules_updated_by_foreign` (`updated_by`),
  KEY `shift_schedules_shift_id_foreign` (`shift_id`),
  CONSTRAINT `shift_schedules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shift_schedules_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `shift_schedules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the shifts table',
  `shift_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the shift',
  `start_time` time NOT NULL COMMENT 'Scheduled start time of the shift',
  `end_time` time NOT NULL COMMENT 'Scheduled end time of the shift',
  `base_hours_per_period` smallint DEFAULT NULL COMMENT 'Standard hours for the shift per pay period',
  `multi_day_shift` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the shift spans multiple calendar days',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shifts_created_by_foreign` (`created_by`),
  KEY `shifts_updated_by_foreign` (`updated_by`),
  CONSTRAINT `shifts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shifts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Employee, links the user account to an employee',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of the last login',
  `is_manager` tinyint NOT NULL DEFAULT '0' COMMENT 'Flag indicating if the user is a manager',
  `is_admin` tinyint NOT NULL DEFAULT '0' COMMENT 'Flag indicating if the user has admin privileges',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users, indicating the record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users, indicating the last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_employee_id_unique` (`employee_id`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_updated_by_foreign` (`updated_by`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_accrual_history`;
/*!50001 DROP VIEW IF EXISTS `vacation_accrual_history`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vacation_accrual_history` AS SELECT 
 1 AS `id`,
 1 AS `employee_id`,
 1 AS `first_name`,
 1 AS `last_name`,
 1 AS `transaction_type`,
 1 AS `hours`,
 1 AS `transaction_date`,
 1 AS `effective_date`,
 1 AS `accrual_period`,
 1 AS `description`,
 1 AS `running_balance`,
 1 AS `policy_name`,
 1 AS `vacation_hours_per_year`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vacation_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to Employees',
  `accrual_rate` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Rate at which vacation time accrues per pay period',
  `accrued_hours` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Total vacation hours accrued',
  `used_hours` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Total vacation hours used',
  `carry_over_hours` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Vacation hours carried over from the previous year',
  `cap_hours` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Maximum allowed vacation hours (cap)',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `accrual_year` int NOT NULL DEFAULT '1' COMMENT 'Current accrual year (1st year, 2nd year, etc.)',
  `last_anniversary_date` date DEFAULT NULL COMMENT 'Date of last anniversary when vacation was credited',
  `next_anniversary_date` date DEFAULT NULL COMMENT 'Date of next anniversary for vacation credit',
  `annual_days_earned` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Days earned this accrual year',
  `previous_year_balance` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Balance from previous accrual year',
  `current_year_awarded` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Hours awarded on most recent anniversary',
  `current_year_used` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Hours used in current accrual year',
  `is_anniversary_based` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Uses anniversary-based accrual vs continuous',
  `accrual_history` json DEFAULT NULL COMMENT 'JSON log of anniversary awards and usage',
  `policy_effective_date` date DEFAULT NULL COMMENT 'Date this accrual policy became effective',
  PRIMARY KEY (`id`),
  KEY `vacation_balances_employee_id_foreign` (`employee_id`),
  KEY `vacation_balances_created_by_foreign` (`created_by`),
  KEY `vacation_balances_updated_by_foreign` (`updated_by`),
  CONSTRAINT `vacation_balances_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vacation_balances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vacation_balances_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_calendars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the vacation_calendars table',
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key referencing the employee taking the vacation',
  `vacation_date` date NOT NULL COMMENT 'Date of the vacation',
  `is_half_day` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the vacation is for a half-day',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the vacation record is active',
  `is_recorded` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if this vacation has been recorded in the Attendance table',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vacation_calendars_employee_id_foreign` (`employee_id`),
  KEY `vacation_calendars_created_by_foreign` (`created_by`),
  KEY `vacation_calendars_updated_by_foreign` (`updated_by`),
  CONSTRAINT `vacation_calendars_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vacation_calendars_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vacation_calendars_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of this policy (e.g., "Standard Employee")',
  `min_tenure_years` int NOT NULL DEFAULT '0' COMMENT 'Minimum years for this tier',
  `max_tenure_years` int DEFAULT NULL COMMENT 'Maximum years for this tier (null = no max)',
  `vacation_days_per_year` decimal(5,2) NOT NULL COMMENT 'Vacation days earned per year',
  `vacation_hours_per_year` decimal(8,2) NOT NULL COMMENT 'Vacation hours earned per year',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this policy tier is active',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT 'Sort order for policy display',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vacation_policies_min_tenure_years_index` (`min_tenure_years`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vacation_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vacation_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT 'Foreign key to employees',
  `transaction_type` enum('accrual','usage','adjustment','carryover','expiration') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of vacation transaction',
  `hours` decimal(8,2) NOT NULL COMMENT 'Hours affected (positive for accrual, negative for usage)',
  `transaction_date` date NOT NULL COMMENT 'Date transaction occurred',
  `effective_date` date NOT NULL COMMENT 'Date transaction takes effect',
  `accrual_period` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Accrual period reference (e.g., "2024-Q1", "2024-Anniversary")',
  `pay_period_id` bigint unsigned DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Human-readable description',
  `metadata` json DEFAULT NULL COMMENT 'Additional transaction data (pay period, policy used, etc.)',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vacation_transactions_employee_id_transaction_date_index` (`employee_id`,`transaction_date`),
  KEY `vacation_transactions_employee_id_transaction_type_index` (`employee_id`,`transaction_type`),
  KEY `vacation_transactions_pay_period_id_foreign` (`pay_period_id`),
  CONSTRAINT `vacation_transactions_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vacation_transactions_pay_period_id_foreign` FOREIGN KEY (`pay_period_id`) REFERENCES `pay_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 DROP PROCEDURE IF EXISTS `fix_shift_schedules` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `fix_shift_schedules`()
BEGIN
  DECLARE fk_name VARCHAR(255);

  /* 1) Drop FK on department_id if it exists */
  SELECT CONSTRAINT_NAME
    INTO fk_name
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shift_schedules'
    AND COLUMN_NAME = 'department_id'
    AND REFERENCED_TABLE_NAME = 'departments'
  LIMIT 1;

  IF fk_name IS NOT NULL THEN
    SET @sql = CONCAT('ALTER TABLE shift_schedules DROP FOREIGN KEY ', fk_name);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;

  /* 2) Drop department_id column if it exists */
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'shift_schedules'
      AND COLUMN_NAME = 'department_id'
  ) THEN
    ALTER TABLE shift_schedules DROP COLUMN department_id;
  END IF;

  /* 3) Add shift_id column if missing */
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'shift_schedules'
      AND COLUMN_NAME = 'shift_id'
  ) THEN
    ALTER TABLE shift_schedules
      ADD COLUMN shift_id BIGINT UNSIGNED NULL AFTER employee_id;
  END IF;

  /* 4) Ensure FK shift_id -> shifts(id) exists (ON DELETE SET NULL, ON UPDATE CASCADE) */
  SELECT CONSTRAINT_NAME
    INTO fk_name
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shift_schedules'
    AND COLUMN_NAME = 'shift_id'
    AND REFERENCED_TABLE_NAME = 'shifts'
  LIMIT 1;

  IF fk_name IS NULL THEN
    ALTER TABLE shift_schedules
      ADD CONSTRAINT shift_schedules_shift_id_foreign
      FOREIGN KEY (shift_id) REFERENCES shifts(id)
      ON DELETE SET NULL ON UPDATE CASCADE;
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `InsertAttendanceTimeGroups` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `InsertAttendanceTimeGroups`()
BEGIN
    DECLARE flex_minutes INT;

    -- Fetch attendance flexibility minutes from company_setup, default to 30 if NULL
    SELECT COALESCE(attendance_flexibility_minutes, 30) 
    INTO flex_minutes 
    FROM company_setup 
    LIMIT 1;

    -- Delete all existing records in attendance_time_groups
    DELETE FROM attendance_time_groups;

    -- Insert new records ensuring no overlap
    INSERT INTO attendance_time_groups (
        employee_id,
        external_group_id,
        shift_date,
        shift_window_start,
        shift_window_end,
        lunch_start_time,
        lunch_end_time,
        created_at,
        updated_at
    )
    SELECT 
        MIN(employee_id) AS employee_id,  -- Ensuring one employee per group
        external_group_id,
        shift_date,
        MIN(shift_window_start) AS shift_window_start,  
        MAX(shift_window_end) AS shift_window_end,  -- Ensure the latest shift end is used
        MIN(lunch_start_time) AS lunch_start_time,  -- Take the earliest lunch start
        MAX(lunch_end_time) AS lunch_end_time,      -- Take the latest lunch end
        NOW() AS created_at,
        NOW() AS updated_at
    FROM (
        SELECT 
            e.id AS employee_id,

            -- Generate unique external_group_id based on employee and shift date
            CONCAT(e.external_id, '_', DATE_FORMAT(
                CASE 
                    WHEN sh.multi_day_shift = 1 AND TIME(a.punch_time) < '12:00:00' 
                        THEN DATE_SUB(DATE(a.punch_time), INTERVAL 1 DAY)
                    ELSE DATE(a.punch_time)
                END, '%Y%m%d'
            )) AS external_group_id,

            -- Determine shift_date properly
            CASE 
                WHEN sh.multi_day_shift = 1 AND TIME(a.punch_time) < '12:00:00' 
                    THEN DATE_SUB(DATE(a.punch_time), INTERVAL 1 DAY)
                ELSE DATE(a.punch_time)
            END AS shift_date,

            -- Prevent Overlapping Shift Windows
            TIMESTAMP(DATE(a.punch_time), 
                LEAST(ss.start_time, '23:59:59')
            ) AS shift_window_start,

            TIMESTAMP(DATE_ADD(DATE(a.punch_time), INTERVAL 1 DAY), 
                GREATEST(ss.end_time, '00:00:00')
            ) AS shift_window_end,

            -- Ensure lunch times are properly assigned
            TIMESTAMP(DATE(a.punch_time),
                COALESCE(ss.lunch_start_time, '12:00:00')
            ) AS lunch_start_time,

            TIMESTAMP(DATE(a.punch_time),
                ADDTIME(COALESCE(ss.lunch_start_time, '12:00:00'), 
                SEC_TO_TIME(COALESCE(ss.lunch_duration, 30) * 60))
            ) AS lunch_end_time

        FROM attendances a
        JOIN employees e ON a.employee_id = e.id  
        LEFT JOIN shift_schedules ss 
            ON ss.employee_id = e.id 
            OR (ss.department_id = e.department_id AND ss.employee_id IS NULL)
        JOIN shifts sh ON ss.shift_id = sh.id
    ) AS grouped_results
    GROUP BY external_group_id, shift_date;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `MatchAttendanceToTimeGroups` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `MatchAttendanceToTimeGroups`()
BEGIN
    DECLARE flex_minutes INT;

    -- Fetch attendance flexibility minutes from company_setup, default to 30 if NULL
    SELECT COALESCE(attendance_flexibility_minutes, 30) 
    INTO flex_minutes 
    FROM company_setup 
    LIMIT 1;

    -- Reset only records that are expected to be updated (more efficient)
    UPDATE attendances
    SET 
        external_group_id = NULL, 
        shift_date = NULL
    WHERE external_group_id IS NOT NULL OR shift_date IS NOT NULL;

    -- Match attendance records to appropriate time groups using improved shift window logic
    UPDATE attendances a
    JOIN (
        SELECT 
            g.employee_id,
            g.external_group_id,
            g.shift_date,
            g.shift_window_start,
            g.shift_window_end,
            e.shift_id
        FROM attendance_time_groups g
        JOIN employees e ON g.employee_id = e.id
    ) AS matched_groups
    ON a.employee_id = matched_groups.employee_id
    AND (
        -- Normal Case: Punch falls within shift window
        (a.punch_time BETWEEN matched_groups.shift_window_start AND matched_groups.shift_window_end)

        -- Special Case: Allow flexibility before shift start
        OR (a.punch_time >= SUBTIME(matched_groups.shift_window_start, SEC_TO_TIME(flex_minutes * 60))
            AND a.punch_time < matched_groups.shift_window_start)
    )
    SET 
        a.external_group_id = matched_groups.external_group_id,
        
        -- Assign shift_date based on shift type
        a.shift_date = CASE 
            -- ✅ **1st Shift Employees → Always Use Punch Date**
            WHEN matched_groups.shift_id = 1 THEN DATE(a.punch_time)

            -- ✅ **2nd Shift Employees → Use Previous Date If Punch Is Between Midnight-5AM**
            WHEN matched_groups.shift_id = 2 
                 AND TIME(a.punch_time) BETWEEN '00:00:00' AND '05:00:00'
                 THEN DATE_SUB(DATE(a.punch_time), INTERVAL 1 DAY)
            
            -- ✅ **All Other Cases → Use the Punch Date**
            ELSE DATE(a.punch_time)
        END;

    -- Optional: Mark unmatched records for debugging
    UPDATE attendances
    SET external_group_id = 'UNMATCHED'
    WHERE external_group_id IS NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `ResetAttendanceAndPunches` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `ResetAttendanceAndPunches`()
BEGIN
    -- Step 1: Reset necessary fields in the `attendances` table
    UPDATE attendances
    SET 
        punch_type_id = NULL,
        status = 'Incomplete',
				punch_state = NULL,
				external_group_id = NULL,
				shift_date = NULL,
        is_migrated = 0,
        issue_notes = NULL,
        classification_id = NULL;

    -- Step 2: Delete attendance records where holiday_id is NULL
    DELETE FROM attendances WHERE holiday_id IS NOT NULL;

    -- Step 3: Delete all records from punches table
    DELETE FROM punches;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `anniversary_vacation_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `anniversary_vacation_status` AS select `e`.`id` AS `employee_id`,`e`.`first_name` AS `first_name`,`e`.`last_name` AS `last_name`,`e`.`date_of_hire` AS `date_of_hire`,(case when (`e`.`date_of_hire` is null) then NULL else (`e`.`date_of_hire` + interval floor(((to_days(curdate()) - to_days(`e`.`date_of_hire`)) / 365.25)) year) end) AS `last_anniversary_date`,(case when (`e`.`date_of_hire` is null) then NULL else (`e`.`date_of_hire` + interval (floor(((to_days(curdate()) - to_days(`e`.`date_of_hire`)) / 365.25)) + 1) year) end) AS `next_anniversary_date`,floor(((to_days(curdate()) - to_days(`e`.`date_of_hire`)) / 365.25)) AS `completed_years`,coalesce(`current_accruals`.`current_year_accrued`,0) AS `current_year_accrued`,coalesce(`current_used`.`current_year_used`,0) AS `current_year_used`,`vp`.`policy_name` AS `policy_name`,`vp`.`vacation_hours_per_year` AS `annual_entitlement`,(case when (`e`.`date_of_hire` is null) then 0 when (curdate() >= (`e`.`date_of_hire` + interval (floor(((to_days(curdate()) - to_days(`e`.`date_of_hire`)) / 365.25)) + 1) year)) then 1 else 0 end) AS `is_due_for_accrual` from ((((`employees` `e` left join `employee_vacation_assignments` `eva` on(((`eva`.`employee_id` = `e`.`id`) and (`eva`.`is_active` = 1) and (`eva`.`effective_date` <= curdate()) and ((`eva`.`end_date` is null) or (`eva`.`end_date` >= curdate()))))) left join `vacation_policies` `vp` on((`vp`.`id` = `eva`.`vacation_policy_id`))) left join (select `vt`.`employee_id` AS `employee_id`,sum(`vt`.`hours`) AS `current_year_accrued` from (`vacation_transactions` `vt` join `employees` `e2` on((`e2`.`id` = `vt`.`employee_id`))) where ((`vt`.`transaction_type` = 'accrual') and (`vt`.`transaction_date` >= (`e2`.`date_of_hire` + interval floor(((to_days(curdate()) - to_days(`e2`.`date_of_hire`)) / 365.25)) year)) and (`vt`.`transaction_date` < (`e2`.`date_of_hire` + interval (floor(((to_days(curdate()) - to_days(`e2`.`date_of_hire`)) / 365.25)) + 1) year))) group by `vt`.`employee_id`) `current_accruals` on((`current_accruals`.`employee_id` = `e`.`id`))) left join (select `vt`.`employee_id` AS `employee_id`,abs(sum(`vt`.`hours`)) AS `current_year_used` from (`vacation_transactions` `vt` join `employees` `e2` on((`e2`.`id` = `vt`.`employee_id`))) where ((`vt`.`transaction_type` = 'usage') and (`vt`.`transaction_date` >= (`e2`.`date_of_hire` + interval floor(((to_days(curdate()) - to_days(`e2`.`date_of_hire`)) / 365.25)) year)) and (`vt`.`transaction_date` < (`e2`.`date_of_hire` + interval (floor(((to_days(curdate()) - to_days(`e2`.`date_of_hire`)) / 365.25)) + 1) year))) group by `vt`.`employee_id`) `current_used` on((`current_used`.`employee_id` = `e`.`id`))) where ((`e`.`is_active` = 1) and (`e`.`date_of_hire` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `employee_vacation_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `employee_vacation_summary` AS select `e`.`id` AS `employee_id`,`e`.`first_name` AS `first_name`,`e`.`last_name` AS `last_name`,`e`.`date_of_hire` AS `date_of_hire`,(case when (`e`.`date_of_hire` is null) then 0 else floor(((to_days(curdate()) - to_days(`e`.`date_of_hire`)) / 365.25)) end) AS `years_of_service`,`eva`.`vacation_policy_id` AS `vacation_policy_id`,`vp`.`policy_name` AS `policy_name`,`vp`.`vacation_days_per_year` AS `vacation_days_per_year`,`vp`.`vacation_hours_per_year` AS `vacation_hours_per_year`,coalesce(`accruals`.`total_accrued`,0) AS `total_accrued`,coalesce(`used_hours`.`total_used`,0) AS `total_used`,coalesce(`adjustments`.`total_adjustments`,0) AS `total_adjustments`,((coalesce(`accruals`.`total_accrued`,0) - coalesce(`used_hours`.`total_used`,0)) + coalesce(`adjustments`.`total_adjustments`,0)) AS `current_balance`,`cs`.`vacation_accrual_method` AS `vacation_accrual_method`,`cs`.`max_accrual_balance` AS `max_accrual_balance`,`cs`.`max_carryover_hours` AS `max_carryover_hours`,`last_trans`.`last_transaction_date` AS `last_transaction_date` from (((((((`employees` `e` left join `employee_vacation_assignments` `eva` on(((`eva`.`employee_id` = `e`.`id`) and (`eva`.`is_active` = 1) and (`eva`.`effective_date` <= curdate()) and ((`eva`.`end_date` is null) or (`eva`.`end_date` >= curdate()))))) left join `vacation_policies` `vp` on((`vp`.`id` = `eva`.`vacation_policy_id`))) left join `company_setup` `cs` on((`cs`.`id` = 1))) left join (select `vacation_transactions`.`employee_id` AS `employee_id`,sum(`vacation_transactions`.`hours`) AS `total_accrued` from `vacation_transactions` where (`vacation_transactions`.`transaction_type` = 'accrual') group by `vacation_transactions`.`employee_id`) `accruals` on((`accruals`.`employee_id` = `e`.`id`))) left join (select `vacation_transactions`.`employee_id` AS `employee_id`,abs(sum(`vacation_transactions`.`hours`)) AS `total_used` from `vacation_transactions` where (`vacation_transactions`.`transaction_type` = 'usage') group by `vacation_transactions`.`employee_id`) `used_hours` on((`used_hours`.`employee_id` = `e`.`id`))) left join (select `vacation_transactions`.`employee_id` AS `employee_id`,sum(`vacation_transactions`.`hours`) AS `total_adjustments` from `vacation_transactions` where (`vacation_transactions`.`transaction_type` = 'adjustment') group by `vacation_transactions`.`employee_id`) `adjustments` on((`adjustments`.`employee_id` = `e`.`id`))) left join (select `vacation_transactions`.`employee_id` AS `employee_id`,max(`vacation_transactions`.`transaction_date`) AS `last_transaction_date` from `vacation_transactions` group by `vacation_transactions`.`employee_id`) `last_trans` on((`last_trans`.`employee_id` = `e`.`id`))) where (`e`.`is_active` = 1) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vacation_accrual_history`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vacation_accrual_history` AS select `vt`.`id` AS `id`,`vt`.`employee_id` AS `employee_id`,`e`.`first_name` AS `first_name`,`e`.`last_name` AS `last_name`,`vt`.`transaction_type` AS `transaction_type`,`vt`.`hours` AS `hours`,`vt`.`transaction_date` AS `transaction_date`,`vt`.`effective_date` AS `effective_date`,`vt`.`accrual_period` AS `accrual_period`,`vt`.`description` AS `description`,(select coalesce(sum((case when (`vt2`.`transaction_type` = 'accrual') then `vt2`.`hours` when (`vt2`.`transaction_type` = 'usage') then `vt2`.`hours` when (`vt2`.`transaction_type` = 'adjustment') then `vt2`.`hours` else 0 end)),0) from `vacation_transactions` `vt2` where ((`vt2`.`employee_id` = `vt`.`employee_id`) and (`vt2`.`transaction_date` <= `vt`.`transaction_date`) and (`vt2`.`id` <= `vt`.`id`))) AS `running_balance`,`vp`.`policy_name` AS `policy_name`,`vp`.`vacation_hours_per_year` AS `vacation_hours_per_year` from (((`vacation_transactions` `vt` join `employees` `e` on((`e`.`id` = `vt`.`employee_id`))) left join `employee_vacation_assignments` `eva` on(((`eva`.`employee_id` = `vt`.`employee_id`) and (`eva`.`effective_date` <= `vt`.`transaction_date`) and ((`eva`.`end_date` is null) or (`eva`.`end_date` >= `vt`.`transaction_date`))))) left join `vacation_policies` `vp` on((`vp`.`id` = `eva`.`vacation_policy_id`))) order by `vt`.`employee_id`,`vt`.`transaction_date`,`vt`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2024_11_22_080042_create_round_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_11_23_231334_create_employees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_11_23_231433_create_employee_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_11_23_231434_create_devices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_11_23_231533_create_cards_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_11_23_231533_create_departments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_11_23_231534_create_holidays_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2024_11_23_231534_create_overtime_rules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2024_11_23_231534_create_pay_periods_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2024_11_23_231534_create_payroll_frequencys_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2024_11_23_231534_create_punch_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2024_11_23_231534_create_punches_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2024_11_23_231534_create_rounding_rules_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2024_11_23_231534_create_users_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2024_11_23_231535_create_shifts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2024_11_23_231535_create_vacation_balances_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2024_11_23_231535_create_vacation_calendars_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2024_12_07_065909_create_shift_schedules_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_01_18_012015_create_classifications_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_01_18_012016_create_attendances_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_02_12_203452_attendance_time_groups',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_02_12_203452_create_company_setup_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_02_14_163807_update_external_group_id_length',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_08_22_085532_create_sessions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_09_12_153516_fix_employee_shift_relationships_and_add_lunch_stop_time',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_09_14_141233_update_debug_punch_assignment_mode_enum_values',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2025_09_14_145949_add_consensus_review_status_to_attendances_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2025_09_14_150330_add_consensus_to_debug_punch_assignment_mode',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2025_09_14_160508_update_consensus_review_status_to_discrepancy',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2025_09_15_202445_create_credentials_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2025_09_15_202448_create_clock_events_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2025_09_16_221917_create_permission_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2025_09_17_122846_add_employment_fields_to_employees_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2025_09_17_124604_enhance_vacation_balances_for_anniversary_system',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2025_09_17_130029_create_configurable_vacation_system',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_09_17_130949_create_vacation_computed_views',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_09_17_131155_remove_redundant_vacation_fields_from_employees',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_09_17_133727_add_vacation_method_specific_fields_to_company_setup',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_09_17_213242_create_holiday_templates_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_09_17_213304_add_holiday_fields_to_vacation_calendars_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_09_17_225010_add_eligible_pay_types_to_holiday_templates_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_09_17_230013_migrate_old_holidays_to_templates',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_09_17_230141_drop_old_holidays_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_09_17_235841_create_holidays_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_09_18_000059_remove_holiday_fields_from_vacation_calendars_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_09_19_075035_add_payperiod_reference_to_vacation_transactions_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_09_19_151751_add_processing_fields_to_clock_events_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_09_19_152935_add_clock_event_sync_settings_to_company_setup_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_09_19_162208_add_esp32_fields_to_devices_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_09_21_160923_fix_registration_status_enum',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_09_21_215850_add_timezone_and_config_fields_to_devices_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_09_21_221740_add_polling_intervals_to_company_setup_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_09_21_225441_add_ntp_server_to_devices_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_09_21_233422_add_start_of_week_to_payroll_frequencies_table',32);
