-- =====================================================================
-- IECEP-LSC MEMSYS - ADDITIONAL TABLES FOR NEW MODULES
-- MySQL/MariaDB Database Setup
-- =====================================================================

-- =====================================================================
-- MESSAGES TABLE (Module 9.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS messages (
    id CHAR(36) PRIMARY KEY,
    sender_id CHAR(36) NOT NULL,
    receiver_id CHAR(36) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MEMORANDA TABLE (Module 6.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS memoranda (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sent_by CHAR(36) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    target_roles JSON,
    target_institutions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sent_by (sent_by),
    INDEX idx_sent_at (sent_at DESC),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENTS TABLE WITH VERSION TRACKING (Module 6.1, 8.1, 8.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS documents (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('affiliation', 'member_records', 'financial', 'compliance', 'memoranda', 'policy', 'constitution', 'bylaws', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    file_hash VARCHAR(64),
    version INT DEFAULT 1,
    uploaded_by CHAR(36),
    institution_id CHAR(36),
    is_public BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_institution (institution_id),
    INDEX idx_version (version),
    INDEX idx_file_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DOCUMENT VERSIONS TABLE (Module 8.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS document_versions (
    id CHAR(36) PRIMARY KEY,
    document_id CHAR(36) NOT NULL,
    version_number INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_hash VARCHAR(64),
    uploaded_by CHAR(36),
    change_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_id (document_id),
    INDEX idx_version_number (version_number),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- POLICY COMPLIANCE TABLE (Module 6.3)
-- =====================================================================
CREATE TABLE IF NOT EXISTS policy_compliance (
    id CHAR(36) PRIMARY KEY,
    institution_id CHAR(36) NOT NULL,
    policy_name VARCHAR(255) NOT NULL,
    policy_description TEXT,
    is_compliant BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    completed_by CHAR(36),
    notes TEXT,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_institution (institution_id),
    INDEX idx_is_compliant (is_compliant),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- NEWSLETTER TABLE (Module 9.3)
-- =====================================================================
CREATE TABLE IF NOT EXISTS newsletters (
    id CHAR(36) PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    html_content TEXT NOT NULL,
    text_content TEXT,
    sent_by CHAR(36) NOT NULL,
    target_roles JSON,
    target_institutions JSON,
    sent_at TIMESTAMP NULL,
    status ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft',
    scheduled_for TIMESTAMP NULL,
    recipient_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sent_by (sent_by),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COMPLETION
-- =====================================================================
SELECT 'Additional tables for new modules completed' AS status;
