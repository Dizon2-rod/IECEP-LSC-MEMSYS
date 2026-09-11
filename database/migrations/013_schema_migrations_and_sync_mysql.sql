-- =====================================================
-- Migration 013: Schema Migrations Tracking & Data Sync Parity
-- Platform: MySQL/MariaDB (XAMPP) — Idempotent
-- =====================================================

-- 1. Create schema_migrations tracking table
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` VARCHAR(255) NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed all migration history up to 013
INSERT IGNORE INTO `schema_migrations` (`version`, `name`, `applied_at`) VALUES
    ('001', 'initial_schema', NOW()),
    ('002', 'events_compliance', NOW()),
    ('003', 'cbl_compliance_system', NOW()),
    ('004', 'auto_generate_accounts', NOW()),
    ('005', 'pending_affiliations', NOW()),
    ('006', 'member_id_counter', NOW()),
    ('007', 'verification_codes', NOW()),
    ('008', 'merchandise', NOW()),
    ('009', 'fee_brackets_system_settings', NOW()),
    ('010', 'email_verifications', NOW()),
    ('011', 'institution_documents', NOW()),
    ('012', 'align_cbl_2025', NOW()),
    ('013', 'schema_migrations_and_sync', NOW());

-- 3. Ensure last_updated exists on compliance_scores
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compliance_scores' AND COLUMN_NAME = 'last_updated');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `compliance_scores` ADD COLUMN `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Ensure attempts column exists on email_verifications
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verifications' AND COLUMN_NAME = 'attempts');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `email_verifications` ADD COLUMN `attempts` INT DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
