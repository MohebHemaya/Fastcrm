-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: driver_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_logs_action_created_at_index` (`action`,`created_at`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_user_id_index` (`user_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'login','App\\Models\\User',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-06 09:49:25'),(2,1,'import_uploaded','App\\Models\\Import',1,'{\"original_name\":\"drivers_import_template.csv\",\"size\":239}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-06 09:50:18'),(3,1,'import_parsed','App\\Models\\Import',1,'{\"total\":4,\"new\":4,\"duplicate_in_file\":0,\"existing\":0,\"active\":0,\"invalid\":0}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-06 09:50:18'),(4,1,'import_committed','App\\Models\\Import',1,'{\"counts\":{\"total\":4,\"new\":4,\"duplicate_in_file\":0,\"existing\":0,\"active\":0,\"invalid\":0},\"user_id\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-06 09:50:23'),(5,NULL,'failed_login',NULL,NULL,'{\"reason\":\"invalid_credentials\",\"email\":\"admin@admin.com\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-07 07:17:11'),(6,NULL,'failed_login',NULL,NULL,'{\"reason\":\"invalid_credentials\",\"email\":\"admin@admin.com\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-07 07:17:17'),(7,NULL,'failed_login',NULL,NULL,'{\"reason\":\"invalid_credentials\",\"email\":\"admin@example.com\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-07 07:17:23'),(8,1,'login','App\\Models\\User',1,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-10-07 07:17:32');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('ntham-adar-alsaykyn-cache-40199966974185def98bb7077526bd84','i:1;',1791291078),('ntham-adar-alsaykyn-cache-40199966974185def98bb7077526bd84:timer','i:1791291077;',1791291077),('ntham-adar-alsaykyn-cache-61fd40d26b2ffd16e35803df284a5bfd','i:2;',1791368291),('ntham-adar-alsaykyn-cache-61fd40d26b2ffd16e35803df284a5bfd:timer','i:1791368291;',1791368291),('ntham-adar-alsaykyn-cache-8d3f92cec846e4aeebbbb05de0392c22','i:2;',1791368303),('ntham-adar-alsaykyn-cache-8d3f92cec846e4aeebbbb05de0392c22:timer','i:1791368303;',1791368303),('ntham-adar-alsaykyn-cache-dashboard_group_perf_1_c49f9382c757f8a5884570772777b55c','a:4:{i:0;a:9:{s:2:\"id\";i:3;s:4:\"name\";s:21:\"جروب أكتوبر\";s:8:\"imported\";i:1;s:7:\"invited\";i:0;s:10:\"interested\";i:0;s:6:\"active\";i:0;s:8:\"declined\";i:0;s:9:\"opted_out\";i:0;s:15:\"conversion_rate\";d:0;}i:1;a:9:{s:2:\"id\";i:2;s:4:\"name\";s:34:\"جروب التجمع الخامس\";s:8:\"imported\";i:1;s:7:\"invited\";i:0;s:10:\"interested\";i:0;s:6:\"active\";i:0;s:8:\"declined\";i:0;s:9:\"opted_out\";i:0;s:15:\"conversion_rate\";d:0;}i:2;a:9:{s:2:\"id\";i:1;s:4:\"name\";s:23:\"جروب المعادي\";s:8:\"imported\";i:1;s:7:\"invited\";i:0;s:10:\"interested\";i:0;s:6:\"active\";i:0;s:8:\"declined\";i:0;s:9:\"opted_out\";i:0;s:15:\"conversion_rate\";d:0;}i:3;a:9:{s:2:\"id\";i:4;s:4:\"name\";s:26:\"جروب مدينة نصر\";s:8:\"imported\";i:1;s:7:\"invited\";i:0;s:10:\"interested\";i:0;s:6:\"active\";i:0;s:8:\"declined\";i:0;s:9:\"opted_out\";i:0;s:15:\"conversion_rate\";d:0;}}',1791368553),('ntham-adar-alsaykyn-cache-dashboard_last_invalidated_at','d:1791291023.990385;',2106651023),('ntham-adar-alsaykyn-cache-dashboard_latest_mutation_at','d:1791291023.990385;',2106651023),('ntham-adar-alsaykyn-cache-dashboard_summary_1_c49f9382c757f8a5884570772777b55c','a:14:{s:15:\"drivers_summary\";a:4:{s:13:\"total_drivers\";i:4;s:14:\"active_drivers\";i:0;s:17:\"opted_out_drivers\";i:0;s:9:\"by_status\";a:10:{s:3:\"new\";a:3:{s:6:\"status\";s:3:\"new\";s:5:\"label\";s:8:\"جديد\";s:5:\"count\";i:4;}s:7:\"invited\";a:3:{s:6:\"status\";s:7:\"invited\";s:5:\"label\";s:19:\"تمت الدعوة\";s:5:\"count\";i:0;}s:10:\"interested\";a:3:{s:6:\"status\";s:10:\"interested\";s:5:\"label\";s:8:\"مهتم\";s:5:\"count\";i:0;}s:25:\"questionnaire_in_progress\";a:3:{s:6:\"status\";s:25:\"questionnaire_in_progress\";s:5:\"label\";s:38:\"جاري استكمال الأسئلة\";s:5:\"count\";i:0;}s:6:\"active\";a:3:{s:6:\"status\";s:6:\"active\";s:5:\"label\";s:19:\"شغال معانا\";s:5:\"count\";i:0;}s:8:\"declined\";a:3:{s:6:\"status\";s:8:\"declined\";s:5:\"label\";s:6:\"رفض\";s:5:\"count\";i:0;}s:11:\"no_response\";a:3:{s:6:\"status\";s:11:\"no_response\";s:5:\"label\";s:11:\"لم يرد\";s:5:\"count\";i:0;}s:9:\"suspended\";a:3:{s:6:\"status\";s:9:\"suspended\";s:5:\"label\";s:21:\"معلق مؤقتاً\";s:5:\"count\";i:0;}s:7:\"blocked\";a:3:{s:6:\"status\";s:7:\"blocked\";s:5:\"label\";s:10:\"محظور\";s:5:\"count\";i:0;}s:9:\"opted_out\";a:3:{s:6:\"status\";s:9:\"opted_out\";s:5:\"label\";s:27:\"إلغاء الاشتراك\";s:5:\"count\";i:0;}}}s:14:\"orders_summary\";a:4:{s:12:\"total_orders\";i:0;s:9:\"by_status\";a:7:{s:5:\"draft\";a:3:{s:6:\"status\";s:5:\"draft\";s:5:\"label\";s:10:\"مسودة\";s:5:\"count\";i:0;}s:7:\"sending\";a:3:{s:6:\"status\";s:7:\"sending\";s:5:\"label\";s:23:\"جاري الإرسال\";s:5:\"count\";i:0;}s:7:\"filling\";a:3:{s:6:\"status\";s:7:\"filling\";s:5:\"label\";s:40:\"جاري استقبال السائقين\";s:5:\"count\";i:0;}s:6:\"filled\";a:3:{s:6:\"status\";s:6:\"filled\";s:5:\"label\";s:21:\"مكتمل العدد\";s:5:\"count\";i:0;}s:11:\"in_progress\";a:3:{s:6:\"status\";s:11:\"in_progress\";s:5:\"label\";s:21:\"قيد التنفيذ\";s:5:\"count\";i:0;}s:9:\"completed\";a:3:{s:6:\"status\";s:9:\"completed\";s:5:\"label\";s:10:\"مكتمل\";s:5:\"count\";i:0;}s:9:\"cancelled\";a:3:{s:6:\"status\";s:9:\"cancelled\";s:5:\"label\";s:8:\"ملغي\";s:5:\"count\";i:0;}}s:12:\"period_start\";s:10:\"2026-10-01\";s:10:\"period_end\";s:10:\"2026-10-31\";}s:16:\"dispatch_metrics\";a:5:{s:17:\"total_offers_sent\";i:0;s:21:\"accepted_offers_count\";i:0;s:15:\"acceptance_rate\";d:0;s:24:\"avg_time_to_fill_minutes\";d:0;s:19:\"filled_orders_count\";i:0;}s:13:\"monthly_trend\";a:6:{i:0;a:5:{s:5:\"month\";s:7:\"2026-05\";s:4:\"name\";s:13:\"مايو 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}i:1;a:5:{s:5:\"month\";s:7:\"2026-06\";s:4:\"name\";s:15:\"يونيو 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}i:2;a:5:{s:5:\"month\";s:7:\"2026-07\";s:4:\"name\";s:15:\"يوليو 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}i:3;a:5:{s:5:\"month\";s:7:\"2026-08\";s:4:\"name\";s:15:\"أغسطس 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}i:4;a:5:{s:5:\"month\";s:7:\"2026-09\";s:4:\"name\";s:17:\"سبتمبر 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}i:5;a:5:{s:5:\"month\";s:7:\"2026-10\";s:4:\"name\";s:17:\"أكتوبر 2026\";s:5:\"total\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}}s:13:\"total_drivers\";i:4;s:20:\"active_drivers_count\";i:0;s:23:\"opted_out_drivers_count\";i:0;s:17:\"drivers_by_status\";a:10:{s:3:\"new\";i:4;s:7:\"invited\";i:0;s:10:\"interested\";i:0;s:25:\"questionnaire_in_progress\";i:0;s:6:\"active\";i:0;s:8:\"declined\";i:0;s:11:\"no_response\";i:0;s:9:\"suspended\";i:0;s:7:\"blocked\";i:0;s:9:\"opted_out\";i:0;}s:17:\"orders_this_month\";a:2:{s:5:\"total\";i:0;s:9:\"by_status\";a:7:{s:5:\"draft\";i:0;s:7:\"sending\";i:0;s:7:\"filling\";i:0;s:6:\"filled\";i:0;s:11:\"in_progress\";i:0;s:9:\"completed\";i:0;s:9:\"cancelled\";i:0;}}s:21:\"offer_acceptance_rate\";d:0;s:24:\"avg_time_to_fill_minutes\";d:0;s:14:\"monthly_trends\";a:6:{i:0;a:4:{s:5:\"month\";s:7:\"2026-05\";s:5:\"label\";s:13:\"مايو 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}i:1;a:4:{s:5:\"month\";s:7:\"2026-06\";s:5:\"label\";s:15:\"يونيو 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}i:2;a:4:{s:5:\"month\";s:7:\"2026-07\";s:5:\"label\";s:15:\"يوليو 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}i:3;a:4:{s:5:\"month\";s:7:\"2026-08\";s:5:\"label\";s:15:\"أغسطس 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}i:4;a:4:{s:5:\"month\";s:7:\"2026-09\";s:5:\"label\";s:17:\"سبتمبر 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}i:5;a:4:{s:5:\"month\";s:7:\"2026-10\";s:5:\"label\";s:17:\"أكتوبر 2026\";s:12:\"total_orders\";i:0;s:16:\"completed_orders\";i:0;}}s:10:\"date_range\";a:2:{s:10:\"start_date\";N;s:8:\"end_date\";N;}s:9:\"cached_at\";s:25:\"2026-10-07T10:17:33+00:00\";}',1791368553),('ntham-adar-alsaykyn-cache-last_scheduler_run_at','s:25:\"2026-10-07T10:35:21+00:00\";',1791372921),('ntham-adar-alsaykyn-cache-sidebar_badge_counts','a:6:{s:11:\"orders_open\";i:0;s:20:\"drivers_needs_review\";i:0;s:11:\"new_drivers\";i:4;s:13:\"imports_ready\";i:0;s:19:\"failed_messages_24h\";i:0;s:14:\"health_warning\";b:0;}',1791369351);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_answers`
--

DROP TABLE IF EXISTS `driver_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `question_version_id` bigint(20) unsigned NOT NULL,
  `option_id` bigint(20) unsigned DEFAULT NULL,
  `value_text` text DEFAULT NULL,
  `value_number` decimal(10,2) DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `is_skipped` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `driver_answers_question_version_id_foreign` (`question_version_id`),
  KEY `driver_answers_option_id_foreign` (`option_id`),
  KEY `driver_answers_driver_id_question_id_is_current_index` (`driver_id`,`question_id`,`is_current`),
  KEY `driver_answers_question_id_is_current_index` (`question_id`,`is_current`),
  KEY `idx_driver_answers_q_opt_curr` (`question_id`,`option_id`,`is_current`),
  CONSTRAINT `driver_answers_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_answers_option_id_foreign` FOREIGN KEY (`option_id`) REFERENCES `question_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `driver_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_answers_question_version_id_foreign` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_answers`
--

LOCK TABLES `driver_answers` WRITE;
/*!40000 ALTER TABLE `driver_answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_group`
--

DROP TABLE IF EXISTS `driver_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  `import_id` bigint(20) unsigned DEFAULT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `driver_group_driver_id_group_id_unique` (`driver_id`,`group_id`),
  KEY `driver_group_import_id_foreign` (`import_id`),
  KEY `idx_driver_group_group_id` (`group_id`),
  CONSTRAINT `driver_group_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_group_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `whatsapp_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `driver_group_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_group`
--

LOCK TABLES `driver_group` WRITE;
/*!40000 ALTER TABLE `driver_group` DISABLE KEYS */;
INSERT INTO `driver_group` VALUES (1,1,1,1,'2026-10-06 09:50:23'),(2,2,2,1,'2026-10-06 09:50:23'),(3,3,3,1,'2026-10-06 09:50:23'),(4,4,4,1,'2026-10-06 09:50:23');
/*!40000 ALTER TABLE `driver_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_metrics`
--

DROP TABLE IF EXISTS `driver_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_metrics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `trips_completed` int(10) unsigned NOT NULL DEFAULT 0,
  `cancellations` int(10) unsigned NOT NULL DEFAULT 0,
  `no_shows` int(10) unsigned NOT NULL DEFAULT 0,
  `late_count` int(10) unsigned NOT NULL DEFAULT 0,
  `avg_rating` decimal(3,2) DEFAULT NULL,
  `ratings_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_trip_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `driver_metrics_driver_id_unique` (`driver_id`),
  KEY `driver_metrics_avg_rating_index` (`avg_rating`),
  KEY `driver_metrics_no_shows_index` (`no_shows`),
  KEY `driver_metrics_trips_completed_index` (`trips_completed`),
  KEY `driver_metrics_last_trip_at_index` (`last_trip_at`),
  CONSTRAINT `driver_metrics_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_metrics`
--

LOCK TABLES `driver_metrics` WRITE;
/*!40000 ALTER TABLE `driver_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `driver_status_histories`
--

DROP TABLE IF EXISTS `driver_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `driver_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `from` varchar(32) NOT NULL,
  `to` varchar(32) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `driver_status_histories_driver_id_foreign` (`driver_id`),
  KEY `driver_status_histories_changed_by_foreign` (`changed_by`),
  CONSTRAINT `driver_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `driver_status_histories_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `driver_status_histories`
--

LOCK TABLES `driver_status_histories` WRITE;
/*!40000 ALTER TABLE `driver_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `driver_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drivers`
--

DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drivers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(32) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `opted_out_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `questionnaire_retries` int(10) unsigned NOT NULL DEFAULT 0,
  `questionnaire_last_sent_at` timestamp NULL DEFAULT NULL,
  `questionnaire_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `review_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `drivers_phone_unique` (`phone`),
  KEY `drivers_status_index` (`status`),
  KEY `idx_drivers_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drivers`
--

LOCK TABLES `drivers` WRITE;
/*!40000 ALTER TABLE `drivers` DISABLE KEYS */;
INSERT INTO `drivers` VALUES (1,'+201012345678',NULL,'new',NULL,NULL,0,NULL,NULL,0,NULL,'2026-10-06 09:50:23','2026-10-06 09:50:23',NULL),(2,'+201198765432',NULL,'new',NULL,NULL,0,NULL,NULL,0,NULL,'2026-10-06 09:50:23','2026-10-06 09:50:23',NULL),(3,'+201234567890',NULL,'new',NULL,NULL,0,NULL,NULL,0,NULL,'2026-10-06 09:50:23','2026-10-06 09:50:23',NULL),(4,'+201555555555',NULL,'new',NULL,NULL,0,NULL,NULL,0,NULL,'2026-10-06 09:50:23','2026-10-06 09:50:23',NULL);
/*!40000 ALTER TABLE `drivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exports`
--

DROP TABLE IF EXISTS `exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`params`)),
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `path` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `error` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exports_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `exports_type_index` (`type`),
  KEY `exports_status_index` (`status`),
  KEY `exports_expires_at_index` (`expires_at`),
  CONSTRAINT `exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exports`
--

LOCK TABLES `exports` WRITE;
/*!40000 ALTER TABLE `exports` DISABLE KEYS */;
/*!40000 ALTER TABLE `exports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_rows`
--

DROP TABLE IF EXISTS `import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_id` bigint(20) unsigned NOT NULL,
  `row_number` int(10) unsigned NOT NULL,
  `raw_phone` varchar(255) DEFAULT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `normalized_phone` varchar(32) DEFAULT NULL,
  `classification` varchar(32) NOT NULL,
  `error` varchar(255) DEFAULT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `import_rows_driver_id_foreign` (`driver_id`),
  KEY `import_rows_normalized_phone_index` (`normalized_phone`),
  KEY `import_rows_classification_index` (`classification`),
  KEY `idx_import_rows_import_class_id` (`import_id`,`classification`,`id`),
  CONSTRAINT `import_rows_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_rows`
--

LOCK TABLES `import_rows` WRITE;
/*!40000 ALTER TABLE `import_rows` DISABLE KEYS */;
INSERT INTO `import_rows` VALUES (1,1,2,'01012345678','جروب المعادي','+201012345678','new',NULL,1,'2026-10-06 09:50:18'),(2,1,3,'01198765432','جروب التجمع الخامس','+201198765432','new',NULL,2,'2026-10-06 09:50:18'),(3,1,4,'01234567890','جروب أكتوبر','+201234567890','new',NULL,3,'2026-10-06 09:50:18'),(4,1,5,'01555555555','جروب مدينة نصر','+201555555555','new',NULL,4,'2026-10-06 09:50:18');
/*!40000 ALTER TABLE `import_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imports`
--

DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `imports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'uploaded',
  `counts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`counts`)),
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_user_id_foreign` (`user_id`),
  KEY `imports_status_index` (`status`),
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imports`
--

LOCK TABLES `imports` WRITE;
/*!40000 ALTER TABLE `imports` DISABLE KEYS */;
INSERT INTO `imports` VALUES (1,1,'drivers_import_template.csv','imports/88XQmZICc8JrbeZcZB8W6nEYFH9Q5KKteolv9nTq.csv','completed','{\"total\":4,\"new\":4,\"duplicate_in_file\":0,\"existing\":0,\"active\":0,\"invalid\":0}',NULL,'2026-10-06 09:50:18','2026-10-06 09:50:23');
/*!40000 ALTER TABLE `imports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_30_114651_create_personal_access_tokens_table',1),(5,'2026_09_30_120000_add_active_and_last_login_to_users_table',1),(6,'2026_09_30_120001_create_activity_logs_table',1),(7,'2026_09_30_130000_create_drivers_table',1),(8,'2026_09_30_130001_create_whatsapp_groups_table',1),(9,'2026_09_30_130002_create_imports_table',1),(10,'2026_09_30_130003_create_driver_group_table',1),(11,'2026_09_30_130004_create_driver_status_histories_table',1),(12,'2026_09_30_130005_create_import_rows_table',1),(13,'2026_09_30_140000_create_whatsapp_messages_table',1),(14,'2026_09_30_140001_create_webhook_events_table',1),(15,'2026_09_30_150000_create_questions_and_answers_tables',1),(16,'2026_09_30_150001_create_settings_and_update_drivers_table',1),(17,'2026_09_30_160000_create_work_orders_tables',1),(18,'2026_10_02_100000_create_phase6_followup_and_metrics_tables',1),(19,'2026_10_02_120000_create_exports_table',1),(20,'2026_10_02_120001_add_phase7_dashboard_indexes',1),(21,'2026_10_02_130000_add_phase8_performance_indexes',1),(22,'2026_10_02_204600_alter_whatsapp_messages_body_to_text',1),(23,'2026_10_06_140000_add_driver_filters_to_work_orders',2),(24,'2026_10_06_150000_add_custom_message_to_work_orders',3),(25,'2026_10_06_160000_create_system_filter_questions',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_options`
--

DROP TABLE IF EXISTS `question_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `value` varchar(255) NOT NULL,
  `label_ar` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_options_question_id_is_active_sort_order_index` (`question_id`,`is_active`,`sort_order`),
  CONSTRAINT `question_options_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_options`
--

LOCK TABLES `question_options` WRITE;
/*!40000 ALTER TABLE `question_options` DISABLE KEYS */;
INSERT INTO `question_options` VALUES (1,1,'pickup','بيك أب / ربع نقل',1,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(2,1,'jumbo','جامبو / نص نقل',2,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(3,1,'van','فان مغلق',3,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(4,1,'truck','نقل ثقيل / تريلا',4,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(5,4,'morning','صباحي',1,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(6,4,'evening','مسائي',2,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(7,4,'any','في أي وقت',3,1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(8,7,'experienced_only','سائق ذو خبرة سابقة (نفّذ رحلات)',1,1,'2026-10-06 11:09:54','2026-10-06 11:09:54'),(9,7,'new_only','سائق جديد (لم ينفّذ رحلات بعد)',2,1,'2026-10-06 11:09:54','2026-10-06 11:09:54');
/*!40000 ALTER TABLE `question_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_versions`
--

DROP TABLE IF EXISTS `question_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint(20) unsigned NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `text_ar` text NOT NULL,
  `type` varchar(30) NOT NULL,
  `options_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_snapshot`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_versions_question_id_version_unique` (`question_id`,`version`),
  CONSTRAINT `question_versions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_versions`
--

LOCK TABLES `question_versions` WRITE;
/*!40000 ALTER TABLE `question_versions` DISABLE KEYS */;
INSERT INTO `question_versions` VALUES (1,1,1,'ما هو نوع سيارتك؟','single_choice','[{\"value\":\"pickup\",\"label_ar\":\"\\u0628\\u064a\\u0643 \\u0623\\u0628 \\/ \\u0631\\u0628\\u0639 \\u0646\\u0642\\u0644\",\"sort_order\":1,\"is_active\":true},{\"value\":\"jumbo\",\"label_ar\":\"\\u062c\\u0627\\u0645\\u0628\\u0648 \\/ \\u0646\\u0635 \\u0646\\u0642\\u0644\",\"sort_order\":2,\"is_active\":true},{\"value\":\"van\",\"label_ar\":\"\\u0641\\u0627\\u0646 \\u0645\\u063a\\u0644\\u0642\",\"sort_order\":3,\"is_active\":true},{\"value\":\"truck\",\"label_ar\":\"\\u0646\\u0642\\u0644 \\u062b\\u0642\\u064a\\u0644 \\/ \\u062a\\u0631\\u064a\\u0644\\u0627\",\"sort_order\":4,\"is_active\":true}]','2026-10-04 08:54:40'),(2,2,1,'هل السيارة مجهزة بصندوق تبريد / تجميد؟','yes_no',NULL,'2026-10-04 08:54:40'),(3,3,1,'كم سنة خبرتك في مجال النقل والشحن؟ (أرسل رقم فقط)','number',NULL,'2026-10-04 08:54:40'),(4,4,1,'ما هي الفترة المفضلة للعمل؟','single_choice','[{\"value\":\"morning\",\"label_ar\":\"\\u0635\\u0628\\u0627\\u062d\\u064a\",\"sort_order\":1,\"is_active\":true},{\"value\":\"evening\",\"label_ar\":\"\\u0645\\u0633\\u0627\\u0626\\u064a\",\"sort_order\":2,\"is_active\":true},{\"value\":\"any\",\"label_ar\":\"\\u0641\\u064a \\u0623\\u064a \\u0648\\u0642\\u062a\",\"sort_order\":3,\"is_active\":true}]','2026-10-04 08:54:40'),(5,5,1,'ما هي المحافظات أو المناطق التي تفضل العمل بها؟ (أرسل نصاً حراً)','text',NULL,'2026-10-04 08:54:40'),(6,6,1,'جروب الواتساب التابع له السائق','single_choice',NULL,'2026-10-06 11:09:54'),(7,7,1,'مستوى خبرة السائق بالأسطول','single_choice','[{\"value\":\"experienced_only\",\"label_ar\":\"\\u0633\\u0627\\u0626\\u0642 \\u0630\\u0648 \\u062e\\u0628\\u0631\\u0629 \\u0633\\u0627\\u0628\\u0642\\u0629 (\\u0646\\u0641\\u0651\\u0630 \\u0631\\u062d\\u0644\\u0627\\u062a)\",\"sort_order\":1,\"is_active\":true},{\"value\":\"new_only\",\"label_ar\":\"\\u0633\\u0627\\u0626\\u0642 \\u062c\\u062f\\u064a\\u062f (\\u0644\\u0645 \\u064a\\u0646\\u0641\\u0651\\u0630 \\u0631\\u062d\\u0644\\u0627\\u062a \\u0628\\u0639\\u062f)\",\"sort_order\":2,\"is_active\":true}]','2026-10-06 11:09:54');
/*!40000 ALTER TABLE `question_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `is_filter` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `type` varchar(30) NOT NULL,
  `current_version_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `questions_key_unique` (`key`),
  KEY `questions_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `questions_current_version_id_foreign` (`current_version_id`),
  CONSTRAINT `questions_current_version_id_foreign` FOREIGN KEY (`current_version_id`) REFERENCES `question_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,'vehicle_type',1,1,1,1,0,'single_choice',1,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(2,'has_refrigeration',2,1,1,1,0,'yes_no',2,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(3,'years_experience',3,1,1,0,0,'number',3,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(4,'daily_availability',4,1,1,1,0,'single_choice',4,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(5,'preferred_routes',5,1,0,0,0,'text',5,'2026-10-04 08:54:40','2026-10-04 08:54:40'),(6,'whatsapp_group',90,0,0,1,0,'single_choice',6,'2026-10-06 11:09:54','2026-10-06 11:09:54'),(7,'driver_experience',91,0,0,1,0,'single_choice',7,'2026-10-06 11:09:54','2026-10-06 11:09:54');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('gtvv069TfbhiXZObcEyaJlyXtrgU2nd94g6G9s7T',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWGU4NUtXSnJHbzh1THh5cnVWa0ZVMUtmZ2dnWW9mcFpXME9QVXpoRCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcGkvdjEvYXV0aC9tZSI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1791368221),('yvjOe2iJykX2dsnPlQ1dQYThuWd0dWToT18TQE55',1,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiaTlvajBZUUNQNFhHR2lLNXRzRm5yME5DU3BWTXZQRmhTdWJXZkMycSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NTM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hcGkvdjEvd2hhdHNhcHAvcHJvdmlkZXItc3RhdHVzIjtzOjU6InJvdXRlIjtOO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MTc6InBhc3N3b3JkX2hhc2hfd2ViIjtzOjY0OiJkYjljYTQ5NjUyYTJlY2YyZTFlOTVmMDgxZmI4ODViMTlkOGQzM2EyZWMyODkwYzA4Mzc1MWY2ZDFmY2UwOTUzIjt9',1791369838);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'string',
  `description_ar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('invite_batch_size','50','integer','حجم دفعة إرسال الدعوات للسائقين الجدد','2026-10-04 08:54:40','2026-10-04 08:54:40'),('invite_no_response_days','3','integer','عدد الأيام لانتهاء مهلة الدعوة وتحويل السائق إلى لم يرد','2026-10-04 08:54:40','2026-10-04 08:54:40'),('questionnaire_reminder_hours','24','integer','عدد الساعات قبل إرسال رسالة تذكير بالاستبيان للسائق','2026-10-04 08:54:40','2026-10-04 08:54:40'),('questionnaire_timeout_days','3','integer','عدد الأيام قبل تحويل السائق إلى حالة لم يرد في حال عدم إكمال الاستبيان','2026-10-04 08:54:40','2026-10-04 08:54:40'),('send_rate_per_minute','30','integer','أقصى معدل إرسال رسائل في الدقيقة للواتساب','2026-10-04 08:54:40','2026-10-04 08:54:40');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'مدير النظام الأول','admin@example.com',NULL,'$2y$12$HI0Um4YMEHXxQPXjVnAIT.GSvJzPwwzcxNd3aidEi9jSq69szfKF.',1,'2026-10-07 07:17:32',NULL,'2026-10-04 08:54:40','2026-10-07 07:17:32'),(2,'مدير النظام','admin@erp.zimpact-eg.com',NULL,'$2y$12$y3fqdSvAX4KvNPVK758dgey5wNxKItr4.r8Q3CNBH0hJatyb3OmmK',1,NULL,NULL,'2026-10-04 08:54:54','2026-10-04 08:54:54');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_events`
--

DROP TABLE IF EXISTS `webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) NOT NULL DEFAULT 'whatsapp',
  `external_id` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_events_external_id_unique` (`external_id`),
  KEY `webhook_events_provider_external_id_index` (`provider`,`external_id`),
  KEY `idx_webhook_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_events`
--

LOCK TABLES `webhook_events` WRITE;
/*!40000 ALTER TABLE `webhook_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `webhook_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_groups`
--

DROP TABLE IF EXISTS `whatsapp_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_groups_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_groups`
--

LOCK TABLES `whatsapp_groups` WRITE;
/*!40000 ALTER TABLE `whatsapp_groups` DISABLE KEYS */;
INSERT INTO `whatsapp_groups` VALUES (1,'جروب المعادي','2026-10-06 09:50:23','2026-10-06 09:50:23'),(2,'جروب التجمع الخامس','2026-10-06 09:50:23','2026-10-06 09:50:23'),(3,'جروب أكتوبر','2026-10-06 09:50:23','2026-10-06 09:50:23'),(4,'جروب مدينة نصر','2026-10-06 09:50:23','2026-10-06 09:50:23');
/*!40000 ALTER TABLE `whatsapp_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_messages`
--

DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `driver_id` bigint(20) unsigned NOT NULL,
  `direction` enum('out','in') NOT NULL DEFAULT 'out',
  `provider_message_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `template_name` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `body` text DEFAULT NULL,
  `status` enum('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `error_code` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_messages_provider_message_id_unique` (`provider_message_id`),
  KEY `whatsapp_messages_driver_id_direction_index` (`driver_id`,`direction`),
  KEY `whatsapp_messages_driver_id_status_index` (`driver_id`,`status`),
  KEY `whatsapp_messages_status_index` (`status`),
  KEY `idx_whatsapp_messages_created_at` (`created_at`),
  CONSTRAINT `whatsapp_messages_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_messages`
--

LOCK TABLES `whatsapp_messages` WRITE;
/*!40000 ALTER TABLE `whatsapp_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_events`
--

DROP TABLE IF EXISTS `work_order_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `driver_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_events_user_id_foreign` (`user_id`),
  KEY `work_order_events_driver_id_foreign` (`driver_id`),
  KEY `work_order_events_work_order_id_created_at_index` (`work_order_id`,`created_at`),
  KEY `work_order_events_action_index` (`action`),
  CONSTRAINT `work_order_events_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_events_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_events`
--

LOCK TABLES `work_order_events` WRITE;
/*!40000 ALTER TABLE `work_order_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_filters`
--

DROP TABLE IF EXISTS `work_order_filters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_filters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint(20) unsigned NOT NULL,
  `question_id` bigint(20) unsigned NOT NULL,
  `operator` varchar(16) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_filters_question_id_foreign` (`question_id`),
  KEY `work_order_filters_work_order_id_question_id_index` (`work_order_id`,`question_id`),
  CONSTRAINT `work_order_filters_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_filters_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_filters`
--

LOCK TABLES `work_order_filters` WRITE;
/*!40000 ALTER TABLE `work_order_filters` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_filters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_recipients`
--

DROP TABLE IF EXISTS `work_order_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_order_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint(20) unsigned NOT NULL,
  `driver_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `sequence_number` int(10) unsigned DEFAULT NULL,
  `stage_batch` int(10) unsigned NOT NULL DEFAULT 1,
  `sent_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `outcome` varchar(32) DEFAULT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `outcome_notes` text DEFAULT NULL,
  `outcome_recorded_at` datetime DEFAULT NULL,
  `outcome_recorded_by` bigint(20) unsigned DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_recipients_work_order_id_driver_id_unique` (`work_order_id`,`driver_id`),
  KEY `work_order_recipients_driver_id_foreign` (`driver_id`),
  KEY `work_order_recipients_work_order_id_status_index` (`work_order_id`,`status`),
  KEY `work_order_recipients_outcome_recorded_by_foreign` (`outcome_recorded_by`),
  KEY `work_order_recipients_work_order_id_outcome_index` (`work_order_id`,`outcome`),
  KEY `idx_work_order_recipients_sent_at` (`sent_at`),
  CONSTRAINT `work_order_recipients_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_recipients_outcome_recorded_by_foreign` FOREIGN KEY (`outcome_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_recipients_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_recipients`
--

LOCK TABLES `work_order_recipients` WRITE;
/*!40000 ALTER TABLE `work_order_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_orders`
--

DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `cargo_type` varchar(255) DEFAULT NULL,
  `pay_amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `custom_message` text DEFAULT NULL,
  `trips_needed` int(10) unsigned NOT NULL DEFAULT 1,
  `backup_needed` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'draft',
  `confirmed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `backup_count` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `filled_at` datetime DEFAULT NULL,
  `send_strategy` varchar(32) NOT NULL DEFAULT 'all',
  `batch_size` int(10) unsigned DEFAULT NULL,
  `batch_interval_minutes` int(10) unsigned DEFAULT NULL,
  `exclude_same_day_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `min_avg_rating` decimal(3,2) DEFAULT NULL,
  `max_no_shows` int(10) unsigned DEFAULT NULL,
  `last_trip_within_days` int(10) unsigned DEFAULT NULL,
  `include_unrated` tinyint(1) NOT NULL DEFAULT 0,
  `target_group_id` bigint(20) unsigned DEFAULT NULL,
  `driver_experience` varchar(32) DEFAULT 'all',
  `cancellation_reason` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_orders_code_unique` (`code`),
  KEY `work_orders_created_by_foreign` (`created_by`),
  KEY `work_orders_scheduled_at_index` (`scheduled_at`),
  KEY `work_orders_status_index` (`status`),
  KEY `idx_work_orders_created_at` (`created_at`),
  KEY `idx_work_orders_status_filled_at` (`status`,`filled_at`),
  KEY `work_orders_target_group_id_foreign` (`target_group_id`),
  CONSTRAINT `work_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_target_group_id_foreign` FOREIGN KEY (`target_group_id`) REFERENCES `whatsapp_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_orders`
--

LOCK TABLES `work_orders` WRITE;
/*!40000 ALTER TABLE `work_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_orders` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-08 22:22:45
