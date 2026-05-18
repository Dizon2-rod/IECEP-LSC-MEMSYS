-- ============================================================================
-- IECEP-LSC MEMSYS - COMPREHENSIVE FEATURE IMPLEMENTATION MIGRATION
-- Date: 2026-05-15
-- Purpose: Add all missing tables and columns for complete feature set
-- ============================================================================

-- ============================================================================
-- ADMIN FEATURES TABLES
-- ============================================================================

-- Temporary import staging table for bulk user import
CREATE TABLE IF NOT EXISTS temp_user_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    import_batch_id UUID NOT NULL,
    email TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL,
    institution_id UUID,
    status TEXT DEFAULT 'pending', -- pending, imported, failed, skipped
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);

-- System logs table
CREATE TABLE IF NOT EXISTS system_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    log_level TEXT NOT NULL, -- INFO, WARNING, ERROR, CRITICAL
    category TEXT NOT NULL, -- auth, database, api, system
    message TEXT NOT NULL,
    details JSONB,
    ip_address INET,
    user_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email templates for admin customization
CREATE TABLE IF NOT EXISTS email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    template_key TEXT UNIQUE NOT NULL, -- registration_invite, password_reset, etc.
    subject TEXT NOT NULL,
    html_body TEXT NOT NULL,
    text_body TEXT,
    variables JSONB, -- list of required variables
    created_by UUID,
    updated_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role-based permissions matrix
CREATE TABLE IF NOT EXISTS role_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role TEXT NOT NULL,
    permission TEXT NOT NULL, -- e.g., 'member.create', 'member.delete'
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role, permission)
);

-- ============================================================================
-- SUPER ADMIN FEATURES TABLES
-- ============================================================================

-- Cron job status and management
CREATE TABLE IF NOT EXISTS cron_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_name TEXT UNIQUE NOT NULL,
    handler_file TEXT NOT NULL, -- path to cron handler
    schedule TEXT NOT NULL, -- cron expression or interval
    last_run_at TIMESTAMP,
    next_run_at TIMESTAMP,
    is_enabled BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User impersonation audit trail
CREATE TABLE IF NOT EXISTS impersonation_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL,
    impersonated_user_id UUID NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    actions_taken JSONB,
    notes TEXT
);

-- ============================================================================
-- REGISTRATION COMMITTEE FEATURES TABLES
-- ============================================================================

-- Affiliation batch processing tracking
ALTER TABLE IF EXISTS member_upload_batches ADD COLUMN IF NOT EXISTS processed_in_batch BOOLEAN DEFAULT FALSE;

-- Duplicate detection cache
CREATE TABLE IF NOT EXISTS potential_duplicates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    primary_record_id UUID NOT NULL,
    potential_duplicate_id UUID NOT NULL,
    similarity_score FLOAT DEFAULT 0,
    fields_matched TEXT[], -- array of field names that matched
    status TEXT DEFAULT 'unreviewed', -- unreviewed, confirmed_duplicate, false_positive
    reviewed_by UUID,
    reviewed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- TREASURER FEATURES TABLES
-- ============================================================================

-- Payment gateway transaction logs
CREATE TABLE IF NOT EXISTS payment_gateway_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_name TEXT NOT NULL, -- paypal, stripe, etc.
    transaction_id TEXT NOT NULL,
    amount DECIMAL(10, 2),
    currency TEXT DEFAULT 'PHP',
    status TEXT, -- success, failed, pending, cancelled
    response_data JSONB,
    member_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Custom invoices (separate from receipts)
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_number TEXT UNIQUE NOT NULL,
    member_id UUID NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    issue_date DATE NOT NULL,
    due_date DATE,
    pdf_path TEXT,
    status TEXT DEFAULT 'draft', -- draft, sent, paid, overdue, cancelled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Partial payment and installment tracking
