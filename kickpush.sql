/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.3.2-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: kickpush
-- ------------------------------------------------------
-- Server version	12.3.2-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `audits`
--

DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(255) NOT NULL,
  `auditable_type` varchar(255) NOT NULL,
  `auditable_id` bigint(20) unsigned NOT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(1023) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audits`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `audits` WRITE;
/*!40000 ALTER TABLE `audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `audits` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `business_expenses`
--

DROP TABLE IF EXISTS `business_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `financial_year_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `incurred_on` date DEFAULT NULL,
  `tax_deductible` tinyint(1) NOT NULL DEFAULT 0,
  `deductible_percentage` decimal(5,2) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_original_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_expenses_user_id_index` (`user_id`),
  KEY `business_expenses_incurred_on_index` (`incurred_on`),
  KEY `business_expenses_tax_deductible_index` (`tax_deductible`),
  KEY `business_expenses_financial_year_id_foreign` (`financial_year_id`),
  KEY `business_expenses_team_id_incurred_on_index` (`team_id`,`incurred_on`),
  CONSTRAINT `business_expenses_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_expenses_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `business_expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `business_expenses`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `business_expenses` WRITE;
/*!40000 ALTER TABLE `business_expenses` DISABLE KEYS */;
INSERT INTO `business_expenses` VALUES
(1,'2026-08-19 07:48:39','2026-08-19 07:53:31',1,1,1,'Mobile data','Mobile data plan reciept',20.00,'2026-06-03',1,100.00,'business-expenses/receipts/WYkQ6uK2EKgc4bjooEOHTRJoELjDftvkgQAkvEUK.pdf','VisaExtension60Days.pdf');
/*!40000 ALTER TABLE `business_expenses` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_user_id_name_index` (`user_id`,`name`),
  KEY `clients_team_id_name_index` (`team_id`,`name`),
  CONSTRAINT `clients_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES
(1,'2026-08-12 07:38:47','2026-09-16 04:48:05',1,1,'The Physio Place','albert@thephysioplace.com.au','AUD',66.00,NULL),
(2,'2026-08-25 06:44:48','2026-08-25 06:44:48',1,1,'Kabushka','hello@kabushka.io','NZD',50.00,NULL);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `financial_years`
--

DROP TABLE IF EXISTS `financial_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `start_year` smallint(5) unsigned NOT NULL,
  `end_year` smallint(5) unsigned NOT NULL,
  `label` varchar(32) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `financial_years_user_id_start_year_unique` (`user_id`,`start_year`),
  KEY `financial_years_team_id_start_year_index` (`team_id`,`start_year`),
  CONSTRAINT `financial_years_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_years_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_years`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `financial_years` WRITE;
