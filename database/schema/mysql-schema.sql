/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
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
  `status` enum('Incomplete','Partial','Complete','Migrated','Posted','NeedsReview') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Incomplete' COMMENT 'Processing status of the attendance record',
  `is_migrated` tinyint(1) DEFAULT NULL COMMENT 'Indicates if the attendance record is migrated',
  `is_processed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the pay period has been processed',
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
  CONSTRAINT `attendances_holiday_id_foreign` FOREIGN KEY (`holiday_id`) REFERENCES `holidays` (`id`) ON DELETE CASCADE,
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
DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Employees',
  `card_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique card number assigned to the employee',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the card is currently active',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cards_card_number_unique` (`card_number`),
  UNIQUE KEY `cards_employee_id_unique` (`employee_id`),
  KEY `cards_created_by_foreign` (`created_by`),
  KEY `cards_updated_by_foreign` (`updated_by`),
  CONSTRAINT `cards_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cards_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cards_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  `debug_punch_assignment_mode` enum('shift_schedule','heuristic','ml','full') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT 'Controls which Punch Type Assignment service runs for debugging',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
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
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the device',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the device',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates if the device is active',
  `department_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Departments',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `devices_department_id_foreign` (`department_id`),
  KEY `devices_created_by_foreign` (`created_by`),
  KEY `devices_updated_by_foreign` (`updated_by`),
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
  `vacation_pay` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the employee is eligible for vacation pay',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payroll_frequency_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the payroll frequencies table',
  `full_names` varchar(101) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Concatenated full name of the employee',
  `shift_schedule_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the shift schedules table',
  `round_group_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the round_groups table',
  PRIMARY KEY (`id`),
  KEY `employees_shift_id_foreign` (`shift_id`),
  KEY `employees_created_by_foreign` (`created_by`),
  KEY `employees_updated_by_foreign` (`updated_by`),
  KEY `employees_payroll_frequency_id_foreign` (`payroll_frequency_id`),
  KEY `employees_shift_schedule_id_foreign` (`shift_schedule_id`),
  KEY `employees_round_group_id_foreign` (`round_group_id`),
  KEY `idx_employee_name` (`first_name`,`last_name`),
  KEY `idx_department_id` (`department_id`),
  CONSTRAINT `employees_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_payroll_frequency_id_foreign` FOREIGN KEY (`payroll_frequency_id`) REFERENCES `payroll_frequencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_round_group_id_foreign` FOREIGN KEY (`round_group_id`) REFERENCES `round_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `employees_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
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
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary key of the holidays table',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the holiday',
  `start_date` date DEFAULT NULL COMMENT 'Start date of the holiday',
  `end_date` date DEFAULT NULL COMMENT 'End date of the holiday, if applicable',
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the holiday recurs annually',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_holiday_dates` (`start_date`,`end_date`),
  KEY `holidays_created_by_foreign` (`created_by`),
  KEY `holidays_updated_by_foreign` (`updated_by`),
  CONSTRAINT `holidays_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `holidays_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  `frequency_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the payroll frequency',
  `weekly_day` int DEFAULT NULL COMMENT 'Day of the week for weekly payroll (0-6, Sun-Sat)',
  `semimonthly_first_day` int DEFAULT NULL COMMENT 'First fixed day of the month for semimonthly payroll',
  `semimonthly_second_day` int DEFAULT NULL COMMENT 'Second fixed day of the month for semimonthly payroll',
  `monthly_day` int DEFAULT NULL COMMENT 'Day of the month for monthly payroll',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for record creator',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key to Users for last updater',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_frequencies_created_by_foreign` (`created_by`),
  KEY `payroll_frequencies_updated_by_foreign` (`updated_by`),
  CONSTRAINT `payroll_frequencies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_frequencies_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  `is_processed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indicates if the punch has been processed in the system',
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the employees table',
  `department_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the departments table',
  `shift_id` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the shifts table',
  `created_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who created the record',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT 'Foreign key referencing the user who last updated the record',
  PRIMARY KEY (`id`),
  KEY `shift_schedules_employee_id_foreign` (`employee_id`),
  KEY `shift_schedules_department_id_foreign` (`department_id`),
  KEY `shift_schedules_shift_id_foreign` (`shift_id`),
  KEY `shift_schedules_created_by_foreign` (`created_by`),
  KEY `shift_schedules_updated_by_foreign` (`updated_by`),
  CONSTRAINT `shift_schedules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shift_schedules_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_schedules_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_schedules_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
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
