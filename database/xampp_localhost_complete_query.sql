-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE XAMPP LOCALHOST MYSQL/MARIADB QUERY
-- MySQL/MariaDB Database Setup for XAMPP Localhost (phpMyAdmin)
-- 100% Synced & Identical with Supabase Cloud Database Schema
-- Generated: 2026-08-28
-- =====================================================================

-- =====================================================================
-- DATABASE RESET & CREATION (Clean Fresh Import)
-- =====================================================================
DROP DATABASE IF EXISTS `iecep_lsc_memsys`;
CREATE DATABASE `iecep_lsc_memsys` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `iecep_lsc_memsys`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. INSTITUTIONS TABLE (Affiliated HEI Universities in Laguna)
CREATE TABLE IF NOT EXISTS `institutions` (
    `id` CHAR(36) PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `acronym` VARCHAR(50),
    `type` ENUM('university', 'college', 'institute', 'school', 'company', 'organization') DEFAULT 'university',
    `address` TEXT,
    `city` VARCHAR(100),
    `province` VARCHAR(100) DEFAULT 'Laguna',
    `region` VARCHAR(100) DEFAULT 'Region IV-A (CALABARZON)',
    `country` VARCHAR(100) DEFAULT 'Philippines',
    `contact_person` VARCHAR(255),
    `contact_email` VARCHAR(255),
    `contact_phone` VARCHAR(50),
    `website` VARCHAR(255),
    `facebook_url` VARCHAR(255),
    `status` ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'active',
    `affiliation_fee_paid` BOOLEAN DEFAULT FALSE,
    `compliance_status` ENUM('compliant', 'at_risk', 'non_compliant') DEFAULT 'compliant',
    `membership_count` INT DEFAULT 0,
    `established_year` INT,
    `accreditation_status` VARCHAR(100),
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_institutions_status` (`status`),
    INDEX `idx_institutions_acronym` (`acronym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS & AUTH
CREATE TABLE IF NOT EXISTS `users` (
    `id` CHAR(36) PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255),
    `full_name` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'school_officer', 'member', 'auditor', 'treasurer', 'guest') DEFAULT 'member',
    `institution_id` CHAR(36),
    `is_active` BOOLEAN DEFAULT TRUE,
    `must_change_password` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_institution` (`institution_id`),
    CONSTRAINT `fk_users_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_users` (
    `id` CHAR(36) PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_auth_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USER PROFILES & AUTH
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` CHAR(36) UNIQUE,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'school_officer', 'member', 'auditor', 'treasurer', 'guest') DEFAULT 'member',
    `institution_id` CHAR(36),
    `phone` VARCHAR(50),
    `avatar_url` TEXT,
    `membership_status` VARCHAR(50) DEFAULT 'active',
    `force_password_change` BOOLEAN DEFAULT FALSE,
    `mfa_enabled` BOOLEAN DEFAULT FALSE,
    `mfa_secret` VARCHAR(255),
    `status` ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_profiles_role` (`role`),
    INDEX `idx_user_profiles_inst` (`institution_id`),
    CONSTRAINT `fk_user_profile_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. MEMBERS TABLE (Official Chapter Membership & Digital ID Roster)
CREATE TABLE IF NOT EXISTS `members` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` CHAR(36),
    `membership_id` VARCHAR(50) UNIQUE NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `phone` VARCHAR(50),
    `institution_id` CHAR(36) NOT NULL,
    `course` VARCHAR(255) DEFAULT 'Bachelor of Science in Electronics Engineering',
    `year_level` VARCHAR(50) DEFAULT '4th Year',
    `student_number` VARCHAR(50),
    `membership_type` ENUM('student', 'associate', 'regular', 'senior', 'fellow', 'honorary') DEFAULT 'student',
    `member_type` VARCHAR(50) DEFAULT 'new',
    `status` ENUM('active', 'inactive', 'pending', 'expired', 'suspended') DEFAULT 'active',
    `payment_status` ENUM('paid', 'pending', 'waived', 'unpaid', 'overdue') DEFAULT 'paid',
    `avatar_url` TEXT,
    `birthday` DATE,
    `address` TEXT,
    `digital_id_hash` VARCHAR(255),
    `digital_id_url` TEXT,
    `qr_code_url` TEXT,
    `joined_date` DATE DEFAULT (CURRENT_DATE),
    `expiration_date` DATE,
    `membership_expiry` DATE,
    `last_renewal_date` DATE,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_members_mem_id` (`membership_id`),
    INDEX `idx_members_inst` (`institution_id`),
    INDEX `idx_members_status` (`status`),
    CONSTRAINT `fk_members_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `members` ADD COLUMN IF NOT EXISTS `program` VARCHAR(255);

-- 4. MEMBER ID COUNTER (For auto-generating sequential IECEP-2026-XXXX)
CREATE TABLE IF NOT EXISTS `member_id_counter` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `year` INT UNIQUE,
    `last_number` INT NOT NULL DEFAULT 0,
    `counter` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. EVENTS TABLE
CREATE TABLE IF NOT EXISTS `events` (
    `id` CHAR(36) PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `event_type` ENUM('seminar', 'workshop', 'technical_summit', 'assembly', 'community', 'competition', 'other') DEFAULT 'seminar',
    `venue` VARCHAR(255) DEFAULT 'Main Auditorium / Online',
    `location` VARCHAR(255),
    `start_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `start_datetime` TIMESTAMP NULL DEFAULT NULL,
    `end_datetime` TIMESTAMP NULL DEFAULT NULL,
    `registration_fee` DECIMAL(10,2) DEFAULT 0.00,
    `fee` DECIMAL(10,2) DEFAULT 0.00,
    `max_attendees` INT DEFAULT 500,
    `max_capacity` INT DEFAULT 500,
    `registration_deadline` TIMESTAMP NULL DEFAULT NULL,
    `requires_payment` BOOLEAN DEFAULT FALSE,
    `is_online` BOOLEAN DEFAULT FALSE,
    `online_link` TEXT,
    `status` ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') DEFAULT 'published',
    `institution_id` CHAR(36),
    `created_by` CHAR(36),
    `target_roles` TEXT,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_events_status` (`status`),
    INDEX `idx_events_start` (`start_date`),
    CONSTRAINT `fk_events_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. EVENT ATTENDEES (Live Dynamic 15s QR & Officer Scanner Attendance)
