-- IECEP-LSC MEMSYS Database Schema
-- Run in Supabase SQL Editor in order

-- 1. Institutions (affiliated schools)
CREATE TABLE IF NOT EXISTS institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    address TEXT,
    contact_person TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'suspended')),
    affiliation_fee_paid BOOLEAN DEFAULT false,
    compliance_status TEXT CHECK (compliance_status IN ('compliant', 'at_risk', 'non_compliant')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 2. Special institution for IECEP officers (run once)
INSERT INTO institutions (id, email, name, address, status, affiliation_fee_paid)
VALUES (
    '00000000-0000-0000-0000-000000000001',
    'executive@iecep-lsc.org',
    'IECEP-LSC Executive Council',
    'Laguna, Philippines',
    'active',
    true
) ON CONFLICT (id) DO NOTHING;

-- 3. Pending affiliations
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    institution_name TEXT NOT NULL,
    address TEXT,
    contact_person TEXT,
    contact_position TEXT,
    contact_phone TEXT,
    documents JSONB,
    status TEXT DEFAULT 'pending_review' CHECK (status IN ('pending_review', 'approved', 'rejected')),
    submitted_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    reviewed_at TIMESTAMP,
    rejection_reason TEXT
);

-- 4. Email verification codes
CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    verified BOOLEAN DEFAULT false
);

-- 5. Contact form messages
CREATE TABLE IF NOT EXISTS contact_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT DEFAULT 'unread' CHECK (status IN ('unread', 'read', 'replied')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 5. User profiles (extends auth.users)
CREATE TABLE IF NOT EXISTS user_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE UNIQUE,
    role TEXT NOT NULL CHECK (role IN (
        'eb_president', 'eb_vp_internal', 'eb_vp_external', 'eb_vp_academic',
        'eb_secretary_general', 'eb_assistant_secretary', 'eb_treasurer', 'eb_auditor',
        'eb_pro_1', 'eb_pro_2',
        'committee_creatives', 'committee_documentation', 'committee_logistics',
        'committee_marketing', 'committee_registration', 'committee_technical',
        'school_officer', 'member'
    )),
    full_name TEXT,
    force_password_change BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 6. Members (every user has exactly one row)
CREATE TABLE IF NOT EXISTS members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL UNIQUE,
    full_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    member_type TEXT CHECK (member_type IN ('new', 'returning', 'honorary')),
    payment_status BOOLEAN DEFAULT false,
    digital_id_url TEXT,
    qr_code TEXT,
    year_level TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 7. Member upload batches
CREATE TABLE IF NOT EXISTS member_upload_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    file_name TEXT,
    status TEXT DEFAULT 'pending_approval' CHECK (status IN ('pending_approval', 'approved_payment_pending', 'fully_paid')),
    uploaded_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    approved_at TIMESTAMP
);

-- 8. Pending members (from CSV before approval)
CREATE TABLE IF NOT EXISTS pending_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id UUID REFERENCES member_upload_batches(id) ON DELETE CASCADE,
    full_name TEXT,
    email TEXT,
    member_type TEXT,
    year_level TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved_payment_pending', 'paid_account_created'))
);

-- 9. Events
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    academic_year TEXT NOT NULL,
    created_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 10. Attendance
CREATE TABLE IF NOT EXISTS attendance (
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    attended BOOLEAN DEFAULT true,
    recorded_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (member_id, event_id)
);

-- 11. Compliance records (historical)
CREATE TABLE IF NOT EXISTS compliance_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    period_start DATE,
    period_end DATE,
    total_members INT,
    attending_members INT,
    participation_rate DECIMAL(5,2),
    status TEXT CHECK (status IN ('compliant', 'at_risk', 'non_compliant')),
    calculated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 12. Transactions (financial + blockchain)
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    receipt_id TEXT UNIQUE NOT NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
    blockchain_tx_hash TEXT,
    block_number BIGINT,
    receipt_url TEXT,
    paid_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 13. Announcements (for Secretary General)
CREATE TABLE IF NOT EXISTS announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    sent_by UUID REFERENCES auth.users(id),
    sent_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 14. Read receipts
