-- =====================================================
-- Migration 009: Fee Brackets & System Settings
-- Board Resolution No. 021-2024
-- MySQL/MariaDB (XAMPP) — Idempotent
-- =====================================================

-- 1. Fee Brackets Table
CREATE TABLE IF NOT EXISTS fee_brackets (
    id CHAR(36) PRIMARY KEY,
    bracket_name VARCHAR(50) NOT NULL UNIQUE,
    min_members INT NOT NULL,
    max_members INT,
    fee DECIMAL(10,2) NOT NULL,
    per_member_fee DECIMAL(10,2) DEFAULT 0.00,
    annual_fee DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_min_members (min_members)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upsert official fee brackets
INSERT INTO fee_brackets (id, bracket_name, min_members, max_members, fee, per_member_fee, annual_fee, is_active)
VALUES
    (UUID(), 'Small',      1,   50,  1500.00, 0.00, 0.00, TRUE),
    (UUID(), 'Medium',    51,  100,  2000.00, 0.00, 0.00, TRUE),
    (UUID(), 'Large',    101,  150,  2500.00, 0.00, 0.00, TRUE),
    (UUID(), 'Enterprise', 151, 999999, 3000.00, 0.00, 0.00, TRUE)
ON DUPLICATE KEY UPDATE
    min_members = VALUES(min_members),
    max_members = VALUES(max_members),
    fee = VALUES(fee),
    per_member_fee = VALUES(per_member_fee),
    annual_fee = VALUES(annual_fee),
    is_active = VALUES(is_active);

-- 2. Member Fees Table
CREATE TABLE IF NOT EXISTS member_fees (
    id CHAR(36) PRIMARY KEY,
    member_type VARCHAR(50) NOT NULL UNIQUE,
    fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO member_fees (id, member_type, fee, is_active)
VALUES
    (UUID(), 'new',       250.00, TRUE),
    (UUID(), 'returning', 200.00, TRUE),
    (UUID(), 'honorary',  300.00, TRUE)
ON DUPLICATE KEY UPDATE
    fee = VALUES(fee),
    is_active = VALUES(is_active);

-- 3. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id CHAR(36) PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (id, setting_key, setting_value, description)
VALUES
    (UUID(), 'operational_fee', '800.00', 'Operational and activity fee per organization, collected upon each renewal of affiliation every new school year (Board Resolution No. 021-2024)')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- 4. Deactivate any stale brackets not matching official names
UPDATE fee_brackets SET is_active = FALSE
WHERE bracket_name NOT IN ('Small', 'Medium', 'Large', 'Enterprise')
  AND is_active = TRUE;