CREATE TABLE IF NOT EXISTS `event_attendees` (
    `id` CHAR(36) PRIMARY KEY,
    `event_id` CHAR(36) NOT NULL,
    `member_id` CHAR(36) NOT NULL,
    `status` ENUM('registered', 'attended', 'cancelled', 'waitlisted') DEFAULT 'attended',
    `check_in_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `check_out_time` TIMESTAMP NULL DEFAULT NULL,
    `qr_hash` VARCHAR(255),
    `verified_by` CHAR(36),
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_event_member` (`event_id`, `member_id`),
    INDEX `idx_att_event` (`event_id`),
    INDEX `idx_att_member` (`member_id`),
    INDEX `idx_att_status` (`status`),
    CONSTRAINT `fk_att_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_att_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BLOCKCHAIN RECORDS (Cryptographic Proof & SHA-256 Ledger)
CREATE TABLE IF NOT EXISTS `blockchain_records` (
    `id` CHAR(36) PRIMARY KEY,
    `block_index` BIGINT,
    `entity_type` VARCHAR(100) NOT NULL,
    `entity_id` CHAR(36) NOT NULL,
    `transaction_hash` VARCHAR(255) NOT NULL,
    `record_hash` VARCHAR(255),
    `data_hash` VARCHAR(255),
    `record_type` VARCHAR(100),
    `reference_id` CHAR(36),
    `previous_hash` VARCHAR(255),
    `merkle_root` VARCHAR(255),
    `data_json` JSON NOT NULL,
    `metadata` JSON,
    `payload` JSON,
    `institution_id` CHAR(36),
    `confirmed` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bc_entity` (`entity_type`, `entity_id`),
    INDEX `idx_bc_hash` (`transaction_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TRANSACTIONS & TREASURY
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` CHAR(36) PRIMARY KEY,
    `transaction_id` VARCHAR(100) UNIQUE NOT NULL,
    `user_id` CHAR(36),
    `member_id` CHAR(36),
    `institution_id` CHAR(36),
    `event_id` CHAR(36),
    `amount` DECIMAL(10,2) NOT NULL,
    `fee_type` VARCHAR(100) DEFAULT 'membership_fee',
    `payment_method` ENUM('gcash', 'maya', 'bank_transfer', 'cash', 'stripe', 'other') DEFAULT 'gcash',
    `reference_number` VARCHAR(100),
    `receipt_url` TEXT,
    `status` ENUM('pending', 'paid', 'completed', 'verified', 'rejected', 'refunded', 'cancelled', 'canceled', 'failed') DEFAULT 'completed',
    `notes` TEXT,
    `verified_by` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `synchronized_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_tx_status` (`status`),
    INDEX `idx_tx_member` (`member_id`),
    CONSTRAINT `fk_tx_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tx_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tx_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `pending_affiliation_id` CHAR(36);
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `type` VARCHAR(50) DEFAULT 'payment';
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `transaction_type` VARCHAR(50) DEFAULT 'payment';
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `receipt_number` VARCHAR(100);
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `receipt_path` TEXT;
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `blockchain_hash` TEXT;

CREATE TABLE IF NOT EXISTS `institution_financial_totals` (
    `id` CHAR(36) PRIMARY KEY,
    `institution_id` CHAR(36) NOT NULL UNIQUE,
    `total_paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_pending` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_refunded` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_cancelled` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `grand_total_all_time` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `current_year_total` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `transaction_count` INT NOT NULL DEFAULT 0,
    `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT `fk_fin_totals_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_audit_logs` (
    `id` CHAR(36) PRIMARY KEY,
    `institution_id` CHAR(36),
    `transaction_id` VARCHAR(100),
    `action` VARCHAR(50) NOT NULL,
    `old_value` JSON,
    `new_value` JSON,
    `performed_by` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_fin_audit_inst` (`institution_id`),
    INDEX `idx_fin_audit_tx` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `transactions`
    MODIFY COLUMN `status` ENUM('pending', 'paid', 'completed', 'verified', 'rejected', 'refunded', 'cancelled', 'canceled', 'failed') DEFAULT 'completed';
ALTER TABLE `transactions` ADD COLUMN IF NOT EXISTS `synchronized_at` TIMESTAMP NULL DEFAULT NULL;

DROP TRIGGER IF EXISTS `trg_audit_transaction_insert`;
DROP TRIGGER IF EXISTS `trg_audit_transaction_update`;
DROP TRIGGER IF EXISTS `trg_audit_transaction_delete`;

CREATE TRIGGER `trg_audit_transaction_insert` AFTER INSERT ON `transactions`
FOR EACH ROW INSERT INTO `financial_audit_logs` (`id`, `institution_id`, `transaction_id`, `action`, `old_value`, `new_value`, `performed_by`)
VALUES (UUID(), NEW.institution_id, NEW.transaction_id, 'created', NULL,
    JSON_OBJECT('amount', NEW.amount, 'status', NEW.status, 'institution_id', NEW.institution_id, 'event_id', NEW.event_id), NEW.verified_by);

CREATE TRIGGER `trg_audit_transaction_update` AFTER UPDATE ON `transactions`
FOR EACH ROW
INSERT INTO `financial_audit_logs` (`id`, `institution_id`, `transaction_id`, `action`, `old_value`, `new_value`, `performed_by`)
SELECT UUID(), NEW.institution_id, NEW.transaction_id,
       CASE WHEN OLD.status <> NEW.status AND NEW.status IN ('paid', 'completed', 'verified') THEN 'marked_paid'
        WHEN OLD.status <> NEW.status AND NEW.status = 'refunded' THEN 'refunded'
        ELSE 'updated' END,
       JSON_OBJECT('amount', OLD.amount, 'status', OLD.status, 'institution_id', OLD.institution_id, 'event_id', OLD.event_id),
       JSON_OBJECT('amount', NEW.amount, 'status', NEW.status, 'institution_id', NEW.institution_id, 'event_id', NEW.event_id),
       NEW.verified_by
WHERE NOT (OLD.amount <=> NEW.amount)
   OR NOT (OLD.status <=> NEW.status)
   OR NOT (OLD.institution_id <=> NEW.institution_id)
   OR NOT (OLD.event_id <=> NEW.event_id);

CREATE TRIGGER `trg_audit_transaction_delete` AFTER DELETE ON `transactions`
FOR EACH ROW INSERT INTO `financial_audit_logs` (`id`, `institution_id`, `transaction_id`, `action`, `old_value`, `new_value`, `performed_by`)
VALUES (UUID(), OLD.institution_id, OLD.transaction_id, 'deleted',
    JSON_OBJECT('amount', OLD.amount, 'status', OLD.status, 'institution_id', OLD.institution_id, 'event_id', OLD.event_id), NULL, OLD.verified_by);

-- 9. PENDING AFFILIATIONS & INSTITUTIONAL APPLICANTS
CREATE TABLE IF NOT EXISTS `pending_affiliations` (
    `id` CHAR(36) PRIMARY KEY,
    `school_name` VARCHAR(255) NOT NULL,
    `acronym` VARCHAR(50),
    `email` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255) NOT NULL,
    `contact_number` VARCHAR(50),
    `status` ENUM('pending', 'under_review', 'approved', 'rejected', 'requires_revision') DEFAULT 'pending',
    `documents` JSON,
    `verification_code` VARCHAR(50),
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `rejection_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pending_aff_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `institution_name` VARCHAR(255);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `contact_email` VARCHAR(255);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `contact_phone` VARCHAR(50);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `contact_position` VARCHAR(100);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `institution_address` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `letter_of_intent` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `endorsement_letter` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `constitution_by_laws` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `officers_cvs` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `organizational_chart` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `member_directory` TEXT;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `institution_id` CHAR(36);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `member_count` INT DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `total_members` INT DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `new_members` INT DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `old_members` INT DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `affiliation_fee` DECIMAL(10,2) DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `membership_total` DECIMAL(10,2) DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `total_fee` DECIMAL(10,2) DEFAULT 0;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `receipt_number` VARCHAR(100);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `submitted_at` TIMESTAMP NULL;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `resubmitted_at` TIMESTAMP NULL;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `approved_at` TIMESTAMP NULL;
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `portal_user_id` CHAR(36);
ALTER TABLE `pending_affiliations` ADD COLUMN IF NOT EXISTS `login_credentials_sent` BOOLEAN DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS `revision_requests` (
    `id` CHAR(36) PRIMARY KEY,
    `affiliation_id` CHAR(36) NOT NULL,
    `token` VARCHAR(255) UNIQUE NOT NULL,
    `explanation` TEXT,
    `requested_by` CHAR(36),
    `deadline` TIMESTAMP NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_revision_requests_affiliation` (`affiliation_id`),
    INDEX `idx_revision_requests_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. VERIFICATION CODES (Email 2FA & Application Validation)
CREATE TABLE IF NOT EXISTS `verification_codes` (
    `id` CHAR(36) PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `purpose` VARCHAR(50) DEFAULT 'affiliation',
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `verified` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ver_code` (`email`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. MERCHANDISE & STORE
CREATE TABLE IF NOT EXISTS `merch_items` (
    `id` CHAR(36) PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) DEFAULT 'apparel',
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `image_url` TEXT,
    `badge` VARCHAR(50),
    `stock` INT DEFAULT 100,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merch_orders` (
    `id` CHAR(36) PRIMARY KEY,
    `order_id` VARCHAR(100) UNIQUE NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(50),
    `shipping_address` TEXT,
    `items` JSON NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'gcash',
    `status` ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. FEATURED CARDS & ANNOUNCEMENTS
CREATE TABLE IF NOT EXISTS `featured_cards` (
    `id` CHAR(36) PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(100) DEFAULT 'Announcement',
    `image_url` TEXT,
    `link_url` TEXT,
    `gradient_from` VARCHAR(20) DEFAULT '#0B1D4A',
    `gradient_to` VARCHAR(20) DEFAULT '#132a5e',
    `button_text` VARCHAR(100) DEFAULT 'Learn More',
    `button_url` TEXT DEFAULT '#',
    `button_color` VARCHAR(20) DEFAULT '#0B1D4A',
    `badge_text` VARCHAR(50),
    `sort_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
    `id` CHAR(36) PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `target_role` VARCHAR(50) DEFAULT 'all',
    `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    `author_id` CHAR(36),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` CHAR(36),
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'danger', 'event', 'system') DEFAULT 'info',
    `link_url` TEXT,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. SYSTEM SETTINGS & FEE BRACKETS (Board Resolution No. 021-2024)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` CHAR(36) PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_brackets` (
    `id` CHAR(36) PRIMARY KEY,
    `bracket_name` VARCHAR(100) UNIQUE NOT NULL,
    `min_members` INT NOT NULL,
    `max_members` INT,
    `fee` DECIMAL(10,2) NOT NULL,
    `per_member_fee` DECIMAL(10,2) DEFAULT 0.00,
    `annual_fee` DECIMAL(10,2) DEFAULT 0.00,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. EXTENDED APPLICATION, MEMBER, FINANCIAL, COMPLIANCE AND CONTENT TABLES
CREATE TABLE IF NOT EXISTS `affiliated_schools` (
    `id` CHAR(36) PRIMARY KEY,
    `name` VARCHAR(255) UNIQUE NOT NULL,
    `facebook_url` VARCHAR(500), `member_count` INT DEFAULT 0,
    `status` VARCHAR(50) DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_profiles` (
    `id` CHAR(36) PRIMARY KEY, `user_id` CHAR(36), `member_id` CHAR(36),
    `full_name` VARCHAR(255), `email` VARCHAR(255), `phone` VARCHAR(50), `address` TEXT,
    `bio` TEXT, `avatar_url` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_member_profiles_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_upload_batches` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36), `batch_name` TEXT,
    `uploaded_by` CHAR(36), `total_rows` INT DEFAULT 0, `status` VARCHAR(50) DEFAULT 'pending_approval',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `upload_batches` (
    `id` VARCHAR(50) PRIMARY KEY, `institution_id` CHAR(36), `application_id` CHAR(36),
    `uploaded_by_user_id` CHAR(36), `file_name` VARCHAR(255), `total_rows` INT DEFAULT 0,
    `validated_rows` INT DEFAULT 0, `status` VARCHAR(50) DEFAULT 'pending',
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pending_members` (
    `id` CHAR(36) PRIMARY KEY, `batch_id` CHAR(36), `institution_id` CHAR(36),
    `full_name` VARCHAR(255) NOT NULL, `email` VARCHAR(255) NOT NULL,
    `student_id` VARCHAR(100), `student_number` VARCHAR(100), `course` VARCHAR(255),
    `year_level` VARCHAR(50), `contact_number` VARCHAR(50), `phone` VARCHAR(50),
    `member_type` VARCHAR(50) DEFAULT 'new', `status` VARCHAR(50) DEFAULT 'pending',
    `error_message` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pending_members_batch` (`batch_id`), INDEX `idx_pending_members_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_applications` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36), `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL, `student_id` VARCHAR(100), `course` VARCHAR(255),
    `year_level` VARCHAR(50), `contact_number` VARCHAR(50), `status` VARCHAR(50) DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `membership_id_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `year` INT UNIQUE, `last_number` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_registrations` (
    `id` CHAR(36) PRIMARY KEY, `event_id` CHAR(36), `user_id` CHAR(36),
    `status` VARCHAR(50) DEFAULT 'registered', `payment_status` VARCHAR(50) DEFAULT 'unpaid',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `checked_in_at` TIMESTAMP NULL,
    `checked_out_at` TIMESTAMP NULL, `qr_token` VARCHAR(255) UNIQUE,
    UNIQUE KEY `uk_event_registration_user` (`event_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_attachments` (
    `id` CHAR(36) PRIMARY KEY, `event_id` CHAR(36), `file_name` TEXT, `file_path` TEXT,
    `file_type` VARCHAR(100), `uploaded_by` CHAR(36), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` CHAR(36) PRIMARY KEY, `user_id` CHAR(36) NOT NULL, `event_id` CHAR(36) NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY `uk_att_log` (`user_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance` (
    `id` CHAR(36) PRIMARY KEY, `event_id` CHAR(36), `user_id` CHAR(36), `member_id` CHAR(36),
    `institution_id` CHAR(36), `attended` BOOLEAN DEFAULT TRUE, `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `type` VARCHAR(50) DEFAULT 'checkin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX `idx_attendance_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `certificates` (
    `id` CHAR(36) PRIMARY KEY, `member_id` CHAR(36), `event_id` CHAR(36), `issue_date` DATE,
    `certificate_number` VARCHAR(255) UNIQUE, `blockchain_hash` TEXT, `file_path` TEXT,
    `template_type` VARCHAR(100) DEFAULT 'participation', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliation_documents` (
    `id` CHAR(36) PRIMARY KEY, `application_id` CHAR(36), `document_type` VARCHAR(100) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL, `file_path` VARCHAR(500) NOT NULL, `file_size` INT,
    `file_hash` VARCHAR(64), `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_aff_docs_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `institution_documents` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36) NOT NULL, `application_id` CHAR(36),
    `document_type` VARCHAR(100) NOT NULL, `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL, `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inst_docs_institution` (`institution_id`), INDEX `idx_inst_docs_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_records` (
    `id` CHAR(36) PRIMARY KEY, `school_id` CHAR(36) NOT NULL, `amount` DECIMAL(10,2) NOT NULL,
    `payment_type` VARCHAR(50) NOT NULL, `payment_status` VARCHAR(50) DEFAULT 'Pending',
    `proof_of_payment` TEXT, `official_receipt_url` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` CHAR(36) PRIMARY KEY, `invoice_number` VARCHAR(100) UNIQUE NOT NULL, `member_id` CHAR(36),
    `institution_id` CHAR(36), `amount` DECIMAL(10,2) NOT NULL, `description` TEXT,
    `issue_date` DATE NOT NULL, `due_date` DATE, `pdf_path` VARCHAR(500), `status` VARCHAR(50) DEFAULT 'draft',
    `created_by` CHAR(36), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` CHAR(36) PRIMARY KEY, `member_id` CHAR(36), `institution_id` CHAR(36), `transaction_id` CHAR(36),
    `batch_id` VARCHAR(50), `amount` DECIMAL(10,2) NOT NULL, `status` VARCHAR(50) DEFAULT 'pending',
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `payment_reference` VARCHAR(100),
    `proof_of_payment` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_waivers` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36), `requested_by` CHAR(36), `reason` TEXT NOT NULL,
    `requested_amount` DECIMAL(10,2) DEFAULT 0, `status` VARCHAR(50) DEFAULT 'pending',
    `reviewed_by` CHAR(36), `reviewed_at` TIMESTAMP NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_waiver_requests` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36), `student_name` VARCHAR(255) NOT NULL,
    `student_number` VARCHAR(100), `waiver_type` VARCHAR(100) DEFAULT 'Financial Hardship', `reason` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending', `requested_by` VARCHAR(255), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_adjustments` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36), `old_bracket_id` VARCHAR(100), `new_bracket_id` VARCHAR(100),
    `member_count` INT, `adjusted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `auto_adjusted` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenditures` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `description` TEXT,
    `amount` DECIMAL(10,2) NOT NULL, `category` VARCHAR(100), `institution_id` CHAR(36),
    `receipt_url` TEXT, `approved_by` CHAR(36), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` CHAR(36) PRIMARY KEY, `email` VARCHAR(255) NOT NULL, `code` VARCHAR(10) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL, `verified` BOOLEAN DEFAULT FALSE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_verifications_lookup` (`email`, `code`, `verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `school_profiles` (
    `id` CHAR(36) PRIMARY KEY, `school_name` VARCHAR(255) UNIQUE NOT NULL, `affiliation_status` VARCHAR(50) DEFAULT 'Pending',
    `total_members` INT DEFAULT 0, `institution_id` CHAR(36), `validity_expiry` DATE, `last_renewal_date` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compliance_docs` (
    `id` CHAR(36) PRIMARY KEY, `school_id` CHAR(36) NOT NULL, `doc_type` VARCHAR(100) NOT NULL,
    `file_url` TEXT NOT NULL, `is_verified` BOOLEAN DEFAULT FALSE, `verified_by` CHAR(36),
    `verified_at` TIMESTAMP NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compliance_scores` (
    `institution_id` CHAR(36) NOT NULL, `year` INT NOT NULL, `participation_rate` DECIMAL(5,2),
    `hosted_event_count` INT DEFAULT 0, `overall_score` DECIMAL(5,2), `compliance_status` VARCHAR(50) DEFAULT 'compliant',
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`institution_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compliance_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `rule_key` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT, `threshold` DECIMAL(5,2), `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `policy_compliance` (
    `id` CHAR(36) PRIMARY KEY, `institution_id` CHAR(36) NOT NULL, `policy_name` VARCHAR(255) NOT NULL,
    `policy_description` TEXT, `is_compliant` BOOLEAN DEFAULT FALSE, `completed_at` TIMESTAMP NULL,
    `completed_by` CHAR(36), `notes` TEXT, `due_date` DATE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `merch_items` ADD COLUMN IF NOT EXISTS `name` VARCHAR(255);
ALTER TABLE `merch_items` ADD COLUMN IF NOT EXISTS `image` TEXT;
ALTER TABLE `merch_items` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `member_id` CHAR(36);
ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `buyer_name` VARCHAR(255);
ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `buyer_email` VARCHAR(255);
ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `transaction_id` CHAR(36);
ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `notes` TEXT;
ALTER TABLE `merch_orders` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `body` TEXT;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `target_roles` TEXT;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `target_institutions` TEXT;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `is_global` BOOLEAN DEFAULT FALSE;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `scheduled_at` TIMESTAMP NULL;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `expires_at` TIMESTAMP NULL;
ALTER TABLE `announcements` ADD COLUMN IF NOT EXISTS `created_by` CHAR(36);

ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `action_url` TEXT;
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `institution_id` CHAR(36);
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `reference_id` CHAR(36);

CREATE TABLE IF NOT EXISTS `messages` (
    `id` CHAR(36) PRIMARY KEY, `sender_id` CHAR(36) NOT NULL, `receiver_id` CHAR(36) NOT NULL,
    `subject` VARCHAR(255) NOT NULL, `body` TEXT NOT NULL, `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_messages_sender` (`sender_id`), INDEX `idx_messages_receiver` (`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memoranda` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `content` TEXT NOT NULL, `sent_by` CHAR(36) NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `expires_at` TIMESTAMP NULL, `is_active` BOOLEAN DEFAULT TRUE,
    `target_roles` TEXT, `target_institutions` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletters` (
    `id` CHAR(36) PRIMARY KEY, `subject` VARCHAR(255) NOT NULL, `html_content` LONGTEXT NOT NULL,
    `text_content` TEXT, `sent_by` CHAR(36) NOT NULL, `target_roles` TEXT, `target_institutions` TEXT,
    `sent_at` TIMESTAMP NULL, `status` VARCHAR(50) DEFAULT 'draft', `scheduled_for` TIMESTAMP NULL,
    `recipient_count` INT DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `description` TEXT, `category` VARCHAR(100) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL, `file_path` VARCHAR(500) NOT NULL, `file_size` INT, `mime_type` VARCHAR(100),
    `file_hash` VARCHAR(64), `version` INT DEFAULT 1, `uploaded_by` CHAR(36), `institution_id` CHAR(36),
    `is_public` BOOLEAN DEFAULT FALSE, `expires_at` TIMESTAMP NULL, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_documents_category` (`category`), INDEX `idx_documents_inst` (`institution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_versions` (
    `id` CHAR(36) PRIMARY KEY, `document_id` CHAR(36) NOT NULL, `version_number` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL, `file_path` VARCHAR(500) NOT NULL, `file_size` INT, `file_hash` VARCHAR(64),
    `uploaded_by` CHAR(36), `change_notes` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_document_versions_document_id` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `action` TEXT, `table_name` TEXT, `record_id` TEXT,
    `old_data` JSON, `new_data` JSON, `performed_by` CHAR(36), `ip_address` VARCHAR(45),
    `user_agent` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_logs_table` (`table_name`(100)), INDEX `idx_audit_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` CHAR(36) PRIMARY KEY, `log_level` VARCHAR(50) NOT NULL, `category` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL, `details` JSON, `ip_address` VARCHAR(45), `user_id` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_logs` (
    `id` CHAR(36) PRIMARY KEY, `job_id` VARCHAR(100) NOT NULL, `duration` DECIMAL(10,2) DEFAULT 0,
    `success` BOOLEAN DEFAULT TRUE, `output` TEXT, `triggered_by` VARCHAR(100) DEFAULT 'system',
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `member_fees` (
    `id` CHAR(36) PRIMARY KEY, `member_type` VARCHAR(50) UNIQUE NOT NULL, `fee` DECIMAL(10,2) NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `surveys` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `description` TEXT, `questions` JSON NOT NULL,
    `event_id` CHAR(36), `target_roles` TEXT, `is_active` BOOLEAN DEFAULT TRUE, `created_by` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `survey_responses` (
    `id` CHAR(36) PRIMARY KEY, `survey_id` CHAR(36) NOT NULL, `member_id` CHAR(36) NOT NULL,
    `event_id` CHAR(36), `answers` JSON NOT NULL, `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_blasts` (
    `id` CHAR(36) PRIMARY KEY, `campaign_id` CHAR(36), `subject` VARCHAR(255) NOT NULL, `html_content` LONGTEXT NOT NULL,
    `recipient_count` INT DEFAULT 0, `sent_at` TIMESTAMP NULL, `status` VARCHAR(50) DEFAULT 'draft',
    `scheduled_for` TIMESTAMP NULL, `created_by` CHAR(36), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_tracking` (
    `id` CHAR(36) PRIMARY KEY, `email_blast_id` CHAR(36), `member_id` CHAR(36), `opened_at` TIMESTAMP NULL,
    `clicked_at` TIMESTAMP NULL, `bounce_status` VARCHAR(100), `tracking_code` VARCHAR(255) UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `awards_distinctions` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `award_year` VARCHAR(20) NOT NULL,
    `description` TEXT, `category` VARCHAR(100) DEFAULT 'Regional Recognition', `image_url` TEXT,
    `sort_order` INT DEFAULT 0, `is_active` BOOLEAN DEFAULT TRUE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendar_activities` (
    `id` CHAR(36) PRIMARY KEY, `title` VARCHAR(255) NOT NULL, `description` TEXT, `event_date` DATE NOT NULL,
    `venue` VARCHAR(255), `time_text` VARCHAR(100), `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` CHAR(36) PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255), `message` TEXT NOT NULL, `status` VARCHAR(50) DEFAULT 'unread',
    `ip_address` VARCHAR(45), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` CHAR(36) PRIMARY KEY, `user_id` CHAR(36) NOT NULL, `subscription_json` JSON NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE, `last_notified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` CHAR(36) PRIMARY KEY, `email` VARCHAR(255) NOT NULL, `token` VARCHAR(255) UNIQUE NOT NULL,
    `expires_at` TIMESTAMP NOT NULL, `used` BOOLEAN DEFAULT FALSE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pw_resets_token` (`token`), INDEX `idx_pw_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` CHAR(36) PRIMARY KEY, `role` VARCHAR(100) NOT NULL, `permission` VARCHAR(100) NOT NULL,
    `description` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_permission` (`role`, `permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA: OFFICIAL LAGUNA HEI CHAPTERS (All 8 Official Campuses)
-- =====================================================================
INSERT INTO `institutions` (`id`, `email`, `name`, `acronym`, `type`, `address`, `city`, `province`, `contact_email`, `facebook_url`, `status`, `compliance_status`, `membership_count`)
VALUES
    ('b2c3d4e5-f6a7-8901-bcde-f12345678901', 'ecelss@letran-calamba.edu.ph', 'Colegio de San Juan de Letran - Calamba', 'Letran - Calamba', 'college', 'Colegio de San Juan de Letran, Calamba, Philippines, 4027', 'Calamba', 'Laguna', 'ecelss@letran-calamba.edu.ph', 'https://www.facebook.com/ECELSSrocks', 'active', 'compliant', 95),
    ('3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'afece_spc@lspu.edu.ph', 'Laguna State Polytechnic University - San Pablo City Campus', 'LSPU - SPCC', 'university', 'San Pablo City, Philippines, 4000', 'San Pablo City', 'Laguna', 'afece_spc@lspu.edu.ph', 'https://www.facebook.com/LSPUAFECE', 'active', 'compliant', 120),
    ('7d8e9f01-1234-4567-89ab-cdef01234567', 'iecepmmcl@gmail.com', 'Mapúa Malayan Colleges Laguna', 'MMCL', 'college', 'Pulo, Cabuyao, Philippines, 4025', 'Cabuyao', 'Laguna', 'iecepmmcl@gmail.com', 'https://www.facebook.com/iecepmmcl', 'active', 'compliant', 110),
    ('4d5e6f7a-8b9c-0123-def4-567890123456', 'jieceppnc@gmail.com', 'University of Cabuyao (Pamantasan ng Cabuyao)', 'PnC', 'university', 'Cabuyao, Philippines, 4025', 'Cabuyao', 'Laguna', 'jieceppnc@gmail.com', 'https://www.facebook.com/jiecep.pnc.official', 'active', 'compliant', 85),
    ('c3d4e5f6-a7b8-9012-cdef-123456789012', 'officialaeces.pupsrc@gmail.com', 'Polytechnic University of the Philippines - Santa Rosa Campus', 'PUP - Santa Rosa', 'university', 'Room 3-4, PUP-Sta. Rosa, Barangay Tagapo, Santa Rosa, Philippines, 4026', 'Santa Rosa', 'Laguna', 'officialaeces.pupsrc@gmail.com', 'https://www.facebook.com/OfficialAECES', 'active', 'compliant', 130),
    ('e5f6a7b8-c9d0-1234-ef12-345678901234', 'uphsl.pieces@gmail.com', 'University of Perpetual Help System Laguna – Biñan Campus', 'UPHSL - Biñan', 'university', 'National Hi-way, Brgy. Sto. Niño, Biñan, Philippines, 4024', 'Biñan', 'Laguna', 'uphsl.pieces@gmail.com', 'https://www.facebook.com/uphslpieces', 'active', 'compliant', 90),
    ('d4e5f6a7-b8c9-0123-def1-234567890123', 'pieces.uphsd@gmail.com', 'University of Perpetual Help System DALTA - Calamba Campus', 'UPHSD - Calamba', 'university', 'Calamba, Philippines, 4027', 'Calamba', 'Laguna', 'pieces.uphsd@gmail.com', 'https://www.facebook.com/eceperpslp.org', 'active', 'compliant', 75),
    ('1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'official.lspusccecess@gmail.com', 'Laguna State Polytechnic University - Santa Cruz Campus', 'LSPU - SCC', 'university', 'Santa Cruz National High-way, Brgy. Bubukal, Santa Cruz, Laguna', 'Santa Cruz', 'Laguna', 'official.lspusccecess@gmail.com', 'https://www.facebook.com/LSPUSCCECESS', 'active', 'compliant', 150)
ON DUPLICATE KEY UPDATE
    `email` = VALUES(`email`),
    `name` = VALUES(`name`),
    `acronym` = VALUES(`acronym`),
    `address` = VALUES(`address`),
    `city` = VALUES(`city`),
    `facebook_url` = VALUES(`facebook_url`),
    `contact_email` = VALUES(`contact_email`),
    `compliance_status` = VALUES(`compliance_status`),
    `membership_count` = VALUES(`membership_count`);

-- SEED DATA: OFFICIAL EVENTS
INSERT INTO `events` (`id`, `title`, `description`, `event_type`, `venue`, `start_date`, `end_date`, `status`, `registration_fee`, `max_attendees`)
VALUES
    ('2f2f99ce-98e1-49f6-8949-760687189aa6', 'IECEP-LSC Regional Technical Summit 2026', 'Flagship regional technical convention and research exposition for Laguna electronics engineering students.', 'technical_summit', 'Main Auditorium / Online', NOW() - INTERVAL 2 HOUR, NOW() + INTERVAL 8 HOUR, 'published', 150.00, 500),
    ('a9b8c7d6-e5f4-3210-fedc-ba9876543210', 'IECEP Leadership & Chapter Assembly 2026', 'Annual quorum and leadership transition assembly for affiliated Laguna HEI chapters.', 'assembly', 'LSPU Main Hall', NOW() + INTERVAL 7 DAY, NOW() + INTERVAL 7 DAY + INTERVAL 5 HOUR, 'published', 0.00, 300)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `status` = VALUES(`status`);

-- SEED DATA: OFFICIAL AUTH USERS & PROFILES
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `role`, `is_active`, `created_at`, `updated_at`)
VALUES
    ('00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', '$2y$12$mypSMbD3y1XR5uuewBIV5ONYYT3yODWWKdOINbV7/2n86Xu0PupXK', 'IECEP-LSC Regional Admin', 'super_admin', 1, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', '$2y$12$7QzP4zCK2as87c1og7U59et9vvPHU90pCYCNXn.zM7RuH/cti.cXa', 'LSPU - SCC School Officer', 'school_officer', 1, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', '$2y$12$t6adOxlvvxUJa4Lu2U6EX.R5U.2KGRTwQNeE9i51ou9Cw59Ft2vDi', 'Rashed Dizon', 'member', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `password` = VALUES(`password`),
    `full_name` = VALUES(`full_name`),
    `role` = VALUES(`role`),
    `is_active` = VALUES(`is_active`);

INSERT INTO `user_profiles` (`id`, `user_id`, `email`, `full_name`, `role`, `institution_id`, `phone`, `status`)
VALUES
    ('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', 'IECEP-LSC Regional Admin', 'super_admin', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09171234567', 'active'),
    ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', 'LSPU - SCC School Officer', 'school_officer', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09181234567', 'active'),
    ('00000000-0000-0000-0000-000000000003', '00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', 'Rashed Dizon', 'member', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09191234567', 'active')
ON DUPLICATE KEY UPDATE
    `full_name` = VALUES(`full_name`),
    `role` = VALUES(`role`),
    `institution_id` = VALUES(`institution_id`);

INSERT INTO `members` (
    `id`, `membership_id`, `full_name`, `first_name`, `last_name`, `email`,
    `phone`, `institution_id`, `course`, `year_level`, `student_number`,
    `membership_type`, `status`, `payment_status`, `digital_id_hash`
)
VALUES
    (
        '10000000-0000-0000-0000-000000000003',
        '20260001',
        'Rashed Dizon',
        'Rashed',
        'Dizon',
        'rasheddizon7@gmail.com',
        '09191234567',
        '1fe48809-8ac6-4428-a6f1-3025cc47f5bb',
        'BS Electronics Engineering',
        '3rd Year',
        '2022-00123',
        'student',
        'active',
        'paid',
        'a1b2c3d4e5f60103'
    )
ON DUPLICATE KEY UPDATE
    `full_name` = VALUES(`full_name`),
    `membership_id` = VALUES(`membership_id`),
    `institution_id` = VALUES(`institution_id`),
    `year_level` = VALUES(`year_level`),
    `course` = VALUES(`course`),
    `student_number` = VALUES(`student_number`);

INSERT INTO `member_id_counter` (`year`, `last_number`)
VALUES (2026, 1)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`));

-- SEED DATA: SYSTEM SETTINGS & FEES
INSERT INTO `fee_brackets` (`id`, `bracket_name`, `min_members`, `max_members`, `fee`, `is_active`)
VALUES
    ('f1a1b2c3-d4e5-6789-0123-456789abcdef', 'Small', 1, 50, 1500.00, TRUE),
    ('f2a1b2c3-d4e5-6789-0123-456789abcdef', 'Medium', 51, 100, 2000.00, TRUE),
    ('f3a1b2c3-d4e5-6789-0123-456789abcdef', 'Large', 101, 150, 2500.00, TRUE),
    ('f4a1b2c3-d4e5-6789-0123-456789abcdef', 'Enterprise', 151, 999999, 3000.00, TRUE)
ON DUPLICATE KEY UPDATE `fee` = VALUES(`fee`);

INSERT INTO `system_settings` (`id`, `key`, `value`, `description`)
VALUES
    ('s1a1b2c3-d4e5-6789-0123-456789abcdef', 'operational_fee', '800.00', 'Annual organization operational fee per Board Resolution No. 021-2024'),
    ('s2a1b2c3-d4e5-6789-0123-456789abcdef', 'facebook_page_url', 'https://www.facebook.com/IECEPLSC', 'Official IECEP-LSC Facebook URL')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;
