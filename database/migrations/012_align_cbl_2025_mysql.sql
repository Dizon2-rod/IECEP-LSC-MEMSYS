-- =====================================================
-- Migration 012: Align Affiliation & Compliance with 2025 Constitution & By-Laws
-- Platform: MySQL/MariaDB (XAMPP) — Idempotent
-- Board Resolution No. 021-2024 & CBL Articles IV & V
-- =====================================================

-- 1. Ensure Fee Brackets Table and Upsert Official 2025 Brackets
CREATE TABLE IF NOT EXISTS `fee_brackets` (
    `id` CHAR(36) PRIMARY KEY,
    `bracket_name` VARCHAR(50) NOT NULL UNIQUE,
    `min_members` INT NOT NULL,
    `max_members` INT,
    `fee` DECIMAL(10,2) NOT NULL,
    `per_member_fee` DECIMAL(10,2) DEFAULT 0.00,
    `annual_fee` DECIMAL(10,2) DEFAULT 0.00,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_fee_brackets_active` (`is_active`),
    INDEX `idx_fee_brackets_min_members` (`min_members`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fee_brackets` (`id`, `bracket_name`, `min_members`, `max_members`, `fee`, `per_member_fee`, `annual_fee`, `is_active`)
VALUES
    (UUID(), 'Small',      1,   50,  1500.00, 0.00, 0.00, TRUE),
    (UUID(), 'Medium',    51,  100,  2000.00, 0.00, 0.00, TRUE),
    (UUID(), 'Large',    101,  150,  2500.00, 0.00, 0.00, TRUE),
    (UUID(), 'Enterprise', 151, 999999, 3000.00, 0.00, 0.00, TRUE)
ON DUPLICATE KEY UPDATE
    `min_members` = VALUES(`min_members`),
    `max_members` = VALUES(`max_members`),
    `fee` = VALUES(`fee`),
    `per_member_fee` = VALUES(`per_member_fee`),
    `annual_fee` = VALUES(`annual_fee`),
    `is_active` = VALUES(`is_active`);

-- 2. Ensure Member Fees Table and Upsert 2025 Member Dues
CREATE TABLE IF NOT EXISTS `member_fees` (
    `id` CHAR(36) PRIMARY KEY,
    `member_type` VARCHAR(50) NOT NULL UNIQUE,
    `fee` DECIMAL(10,2) NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `member_fees` (`id`, `member_type`, `fee`, `is_active`)
VALUES
    (UUID(), 'new',       250.00, TRUE),
    (UUID(), 'returning', 200.00, TRUE),
    (UUID(), 'honorary',  300.00, TRUE)
ON DUPLICATE KEY UPDATE
    `fee` = VALUES(`fee`),
    `is_active` = VALUES(`is_active`);

-- 3. Ensure System Settings Table and Upsert CBL 2025 Rates
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` CHAR(36) PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`id`, `key`, `value`, `description`)
VALUES
    (UUID(), 'operational_fee', '800.00', 'Annual organization operational fee per Board Resolution No. 021-2024 (Art. IV)'),
    (UUID(), 'returning_member_fee', '200.00', 'Individual membership due for returning (old) members per CBL Art. IV Sec. 2'),
    (UUID(), 'new_member_fee', '250.00', 'Individual membership due for new members per CBL Art. IV Sec. 2'),
    (UUID(), 'honorary_member_fee', '300.00', 'Individual membership due for honorary members per CBL Art. IV Sec. 2')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `description` = VALUES(`description`);

-- 4. Compliance Rules Table (Art. V Sec. 3)
CREATE TABLE IF NOT EXISTS `compliance_rules` (
    `id` CHAR(36) PRIMARY KEY,
    `rule_key` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT,
    `threshold` DECIMAL(5,2),
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `compliance_rules` (`id`, `rule_key`, `description`, `threshold`, `is_active`)
VALUES 
    (UUID(), 'min_participation', 'Minimum participation rate required for chapter compliance (40%)', 40.00, TRUE),
    (UUID(), 'required_hosted_events', 'Minimum hosted or venue events per academic year (1)', 1.00, TRUE)
ON DUPLICATE KEY UPDATE
    `threshold` = VALUES(`threshold`),
    `description` = VALUES(`description`),
    `is_active` = VALUES(`is_active`);

-- 5. Add venue_institution_id to Events Table to support venue hosting credit (Art. V Sec. 3)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'venue_institution_id');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `events` ADD COLUMN `venue_institution_id` CHAR(36) NULL, ADD INDEX `idx_events_venue_institution` (`venue_institution_id`)', 'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Ensure institutions and compliance_scores support compliance_status ('compliant', 'at_risk', 'non_compliant')
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'institutions' AND COLUMN_NAME = 'compliance_status');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `institutions` ADD COLUMN `compliance_status` ENUM(\'compliant\', \'at_risk\', \'non_compliant\') DEFAULT \'compliant\'', 'ALTER TABLE `institutions` MODIFY COLUMN `compliance_status` ENUM(\'compliant\', \'at_risk\', \'non_compliant\') DEFAULT \'compliant\'');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compliance_scores' AND COLUMN_NAME = 'compliance_status');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `compliance_scores` ADD COLUMN `compliance_status` ENUM(\'compliant\', \'at_risk\', \'non_compliant\') DEFAULT \'compliant\'', 'ALTER TABLE `compliance_scores` MODIFY COLUMN `compliance_status` ENUM(\'compliant\', \'at_risk\', \'non_compliant\') DEFAULT \'compliant\'');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Ensure members table has membership_expiry for 1 academic year validity
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'membership_expiry');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE `members` ADD COLUMN `membership_expiry` DATE NULL', 'SELECT 1');
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