CREATE TABLE IF NOT EXISTS read_receipts (
    announcement_id UUID REFERENCES announcements(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    read_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (announcement_id, user_id)
);

-- 15. Committee tasks
CREATE TABLE IF NOT EXISTS committee_tasks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    committee_name TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    assigned_to UUID REFERENCES auth.users(id),
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 16. Audit logs (for auditor flags)
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id),
    action TEXT,
    details JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 17. System settings
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Insert default settings
INSERT INTO system_settings (key, value) VALUES
    ('fee_new_member', '250'),
    ('fee_returning_member', '200'),
    ('fee_honorary_member', '300'),
    ('fee_affiliation', '500'),
    ('academic_year', '2025-2026'),
    ('compliance_threshold', '40'),
    ('at_risk_threshold', '20')
ON CONFLICT (key) DO NOTHING;

-- Row Level Security policies
ALTER TABLE institutions ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE email_verifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;
ALTER TABLE member_upload_batches ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE attendance ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE read_receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE committee_tasks ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE system_settings ENABLE ROW LEVEL SECURITY;

-- Service role can do everything
CREATE POLICY "Service role full access" ON institutions FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON pending_affiliations FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON email_verifications FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON user_profiles FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON members FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON member_upload_batches FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON pending_members FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON events FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON attendance FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON compliance_records FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON transactions FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON announcements FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON read_receipts FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON committee_tasks FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON audit_logs FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON system_settings FOR ALL USING (true) WITH CHECK (true);

-- Authenticated users can read their own profile and member record
CREATE POLICY "Users read own profile" ON user_profiles FOR SELECT USING (user_id = auth.uid());
CREATE POLICY "Users read own member" ON members FOR SELECT USING (user_id = auth.uid());
CREATE POLICY "Users read announcements" ON announcements FOR SELECT USING (true);
CREATE POLICY "Users read events" ON events FOR SELECT USING (true);
CREATE POLICY "Users read own institution" ON institutions FOR SELECT USING (true);

-- Storage buckets (run via Supabase dashboard or API)
-- pending_affiliations: public read off, authenticated upload
-- member_ids: public read on, service role upload
-- receipts: public read off, service role upload
-- committee_assets: authenticated read, committee role upload

-- 18. Creatives Committee Announcements
CREATE TABLE IF NOT EXISTS creatives_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    author TEXT DEFAULT 'Creatives Committee',
    status TEXT DEFAULT 'published' CHECK (status IN ('published', 'draft', 'archived')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 19. Creatives Committee Graphics
CREATE TABLE IF NOT EXISTS creatives_graphics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    image TEXT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 20. Creatives Committee Publications
CREATE TABLE IF NOT EXISTS creatives_publications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    file TEXT NOT NULL,
    size TEXT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 21. Creatives Committee Team Members
CREATE TABLE IF NOT EXISTS creatives_team (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    email TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- 22. Creatives Committee Homepage Features
CREATE TABLE IF NOT EXISTS creatives_features (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    image TEXT NOT NULL,
    link TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Enable Row Level Security for creatives tables
ALTER TABLE creatives_announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_graphics ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_publications ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_team ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_features ENABLE ROW LEVEL SECURITY;

-- Service role can do everything on creatives tables
CREATE POLICY "Service role full access" ON creatives_announcements FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON creatives_graphics FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON creatives_publications FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON creatives_team FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "Service role full access" ON creatives_features FOR ALL USING (true) WITH CHECK (true);

-- Committee members can read and write creatives data
CREATE POLICY "Committee members can read creatives" ON creatives_announcements FOR SELECT USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can read creatives" ON creatives_graphics FOR SELECT USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can read creatives" ON creatives_publications FOR SELECT USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can read creatives" ON creatives_team FOR SELECT USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can read creatives" ON creatives_features FOR SELECT USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));

CREATE POLICY "Committee members can insert creatives" ON creatives_announcements FOR INSERT WITH CHECK (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can insert creatives" ON creatives_graphics FOR INSERT WITH CHECK (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can insert creatives" ON creatives_publications FOR INSERT WITH CHECK (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can insert creatives" ON creatives_team FOR INSERT WITH CHECK (auth.jwt() ->> 'role' = 'eb_pro_1');
CREATE POLICY "Committee members can insert creatives" ON creatives_features FOR INSERT WITH CHECK (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));

CREATE POLICY "Committee members can update creatives" ON creatives_announcements FOR UPDATE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can update creatives" ON creatives_graphics FOR UPDATE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can update creatives" ON creatives_publications FOR UPDATE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can update creatives" ON creatives_team FOR UPDATE USING (auth.jwt() ->> 'role' = 'eb_pro_1');
CREATE POLICY "Committee members can update creatives" ON creatives_features FOR UPDATE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));

