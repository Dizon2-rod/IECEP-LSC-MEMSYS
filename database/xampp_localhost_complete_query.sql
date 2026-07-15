-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE XAMPP LOCALHOST MYSQL/MARIADB QUERY
-- MySQL/MariaDB Database Setup for XAMPP Localhost
-- Created: June 30, 2026
-- =====================================================================

-- =====================================================================
-- DATABASE CREATION
-- =====================================================================
CREATE DATABASE IF NOT EXISTS iecep_lsc_memsys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iecep_lsc_memsys;

-- =====================================================================
-- CORE ENTITIES - LEVEL 1 (No dependencies)
-- =====================================================================

CREATE TABLE IF NOT EXISTS auth_users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS institutions (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    acronym VARCHAR(50),
    type ENUM('university', 'college', 'institute', 'school', 'company', 'organization') NOT NULL,
    address TEXT,
    city VARCHAR(100),
    province VARCHAR(100),
    region VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Philippines',
    contact_person VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    website VARCHAR(255),
    status ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'active',
    affiliation_fee_paid BOOLEAN DEFAULT FALSE,
    compliance_status ENUM('compliant', 'at_risk', 'non_compliant'),
    membership_count INT DEFAULT 0,
    established_year INT,
    accreditation_status VARCHAR(100),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliated_schools (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    facebook_url VARCHAR(500),
    member_count INT DEFAULT 0,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_chapters (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    partnership_status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    website VARCHAR(255),
    description TEXT,
    headquarters_location VARCHAR(255),
    founding_year INT,
    member_count INT DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partnership_status (partnership_status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SYSTEM TABLES - LEVEL 1 (No dependencies)
-- =====================================================================

CREATE TABLE IF NOT EXISTS fee_brackets (
    id CHAR(36) PRIMARY KEY,
    bracket_name VARCHAR(100) UNIQUE NOT NULL,
    min_members INT NOT NULL DEFAULT 0,
    max_members INT,
    affiliation_fee DECIMAL(10,2) NOT NULL,
    per_member_fee DECIMAL(10,2),
    annual_fee DECIMAL(10,2),
    valid_from DATE,
    valid_to DATE,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_min_max (min_members, max_members),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fee_brackets (id, bracket_name, min_members, max_members, affiliation_fee, per_member_fee, annual_fee, is_active, created_at, updated_at) VALUES
    (UUID(), 'Small', 1, 50, 5000.00, 100.00, 1200.00, TRUE, NOW(), NOW()),
    (UUID(), 'Medium', 51, 150, 7500.00, 90.00, 2400.00, TRUE, NOW(), NOW()),
    (UUID(), 'Large', 151, 999999, 10000.00, 80.00, 4200.00, TRUE, NOW(), NOW()),
    (UUID(), 'Enterprise', 501, NULL, NULL, NULL, 6800.00, TRUE, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

CREATE TABLE IF NOT EXISTS compliance_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_key VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    threshold DECIMAL(5,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO compliance_rules (rule_key, description, threshold, is_active)
VALUES 
    ('min_participation', 'Minimum participation rate required', 40.00, TRUE),
    ('required_hosted_events', 'Minimum events to host per year', 1.00, TRUE)
ON DUPLICATE KEY UPDATE is_active = TRUE;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (`key`, value, description)
VALUES 
    ('academic_year_start', '2026-06-01', 'Start date of academic year'),
    ('academic_year_end', '2027-05-31', 'End date of academic year'),
    ('compliance_participation_threshold', '40', 'Minimum participation percentage required')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- =====================================================================
-- USER & PROFILE - LEVEL 2 (Depends on auth_users)
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_profiles (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) UNIQUE,
    institution_id CHAR(36),
    role ENUM('admin', 'school_officer') NOT NULL,
    full_name VARCHAR(255),
    school_name VARCHAR(255),
    contact_phone VARCHAR(50),
    address TEXT,
    membership_status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'active',
    membership_type ENUM('regular', 'student', 'lifetime') DEFAULT 'regular',
    force_password_change BOOLEAN DEFAULT TRUE,
    profile_data JSON,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MEMBERSHIP - LEVEL 3 (Depends on institutions, user_profiles, auth_users)
-- =====================================================================

CREATE TABLE IF NOT EXISTS members (
    id CHAR(36) PRIMARY KEY,
    institution_id CHAR(36),
    user_id CHAR(36) UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    membership_id VARCHAR(50) UNIQUE,
    member_type ENUM('new', 'returning', 'honorary'),
    payment_status BOOLEAN DEFAULT FALSE,
    digital_id_url VARCHAR(500),
    qr_code TEXT,
    digital_id_hash VARCHAR(64),
    year_level VARCHAR(50),
    school_affiliate VARCHAR(255),
    is_new BOOLEAN DEFAULT TRUE,
    validated_at TIMESTAMP NULL,
    validated_by CHAR(36),
    picture_url VARCHAR(500),
    signature_url VARCHAR(500),
    birthday DATE,
    phone VARCHAR(20),
    alumni_status BOOLEAN DEFAULT FALSE,
    alumni_since DATE,
    graduation_year INT,
    membership_expiry DATE,
    last_renewal_date DATE,
    application_id CHAR(36),
    upload_batch_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_institution (institution_id),
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_membership_id (membership_id),
    INDEX idx_digital_id_hash (digital_id_hash),
    INDEX idx_year_level (year_level),
    INDEX idx_payment_status (payment_status),
    INDEX idx_validated_at (validated_at),
    INDEX idx_upload_batch (upload_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_id_counter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT UNIQUE,
    last_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS upload_batches (
    id VARCHAR(50) PRIMARY KEY,
    institution_id CHAR(36) NOT NULL,
    application_id CHAR(36),
    uploaded_by_user_id CHAR(36),
    file_name VARCHAR(255),
    total_rows INT DEFAULT 0,
    validated_rows INT DEFAULT 0,
    status ENUM('pending', 'in_progress', 'completed', 'exported') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_institution (institution_id),
    INDEX idx_uploaded_at (uploaded_at DESC),
    INDEX idx_status (status),
    INDEX idx_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- AFFILIATION & APPLICATIONS - LEVEL 3 (Depends on institutions, user_profiles)
-- =====================================================================

CREATE TABLE IF NOT EXISTS pending_affiliations (
    id CHAR(36) PRIMARY KEY,
    institution_id CHAR(36),
    applicant_id CHAR(36),
    application_type ENUM('new_membership', 'renewal', 'upgrade', 'transfer') NOT NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'requires_revision') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    reviewed_by CHAR(36),
    approval_notes TEXT,
    documents JSON,
    requirements_checklist JSON,
    rejection_reason TEXT,
    all_documents_verified BOOLEAN DEFAULT FALSE,
    directory_validated BOOLEAN DEFAULT FALSE,
    directory_validated_at TIMESTAMP NULL,
    directory_validated_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliation_applications (
    id CHAR(36) PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    org_name VARCHAR(255) NOT NULL,
    rep_name VARCHAR(255) NOT NULL,
    rep_email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'requires_revision') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by CHAR(36),
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (rep_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliation_documents (
    id CHAR(36) PRIMARY KEY,
    application_id CHAR(36) NOT NULL,
    document_type ENUM(
        'letter_of_intent',
        'endorsement_letter',
        'constitution_bylaws',
        'constitution_by_laws',
        'officers_cv',
        'officers_cvs',
        'org_chart',
        'organizational_chart',
        'member_directory'
    ) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    verified BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by CHAR(36),
    verified_at TIMESTAMP NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_doc_type (application_id, document_type),
    INDEX idx_application (application_id),
    INDEX idx_type (document_type),
    INDEX idx_verified (verified),
    INDEX idx_is_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS affiliation_approvals (
    id CHAR(36) PRIMARY KEY,
    affiliation_id CHAR(36) NOT NULL,
    approver_id CHAR(36) NOT NULL,
    approval_level ENUM('initial_review', 'board_review', 'final_approval') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'conditional') NOT NULL,
    comments TEXT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliation (affiliation_id),
    INDEX idx_approver (approver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MEMBER DIRECTORY IMPORTS - LEVEL 4 (Depends on members, affiliation_applications, upload_batches)
-- =====================================================================

CREATE TABLE IF NOT EXISTS member_directory_imports (
    id CHAR(36) PRIMARY KEY,
    batch_id VARCHAR(50),
    application_id CHAR(36),
    sheet_name VARCHAR(100),
    row_index INT,
    full_name VARCHAR(255),
    birthday_clean DATE,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    picture_raw TEXT,
    signature_raw TEXT,
    member_number VARCHAR(50),
    name VARCHAR(255),
    birthday DATE,
    picture_url VARCHAR(500),
    signature_url VARCHAR(500),
    is_valid BOOLEAN DEFAULT FALSE,
    validation_errors TEXT,
    payment_status BOOLEAN DEFAULT FALSE,
    is_paid BOOLEAN DEFAULT FALSE,
    is_new_member BOOLEAN DEFAULT TRUE,
    member_type ENUM('new', 'old'),
    assigned_membership_id VARCHAR(50),
    member_id CHAR(36),
    assigned_at TIMESTAMP NULL,
    assigned_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch_id),
    INDEX idx_application (application_id),
    INDEX idx_email (email),
    INDEX idx_assigned (assigned_membership_id),
    INDEX idx_is_valid (is_valid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL UNIQUE,
    last_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TRANSACTIONS & PAYMENTS - LEVEL 3 (Depends on members, institutions, user_profiles)
-- =====================================================================

CREATE TABLE IF NOT EXISTS transactions (
    id CHAR(36) PRIMARY KEY,
    receipt_id VARCHAR(100) UNIQUE,
    user_id CHAR(36),
    member_id CHAR(36),
    institution_id CHAR(36),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'PHP',
    type ENUM('membership_fee', 'event_fee', 'donation', 'refund', 'penalty') NOT NULL,
    transaction_type VARCHAR(50) DEFAULT 'payment',
    status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('bank_transfer', 'credit_card', 'debit_card', 'online_payment', 'cash', 'check'),
    reference_number VARCHAR(100) UNIQUE,
    receipt_number VARCHAR(100) UNIQUE,
    transaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    notes TEXT,
    receipt_url VARCHAR(500),
    receipt_path VARCHAR(500),
    blockchain_hash VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_member (member_id),
    INDEX idx_institution (institution_id),
    INDEX idx_status (status),
    INDEX idx_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id CHAR(36) PRIMARY KEY,
    member_id CHAR(36),
    batch_id VARCHAR(50),
    amount DECIMAL(10,2),
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    payment_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_member (member_id),
    INDEX idx_batch (batch_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id CHAR(36) PRIMARY KEY,
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    member_id CHAR(36) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    issue_date DATE NOT NULL,
    due_date DATE,
    pdf_path VARCHAR(500),
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_plans (
    id CHAR(36) PRIMARY KEY,
    invoice_id CHAR(36) NOT NULL,
    member_id CHAR(36) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    installments INT DEFAULT 1,
    frequency ENUM('monthly', 'quarterly', 'semi-annual') DEFAULT 'monthly',
    start_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id CHAR(36) PRIMARY KEY,
    fiscal_year INT NOT NULL,
    department VARCHAR(100),
    category VARCHAR(100) NOT NULL,
    budgeted_amount DECIMAL(10,2) NOT NULL,
    actual_amount DECIMAL(10,2) DEFAULT 0,
    variance DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions_archive (
    id CHAR(36) PRIMARY KEY,
    original_transaction_id CHAR(36),
    fiscal_year INT,
    amount DECIMAL(10,2),
    description TEXT,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_gateway_logs (
    id CHAR(36) PRIMARY KEY,
    gateway_name VARCHAR(100) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'PHP',
    status VARCHAR(50),
    response_data JSON,
    member_id CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- EVENTS - LEVEL 3 (Depends on institutions, user_profiles)
-- =====================================================================

CREATE TABLE IF NOT EXISTS events (
    id CHAR(36) PRIMARY KEY,
    institution_id CHAR(36),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type ENUM('conference', 'seminar', 'workshop', 'meeting', 'training', 'social', 'ceremony', 'community', 'chapter_meeting', 'other'),
    start_date TIMESTAMP NOT NULL,
    start_datetime TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    end_datetime TIMESTAMP NOT NULL,
    venue VARCHAR(255),
    location VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    is_online BOOLEAN DEFAULT FALSE,
    online_link VARCHAR(500),
    max_attendees INT,
    max_capacity INT,
    registration_deadline TIMESTAMP NULL,
    registration_fee DECIMAL(10,2) DEFAULT 0,
    fee DECIMAL(10,2) DEFAULT 0,
    requires_payment BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'upcoming', 'ongoing', 'completed', 'cancelled', 'published') DEFAULT 'upcoming',
    organizer_id CHAR(36),
    created_by CHAR(36),
    is_public BOOLEAN DEFAULT TRUE,
    requires_registration BOOLEAN DEFAULT FALSE,
    agenda JSON,
    resources JSON,
    target_roles JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_institution (institution_id),
    INDEX idx_start_date (start_date),
    INDEX idx_start_datetime (start_datetime),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_registrations (
    id CHAR(36) PRIMARY KEY,
    event_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'confirmed', 'attended', 'cancelled', 'waitlist') DEFAULT 'registered',
    payment_status ENUM('pending', 'paid', 'refunded', 'unpaid', 'waived') DEFAULT 'pending',
    special_requirements TEXT,
    checked_in_at TIMESTAMP NULL,
    checked_out_at TIMESTAMP NULL,
    qr_token VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_user (event_id, user_id),
    INDEX idx_event (event_id),
    INDEX idx_user (user_id),
    INDEX idx_qr (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_attachments (
    id CHAR(36) PRIMARY KEY,
    event_id CHAR(36),
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_type VARCHAR(100),
    uploaded_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_attendees (
    id CHAR(36) PRIMARY KEY,
    event_id CHAR(36) NOT NULL,
    member_id CHAR(36) NOT NULL,
    status ENUM('registered', 'attended', 'absent', 'cancelled') DEFAULT 'registered',
    check_in_time TIMESTAMP NULL,
    UNIQUE KEY unique_event_member (event_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_logistics (
    id CHAR(36) PRIMARY KEY,
    event_id CHAR(36) NOT NULL,
    venue_name VARCHAR(255),
    venue_address TEXT,
    capacity INT,
    catering_needed BOOLEAN DEFAULT FALSE,
    transport_needed BOOLEAN DEFAULT FALSE,
    equipment_needed JSON,
    budget DECIMAL(10,2),
    status ENUM('planning', 'confirmed', 'completed', 'cancelled') DEFAULT 'planning',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- NOTIFICATIONS & MESSAGING - LEVEL 3 (Depends on auth_users, institutions)
-- =====================================================================

CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('system', 'alert', 'success', 'info', 'warning') DEFAULT 'system',
    link VARCHAR(500),
    action_url VARCHAR(500),
    institution_id CHAR(36),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    subscription_json JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_notified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id CHAR(36) PRIMARY KEY,
    template_key VARCHAR(100) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_body TEXT NOT NULL,
    text_body TEXT,
    variables JSON,
    created_by CHAR(36),
    updated_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- AUDIT & COMPLIANCE - LEVEL 3 (Depends on auth_users, institutions)
-- =====================================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36),
    action VARCHAR(255) NOT NULL,
    details JSON,
    affected_entity_type VARCHAR(100),
    affected_entity_id CHAR(36),
    ip_address VARCHAR(45),
    user_agent TEXT,
    table_name VARCHAR(100),
    record_id VARCHAR(100),
    old_data JSON,
    new_data JSON,
    performed_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at DESC),
    INDEX idx_affected_entity (affected_entity_type, affected_entity_id),
    INDEX idx_table (table_name),
    INDEX idx_record (record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compliance_scores (
    institution_id CHAR(36) NOT NULL,
    year INT NOT NULL,
    participation_rate DECIMAL(5,2),
    hosted_event_count INT DEFAULT 0,
    overall_score DECIMAL(5,2),
    last_updated TIMESTAMP NULL,
    PRIMARY KEY (institution_id, year),
    INDEX idx_year (year),
    INDEX idx_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_audit_trail (
    id CHAR(36) PRIMARY KEY,
    transaction_id CHAR(36),
    action_type VARCHAR(100),
    old_values JSON,
    new_values JSON,
    audit_user_id CHAR(36),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compliance_checks (
    id CHAR(36) PRIMARY KEY,
    check_type VARCHAR(100) NOT NULL,
    target_entity_id CHAR(36),
    target_entity_type VARCHAR(100),
    status ENUM('pending', 'passed', 'failed', 'exception') DEFAULT 'pending',
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ANNOUNCEMENTS & CONTENT - LEVEL 3 (Depends on user_profiles)
-- =====================================================================

CREATE TABLE IF NOT EXISTS announcements (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    target_roles JSON,
    target_institutions JSON,
    is_global BOOLEAN DEFAULT FALSE,
    scheduled_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_global (is_global)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_announcements (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    scheduled_for TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    status ENUM('scheduled', 'published', 'cancelled') DEFAULT 'scheduled',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_workflow (
    id CHAR(36) PRIMARY KEY,
    content_id CHAR(36) NOT NULL,
    content_type VARCHAR(100) NOT NULL,
    current_state ENUM('draft', 'review', 'approved', 'published', 'archived') DEFAULT 'draft',
    created_by CHAR(36),
    submitted_by CHAR(36),
    approved_by CHAR(36),
    submitted_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CERTIFICATES & DIGITAL CREDENTIALS - LEVEL 4 (Depends on members, events)
-- =====================================================================

CREATE TABLE IF NOT EXISTS certificates (
    id CHAR(36) PRIMARY KEY,
    member_id CHAR(36),
    event_id CHAR(36),
    issue_date DATE,
    certificate_number VARCHAR(100) UNIQUE,
    blockchain_hash VARCHAR(255),
    file_path VARCHAR(500),
    template_type VARCHAR(100) DEFAULT 'participation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member (member_id),
    INDEX idx_event (event_id),
    INDEX idx_number (certificate_number),
    INDEX idx_hash (blockchain_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- BLOCKCHAIN - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS blockchain_records (
    id CHAR(36) PRIMARY KEY,
    entity_type VARCHAR(100) NOT NULL,
    entity_id CHAR(36) NOT NULL,
    transaction_hash VARCHAR(255) UNIQUE,
    record_hash VARCHAR(255),
    block_number INT,
    confirmed BOOLEAN DEFAULT FALSE,
    institution_id CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_hash (transaction_hash),
    INDEX idx_confirmed (confirmed),
    INDEX idx_institution_id (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COLLABORATION - LEVEL 3 (Depends on partner_chapters, auth_users)
-- =====================================================================

CREATE TABLE IF NOT EXISTS collaboration_posts (
    id CHAR(36) PRIMARY KEY,
    chapter_id CHAR(36) NOT NULL,
    content TEXT NOT NULL,
    created_by CHAR(36) NOT NULL,
    attachment_url VARCHAR(500),
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chapter_id (chapter_id),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ADMIN & SUPER-ADMIN FEATURES - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS system_logs (
    id CHAR(36) PRIMARY KEY,
    log_level VARCHAR(50) NOT NULL,
    category VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    user_id CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (log_level),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id CHAR(36) PRIMARY KEY,
    role VARCHAR(100) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_jobs (
    id CHAR(36) PRIMARY KEY,
    job_name VARCHAR(100) UNIQUE NOT NULL,
    handler_file VARCHAR(255) NOT NULL,
    schedule VARCHAR(100) NOT NULL,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impersonation_sessions (
    id CHAR(36) PRIMARY KEY,
    admin_user_id CHAR(36) NOT NULL,
    impersonated_user_id CHAR(36) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    actions_taken JSON,
    notes TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MEMBER FEATURES - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_reminder_settings (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL UNIQUE,
    affiliation_renewal_days INT DEFAULT 30,
    payment_due_days INT DEFAULT 7,
    event_reminder_days INT DEFAULT 3,
    push_notifications_enabled BOOLEAN DEFAULT TRUE,
    email_notifications_enabled BOOLEAN DEFAULT TRUE,
    sms_notifications_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SCHOOL OFFICER FEATURES - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS schools (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    contact_person VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    website VARCHAR(255),
    logo_path VARCHAR(500),
    member_count INT DEFAULT 0,
    active_members INT DEFAULT 0,
    alumni_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS temp_school_members (
    id CHAR(36) PRIMARY KEY,
    school_id CHAR(36) NOT NULL,
    upload_batch_id CHAR(36),
    email VARCHAR(255),
    full_name VARCHAR(255),
    student_id VARCHAR(100),
    program VARCHAR(100),
    year_level INT,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECRETARY FEATURES - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS documents (
    id CHAR(36) PRIMARY KEY,
    parent_id CHAR(36),
    title VARCHAR(255) NOT NULL,
    content TEXT,
    file_path VARCHAR(500),
    version INT DEFAULT 1,
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS minutes_templates (
    id CHAR(36) PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    sections JSON,
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS committee_tasks (
    id CHAR(36) PRIMARY KEY,
    committee_id CHAR(36),
    task_title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to CHAR(36),
    assigned_by CHAR(36),
    due_date DATE,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    depends_on CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- CREATIVES COMMITTEE - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS scheduled_announcements_creatives (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    scheduled_for TIMESTAMP NULL,
    published_at TIMESTAMP NULL,
    status ENUM('scheduled', 'published', 'cancelled') DEFAULT 'scheduled',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MARKETING COMMITTEE - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id CHAR(36) PRIMARY KEY,
    campaign_name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(10,2),
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_blasts (
    id CHAR(36) PRIMARY KEY,
    campaign_id CHAR(36),
    subject VARCHAR(255),
    html_content TEXT,
    recipient_count INT,
    sent_at TIMESTAMP NULL,
    status ENUM('draft', 'sent', 'failed', 'scheduled') DEFAULT 'draft',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_tracking (
    id CHAR(36) PRIMARY KEY,
    email_blast_id CHAR(36),
    member_id CHAR(36),
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    bounce_status VARCHAR(50),
    tracking_code VARCHAR(100) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id CHAR(36) PRIMARY KEY,
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    organization VARCHAR(255),
    source VARCHAR(100),
    status ENUM('new', 'contacted', 'interested', 'converted', 'rejected') DEFAULT 'new',
    notes TEXT,
    assigned_to CHAR(36),
    converted_member_id CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_posts (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    scheduled_for TIMESTAMP NULL,
    posted_at TIMESTAMP NULL,
    platform ENUM('facebook', 'twitter', 'instagram', 'linkedin'),
    status ENUM('scheduled', 'posted', 'failed', 'cancelled') DEFAULT 'scheduled',
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- LOGISTICS COMMITTEE - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS inventory_items (
    id CHAR(36) PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    quantity INT DEFAULT 0,
    reorder_level INT,
    unit_cost DECIMAL(10,2),
    location VARCHAR(255),
    supplier_id CHAR(36),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendors (
    id CHAR(36) PRIMARY KEY,
    vendor_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    service_category VARCHAR(100),
    rating FLOAT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_loans (
    id CHAR(36) PRIMARY KEY,
    asset_id CHAR(36),
    borrower_id CHAR(36) NOT NULL,
    checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    return_date TIMESTAMP NULL,
    condition_on_checkout TEXT,
    condition_on_return TEXT,
    status ENUM('loaned', 'returned', 'overdue') DEFAULT 'loaned',
    notes TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- REGISTRATION COMMITTEE - LEVEL 3
-- =====================================================================

CREATE TABLE IF NOT EXISTS potential_duplicates (
    id CHAR(36) PRIMARY KEY,
    primary_record_id CHAR(36) NOT NULL,
    potential_duplicate_id CHAR(36) NOT NULL,
    similarity_score FLOAT DEFAULT 0,
    fields_matched JSON,
    status ENUM('unreviewed', 'confirmed_duplicate', 'false_positive') DEFAULT 'unreviewed',
    reviewed_by CHAR(36),
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS temp_user_imports (
    id CHAR(36) PRIMARY KEY,
    import_batch_id CHAR(36) NOT NULL,
    email VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL,
    institution_id CHAR(36),
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- COMPLETION
-- =====================================================================

-- Note: MySQL/MariaDB does not support Row Level Security (RLS) like PostgreSQL
-- Security should be implemented at the application level or using views

-- Note: MySQL/MariaDB does not have built-in publication/subscription for realtime
-- Realtime features should be implemented using websockets or polling

SELECT 'IECEP-LSC MEMSYS XAMPP Localhost Complete Query - Completed' AS status;