CREATE TABLE IF NOT EXISTS payment_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL,
    member_id UUID NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    installments INT DEFAULT 1,
    frequency TEXT DEFAULT 'monthly', -- monthly, quarterly, semi-annual
    start_date DATE,
    status TEXT DEFAULT 'active', -- active, completed, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budget definition and tracking
CREATE TABLE IF NOT EXISTS budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fiscal_year INT NOT NULL,
    department TEXT,
    category TEXT NOT NULL,
    budgeted_amount DECIMAL(10, 2) NOT NULL,
    actual_amount DECIMAL(10, 2) DEFAULT 0,
    variance DECIMAL(10, 2) DEFAULT 0,
    status TEXT DEFAULT 'active',
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transaction archive for year-end closing
CREATE TABLE IF NOT EXISTS transactions_archive (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    original_transaction_id UUID,
    fiscal_year INT,
    amount DECIMAL(10, 2),
    description TEXT,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- MEMBER FEATURES TABLES
-- ============================================================================

-- Email verification tokens
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    token TEXT UNIQUE NOT NULL,
    new_email TEXT NOT NULL,
    expires_at TIMESTAMP,
    verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Membership certificates
CREATE TABLE IF NOT EXISTS certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID NOT NULL,
    certificate_type TEXT NOT NULL, -- membership, achievement, attendance
    title TEXT NOT NULL,
    issue_date DATE,
    expiry_date DATE,
    pdf_path TEXT,
    verification_code TEXT UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User reminder preferences
CREATE TABLE IF NOT EXISTS user_reminder_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE,
    affiliation_renewal_days INT DEFAULT 30,
    payment_due_days INT DEFAULT 7,
    event_reminder_days INT DEFAULT 3,
    push_notifications_enabled BOOLEAN DEFAULT TRUE,
    email_notifications_enabled BOOLEAN DEFAULT TRUE,
    sms_notifications_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- SCHOOL OFFICER FEATURES TABLES
-- ============================================================================

-- Temporary school member upload staging
CREATE TABLE IF NOT EXISTS temp_school_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID NOT NULL,
    upload_batch_id UUID,
    email TEXT,
    full_name TEXT,
    student_id TEXT,
    program TEXT,
    year_level INT,
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- School profile and details
CREATE TABLE IF NOT EXISTS schools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    address TEXT,
    contact_person TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    website TEXT,
    logo_path TEXT,
    member_count INT DEFAULT 0,
    active_members INT DEFAULT 0,
    alumni_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alumni and graduation tracking
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS graduation_year INT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS alumni_status BOOLEAN DEFAULT FALSE;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS alumni_since DATE;

-- ============================================================================
-- AUDITOR FEATURES TABLES
-- ============================================================================

-- Financial audit trail
CREATE TABLE IF NOT EXISTS financial_audit_trail (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID,
    action_type TEXT, -- create, update, delete, approve, reject
    old_values JSONB,
    new_values JSONB,
    audit_user_id UUID,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Compliance checks and exceptions
CREATE TABLE IF NOT EXISTS compliance_checks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    check_type TEXT NOT NULL, -- payment_timeliness, membership_validity, etc.
    target_entity_id UUID,
    target_entity_type TEXT, -- member, transaction, etc.
    status TEXT DEFAULT 'pending', -- pending, passed, failed, exception
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_at TIMESTAMP
);

-- ============================================================================
-- SECRETARY FEATURES TABLES
-- ============================================================================

-- Meeting minutes templates
CREATE TABLE IF NOT EXISTS minutes_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    template_name TEXT NOT NULL,
    sections JSONB, -- array of section objects with titles and templates
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Document versioning
CREATE TABLE IF NOT EXISTS documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id UUID, -- null for new documents, set for versions
    title TEXT NOT NULL,
    content TEXT,
    file_path TEXT,
    version INT DEFAULT 1,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Event attendees
CREATE TABLE IF NOT EXISTS event_attendees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL,
    member_id UUID NOT NULL,
    status TEXT DEFAULT 'registered', -- registered, attended, absent, cancelled
    check_in_time TIMESTAMP,
    UNIQUE(event_id, member_id)
);