/*!40000 ALTER TABLE `financial_years` DISABLE KEYS */;
INSERT INTO `financial_years` VALUES
(1,1,1,2026,2027,'2026/2027','2026-04-01','2027-03-31','2026-08-14 05:00:20','2026-08-19 08:11:47'),
(2,2,1,2026,2027,'2026/2027','2026-04-01','2027-03-31','2026-08-18 04:52:35','2026-08-18 04:52:35'),
(7,7,3,2026,2027,'2026/2027','2026-04-01','2027-03-31','2026-08-27 07:05:21','2026-08-27 07:05:21'),
(8,8,4,2026,2027,'2026/2027','2026-04-01','2027-03-31','2026-08-27 07:06:28','2026-08-27 07:06:28');
/*!40000 ALTER TABLE `financial_years` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `conversion_source_currency` varchar(3) DEFAULT NULL,
  `conversion_target_currency` varchar(3) DEFAULT NULL,
  `conversion_rate` decimal(18,8) DEFAULT NULL,
  `conversion_rate_fetched_at` timestamp NULL DEFAULT NULL,
  `financial_year_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `issued_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `discount_type` varchar(20) DEFAULT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_user_id_status_index` (`user_id`,`status`),
  KEY `invoices_client_id_foreign` (`client_id`),
  KEY `invoices_user_id_client_id_index` (`user_id`,`client_id`),
  KEY `invoices_financial_year_id_foreign` (`financial_year_id`),
  KEY `invoices_team_id_status_index` (`team_id`,`status`),
  CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_financial_year_id_foreign` FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES
(1,'2026-08-12 06:46:40','2026-08-14 06:08:41',1,1,1,'AUD','NZD',1.20110000,NULL,1,'INV-20260812-064640-HLXU','paid','2026-08-14 05:51:13','2026-08-28 05:56:35','2026-08-14 06:08:41',NULL,NULL,0.00),
(6,'2026-08-14 04:10:47','2026-08-17 01:23:05',1,1,1,'AUD','NZD',1.20200000,NULL,1,'6','paid','2026-08-14 23:27:30','2026-08-28 23:38:41','2026-08-17 01:23:05',NULL,NULL,0.00),
(7,'2026-08-17 05:01:08','2026-09-16 07:43:12',1,1,1,'AUD','NZD',1.24136000,'2026-09-16 04:51:56',1,'7','paid','2026-09-16 04:39:00',NULL,'2026-09-16 07:43:12',NULL,NULL,0.00),
(8,'2026-08-25 07:45:01','2026-08-25 07:45:01',1,1,2,NULL,NULL,NULL,NULL,1,'8','draft',NULL,NULL,NULL,NULL,NULL,0.00),
(9,'2026-09-16 04:54:52','2026-09-16 04:54:52',1,1,1,NULL,NULL,NULL,NULL,1,'9','draft',NULL,NULL,NULL,NULL,NULL,0.00);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `line_items`
--

DROP TABLE IF EXISTS `line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `line_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_invoice_id_index` (`invoice_id`),
  CONSTRAINT `expenses_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `line_items`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `line_items` WRITE;
/*!40000 ALTER TABLE `line_items` DISABLE KEYS */;
INSERT INTO `line_items` VALUES
(3,'2026-09-16 04:35:40','2026-09-16 04:35:40',8,'tes',NULL,34.00),
(4,'2026-09-16 04:35:57','2026-09-16 04:35:57',8,'few',NULL,45.00);
/*!40000 ALTER TABLE `line_items` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2025_07_25_014224_add_two_factor_columns_to_users_table',1),
(5,'2025_07_25_014237_create_personal_access_tokens_table',1),
(6,'2025_07_25_014237_create_teams_table',1),
(7,'2025_07_25_014238_create_team_user_table',1),
(8,'2025_07_25_014239_create_team_invitations_table',1),
(9,'2025_09_06_080614_create_days_table',1),
(10,'2025_09_06_081440_create_submissions_table',1),
(11,'2025_09_10_072418_create_vote_counts_table',1),
(12,'2025_09_12_054909_add_reenable_request_fields_to_user',1),
(13,'2025_09_13_021441_create_submission_vote_count_pivot_table',1),
(14,'2025_09_14_010806_update_users_table',1),
(15,'2025_09_16_083105_create_prize_pools_table',1),
(16,'2025_09_20_080108_create_comments_table',1),
(17,'2025_09_20_081312_create_comment_likes_table',1),
(18,'2025_09_20_081356_create_comment_dislikes_table',1),
(19,'2025_10_04_013450_create_topics_table',1),
(20,'2025_10_04_100222_add_winner_flags_to_submissions',1),
(21,'2025_10_12_011424_create_audits_table',1),
(22,'2025_10_12_015627_create_behaviour_reports_table',1),
(23,'2025_10_19_004348_update_users_table',1),
(24,'2025_10_27_003053_add_payment_details_to_submission_modal',1),
(25,'2025_10_27_033248_update_prize_pools_total',1),
(26,'2025_11_02_072337_update_for_transfers',1),
(27,'2025_11_23_075633_create_contact_submissions_table',1),
(28,'2025_12_05_232540_add_notification_flags_to_user',1),
(29,'2026_08_12_000000_create_timer_sessions_table',2),
(30,'2026_08_12_000100_create_invoices_table',3),
(31,'2026_08_12_000200_add_invoice_id_to_timer_sessions_table',3),
(32,'2026_08_12_000300_remove_device_id_from_timer_sessions_table',4),
(33,'2026_08_12_000500_create_expenses_table',4),
(34,'2026_08_12_000600_add_hourly_rate_to_users_table',5),
(35,'2026_08_12_000700_add_due_at_to_invoices_table',6),
(36,'2026_08_12_000800_create_clients_table',7),
(37,'2026_08_12_000900_add_client_id_to_invoices_table',7),
(38,'2026_08_14_000100_add_pause_fields_to_timer_sessions_table',8),
(39,'2026_08_14_000900_add_tax_rates_to_users_table',9),
(40,'2026_08_14_001000_create_financial_years_and_assign_to_invoices',10),
(41,'2026_08_14_001200_add_payment_information_to_users_table',11),
(42,'2026_08_14_001300_add_bsb_code_to_users_table',11),
(43,'2026_08_16_000900_add_currency_to_clients_table',12),
(44,'2026_08_16_001000_add_hourly_rate_to_clients_table',13),
(45,'2026_08_16_001100_add_conversion_fields_to_invoices_table',14),
(46,'2026_08_17_000100_create_user_additional_taxes_table',15),
(47,'2026_08_17_000200_add_currency_to_user_additional_taxes_table',16),
(48,'2026_08_19_000100_create_business_expenses_table',17),
(49,'2026_08_19_000200_add_receipt_fields_to_business_expenses_table',18),
(50,'2026_08_19_000300_add_deductible_percentage_to_business_expenses_table',19),
(51,'2026_08_19_000500_add_financial_year_id_to_business_expenses_table',20),
(52,'2026_08_19_000600_add_discount_fields_to_invoices_table',21),
(56,'2026_08_21_000100_create_projects_table',22),
(57,'2026_08_21_000200_create_tasks_table',22),
(58,'2026_08_21_000300_add_task_id_to_timer_sessions_table',22),
(59,'2026_08_21_000400_add_deleted_at_to_timer_sessions_table',23),
(60,'2026_08_24_000100_add_team_id_to_billing_entities',24),
(61,'2026_08_24_000200_create_default_shared_team',25),
(62,'2026_08_24_000300_assign_existing_records_to_default_shared_team',26),
(63,'2026_08_24_000400_add_team_id_to_user_additional_taxes_table',27),
(64,'2026_08_24_000500_rename_user_additional_taxes_table',27),
(65,'2026_08_24_000600_add_payment_information_to_teams_table',28),
(66,'2026_08_27_000700_add_account_type_to_users_table',29),
(67,'2026_08_28_000100_create_project_notes_table',30),
(68,'2026_08_28_000200_add_visibility_to_project_notes_table',31),
(69,'2026_09_06_000100_add_active_started_at_to_timer_sessions_table',32),
(70,'2026_09_06_000200_add_timezone_to_teams_table',33),
(71,'2026_09_06_000300_add_billing_snapshot_to_timer_sessions_table',34),
(72,'2026_09_06_000400_add_identity_snapshot_to_timer_sessions_table',35),
(73,'2026_09_16_000100_rename_expenses_to_line_items_table',36),
(74,'2026_08_12_000500_create_line_items_table',37),
(75,'2026_09_16_000200_drop_unused_expenses_table',37);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES
('hello@email.com','$2y$12$fwNN/4Af0t6bG7OtnpHHX.C/TYDXzzyXx.0KRYRKLyFUsF.vUMJ3e','2026-08-18 04:53:02');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `project_notes`
--

DROP TABLE IF EXISTS `project_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `visibility` varchar(20) NOT NULL DEFAULT 'team',
  `body` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `project_notes_user_id_foreign` (`user_id`),
  KEY `project_notes_project_id_created_at_index` (`project_id`,`created_at`),
  KEY `project_notes_team_id_project_id_index` (`team_id`,`project_id`),
  KEY `project_notes_project_id_visibility_created_at_index` (`project_id`,`visibility`,`created_at`),
  CONSTRAINT `project_notes_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_notes_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_notes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `project_notes` WRITE;
/*!40000 ALTER TABLE `project_notes` DISABLE KEYS */;
INSERT INTO `project_notes` VALUES
(1,'2026-08-28 05:10:15','2026-08-28 05:10:15',1,1,11,'team','Password for admin nookal account is funny.'),
(2,'2026-08-28 05:12:16','2026-08-28 05:12:16',1,1,1,'team','things to remember to do\n	- handle remittance emails, set sessions as paid in nookle\n		- mostly finding by dates, give or take two days, if no name provided in email, use invoice # to identify.\n\n	- send out invoices on a two day rolling basis\n		- either send out directly from nookal,or in some instances need to find invoices@... email or accounts@... email to send to, depending on the gov department paying for it\n		- if not directly through nookal, then through HICAPS, assign via customer name, should then generate email automatically with invoice\n		- some others need to be done through TYPO but haven\'t walked through that yet\n		- some are cash, in which case, leave a note on calendar for alb to remnd him to pay himself, and record on invoice that it was cash and is paid already.\n		- some are directly paid for by themselves, either send email to private email address or paid for at pos in clinic\n		- if sending invoice for Jan Phippard it needs to have notes provided on the invoice, see last invoice to her for details\n\n	- assign documents in TPP files dir on drive to related user on nookle\n\n	- pilates classes notes\n		- need to copy over clinical notes on pilates classes from full class notes to each individual client account\n		- dont need to do Debbie florenstein notes\n\n\n	- Tally up sessions for the week, half hour billing sessions count as one\n\n	- bill COC when necessary on same invoice if possible\n		- no such thing as COC for TAC clients, so if lily accidently expects that, it\'s a mistake\n\n	- EPC in tyro\n		- alb ends paper with details of client\n		- medicare in tyro, field for initials + date e.g rg3226 (3/2/26)\n		- who will claim, patient\n		- referral, doctor number off of sheet, referatl date on sheet\n		- service item type physio\n		- payment other\n		- make not on diary \"b25 on tyro\"\n\n\n	- DVA for veterans\n		- proda website\n		- HPOS-> Claims -> DVA webclaim\n		- 484004 Location 4W \n		- Item number not populated, so need write PH20 and $75.10 (always will be these values)\n		- Not duplicate\n		- submit and make b25 note\n		- payment made directly to alb so no remittance email but he will give a list of payments every couple weeks and we treat that like remittance email\n\n\n	 - Payments Plus - TAC gym memberships 3 month\n		- find remmitance via TAX payment plus platform, do once a month or so\n		- if sending invoices, tac will go to invoices@ whereas if WS will be to claim manager\n			-RE2700 code for getting into gym remittance emails for WS'),
(3,'2026-08-28 05:42:15','2026-08-28 05:42:15',1,1,1,'private','This is a private note that my employer or employees should not be able to see respectively'),
(4,'2026-09-07 05:19:37','2026-09-12 23:51:50',1,3,1,'team','- have AI compare the code with the explicit intention of finding overlapping functionality, eg the main-session has logic that isn\'t being reused in the timesheet but could be (example)\n- login redirect issue\n- email validation\n- page titles to show in browser tabs\n- ask AI if Stancl Tenancy bootstrapping should be used instead of the per-team structure it currently uses (i think it might make more sense for dharta because some users can exists in more than one tenant (organization))\n- explore the idea of a walk-through simulation for testing daylight savings time changes and other possible edge cases for this code base\n- add notes to sessions .eg. \"Debugging\" or \"Updating email signatures\"\n- Check over codebase for \"harmless clutter: stopinlineTimer in Show.vue for example has been left but is no longer wired or used. These refactors need to happen and be tested'),
(6,'2026-09-12 23:53:11','2026-09-12 23:57:48',1,3,1,'team','FEATURES\n\n- test suite, walk through simulation\n\n- project budgets (fixed fee?) to compare hours spent against fee/budget charged\n\n- non-billable projects/tasks (time spent on admin that can\'t be charged to client) for overall business analysis\n\n- tool tracking (teams setting to dictate what kind of business, if tradie, show tool tracking feature?)\n\n- employee charge out rates, there rates might need to be per client per employee (currently no care for currencies when setting employee rates)\n\n- maybe break taxes and allocations down into before and after invoice payment\n\n- create tests to ensure no data is shared between teams/businesses\n\n- decide if per client database & domain is necessary or if best option'),
(7,'2026-09-16 04:53:09','2026-09-16 04:53:09',1,3,1,'team','BUGS\n\n- the hourly rate pulled through to invoice is 66.02... where is that 2 cents coming from?');
/*!40000 ALTER TABLE `project_notes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_client_id_name_unique` (`client_id`,`name`),
  KEY `projects_user_id_foreign` (`user_id`),
  KEY `projects_client_id_is_active_index` (`client_id`,`is_active`),
  KEY `projects_team_id_name_index` (`team_id`,`name`),
  CONSTRAINT `projects_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES
(1,'2026-08-21 05:52:15','2026-08-21 05:52:15',1,1,1,'TPP Admin Work','All general admin work',1),
(2,'2026-08-21 05:53:11','2026-08-21 05:53:11',1,1,1,'Website Work','Any design/dev work on tpp website',1),
(3,'2026-08-25 06:47:00','2026-08-25 06:47:16',1,1,2,'Platform Build',NULL,1);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES
('7CLFW5Kc7vfNhGXxEpu38c7nKI0jUUw3fzBxPuz9',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWTNiaVlCSmJ6cmFGOWlQdjlIUHczMWFMakI3T0lVZW1kb0JESU43OSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789791052),
('iq0Te8xV2JRlObsL68kYPIOW8CSDOMrYWESwA081',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiVDFxTmxidlNUWG0xV2xXS29KQ0MxZGFWZTBTUzB0OXcybkRqdDVLZSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNDoiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC9pbnZvaWNlcyI7czo1OiJyb3V0ZSI7czoxNDoiaW52b2ljZXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789704244),
('IrBcX0YgKr4SozFqqCaZV9Krhhz49Aqf4C8NHPYH',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiSVlkd0hPTWwwRGJrVzBNZU03c2RKZDZqcnZTWjJGdVd4VHhaSDhjSCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNToiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC90aW1lc2hlZXQiO3M6NToicm91dGUiO3M6MTY6InRpbWVzaGVldHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790112760),
('Mble1M6vGZQKOq4D1uqEEaDwofvxvTOxnuO0wbEt',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoicVUyelJTQ1hTYTl4SDZpS25lc0Q2U25JREJTVmJRbU84SURrSVQ3YyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNToiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC90aW1lc2hlZXQiO3M6NToicm91dGUiO3M6MTY6InRpbWVzaGVldHMuaW5kZXgiO319',1789616055),
('Mvr1qnPpHVCAYYNtMeFUq7apq1rbgA8ziLuoKaLN',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiWjl4dDlCM3dpYkF3bVR3WGtIaDY5NTZFdDVBa0o0cTRvUVB3c3pzYSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNDoiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC9pbnZvaWNlcyI7czo1OiJyb3V0ZSI7czoxNDoiaW52b2ljZXMuaW5kZXgiO31zOjIxOiJwYXNzd29yZF9oYXNoX3NhbmN0dW0iO3M6NjQ6IjY4Zjk5MmQ0OTk0ZTFhYThiNDlkNmFlMGQ3OWNjOGE1NDEyOTc2OTU3NTFmM2QwNTA0NjE4MjlhMTNjNDUzNGQiO30=',1789544594),
('PEXiCqM00d7J61K8KNlOqVdHA29XsqantVmtrtah',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiRXQ2blRlT1RzV0pTME9yM1UyVEVOQzVIQmc4d0Z1NVdzaW9WT0IzWSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNToiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC90aW1lc2hlZXQiO3M6NToicm91dGUiO3M6MTY6InRpbWVzaGVldHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790049205),
('TJH6Q4y5SfmQrT0qgCXGpAmC8M1FGYVWmq2MYNEF',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiSjVVQm5VS0VFMEs1WTNLQ3lwQTdPTkc1eFlKc0JISUVWMDdXSDdsMCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNToiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC90aW1lc2hlZXQiO3M6NToicm91dGUiO3M6MTY6InRpbWVzaGVldHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1789781858),
('V32JSPM0YwMxLbhq79dc2CBo5nDjQ0UHjxbd0nW5',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiMmZ5SE1ja2Y4TTRGclo5anpiZ2lpd0dtalhoeW9RcFd0a2pQdDJROCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czo0ODoiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC9pbnZvaWNlcy83L3RheC1zdW1tYXJ5IjtzOjU6InJvdXRlIjtzOjE5OiJpbnZvaWNlcy50YXhTdW1tYXJ5Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789963466),
('vyO2GKrM9QDlC0wxqEx7fpUyR65FlnPVQOVeEFEg',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWW12MWZDTktRZXkxUkQyRW1tU0ZHVVB4cWZHTDE5a0hWakVUVUVEYyI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790223803),
('xDzz8HNrLIK0IWE3JSGrG9N3YyBDGW8zgwTf9Cmd',1,'172.18.0.2','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiYjBXSGt5d1VJVVhwTmZCdmpnazBuZlozTGZCTFc5TVYwQUpKQWJwaiI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNToiaHR0cDovL2tpY2twdXNoLmxvY2FsaG9zdC90aW1lc2hlZXQiO3M6NToicm91dGUiO3M6MTY6InRpbWVzaGVldHMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790138816);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_project_id_name_unique` (`project_id`,`name`),
  KEY `tasks_client_id_is_active_index` (`client_id`,`is_active`),
  KEY `tasks_client_id_is_default_index` (`client_id`,`is_default`),
  KEY `tasks_team_id_name_index` (`team_id`,`name`),
  CONSTRAINT `tasks_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES
(1,'2026-08-21 05:52:39','2026-08-21 05:53:48',1,1,1,'Admin work',NULL,1,1),
(2,'2026-08-21 05:53:36','2026-08-21 05:53:48',1,2,1,'Development',NULL,1,0),
(3,'2026-08-21 05:53:46','2026-08-21 05:53:48',1,2,1,'Design',NULL,1,0),
(4,'2026-08-25 06:47:45','2026-08-25 06:47:45',2,3,1,'Software Development',NULL,1,0),
(5,'2026-08-25 06:47:57','2026-08-25 06:47:57',2,3,1,'UI Design',NULL,1,0);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `team_additional_taxes`
--

DROP TABLE IF EXISTS `team_additional_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_additional_taxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `category` varchar(20) NOT NULL,
  `value_type` varchar(20) NOT NULL,
  `value` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_additional_taxes_user_id_position_index` (`user_id`,`position`),
  KEY `user_additional_taxes_user_id_currency_index` (`user_id`,`currency`),
  KEY `user_additional_taxes_team_id_position_index` (`team_id`,`position`),
  CONSTRAINT `user_additional_taxes_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_additional_taxes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_additional_taxes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `team_additional_taxes` WRITE;
/*!40000 ALTER TABLE `team_additional_taxes` DISABLE KEYS */;
INSERT INTO `team_additional_taxes` VALUES
(23,1,1,'ACC','levy','percentage',2.00,NULL,0,'2026-08-20 02:45:25','2026-08-20 02:45:25'),
(24,1,1,'Invest Now - S&P','allocation','fixed',250.00,'NZD',1,'2026-08-20 02:45:25','2026-08-20 02:45:25'),
(25,1,1,'Joy\'s SL','allocation','fixed',215.00,'NZD',2,'2026-08-20 02:45:25','2026-08-20 02:45:25'),
(26,1,1,'Rental Tax Bill','allocation','fixed',183.00,'NZD',3,'2026-08-20 02:45:25','2026-08-20 02:45:25'),
(27,1,1,'Income Tax','tax','percentage',30.00,NULL,4,'2026-08-20 02:45:25','2026-08-20 02:45:25'),
(28,1,1,'Student Loan','tax','percentage',0.00,NULL,5,'2026-08-20 02:45:25','2026-08-20 02:45:25');
/*!40000 ALTER TABLE `team_additional_taxes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `team_invitations`
--

DROP TABLE IF EXISTS `team_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_invitations_team_id_email_unique` (`team_id`,`email`),
  CONSTRAINT `team_invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_invitations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `team_invitations` WRITE;
/*!40000 ALTER TABLE `team_invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_invitations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `team_user`
--

DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_user`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `team_user` WRITE;
/*!40000 ALTER TABLE `team_user` DISABLE KEYS */;
INSERT INTO `team_user` VALUES
(1,1,1,'admin','2026-08-24 06:53:40','2026-08-24 06:58:01'),
(2,1,2,'editor','2026-08-24 06:53:40','2026-08-24 06:58:01'),
(4,1,4,'admin','2026-08-27 06:03:36','2026-08-27 06:03:36'),
(5,4,9,'admin','2026-08-27 07:19:31','2026-08-27 07:19:31'),
(6,4,10,'editor','2026-08-27 07:21:19','2026-08-27 07:21:19'),
(7,1,11,'employee','2026-08-27 07:34:12','2026-08-27 07:34:12');
/*!40000 ALTER TABLE `team_user` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `personal_team` tinyint(1) NOT NULL,
  `bank_account_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bsb_code` varchar(32) DEFAULT NULL,
  `bank_account_number` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teams_user_id_index` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES
(1,1,'Default Shared Team','Pacific/Auckland',0,'Raymond Geuze',NULL,'774001','235471304','2026-08-24 06:53:40','2026-09-06 07:17:40'),
(2,6,'Fixed Trading','UTC',0,NULL,NULL,NULL,NULL,'2026-08-27 06:58:22','2026-08-27 06:58:22'),
(3,7,'Jimbo\'s','UTC',0,NULL,NULL,NULL,NULL,'2026-08-27 07:02:55','2026-08-27 07:02:55'),
(4,8,'Not Jimbo\'s','UTC',0,NULL,NULL,NULL,NULL,'2026-08-27 07:06:23','2026-08-27 07:06:23');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `timer_sessions`
--

DROP TABLE IF EXISTS `timer_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `timer_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `user_name_snapshot` varchar(255) DEFAULT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `task_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `task_name_snapshot` varchar(255) DEFAULT NULL,
  `project_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `project_name_snapshot` varchar(255) DEFAULT NULL,
  `client_id_snapshot` bigint(20) unsigned DEFAULT NULL,
  `client_name_snapshot` varchar(255) DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `active_started_at` timestamp NULL DEFAULT NULL,
  `paused_at` timestamp NULL DEFAULT NULL,
  `stopped_at` timestamp NULL DEFAULT NULL,
  `accumulated_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `duration_seconds` int(10) unsigned DEFAULT NULL,
  `hourly_rate_snapshot` decimal(10,2) DEFAULT NULL,
  `hourly_rate_source` varchar(16) DEFAULT NULL,
  `currency_snapshot` varchar(3) DEFAULT NULL,
  `rate_snapshot_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `timer_sessions_user_id_stopped_at_index` (`user_id`,`stopped_at`),
  KEY `timer_sessions_device_id_stopped_at_index` (`stopped_at`),
  KEY `timer_sessions_invoice_id_foreign` (`invoice_id`),
  KEY `timer_sessions_user_id_paused_at_index` (`user_id`,`paused_at`),
  KEY `timer_sessions_task_id_foreign` (`task_id`),
  KEY `timer_sessions_user_id_task_id_index` (`user_id`,`task_id`),
  KEY `timer_sessions_deleted_at_stopped_at_index` (`deleted_at`,`stopped_at`),
  KEY `timer_sessions_team_id_stopped_at_index` (`team_id`,`stopped_at`),
  KEY `timer_sessions_project_snapshot_started_index` (`team_id`,`project_id_snapshot`,`started_at`),
  CONSTRAINT `timer_sessions_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `timer_sessions_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `timer_sessions_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `timer_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timer_sessions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `timer_sessions` WRITE;
/*!40000 ALTER TABLE `timer_sessions` DISABLE KEYS */;
INSERT INTO `timer_sessions` VALUES
(1,'2026-08-12 06:39:38','2026-08-12 06:39:53','2026-08-21 06:21:20',NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 06:39:38',NULL,NULL,'2026-08-12 06:39:53',0,15,0.00,'client',NULL,'2026-09-06 06:47:17'),
(2,'2026-08-12 06:46:49','2026-08-12 06:47:05','2026-08-21 06:21:20',NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 06:46:49',NULL,NULL,'2026-08-12 06:47:05',0,16,0.00,'client',NULL,'2026-09-06 06:47:17'),
(3,'2026-08-12 06:55:01','2026-08-14 05:18:53','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 06:55:01',NULL,NULL,'2026-08-12 06:55:04',0,3,66.00,'user',NULL,'2026-09-06 06:47:17'),
(4,'2026-08-12 06:55:21','2026-08-14 05:18:53','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 06:55:21',NULL,NULL,'2026-08-12 06:55:24',0,3,66.00,'user',NULL,'2026-09-06 06:47:17'),
(5,'2026-08-12 07:19:16','2026-08-14 05:18:56','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:19:16',NULL,NULL,'2026-08-12 07:19:53',0,38,66.00,'user',NULL,'2026-09-06 06:47:17'),
(6,'2026-08-12 07:20:24','2026-08-12 07:22:20','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:20:24',NULL,NULL,'2026-08-12 07:50:24',0,1800,66.00,'user',NULL,'2026-09-06 06:47:17'),
(7,'2026-08-12 07:22:16','2026-08-14 05:18:56','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:22:16',NULL,NULL,'2026-08-12 07:52:16',0,1800,66.00,'user',NULL,'2026-09-06 06:47:17'),
(8,'2026-08-12 07:25:59','2026-08-14 05:18:56','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 19:25:00',NULL,NULL,'2026-08-12 19:40:00',0,900,66.00,'user',NULL,'2026-09-06 06:47:17'),
(9,'2026-08-12 07:44:48','2026-08-12 07:47:28','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:44:48',NULL,NULL,'2026-08-12 07:45:42',0,55,66.00,'user',NULL,'2026-09-06 06:47:17'),
(10,'2026-08-12 07:45:53','2026-08-14 05:18:58','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:45:53',NULL,NULL,'2026-08-12 07:47:22',0,90,66.00,'user',NULL,'2026-09-06 06:47:17'),
(11,'2026-08-12 07:47:25','2026-08-14 05:18:58','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:47:25',NULL,NULL,'2026-08-12 07:59:30',0,725,66.00,'user',NULL,'2026-09-06 06:47:17'),
(12,'2026-08-12 07:59:49','2026-08-14 05:18:58','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 07:59:49',NULL,NULL,'2026-08-12 07:59:58',0,10,66.00,'user',NULL,'2026-09-06 06:47:17'),
(13,'2026-08-13 04:46:41','2026-08-13 05:35:16','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 04:46:41',NULL,NULL,'2026-08-13 05:35:16',0,2916,66.00,'user',NULL,'2026-09-06 06:47:17'),
(14,'2026-08-13 05:35:28','2026-08-13 05:35:31','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 05:35:28',NULL,NULL,'2026-08-13 05:35:31',0,4,66.00,'user',NULL,'2026-09-06 06:47:17'),
(15,'2026-08-13 22:09:52','2026-08-14 05:19:03','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 22:09:52',NULL,NULL,'2026-08-13 22:09:55',0,3,66.00,'user',NULL,'2026-09-06 06:47:17'),
(16,'2026-08-13 22:12:56','2026-08-14 05:19:03','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 22:16:10',NULL,NULL,'2026-08-13 22:16:15',38,38,66.00,'user',NULL,'2026-09-06 06:47:17'),
(17,'2026-08-13 22:16:21','2026-08-14 05:19:03','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 22:16:33',NULL,NULL,'2026-08-13 22:16:45',18,18,66.00,'user',NULL,'2026-09-06 06:47:17'),
(18,'2026-08-13 22:17:29','2026-08-14 05:19:03','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 22:17:29',NULL,NULL,'2026-08-13 22:17:51',0,0,66.00,'user',NULL,'2026-09-06 06:47:17'),
(19,'2026-08-13 22:19:49','2026-08-14 05:19:03','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-13 22:19:56',NULL,NULL,'2026-08-13 22:20:01',4,9,66.00,'user',NULL,'2026-09-06 06:47:17'),
(20,'2026-08-14 04:11:06','2026-08-14 04:31:23',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-14 04:11:06',NULL,NULL,'2026-08-14 04:31:23',0,1217,66.00,'user','AUD','2026-09-06 06:47:17'),
(21,'2026-08-14 05:19:51','2026-08-14 05:19:51',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-15 00:00:00',NULL,NULL,'2026-07-15 00:20:00',0,1200,66.00,'user','AUD','2026-09-06 06:47:17'),
(22,'2026-08-14 05:20:18','2026-08-14 05:20:18',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-16 00:00:00',NULL,NULL,'2026-07-16 00:24:00',0,1440,66.00,'user','AUD','2026-09-06 06:47:17'),
(23,'2026-08-14 05:20:37','2026-08-14 05:20:37',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-17 00:00:00',NULL,NULL,'2026-07-17 00:38:00',0,2280,66.00,'user','AUD','2026-09-06 06:47:17'),
(24,'2026-08-14 05:20:49','2026-08-14 05:20:49',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-18 00:00:00',NULL,NULL,'2026-07-18 00:35:00',0,2100,66.00,'user','AUD','2026-09-06 06:47:17'),
(25,'2026-08-14 05:21:04','2026-08-14 05:21:04',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-21 00:00:00',NULL,NULL,'2026-07-21 00:32:00',0,1920,66.00,'user','AUD','2026-09-06 06:47:17'),
(26,'2026-08-14 05:21:14','2026-08-14 05:21:14',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-22 00:00:00',NULL,NULL,'2026-07-22 00:35:00',0,2100,66.00,'user','AUD','2026-09-06 06:47:17'),
(27,'2026-08-14 05:21:24','2026-08-14 05:21:24',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-23 00:00:00',NULL,NULL,'2026-07-23 00:46:00',0,2760,66.00,'user','AUD','2026-09-06 06:47:17'),
(28,'2026-08-14 05:21:34','2026-08-14 05:21:34',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-24 00:00:00',NULL,NULL,'2026-07-24 00:23:00',0,1380,66.00,'user','AUD','2026-09-06 06:47:17'),
(29,'2026-08-14 05:21:48','2026-08-14 05:21:48',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-25 00:00:00',NULL,NULL,'2026-07-25 00:28:00',0,1680,66.00,'user','AUD','2026-09-06 06:47:17'),
(30,'2026-08-14 05:21:58','2026-08-14 05:21:58',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-27 00:00:00',NULL,NULL,'2026-07-27 00:13:00',0,780,66.00,'user','AUD','2026-09-06 06:47:17'),
(31,'2026-08-14 05:22:09','2026-08-14 05:22:09',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-28 00:00:00',NULL,NULL,'2026-07-28 00:18:00',0,1080,66.00,'user','AUD','2026-09-06 06:47:17'),
(32,'2026-08-14 05:22:18','2026-08-14 05:22:18',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-29 00:00:00',NULL,NULL,'2026-07-29 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(33,'2026-08-14 05:22:30','2026-08-14 05:22:30',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-30 00:00:00',NULL,NULL,'2026-07-30 00:42:00',0,2520,66.00,'user','AUD','2026-09-06 06:47:17'),
(34,'2026-08-14 05:22:41','2026-08-14 05:22:41',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-31 00:00:00',NULL,NULL,'2026-07-31 00:23:00',0,1380,66.00,'user','AUD','2026-09-06 06:47:17'),
(35,'2026-08-14 05:22:49','2026-08-14 05:22:49',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-01 00:00:00',NULL,NULL,'2026-08-01 00:28:00',0,1680,66.00,'user','AUD','2026-09-06 06:47:17'),
(36,'2026-08-14 05:22:58','2026-08-14 05:22:58',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-03 00:00:00',NULL,NULL,'2026-08-03 00:55:00',0,3300,66.00,'user','AUD','2026-09-06 06:47:17'),
(37,'2026-08-14 05:23:06','2026-08-14 05:23:06',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-04 00:00:00',NULL,NULL,'2026-08-04 00:29:00',0,1740,66.00,'user','AUD','2026-09-06 06:47:17'),
(38,'2026-08-14 05:23:14','2026-08-14 05:23:14',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-05 00:00:00',NULL,NULL,'2026-08-05 00:36:00',0,2160,66.00,'user','AUD','2026-09-06 06:47:17'),
(39,'2026-08-14 05:23:22','2026-08-14 05:23:22',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-06 00:00:00',NULL,NULL,'2026-08-06 00:32:00',0,1920,66.00,'user','AUD','2026-09-06 06:47:17'),
(40,'2026-08-14 05:23:30','2026-08-14 05:23:30',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-07 00:00:00',NULL,NULL,'2026-08-07 00:22:00',0,1320,66.00,'user','AUD','2026-09-06 06:47:17'),
(41,'2026-08-14 05:23:39','2026-08-14 05:23:39',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-08 00:00:00',NULL,NULL,'2026-08-08 00:31:00',0,1860,66.00,'user','AUD','2026-09-06 06:47:17'),
(42,'2026-08-14 05:23:47','2026-08-14 05:23:47',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-10 00:00:00',NULL,NULL,'2026-08-10 00:15:00',0,900,66.00,'user','AUD','2026-09-06 06:47:17'),
(43,'2026-08-14 05:23:55','2026-08-14 05:23:55',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-11 00:00:00',NULL,NULL,'2026-08-11 00:34:00',0,2040,66.00,'user','AUD','2026-09-06 06:47:17'),
(44,'2026-08-14 05:24:01','2026-08-14 05:24:01',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-12 00:00:00',NULL,NULL,'2026-08-12 00:21:00',0,1260,66.00,'user','AUD','2026-09-06 06:47:17'),
(45,'2026-08-14 05:24:09','2026-08-14 05:24:09',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-13 00:00:00',NULL,NULL,'2026-08-13 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(46,'2026-08-14 05:34:25','2026-08-14 05:43:12',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-16 00:00:00',NULL,NULL,'2026-06-16 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(47,'2026-08-14 05:34:38','2026-08-14 05:43:24',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-17 00:00:00',NULL,NULL,'2026-06-17 00:35:00',0,2100,66.00,'user','AUD','2026-09-06 06:47:17'),
(48,'2026-08-14 05:34:47','2026-08-14 05:43:28',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-18 00:00:00',NULL,NULL,'2026-06-18 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(49,'2026-08-14 05:34:58','2026-08-14 05:43:30',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-19 00:00:00',NULL,NULL,'2026-06-19 00:45:00',0,2700,66.00,'user','AUD','2026-09-06 06:47:17'),
(50,'2026-08-14 05:35:08','2026-08-14 05:43:41',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-20 00:00:00',NULL,NULL,'2026-06-20 00:34:00',0,2040,66.00,'user','AUD','2026-09-06 06:47:17'),
(51,'2026-08-14 05:35:18','2026-08-14 05:43:44',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-21 00:00:00',NULL,NULL,'2026-06-21 00:15:00',0,900,66.00,'user','AUD','2026-09-06 06:47:17'),
(52,'2026-08-14 05:35:28','2026-08-14 05:43:46',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-22 00:00:00',NULL,NULL,'2026-06-22 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(53,'2026-08-14 05:35:42','2026-08-14 05:43:54',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-23 00:00:00',NULL,NULL,'2026-06-23 00:40:00',0,2400,66.00,'user','AUD','2026-09-06 06:47:17'),
(54,'2026-08-14 05:35:52','2026-08-14 05:43:59',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-24 00:00:00',NULL,NULL,'2026-06-24 00:35:00',0,2100,66.00,'user','AUD','2026-09-06 06:47:17'),
(55,'2026-08-14 05:36:01','2026-08-14 05:44:01',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-25 00:00:00',NULL,NULL,'2026-06-25 00:26:00',0,1560,66.00,'user','AUD','2026-09-06 06:47:17'),
(56,'2026-08-14 05:36:14','2026-08-14 05:44:09',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-26 00:00:00',NULL,NULL,'2026-06-26 00:40:00',0,2400,66.00,'user','AUD','2026-09-06 06:47:17'),
(57,'2026-08-14 05:36:22','2026-08-14 05:44:12',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-27 00:00:00',NULL,NULL,'2026-06-27 00:15:00',0,900,66.00,'user','AUD','2026-09-06 06:47:17'),
(58,'2026-08-14 05:36:34','2026-08-14 05:44:14',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-06-28 00:00:00',NULL,NULL,'2026-06-28 00:27:00',0,1620,66.00,'user','AUD','2026-09-06 06:47:17'),
(59,'2026-08-14 05:36:54','2026-08-14 05:36:54',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-01 00:00:00',NULL,NULL,'2026-07-01 00:42:00',0,2520,66.00,'user','AUD','2026-09-06 06:47:17'),
(60,'2026-08-14 05:37:22','2026-08-14 05:37:22',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-02 00:00:00',NULL,NULL,'2026-07-02 00:17:00',0,1020,66.00,'user','AUD','2026-09-06 06:47:17'),
(61,'2026-08-14 05:37:32','2026-08-14 05:37:32',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-03 00:00:00',NULL,NULL,'2026-07-03 00:32:00',0,1920,66.00,'user','AUD','2026-09-06 06:47:17'),
(62,'2026-08-14 05:37:45','2026-08-14 05:37:45',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-04 00:00:00',NULL,NULL,'2026-07-04 00:20:00',0,1200,66.00,'user','AUD','2026-09-06 06:47:17'),
(63,'2026-08-14 05:38:00','2026-08-14 05:38:00',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-06 00:00:00',NULL,NULL,'2026-07-06 00:25:00',0,1500,66.00,'user','AUD','2026-09-06 06:47:17'),
(64,'2026-08-14 05:38:10','2026-08-14 05:44:36',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-07 00:00:00',NULL,NULL,'2026-07-07 00:17:00',0,1020,66.00,'user','AUD','2026-09-06 06:47:17'),
(65,'2026-08-14 05:38:23','2026-08-14 05:38:23',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-08 00:00:00',NULL,NULL,'2026-07-08 00:45:00',0,2700,66.00,'user','AUD','2026-09-06 06:47:17'),
(66,'2026-08-14 05:38:34','2026-08-14 05:38:34',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-09 00:00:00',NULL,NULL,'2026-07-09 00:20:00',0,1200,66.00,'user','AUD','2026-09-06 06:47:17'),
(67,'2026-08-14 05:38:48','2026-08-14 05:38:48',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-10 00:00:00',NULL,NULL,'2026-07-10 00:16:00',0,960,66.00,'user','AUD','2026-09-06 06:47:17'),
(68,'2026-08-14 05:38:57','2026-08-14 05:38:57',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-11 00:00:00',NULL,NULL,'2026-07-11 00:20:00',0,1200,66.00,'user','AUD','2026-09-06 06:47:17'),
(69,'2026-08-14 05:39:06','2026-08-14 05:39:06',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-13 00:00:00',NULL,NULL,'2026-07-13 00:17:00',0,1020,66.00,'user','AUD','2026-09-06 06:47:17'),
(70,'2026-08-14 05:39:14','2026-08-14 05:39:14',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-14 00:00:00',NULL,NULL,'2026-07-14 00:19:00',0,1140,66.00,'user','AUD','2026-09-06 06:47:17'),
(71,'2026-08-14 05:39:23','2026-08-14 05:39:23',NULL,1,1,'hello',1,1,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-07-15 00:00:00',NULL,NULL,'2026-07-15 00:33:00',0,1980,66.00,'user','AUD','2026-09-06 06:47:17'),
(72,'2026-08-14 23:01:17','2026-08-14 23:25:38',NULL,1,1,'hello',1,6,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-14 23:01:17',NULL,NULL,'2026-08-14 23:25:38',1447,1447,66.00,'user','AUD','2026-09-06 06:47:17'),
(73,'2026-08-17 05:01:41','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-17 05:01:41',NULL,NULL,'2026-08-17 05:18:53',1028,1028,66.00,'user','AUD','2026-09-16 04:52:03'),
(74,'2026-08-18 04:54:44','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-18 05:36:19',NULL,NULL,'2026-08-18 06:00:47',0,1468,66.00,'user','AUD','2026-09-16 04:52:03'),
(75,'2026-08-19 07:04:32','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-19 07:04:32',NULL,NULL,'2026-08-19 07:27:33',0,1381,66.00,'user','AUD','2026-09-16 04:52:03'),
(76,'2026-08-19 07:37:41','2026-08-19 07:37:47','2026-08-21 06:21:20',1,1,'hello',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-19 00:00:00',NULL,NULL,'2026-08-19 00:02:00',0,120,66.00,'user',NULL,'2026-09-06 06:47:17'),
(77,'2026-08-20 04:33:36','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-20 05:08:44',NULL,NULL,'2026-08-20 05:24:58',2304,2304,66.00,'user','AUD','2026-09-16 04:52:03'),
(78,'2026-08-21 04:10:15','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-21 04:10:15',NULL,NULL,'2026-08-21 04:27:51',0,1056,66.00,'user','AUD','2026-09-16 04:52:03'),
(79,'2026-08-21 04:59:14','2026-08-21 04:59:22','2026-08-21 06:21:20',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-21 04:59:14',NULL,NULL,'2026-08-21 04:59:20',4,4,66.00,'user','AUD','2026-09-06 06:47:17'),
(80,'2026-08-21 05:16:40','2026-08-21 05:16:46','2026-08-21 06:21:20',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-21 05:16:40',NULL,NULL,'2026-08-21 05:16:44',2,2,66.00,'user','AUD','2026-09-06 06:47:17'),
(81,'2026-08-21 05:19:08','2026-08-21 05:19:14','2026-08-21 06:21:20',1,1,'hello',1,NULL,2,2,'Development',2,'Website Work',1,'The Physio Place','2026-08-21 05:19:08',NULL,NULL,'2026-08-21 05:19:10',0,2,66.00,'user','AUD','2026-09-06 06:47:17'),
(82,'2026-08-21 06:08:26','2026-08-21 06:08:43','2026-08-21 06:21:20',1,1,'hello',1,NULL,2,2,'Development',2,'Website Work',1,'The Physio Place','2026-08-21 06:08:30',NULL,NULL,'2026-08-21 06:08:34',3,7,66.00,'user','AUD','2026-09-06 06:47:17'),
(83,'2026-08-21 06:12:18','2026-08-21 06:52:09','2026-08-21 06:21:20',1,1,'hello',1,NULL,3,3,'Design',2,'Website Work',1,'The Physio Place','2026-08-21 06:12:18',NULL,NULL,'2026-08-21 06:21:20',0,542,66.00,'user','AUD','2026-09-06 06:47:17'),
(84,'2026-08-22 02:04:46','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-22 02:29:10',NULL,NULL,'2026-08-22 02:34:55',1691,1691,66.00,'user','AUD','2026-09-16 04:52:03'),
(85,'2026-08-24 05:11:45','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-24 05:23:27',NULL,NULL,'2026-08-24 05:38:04',0,877,66.00,'user','AUD','2026-09-16 04:52:03'),
(86,'2026-08-24 05:50:01','2026-08-24 05:50:05',NULL,1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-24 05:50:01',NULL,NULL,'2026-08-24 05:50:03',0,2,66.00,'user','AUD','2026-09-06 06:47:17'),
(87,'2026-08-24 05:51:12','2026-08-24 05:51:23',NULL,1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-24 05:51:12',NULL,NULL,'2026-08-24 05:53:14',0,122,66.00,'user','AUD','2026-09-06 06:47:17'),
(88,'2026-08-24 07:12:33','2026-08-24 07:16:44',NULL,2,2,'raymond',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-24 07:12:33',NULL,NULL,'2026-08-24 07:14:38',0,125,66.00,'client','AUD','2026-09-06 06:47:17'),
(89,'2026-08-25 04:59:41','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-25 05:52:10',NULL,NULL,'2026-08-25 06:01:24',2896,3450,66.00,'client','AUD','2026-09-16 04:52:03'),
(90,'2026-08-25 07:44:44','2026-08-25 07:46:25',NULL,1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-08-25 07:45:54',NULL,NULL,'2026-08-25 07:46:25',8,39,66.00,'user','NZD','2026-09-06 06:47:17'),
(91,'2026-08-25 07:49:06','2026-08-25 07:49:33',NULL,1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-08-25 07:49:06',NULL,NULL,'2026-08-25 08:09:14',0,1208,66.00,'user','NZD','2026-09-06 06:47:17'),
(92,'2026-08-26 01:43:41','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-26 04:49:15',NULL,NULL,'2026-08-26 05:47:33',0,3498,66.00,'client','AUD','2026-09-16 04:52:03'),
(93,'2026-08-26 03:06:41','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,2,2,'Development',2,'Website Work',1,'The Physio Place','2026-08-26 05:15:52',NULL,NULL,'2026-08-26 05:31:18',0,926,66.00,'client','AUD','2026-09-16 04:52:03'),
(94,'2026-08-27 04:54:05','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-27 04:54:05',NULL,NULL,'2026-08-27 05:19:26',0,1521,66.00,'client','AUD','2026-09-16 04:52:03'),
(95,'2026-08-28 04:11:48','2026-09-16 04:52:03',NULL,11,11,'Ray Employee',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-28 04:11:48',NULL,NULL,'2026-08-28 04:30:55',1146,1146,66.00,'client','AUD','2026-09-16 04:52:03'),
(96,'2026-08-29 01:25:05','2026-09-16 04:52:03',NULL,11,11,'Ray Employee',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-29 03:05:12',NULL,NULL,'2026-08-29 03:08:25',2311,2504,66.00,'client','AUD','2026-09-16 04:52:03'),
(97,'2026-08-31 04:54:59','2026-09-08 04:13:30','2026-09-08 04:13:30',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-31 04:54:59',NULL,NULL,'2026-08-31 05:08:36',816,816,66.00,'user','AUD','2026-09-06 06:47:17'),
(98,'2026-08-31 05:09:29','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-08-31 05:09:29',NULL,NULL,'2026-08-31 05:21:12',701,701,66.00,'user','AUD','2026-09-16 04:52:03'),
(99,'2026-09-02 04:27:58','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-02 06:18:17',NULL,NULL,'2026-09-02 06:21:15',2571,2571,66.00,'client','AUD','2026-09-16 04:52:03'),
(100,'2026-09-02 08:59:44','2026-09-06 03:54:53',NULL,11,11,'Ray Employee',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-02 08:59:44',NULL,NULL,'2026-09-02 09:09:50',0,606,50.00,'client','NZD','2026-09-06 06:47:17'),
(101,'2026-09-03 03:59:09','2026-09-16 04:52:03',NULL,11,11,'Ray Employee',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-03 04:30:34',NULL,NULL,'2026-09-03 04:34:47',1848,2101,66.00,'client','AUD','2026-09-16 04:52:03'),
(102,'2026-09-04 03:34:46','2026-09-16 04:52:03',NULL,2,2,'raymond',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-04 04:05:14',NULL,NULL,'2026-09-04 04:15:45',966,966,66.00,'client','AUD','2026-09-16 04:52:03'),
(103,'2026-09-04 22:37:40','2026-09-04 22:37:46',NULL,1,1,'hello',1,NULL,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-04 22:37:40',NULL,NULL,'2026-09-04 22:37:46',0,6,66.00,'user','NZD','2026-09-06 06:47:17'),
(104,'2026-09-04 22:38:00','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-04 22:50:31',NULL,NULL,'2026-09-04 22:58:50',680,1179,66.00,'user','AUD','2026-09-16 04:52:03'),
(105,'2026-09-06 03:19:30','2026-09-06 03:54:09','2026-09-06 03:54:09',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-06 03:19:30',NULL,'2026-09-06 03:27:32',NULL,482,NULL,66.00,'user','NZD','2026-09-06 06:47:17'),
(106,'2026-09-06 03:54:14','2026-09-06 03:54:24','2026-09-06 03:54:24',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-06 03:54:14',NULL,NULL,NULL,0,NULL,66.00,'user','NZD','2026-09-06 06:47:17'),
(107,'2026-09-06 03:54:44','2026-09-06 03:54:46',NULL,1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-06 03:54:44',NULL,NULL,'2026-09-06 03:54:46',0,2,66.00,'user','NZD','2026-09-06 06:47:17'),
(108,'2026-09-06 05:53:34','2026-09-06 05:53:49','2026-09-06 05:53:49',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-06 05:53:34',NULL,NULL,NULL,0,NULL,66.00,'user','NZD','2026-09-06 06:47:17'),
(109,'2026-09-06 06:19:29','2026-09-06 06:19:52','2026-09-06 06:19:52',1,1,'hello',1,NULL,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-06 06:19:29',NULL,NULL,'2026-09-06 06:19:48',10,10,66.00,'user','NZD','2026-09-06 06:47:17'),
(110,'2026-09-07 04:10:55','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-07 04:10:55',NULL,NULL,'2026-09-07 05:02:51',622,622,66.00,'user','AUD','2026-09-16 04:52:03'),
(111,'2026-09-07 05:00:22','2026-09-07 05:00:54','2026-09-07 05:00:54',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-07 05:00:22',NULL,'2026-09-07 05:00:42',NULL,20,NULL,66.00,'user','AUD','2026-09-07 05:00:22'),
(112,'2026-09-07 05:08:44','2026-09-07 05:15:44','2026-09-07 05:15:44',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-09 05:08:44',NULL,NULL,'2026-09-07 05:08:52',7,7,66.00,'user','AUD','2026-09-07 05:08:44'),
(113,'2026-09-07 05:14:50','2026-09-07 05:15:41','2026-09-07 05:15:41',1,1,'hello',1,NULL,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-07 05:14:50',NULL,NULL,'2026-09-07 05:15:03',12,12,66.00,'user','NZD','2026-09-07 05:14:50'),
(114,'2026-09-07 05:15:10','2026-09-07 05:15:36','2026-09-07 05:15:36',1,1,'hello',1,NULL,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-07 05:15:10',NULL,NULL,'2026-09-07 05:15:19',7,7,66.00,'user','AUD','2026-09-07 05:15:10'),
(115,'2026-09-07 05:20:09','2026-09-07 05:20:44','2026-09-07 05:20:44',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-08 05:20:09',NULL,NULL,'2026-09-07 05:20:37',24,24,66.00,'user','NZD','2026-09-07 05:20:09'),
(116,'2026-09-07 05:31:46','2026-09-07 05:32:11','2026-09-07 05:32:11',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-08 05:31:46',NULL,NULL,'2026-09-07 05:32:01',0,15,66.00,'user','NZD','2026-09-07 05:31:46'),
(117,'2026-09-07 05:36:27','2026-09-07 05:37:48','2026-09-07 05:37:48',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-08 05:36:27',NULL,NULL,'2026-09-07 05:37:25',778,790,66.00,'user','NZD','2026-09-07 05:36:27'),
(118,'2026-09-07 05:38:03','2026-09-07 05:38:32','2026-09-07 05:38:32',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-09 05:38:03',NULL,NULL,'2026-09-07 05:38:05',0,2,66.00,'user','NZD','2026-09-07 05:38:03'),
(119,'2026-09-08 04:48:49','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-08 04:48:49',NULL,NULL,'2026-09-08 05:15:06',0,1577,66.00,'user','AUD','2026-09-16 04:52:03'),
(120,'2026-09-09 03:11:59','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-09 03:11:59',NULL,NULL,'2026-09-09 03:49:55',1446,1448,66.00,'user','AUD','2026-09-16 04:52:03'),
(121,'2026-09-10 03:42:56','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-10 03:42:56',NULL,NULL,'2026-09-10 04:09:15',0,1579,66.00,'user','AUD','2026-09-16 04:52:03'),
(122,'2026-09-11 04:09:00','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-11 04:09:00',NULL,NULL,'2026-09-11 04:22:06',0,786,66.00,'user','AUD','2026-09-16 04:52:03'),
(123,'2026-09-11 21:35:07','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-11 21:35:07',NULL,NULL,'2026-09-11 22:58:48',808,4608,66.00,'user','AUD','2026-09-16 04:52:03'),
(124,'2026-09-11 23:02:40','2026-09-11 23:03:06','2026-09-11 23:03:06',1,1,'hello',1,8,4,4,'Software Development',3,'Platform Build',2,'Kabushka','2026-09-11 23:02:40',NULL,NULL,'2026-09-11 23:03:00',0,20,66.00,'user','NZD','2026-09-11 23:02:40'),
(125,'2026-09-14 04:34:41','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-14 04:34:41',NULL,NULL,'2026-09-14 04:57:13',0,1352,66.00,'user','AUD','2026-09-16 04:52:03'),
(126,'2026-09-15 03:43:21','2026-09-16 04:52:03',NULL,1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-15 03:43:21',NULL,NULL,'2026-09-15 04:08:06',0,1485,66.00,'user','AUD','2026-09-16 04:52:03'),
(127,'2026-09-16 03:50:54','2026-09-16 04:07:38','2026-09-16 04:07:38',1,1,'hello',1,7,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-16 03:50:54',NULL,NULL,'2026-09-16 04:06:47',321,918,66.00,'user','AUD','2026-09-16 03:50:54'),
(128,'2026-09-16 04:54:52','2026-09-16 04:55:06',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-16 04:54:52',NULL,NULL,'2026-09-16 05:09:55',0,903,66.00,'user','AUD','2026-09-16 04:54:52'),
(129,'2026-09-17 02:41:22','2026-09-17 03:34:11',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-17 02:41:22',NULL,NULL,'2026-09-17 03:34:11',2808,2827,66.00,'user','AUD','2026-09-17 02:41:22'),
(130,'2026-09-18 03:37:45','2026-09-18 04:04:04',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-18 03:37:45',NULL,NULL,'2026-09-18 04:04:04',982,1386,66.00,'user','AUD','2026-09-18 03:37:45'),
(131,'2026-09-19 01:12:50','2026-09-19 01:37:38',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-19 01:12:50',NULL,NULL,'2026-09-19 01:37:38',0,1488,66.00,'user','AUD','2026-09-19 01:12:50'),
(132,'2026-09-21 03:57:58','2026-09-21 04:04:26',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-21 03:57:58',NULL,NULL,'2026-09-21 04:04:26',0,388,66.00,'user','AUD','2026-09-21 03:57:58'),
(133,'2026-09-22 02:46:01','2026-09-22 03:52:45',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-22 02:46:01',NULL,NULL,'2026-09-22 03:52:45',338,1895,66.00,'user','AUD','2026-09-22 02:46:01'),
(134,'2026-09-23 03:46:08','2026-09-23 04:29:55',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-23 03:46:08',NULL,NULL,'2026-09-23 04:29:55',0,2627,66.00,'user','AUD','2026-09-23 03:46:08'),
(135,'2026-09-24 04:03:52','2026-09-24 04:22:07',NULL,1,1,'hello',1,9,1,1,'Admin work',1,'TPP Admin Work',1,'The Physio Place','2026-09-24 04:03:52',NULL,NULL,'2026-09-24 04:22:07',0,1095,66.00,'user','AUD','2026-09-24 04:03:52'),
(136,'2026-09-24 04:22:59','2026-09-24 04:23:14',NULL,1,1,'hello',1,9,3,3,'Design',2,'Website Work',1,'The Physio Place','2026-09-24 04:22:59',NULL,NULL,'2026-09-24 06:23:02',0,7203,66.00,'user','AUD','2026-09-24 04:22:59');
/*!40000 ALTER TABLE `timer_sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `account_type` varchar(32) NOT NULL DEFAULT 'individual',
  `country` varchar(255) NOT NULL DEFAULT 'NZ',
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `income_tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `student_loan_tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `bank_account_name` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bsb_code` varchar(32) DEFAULT NULL,
  `bank_account_number` varchar(64) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reenable_requested` tinyint(1) NOT NULL DEFAULT 0,
  `reenable_requested_at` timestamp NULL DEFAULT NULL,
  `reenable_requested_ip` varchar(45) DEFAULT NULL,
  `reenable_requested_description` longtext DEFAULT NULL,
  `can_accept_payouts` tinyint(1) NOT NULL DEFAULT 0,
  `notified_to_finish_stripe_setup` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'hello','hello@email.com','individual','NZ','acct_1U3W3FRzoTiceyPa','cus_V3dJtYqbeTCE9I',66.00,33.00,0.00,'Raymond Geuze',NULL,'774001','235471304',NULL,'$2y$12$mVyAo4hLAgPJ5IYM98RG1us4OmFwovwc7lmpFpebFPmTicXg/zdw2',NULL,NULL,NULL,'5VRDqshu8SMVMnwkAOkLWrqE3bwtFrGlqMpFn6wEzYAh7SOYu5g8nYAUsNH0',1,NULL,0,0,'2026-08-12 06:54:13','2026-08-28 06:04:29',0,NULL,NULL,NULL,0,0),
(2,'raymond','raymondgeuze@gmail.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$mVyAo4hLAgPJ5IYM98RG1us4OmFwovwc7lmpFpebFPmTicXg/zdw2',NULL,NULL,NULL,NULL,1,NULL,0,0,'2026-08-18 04:49:18','2026-08-18 04:49:18',0,NULL,NULL,NULL,0,0),
(3,'admin','admin@test.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$QpXnKFrPHHqvitsLiHrE6e9OxJ1k/bdPHqeDfNnq.VGr9BInXf2Yi',NULL,NULL,NULL,NULL,NULL,NULL,0,0,'2026-08-27 05:41:21','2026-08-27 05:41:21',0,NULL,NULL,NULL,0,0),
(4,'admin1','admin+1@email.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$M.Jn/WQwvZAUZ2c58GevN.ijP2akdkaQjAmTwQsMkQxdTgEPwpNP6',NULL,NULL,NULL,NULL,1,NULL,0,0,'2026-08-27 06:03:36','2026-08-27 06:03:36',0,NULL,NULL,NULL,0,0),
(5,'Second Team','secondteam@email.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$AXnmbX8BJn9jttFK5z1XDuuXobZPTyNwfcfBStxwK.XslTuvIx3li',NULL,NULL,NULL,NULL,NULL,NULL,0,0,'2026-08-27 06:18:26','2026-08-27 06:18:26',0,NULL,NULL,NULL,0,0),
(6,'Fixed Person','fixed1787813902357@example.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$KiL5Iw/84U/qfBH3SJMaS.2ZHrbS7v1AUvT6GtyA4uIsUT4dRlkKi',NULL,NULL,NULL,NULL,2,NULL,0,0,'2026-08-27 06:58:22','2026-08-27 06:58:22',0,NULL,NULL,NULL,0,0),
(7,'Second Team1','secondteam+1@email.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$f4nI.HbkpIJ89iRx.EhmM.ALlmexXnx3aLpouLFfw8b8vq3/pWArO',NULL,NULL,NULL,NULL,3,NULL,0,0,'2026-08-27 07:02:55','2026-08-27 07:02:55',0,NULL,NULL,NULL,0,0),
(8,'Third Team','thirdteam@email.com','individual','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$Xl7JIKnENuG/ZiXSc6AWIeuMWRAmFP3xQM6KV70/gKAHt8ZAVbtia',NULL,NULL,NULL,NULL,4,NULL,0,0,'2026-08-27 07:06:23','2026-08-27 07:06:23',0,NULL,NULL,NULL,0,0),
(9,'not jimbos admin','notjimbosadmin@email.com','employee','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$oCrJYZ7AmRbIUpp0Td031uvqsdU8b1VmNn6sPGoGKR6emoV39WHGy',NULL,NULL,NULL,NULL,4,NULL,0,0,'2026-08-27 07:19:31','2026-08-27 07:19:31',0,NULL,NULL,NULL,0,0),
(10,'notjimbos editor','notjimboseditor@email.com','employee','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$LrtObue6L.BlWUGPywNice2GauEBHYPFaFTHash9HAElxsSNCutT2',NULL,NULL,NULL,NULL,4,NULL,0,0,'2026-08-27 07:21:18','2026-08-27 07:21:19',0,NULL,NULL,NULL,0,0),
(11,'Ray Employee','rayemployee@email.com','employee','NZ',NULL,NULL,0.00,0.00,0.00,NULL,NULL,NULL,NULL,NULL,'$2y$12$0aDVT3ocPAX1xVbsYDig/.cb3SfepB.yeLo4pTtWp73cMbz4N36qC',NULL,NULL,NULL,NULL,1,NULL,0,0,'2026-08-27 07:34:12','2026-08-27 07:34:12',0,NULL,NULL,NULL,0,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-25  3:37:32
