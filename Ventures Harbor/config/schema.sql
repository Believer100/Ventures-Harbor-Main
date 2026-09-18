-- ============================================================
-- VENTURES HARBOR — BASE SCHEMA (structure only, no user data)
-- ============================================================
-- This is the file config/setup.sql was always supposed to be. It creates
-- every table the application needs, on an EMPTY database.
--
-- Why it exists: config/migrate.sql is an UPGRADE script, not an installer.
-- Its very first statement is "ALTER TABLE ventures ADD COLUMN ..." — it
-- assumes the core tables are already there. Nine of the 26 tables (users,
-- ventures, venture_members, transactions, notifications, meetups,
-- meetup_attendees, user_documents, ca_bookings) have no CREATE statement
-- anywhere in the repository, so a fresh install from the code alone fails
-- with "Table 'venture_harbor.ventures' doesn't exist".
--
-- SETTING UP A LOCAL COPY — run these two, IN THIS ORDER:
--   1. config/schema.sql   (this file)  — creates the tables
--   2. config/migrate.sql               — applies every later change
-- Both are idempotent, so re-running either is a no-op.
--
--   CLI:
--     mysql -u root --default-character-set=utf8mb4 venture_harbor < config/schema.sql
--     mysql -u root --default-character-set=utf8mb4 venture_harbor < config/migrate.sql
--   phpMyAdmin:
--     create the database, select it, then Import each file in turn.
--
-- This file contains NO users, ventures, payments or personal data. For real
-- content, export the live database from Hostinger -> phpMyAdmin -> Export.
--
-- Collation is stated explicitly on every table (utf8mb4_general_ci) rather
-- than inherited from the server default, which differs between MariaDB 10.4
-- (local XAMPP) and 11.4+ (the live host) and otherwise causes
-- "#1267 Illegal mix of collations" on joins.
--
-- FOREIGN_KEY_CHECKS is disabled for the duration, so table order is safe.
-- ============================================================

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ca_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `booking_date` date NOT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `ca_name` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ca_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `legal_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `legal_templates_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `meetup_attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meetup_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rsvp_status` varchar(20) DEFAULT 'accepted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendee` (`meetup_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `meetup_attendees_ibfk_1` FOREIGN KEY (`meetup_id`) REFERENCES `meetups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meetup_attendees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `meetups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `date` varchar(30) NOT NULL,
  `time` varchar(30) NOT NULL,
  `location_type` varchar(20) DEFAULT 'offline',
  `location` varchar(150) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `rsvp_status` varchar(20) DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'upcoming',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `venture_id` (`venture_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `meetups_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meetups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `payment_intents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `venture_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'silent',
  `invested_amount` int(11) NOT NULL DEFAULT 0,
  `fee_amount` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `purpose` varchar(20) NOT NULL DEFAULT 'membership',
  `status` varchar(20) NOT NULL DEFAULT 'created',
  `gateway` varchar(20) NOT NULL DEFAULT 'payu',
  `gateway_mode` varchar(10) NOT NULL DEFAULT 'test',
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_status` varchar(50) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `raw_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `txn_id` (`txn_id`),
  KEY `venture_id` (`venture_id`),
  KEY `idx_intent_lookup` (`status`,`created_at`),
  KEY `idx_intent_user` (`user_id`,`venture_id`),
  CONSTRAINT `payment_intents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_intents_ibfk_2` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `venture_id` int(11) DEFAULT NULL,
  `venture_name` varchar(150) NOT NULL,
  `amount` int(11) NOT NULL,
  `principal_amount` int(11) NOT NULL DEFAULT 0,
  `fee_amount` int(11) NOT NULL DEFAULT 0,
  `type` varchar(50) DEFAULT 'commitment_fee',
  `status` varchar(20) DEFAULT 'completed',
  `refund_type` varchar(30) DEFAULT NULL,
  `txn_id` varchar(50) NOT NULL,
  `payment_gateway` varchar(20) DEFAULT 'manual',
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(120) DEFAULT NULL,
  `bank_account_number` varchar(40) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `payout_note` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `txn_id` (`txn_id`),
  KEY `user_id` (`user_id`),
  KEY `transactions_ibfk_venture` (`venture_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_venture` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `user_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `doc_type` varchar(50) NOT NULL,
  `file_name` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `previous_email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(10) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `account_type` varchar(20) NOT NULL DEFAULT 'individual',
  `account_type_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `individual_name` varchar(120) DEFAULT NULL,
  `company_name` varchar(120) DEFAULT NULL,
  `individual_bio` text DEFAULT NULL,
  `company_bio` text DEFAULT NULL,
  `individual_website_url` varchar(255) DEFAULT NULL,
  `company_website_url` varchar(255) DEFAULT NULL,
  `is_deactivated` tinyint(1) NOT NULL DEFAULT 0,
  `kyc_status` varchar(20) DEFAULT 'pending',
  `wallet_balance` int(11) NOT NULL DEFAULT 0,
  `city` varchar(100) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` varchar(500) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `past_experience` text DEFAULT NULL,
  `company_registration_no` varchar(60) DEFAULT NULL,
  `company_founded_year` int(11) DEFAULT NULL,
  `company_size` varchar(40) DEFAULT NULL,
  `company_industry` varchar(80) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'active',
  `invested_amount` int(11) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `resume_name` varchar(255) DEFAULT NULL,
  `skill_match_note` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decided_at` timestamp NULL DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_application` (`venture_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_app_slot` (`slot_id`,`status`),
  CONSTRAINT `venture_applications_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `venture_id` (`venture_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `venture_chat_messages_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_chat_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `venture_id` (`venture_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `venture_documents_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_financial_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `venture_financial_expenses_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `venture_financial_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_financial_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `report_month` char(7) NOT NULL,
  `total_revenue` int(11) NOT NULL DEFAULT 0,
  `total_expenses` int(11) NOT NULL DEFAULT 0,
  `profit_loss` int(11) NOT NULL DEFAULT 0,
  `cash_flow_summary` text DEFAULT NULL,
  `balance_sheet_summary` text DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_name` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_report` (`venture_id`,`report_month`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `venture_financial_reports_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_financial_reports_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `media_type` enum('image','video','embed','link') NOT NULL DEFAULT 'image',
  `file_path` varchar(255) DEFAULT NULL,
  `embed_url` varchar(500) DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_venture_sort` (`venture_id`,`sort_order`,`id`),
  CONSTRAINT `venture_media_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_media_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL,
  `invested_amount` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quit_window_opened_at` timestamp NULL DEFAULT NULL,
  `joined_via_slot_id` int(11) DEFAULT NULL,
  `equity_min_investment` int(11) DEFAULT NULL,
  `equity_active_percent` decimal(5,2) DEFAULT NULL,
  `equity_active_ops_percent` decimal(5,2) DEFAULT NULL,
  `equity_silent_percent` decimal(5,2) DEFAULT NULL,
  `equity_partner_types` varchar(20) DEFAULT NULL,
  `equity_agreed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`venture_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_members_venture_role` (`venture_id`,`role`),
  CONSTRAINT `venture_members_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_qna_likes` (
  `target_type` enum('question','answer','reply') NOT NULL,
  `target_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`target_type`,`target_id`,`user_id`),
  KEY `idx_vql_target` (`target_type`,`target_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `venture_qna_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_question_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `parent_target` enum('question','answer') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_vqr_question` (`question_id`,`parent_target`,`created_at`),
  CONSTRAINT `venture_question_replies_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `venture_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_question_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_question_votes` (
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target` enum('question','answer') NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`question_id`,`user_id`,`target`),
  KEY `idx_vqv_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `answered_by` int(11) DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `venture_id` (`venture_id`),
  KEY `user_id` (`user_id`),
  KEY `answered_by` (`answered_by`),
  CONSTRAINT `venture_questions_ibfk_1` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_questions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `venture_questions_ibfk_3` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `vacated_by_user_id` int(11) DEFAULT NULL,
  `vacated_by_name` varchar(150) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'silent',
  `invested_amount` int(11) NOT NULL DEFAULT 0,
  `fee_amount` int(11) NOT NULL DEFAULT 0,
  `exit_reason` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `claimed_by_user_id` int(11) DEFAULT NULL,
  `claim_expires_at` timestamp NULL DEFAULT NULL,
  `filled_by_user_id` int(11) DEFAULT NULL,
  `filled_at` timestamp NULL DEFAULT NULL,
  `notified_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slots_venture` (`venture_id`,`status`),
  KEY `idx_slots_claim` (`status`,`claim_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_waitlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venture_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_waitlist_person` (`venture_id`,`user_id`),
  KEY `idx_waitlist_venture` (`venture_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `venture_wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `venture_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_venture` (`user_id`,`venture_id`),
  KEY `venture_id` (`venture_id`),
  CONSTRAINT `venture_wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venture_wishlist_ibfk_2` FOREIGN KEY (`venture_id`) REFERENCES `ventures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `ventures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `founder_user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `full_description` text DEFAULT NULL,
  `use_of_funds` text DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `preferred_meeting_venue` varchar(255) DEFAULT NULL,
  `target_capital` int(11) DEFAULT 0,
  `raised_capital` int(11) DEFAULT 0,
  `progress_percent` int(11) DEFAULT 0,
  `members_count` int(11) DEFAULT 0,
  `max_members` int(11) DEFAULT 0,
  `days_left` int(11) DEFAULT 0,
  `listing_ends_at` datetime DEFAULT NULL,
  `extension_count` tinyint(4) NOT NULL DEFAULT 0,
  `extended_at` timestamp NULL DEFAULT NULL,
  `expired_at` timestamp NULL DEFAULT NULL,
  `expiry_reminders_sent` tinyint(4) NOT NULL DEFAULT 0,
  `last_expiry_reminder_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` varchar(20) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `min_investment` int(11) DEFAULT 0,
  `silent_capital_limit` int(11) DEFAULT NULL,
  `silent_min_investment` int(11) DEFAULT NULL,
  `active_min_investment` int(11) DEFAULT NULL,
  `founder_contribution` int(11) DEFAULT 0,
  `founder_name` varchar(100) DEFAULT NULL,
  `founder_avatar` varchar(10) DEFAULT NULL,
  `founder_bio` text DEFAULT NULL,
  `founder_type` varchar(20) NOT NULL DEFAULT 'individual',
  `category` varchar(50) DEFAULT NULL,
  `asset_class` varchar(255) DEFAULT NULL,
  `icon_bg` varchar(30) DEFAULT NULL,
  `icon_color` varchar(30) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `is_showcase` tinyint(1) NOT NULL DEFAULT 0,
  `showcase_raised_silent` int(11) DEFAULT NULL,
  `showcase_raised_active` int(11) DEFAULT NULL,
  `partner_types` varchar(50) DEFAULT 'both',
  `requirements` text DEFAULT NULL,
  `silent_requirements` text DEFAULT NULL,
  `required_skills` text DEFAULT NULL,
  `silent_required_skills` text DEFAULT NULL,
  `work_mode` varchar(20) DEFAULT 'physical',
  `timeline` varchar(50) DEFAULT '1 Year',
  `application_deadline` date DEFAULT NULL,
  `equity_distribution` varchar(20) DEFAULT NULL,
  `founder_equity_percent` decimal(5,2) DEFAULT NULL,
  `founder_ops_equity_percent` decimal(5,2) DEFAULT NULL,
  `founder_monthly_salary` int(11) DEFAULT NULL,
  `active_equity_percent` decimal(5,2) DEFAULT NULL,
  `active_ops_equity_percent` decimal(5,2) DEFAULT NULL,
  `active_monthly_salary` int(11) DEFAULT NULL,
  `silent_equity_percent` decimal(5,2) DEFAULT NULL,
  `expected_roi` varchar(120) DEFAULT NULL,
  `expected_roi_min` decimal(5,2) DEFAULT NULL,
  `expected_roi_max` decimal(5,2) DEFAULT NULL,
  `lockin_period` varchar(60) DEFAULT NULL,
  `expected_exit_timeline` varchar(120) DEFAULT NULL,
  `exit_options` text DEFAULT NULL,
  `early_exit_allowed` tinyint(1) DEFAULT NULL,
  `early_exit_notice_period` varchar(120) DEFAULT NULL,
  `early_exit_conditions` text DEFAULT NULL,
  `ownership_transfer_allowed` tinyint(1) DEFAULT NULL,
  `ownership_transfer_approval` varchar(30) DEFAULT NULL,
  `profit_distribution_frequency` varchar(30) DEFAULT NULL,
  `exit_valuation_method` varchar(40) DEFAULT NULL,
  `exit_valuation_notes` text DEFAULT NULL,
  `quit_window_opened_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ventures_ibfk_founder` (`founder_user_id`),
  KEY `idx_lifecycle_sweep` (`status`,`listing_ends_at`),
  KEY `idx_ventures_showcase` (`is_showcase`,`status`),
  KEY `idx_ventures_roi` (`expected_roi_min`,`expected_roi_max`),
  KEY `idx_ventures_asset_class` (`asset_class`),
  CONSTRAINT `ventures_ibfk_1` FOREIGN KEY (`founder_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ventures_ibfk_founder` FOREIGN KEY (`founder_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ---- Seed: default settings rows (configuration, not user data) ----

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('commitment_fee_percent','0.5','2026-07-24 16:56:20'),('kyc_required_to_create','1','2026-07-24 16:56:20'),('kyc_required_to_join','1','2026-07-24 16:56:20'),('payu_enabled','1','2026-09-08 11:21:27'),('payu_mode','test','2026-09-08 11:21:30'),('razorpay_enabled','0','2026-07-24 16:56:20'),('razorpay_key_id','','2026-07-24 16:56:20'),('site_name','Ventures Harbor','2026-07-24 16:56:20'),('support_email','support@venturesharbor.com','2026-08-15 01:19:34'),('venture_decision_grace_days','2','2026-08-11 23:26:16'),('venture_lifecycle_last_sweep','2026-09-10 18:39:22','2026-09-10 16:39:22'),('venture_max_extension_days','10','2026-08-06 16:31:06'),('venture_max_listing_days','30','2026-08-06 15:32:28');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