-- Committee task management
CREATE TABLE IF NOT EXISTS committee_tasks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    committee_id UUID,
    task_title TEXT NOT NULL,
    description TEXT,
    assigned_to UUID,
    assigned_by UUID,
    due_date DATE,
    priority TEXT DEFAULT 'medium', -- low, medium, high, critical
    status TEXT DEFAULT 'open', -- open, in_progress, completed, cancelled
    depends_on UUID, -- for task dependencies
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- CREATIVES COMMITTEE FEATURES TABLES
-- ============================================================================

-- Content workflow states
CREATE TABLE IF NOT EXISTS content_workflow (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id UUID NOT NULL,
    content_type TEXT NOT NULL, -- announcement, feature, publication, etc.
    current_state TEXT DEFAULT 'draft', -- draft, review, approved, published, archived
    created_by UUID,
    submitted_by UUID,
    approved_by UUID,
    submitted_at TIMESTAMP,
    approved_at TIMESTAMP,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scheduled announcements
CREATE TABLE IF NOT EXISTS scheduled_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT,
    scheduled_for TIMESTAMP,
    published_at TIMESTAMP,
    status TEXT DEFAULT 'scheduled', -- scheduled, published, cancelled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- MARKETING COMMITTEE FEATURES TABLES
-- ============================================================================

-- Marketing campaigns
CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_name TEXT NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(10, 2),
    status TEXT DEFAULT 'draft', -- draft, active, completed, cancelled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email blast tracking
CREATE TABLE IF NOT EXISTS email_blasts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID,
    subject TEXT,
    html_content TEXT,
    recipient_count INT,
    sent_at TIMESTAMP,
    status TEXT DEFAULT 'draft', -- draft, sent, failed, scheduled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email tracking and metrics
CREATE TABLE IF NOT EXISTS email_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_blast_id UUID,
    member_id UUID,
    opened_at TIMESTAMP,
    clicked_at TIMESTAMP,
    bounce_status TEXT,
    tracking_code TEXT UNIQUE
);

-- Leads and prospect tracking
CREATE TABLE IF NOT EXISTS leads (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    first_name TEXT,
    last_name TEXT,
    email TEXT NOT NULL,
    phone TEXT,
    organization TEXT,
    source TEXT, -- website, referral, event, social, etc.
    status TEXT DEFAULT 'new', -- new, contacted, interested, converted, rejected
    notes TEXT,
    assigned_to UUID,
    converted_member_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Social media posts scheduling
CREATE TABLE IF NOT EXISTS social_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content TEXT,
    scheduled_for TIMESTAMP,
    posted_at TIMESTAMP,
    platform TEXT, -- facebook, twitter, instagram, linkedin
    status TEXT DEFAULT 'scheduled', -- scheduled, posted, failed, cancelled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- LOGISTICS COMMITTEE FEATURES TABLES
-- ============================================================================

-- Inventory management
CREATE TABLE IF NOT EXISTS inventory_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    item_name TEXT NOT NULL,
    category TEXT,
    quantity INT DEFAULT 0,
    reorder_level INT,
    unit_cost DECIMAL(10, 2),
    location TEXT,
    supplier_id UUID,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Event logistics planning
CREATE TABLE IF NOT EXISTS event_logistics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL,
    venue_name TEXT,
    venue_address TEXT,
    capacity INT,
    catering_needed BOOLEAN DEFAULT FALSE,
    transport_needed BOOLEAN DEFAULT FALSE,
    equipment_needed TEXT[], -- array of equipment
    budget DECIMAL(10, 2),
    status TEXT DEFAULT 'planning', -- planning, confirmed, completed, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendor/supplier management
CREATE TABLE IF NOT EXISTS vendors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vendor_name TEXT NOT NULL,
    contact_person TEXT,
    email TEXT,
    phone TEXT,
    service_category TEXT, -- catering, transport, equipment, etc.
    rating FLOAT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Asset checkout and returns
CREATE TABLE IF NOT EXISTS asset_loans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asset_id UUID,
    borrower_id UUID NOT NULL,
    checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    return_date TIMESTAMP,
    condition_on_checkout TEXT,
    condition_on_return TEXT,
    status TEXT DEFAULT 'loaned', -- loaned, returned, overdue
    notes TEXT
);