CREATE POLICY "Committee members can delete creatives" ON creatives_announcements FOR DELETE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can delete creatives" ON creatives_graphics FOR DELETE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can delete creatives" ON creatives_publications FOR DELETE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));
CREATE POLICY "Committee members can delete creatives" ON creatives_team FOR DELETE USING (auth.jwt() ->> 'role' = 'eb_pro_1');
CREATE POLICY "Committee members can delete creatives" ON creatives_features FOR DELETE USING (auth.jwt() ->> 'role' IN ('eb_pro_1', 'committee_creatives'));

-- Public can read features (for homepage)
CREATE POLICY "Public can read features" ON creatives_features FOR SELECT USING (true);

-- Insert initial data for announcements
INSERT INTO creatives_announcements (title, content, date, author, status) VALUES
('Upcoming Electronics Workshop', 'Join us for an exciting workshop on modern electronics design and innovation. Open to all IECEP-LSC members.', '2025-04-18', 'Creatives Committee', 'published'),
('National Electronics Conference Registration', 'Registration is now open for the Annual National Electronics Conference. Early bird discounts available until next month.', '2025-04-16', 'Creatives Committee', 'published'),
('Chapter Meeting Schedule', 'Monthly chapter meeting will be held on the 25th. All committee members are required to attend.', '2025-04-11', 'Creatives Committee', 'published')
ON CONFLICT DO NOTHING;

-- Insert initial data for graphics
INSERT INTO creatives_graphics (name, image, date) VALUES
('Event Poster - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/images/nec-poster.jpg', '2025-04-18'),
('Workshop Banner', '/IECEP-LSC-MEMSYS/public/assets/images/workshop-banner.jpg', '2025-04-16'),
('Social Media Template', '/IECEP-LSC-MEMSYS/public/assets/images/social-template.jpg', '2025-04-11'),
('Chapter Logo Variant', '/IECEP-LSC-MEMSYS/public/assets/images/logo-variant.jpg', '2025-04-04')
ON CONFLICT DO NOTHING;

-- Insert initial data for publications
INSERT INTO creatives_publications (title, file, size, date) VALUES
('Monthly Newsletter - March 2025', '/IECEP-LSC-MEMSYS/public/assets/newsletters/march-2025.pdf', '2.4 MB', '2025-04-15'),
('Chapter Annual Report 2024', '/IECEP-LSC-MEMSYS/public/assets/reports/annual-report-2024.pdf', '5.1 MB', '2025-04-11'),
('Event Program - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/programs/nec-2025.pdf', '1.8 MB', '2025-04-04')
ON CONFLICT DO NOTHING;

-- Insert initial data for team members
INSERT INTO creatives_team (name, role, email) VALUES
('John Doe', 'PRO 1 - Committee Head', 'john.doe@iecep-lsc.org'),
('Jane Smith', 'Committee Member', 'jane.smith@iecep-lsc.org'),
('Mike Johnson', 'Committee Member', 'mike.johnson@iecep-lsc.org')
ON CONFLICT DO NOTHING;

-- Insert initial data for homepage features
INSERT INTO creatives_features (title, description, image, link) VALUES
('IECEP News', 'Stay updated with the latest news and announcements from IECEP-LSC', '/IECEP-LSC-MEMSYS/public/assets/images/iecep-news.jpg', '#'),
('Recent Activities', 'View recent activities and events from our chapter', '/IECEP-LSC-MEMSYS/public/assets/images/activities.jpg', '#'),
('Featured Content', 'Explore featured content and highlights from our community', '/IECEP-LSC-MEMSYS/public/assets/images/featured.jpg', '#')
ON CONFLICT DO NOTHING;
