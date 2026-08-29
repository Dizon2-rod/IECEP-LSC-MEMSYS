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

-- 2. USER PROFILES & AUTH
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` CHAR(36) PRIMARY KEY,
    `user_id` CHAR(36) UNIQUE,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'school_officer', 'member', 'auditor', 'treasurer', 'guest') DEFAULT 'member',
    `institution_id` CHAR(36),
    `phone` VARCHAR(50),
    `avatar_url` TEXT,
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
    `status` ENUM('active', 'inactive', 'pending', 'expired', 'suspended') DEFAULT 'active',
    `digital_id_hash` VARCHAR(255),
    `qr_code_url` TEXT,
    `joined_date` DATE DEFAULT (CURRENT_DATE),
    `expiration_date` DATE,
    `metadata` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_members_mem_id` (`membership_id`),
    INDEX `idx_members_inst` (`institution_id`),
    INDEX `idx_members_status` (`status`),
    CONSTRAINT `fk_members_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MEMBER ID COUNTER (For auto-generating sequential IECEP-2026-XXXX)
CREATE TABLE IF NOT EXISTS `member_id_counter` (
    `year` INT PRIMARY KEY,
    `last_number` INT NOT NULL DEFAULT 0,
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
    `status` ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') DEFAULT 'published',
    `institution_id` CHAR(36),
    `created_by` CHAR(36),
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
    `previous_hash` VARCHAR(255),
    `merkle_root` VARCHAR(255),
    `data_json` JSON NOT NULL,
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
    `status` ENUM('pending', 'completed', 'verified', 'rejected', 'refunded') DEFAULT 'completed',
    `notes` TEXT,
    `verified_by` CHAR(36),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tx_status` (`status`),
    INDEX `idx_tx_member` (`member_id`),
    CONSTRAINT `fk_tx_member` FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tx_inst` FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tx_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 10. VERIFICATION CODES (Email 2FA & Application Validation)
CREATE TABLE IF NOT EXISTS `verification_codes` (
    `id` CHAR(36) PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `purpose` VARCHAR(50) DEFAULT 'affiliation',
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
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
    `badge_text` VARCHAR(50),
    `sort_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    -- 1. Colegio de San Juan de Letran - Calamba (ECELSS)
    ('50000000-0000-0000-0000-000000000001', 'IECEP-2026-0501', 'Alyssa Reyes', 'Alyssa', 'Reyes', 'alyssa.reyes@letran-calamba.edu.ph', '09215550001', 'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'BS Electronics Engineering', '4th Year', '2022-05001', 'student', 'active', 'paid', 'a1b2c3d4e5f60501'),
    ('50000000-0000-0000-0000-000000000002', 'IECEP-2026-0502', 'Gabriel Santos', 'Gabriel', 'Santos', 'gabriel.santos@letran-calamba.edu.ph', '09215550002', 'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'BS Electronics Engineering', '2nd Year', '2024-05002', 'student', 'active', 'paid', 'a1b2c3d4e5f60502'),

    -- 2. LSPU - San Pablo City Campus (AFECE)
    ('20000000-0000-0000-0000-000000000001', 'IECEP-2026-0201', 'Patricia Reyes', 'Patricia', 'Reyes', 'patricia.spcc@lspu.edu.ph', '09182220001', '3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'BS Electronics Engineering', '1st Year', '2025-02001', 'student', 'active', 'paid', 'a1b2c3d4e5f60201'),
    ('20000000-0000-0000-0000-000000000002', 'IECEP-2026-0202', 'Angelo Bautista', 'Angelo', 'Bautista', 'angelo.spcc@lspu.edu.ph', '09182220002', '3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'BS Electronics Engineering', '3rd Year', '2023-02002', 'student', 'active', 'paid', 'a1b2c3d4e5f60202'),

    -- 3. Mapúa Malayan Colleges Laguna (IECEP - MMCL)
    ('30000000-0000-0000-0000-000000000001', 'IECEP-2026-0301', 'Kyla Ramos', 'Kyla', 'Ramos', 'kyla.ramos@mmcl.edu.ph', '09193330001', '7d8e9f01-1234-4567-89ab-cdef01234567', 'BS Electronics Engineering', '2nd Year', '2024-03001', 'student', 'active', 'paid', 'a1b2c3d4e5f60301'),
    ('30000000-0000-0000-0000-000000000002', 'IECEP-2026-0302', 'Justin Tan', 'Justin', 'Tan', 'justin.tan@mmcl.edu.ph', '09193330002', '7d8e9f01-1234-4567-89ab-cdef01234567', 'BS Electronics Engineering', '4th Year', '2022-03002', 'student', 'active', 'paid', 'a1b2c3d4e5f60302'),

    -- 4. University of Cabuyao / Pamantasan ng Cabuyao (OECES / PnC)
    ('40000000-0000-0000-0000-000000000001', 'IECEP-2026-0401', 'Christian Flores', 'Christian', 'Flores', 'christian.pnc@gmail.com', '09204440001', '4d5e6f7a-8b9c-0123-def4-567890123456', 'BS Electronics Engineering', '1st Year', '2025-04001', 'student', 'active', 'paid', 'a1b2c3d4e5f60401'),
    ('40000000-0000-0000-0000-000000000002', 'IECEP-2026-0402', 'Erika Mae Ramos', 'Erika Mae', 'Ramos', 'erika.pnc@gmail.com', '09204440002', '4d5e6f7a-8b9c-0123-def4-567890123456', 'BS Electronics Engineering', '3rd Year', '2023-04002', 'student', 'active', 'paid', 'a1b2c3d4e5f60402'),

    -- 5. PUP - Santa Rosa Campus (AECES)
    ('60000000-0000-0000-0000-000000000001', 'IECEP-2026-0601', 'John Paul Castro', 'John Paul', 'Castro', 'jp.castro@pup.edu.ph', '09226660001', 'c3d4e5f6-a7b8-9012-cdef-123456789012', 'BS Electronics Engineering', '2nd Year', '2024-06001', 'student', 'active', 'paid', 'a1b2c3d4e5f60601'),
    ('60000000-0000-0000-0000-000000000002', 'IECEP-2026-0602', 'Nicole Mendoza', 'Nicole', 'Mendoza', 'nicole.castro@pup.edu.ph', '09226660002', 'c3d4e5f6-a7b8-9012-cdef-123456789012', 'BS Electronics Engineering', '4th Year', '2022-06002', 'student', 'active', 'paid', 'a1b2c3d4e5f60602'),

    -- 6. UPHSL – Biñan Campus (PIECES)
    ('70000000-0000-0000-0000-000000000001', 'IECEP-2026-0701', 'Joshua Garcia', 'Joshua', 'Garcia', 'joshua.uphsl@gmail.com', '09237770001', 'e5f6a7b8-c9d0-1234-ef12-345678901234', 'BS Electronics Engineering', '3rd Year', '2023-07001', 'student', 'active', 'paid', 'a1b2c3d4e5f60701'),

    -- 7. UPHSD - Calamba Campus (ECESS - UPHSD)
    ('80000000-0000-0000-0000-000000000001', 'IECEP-2026-0801', 'Marielle Cruz', 'Marielle', 'Cruz', 'marielle.uphsd@gmail.com', '09248880001', 'd4e5f6a7-b8c9-0123-def1-234567890123', 'BS Electronics Engineering', '1st Year', '2025-08001', 'student', 'active', 'paid', 'a1b2c3d4e5f60801'),

    -- 8. LSPU - Santa Cruz Campus (ECESS - LSPU SCC)
    ('10000000-0000-0000-0000-000000000001', 'IECEP-2026-0101', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', 'juan.scc1@lspu.edu.ph', '09171110001', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '1st Year', '2025-01001', 'student', 'active', 'paid', 'a1b2c3d4e5f60101'),
    ('10000000-0000-0000-0000-000000000002', 'IECEP-2026-0102', 'Maria Santos', 'Maria', 'Santos', 'maria.scc2@lspu.edu.ph', '09171110002', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '2nd Year', '2024-01002', 'student', 'active', 'paid', 'a1b2c3d4e5f60102'),
    ('10000000-0000-0000-0000-000000000003', 'IECEP-2026-0103', 'Rashed Dizon', 'Rashed', 'Dizon', 'rasheddizon7@gmail.com', '09191234567', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '3rd Year', '2022-00123', 'student', 'active', 'paid', 'a1b2c3d4e5f60103'),
    ('10000000-0000-0000-0000-000000000004', 'IECEP-2026-0104', 'Carlo Mendoza', 'Carlo', 'Mendoza', 'carlo.scc4@lspu.edu.ph', '09171110004', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '4th Year', '2022-01004', 'student', 'active', 'paid', 'a1b2c3d4e5f60104')
ON DUPLICATE KEY UPDATE
    `full_name` = VALUES(`full_name`),
    `membership_id` = VALUES(`membership_id`),
    `institution_id` = VALUES(`institution_id`),
    `year_level` = VALUES(`year_level`),
    `course` = VALUES(`course`),
    `student_number` = VALUES(`student_number`);

INSERT INTO `member_id_counter` (`year`, `last_number`)
VALUES (2026, 14)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`));

-- SEED DATA: INITIAL ATTENDANCE & BLOCKCHAIN LOG
INSERT INTO `event_attendees` (`id`, `event_id`, `member_id`, `status`, `check_in_time`)
VALUES
    ('00000000-0000-0000-0000-000000000021', '2f2f99ce-98e1-49f6-8949-760687189aa6', '10000000-0000-0000-0000-000000000001', 'attended', NOW() - INTERVAL 45 MINUTE),
    ('00000000-0000-0000-0000-000000000022', '2f2f99ce-98e1-49f6-8949-760687189aa6', '10000000-0000-0000-0000-000000000003', 'attended', NOW() - INTERVAL 30 MINUTE)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `blockchain_records` (`id`, `block_index`, `entity_type`, `entity_id`, `transaction_hash`, `record_hash`, `data_json`, `confirmed`)
VALUES
    ('00000000-0000-0000-0000-000000000031', 1, 'event_attendance', '10000000-0000-0000-0000-000000000001', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f01234', '{"event": "IECEP-LSC Regional Technical Summit 2026", "student": "Juan Dela Cruz", "membership_id": "IECEP-2026-0101"}', TRUE)
ON DUPLICATE KEY UPDATE `confirmed` = VALUES(`confirmed`);

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