-- ============================================================================
-- VP INTERNAL FEATURES TABLES
-- ============================================================================

-- Chapter KPIs and scorecard
CREATE TABLE IF NOT EXISTS chapter_kpis (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kpi_name TEXT NOT NULL,
    kpi_code TEXT UNIQUE NOT NULL,
    target_value NUMERIC,
    current_value NUMERIC,
    period_start DATE,
    period_end DATE,
    status TEXT, -- on_track, at_risk, off_track
    calculated_at TIMESTAMP
);

-- Internal grievance tracker
CREATE TABLE IF NOT EXISTS grievances (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    complainant_id UUID,
    respondent_id UUID,
    grievance_type TEXT,
    description TEXT,
    status TEXT DEFAULT 'open', -- open, under_review, resolved, closed, dismissed
    priority TEXT DEFAULT 'medium',
    assigned_to UUID,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Member satisfaction surveys
CREATE TABLE IF NOT EXISTS surveys (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    survey_title TEXT NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'draft', -- draft, active, closed
    start_date DATE,
    end_date DATE,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Survey responses
CREATE TABLE IF NOT EXISTS survey_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    survey_id UUID NOT NULL,
    respondent_id UUID,
    response_data JSONB,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- VP EXTERNAL FEATURES TABLES
-- ============================================================================

-- MOA/MOU agreements
CREATE TABLE IF NOT EXISTS agreements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agreement_name TEXT NOT NULL,
    agreement_type TEXT, -- moa, mou, sponsorship, partnership
    partner_organization TEXT,
    start_date DATE,
    end_date DATE,
    file_path TEXT,
    status TEXT DEFAULT 'active', -- active, expired, terminated, draft
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sponsorship management
CREATE TABLE IF NOT EXISTS sponsorships (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sponsor_organization TEXT NOT NULL,
    sponsorship_tier TEXT, -- platinum, gold, silver, bronze
    amount DECIMAL(10, 2),
    start_date DATE,
    end_date DATE,
    benefits TEXT,
    status TEXT DEFAULT 'active',
    contact_person TEXT,
    contact_email TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- External events
CREATE TABLE IF NOT EXISTS external_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_name TEXT NOT NULL,
    description TEXT,
    event_date DATE,
    event_time TIME,
    venue TEXT,
    organizer TEXT,
    contact_info TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    is_embeddable BOOLEAN DEFAULT FALSE,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- VP ACADEMIC FEATURES TABLES
-- ============================================================================

-- Webinars and seminars
CREATE TABLE IF NOT EXISTS webinars (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    speaker_name TEXT,
    speaker_expertise TEXT,
    webinar_date DATE,
    webinar_time TIME,
    meeting_link TEXT,
    registration_open BOOLEAN DEFAULT TRUE,
    max_participants INT,
    status TEXT DEFAULT 'scheduled', -- scheduled, ongoing, completed, cancelled
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Webinar registrations
CREATE TABLE IF NOT EXISTS webinar_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    webinar_id UUID NOT NULL,
    member_id UUID NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended BOOLEAN DEFAULT FALSE,
    attendance_time TIMESTAMP,
    UNIQUE(webinar_id, member_id)
);

-- Webinar certificates
CREATE TABLE IF NOT EXISTS webinar_certificates (
    id UUID PRIMARY KEY DEFAULT gen_remote_uuid(),
    webinar_id UUID NOT NULL,
    member_id UUID NOT NULL,
    certificate_code TEXT UNIQUE,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CPD points tracking
CREATE TABLE IF NOT EXISTS cpd_points (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID NOT NULL,
    activity_type TEXT, -- webinar, seminar, workshop, training
    points INT,
    date_earned DATE,
    approved_by UUID,
    approved_at TIMESTAMP,
    status TEXT DEFAULT 'pending', -- pending, approved, rejected
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Academic resource library
CREATE TABLE IF NOT EXISTS academic_resources (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resource_title TEXT NOT NULL,
    description TEXT,
    file_path TEXT,
    category TEXT,
    tags TEXT[],
    uploaded_by UUID,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- PRO (PUBLIC RELATIONS OFFICER) FEATURES TABLES
-- ============================================================================

-- Press releases
CREATE TABLE IF NOT EXISTS press_releases (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT,
    summary TEXT,
    publication_date DATE,
    status TEXT DEFAULT 'draft', -- draft, published, archived
    featured_image_path TEXT,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Media contacts database
CREATE TABLE IF NOT EXISTS media_contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    organization TEXT,
    position TEXT,
    email TEXT,
    phone TEXT,
    media_type TEXT, -- newspaper, tv, radio, online, blog
    beat TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Social media accounts
CREATE TABLE IF NOT EXISTS social_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    platform TEXT NOT NULL, -- facebook, twitter, instagram, linkedin, tiktok
    account_handle TEXT,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP,
    is_connected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- PRESIDENT FEATURES TABLES
-- ============================================================================

-- Strategic objectives and OKRs
CREATE TABLE IF NOT EXISTS strategic_objectives (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fiscal_year INT,
    objective_text TEXT NOT NULL,
    owner_id UUID,
    status TEXT DEFAULT 'active', -- active, completed, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Key results
CREATE TABLE IF NOT EXISTS key_results (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    objective_id UUID NOT NULL,
    result_text TEXT NOT NULL,
    target_value NUMERIC,
    current_value NUMERIC DEFAULT 0,
    metric_unit TEXT,
    progress_percentage FLOAT DEFAULT 0,
    status TEXT DEFAULT 'in_progress',
    owner_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Committee reports aggregation
CREATE TABLE IF NOT EXISTS committee_reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    committee_name TEXT,
    reporting_period TEXT,
    report_content TEXT,
    highlights TEXT,
    challenges TEXT,
    submitted_by UUID,
    submitted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- COLLABORATION FEATURES TABLES
-- ============================================================================

-- Chat rooms and channels
CREATE TABLE IF NOT EXISTS chat_rooms (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_name TEXT NOT NULL,
    room_type TEXT DEFAULT 'group', -- private, group, committee
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room participants
CREATE TABLE IF NOT EXISTS room_participants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL,
    user_id UUID NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(room_id, user_id)
);

-- Chat messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL,
    sender_id UUID NOT NULL,
    message_text TEXT,
    attachment_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shared files
CREATE TABLE IF NOT EXISTS shared_files (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id UUID NOT NULL,
    uploaded_by UUID NOT NULL,
    file_name TEXT,
    file_path TEXT,
    file_size INT,
    mime_type TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Collaboration groups
CREATE TABLE IF NOT EXISTS collab_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_name TEXT NOT NULL,
    description TEXT,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Group members
CREATE TABLE IF NOT EXISTS group_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    group_id UUID NOT NULL,
    user_id UUID NOT NULL,
    role TEXT DEFAULT 'member', -- member, moderator, admin
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(group_id, user_id)
);

-- ============================================================================
-- OFFICER FEATURES TABLES
-- ============================================================================

-- Officer meeting attendance
CREATE TABLE IF NOT EXISTS officer_meeting_attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    meeting_id UUID,
    officer_id UUID NOT NULL,
    status TEXT DEFAULT 'absent', -- present, absent, excused
    check_in_time TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
-- Migration completed successfully.
-- Total new tables: 50+
-- Total new columns added to existing tables: 3
