-- ==========================================================
-- IECEP-LSC MEMSYS Database Migrations - May 2026
-- All features implementation
-- ==========================================================

-- 1. ADD institution_id TO events TABLE
-- ==========================================================
ALTER TABLE events ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_events_institution_id ON events(institution_id);

-- 2. CREATE partner_chapters TABLE
-- ==========================================================
CREATE TABLE IF NOT EXISTS partner_chapters (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    institution TEXT NOT NULL,
    contact_email TEXT,
    contact_phone TEXT,
    partnership_status TEXT DEFAULT 'active' CHECK (partnership_status IN ('active', 'inactive', 'pending')),
    website TEXT,
    description TEXT,
    headquarters_location TEXT,
    founding_year INTEGER,
    member_count INTEGER DEFAULT 0,
    created_by UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_chapters_status ON partner_chapters(partnership_status);
CREATE INDEX IF NOT EXISTS idx_partner_chapters_created_by ON partner_chapters(created_by);

CREATE TRIGGER update_partner_chapters_updated_at
    BEFORE UPDATE ON partner_chapters
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- 3. CREATE collaboration_posts TABLE
-- ==========================================================
CREATE TABLE IF NOT EXISTS collaboration_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    chapter_id UUID NOT NULL REFERENCES partner_chapters(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_by UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    attachment_url TEXT,
    likes_count INTEGER DEFAULT 0,
    shares_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_collaboration_posts_chapter_id ON collaboration_posts(chapter_id);
CREATE INDEX IF NOT EXISTS idx_collaboration_posts_created_at ON collaboration_posts(created_at DESC);

CREATE TRIGGER update_collaboration_posts_updated_at
    BEFORE UPDATE ON collaboration_posts
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- 4. ADD QR CODE COLUMNS TO members TABLE
-- ==========================================================
ALTER TABLE members ADD COLUMN IF NOT EXISTS qr_code TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_hash VARCHAR(64);

CREATE INDEX IF NOT EXISTS idx_members_digital_id_hash ON members(digital_id_hash);

-- 5. ADD institution_id TO compliance_records TABLE
-- ==========================================================
ALTER TABLE compliance_records ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;
ALTER TABLE compliance_records ADD COLUMN IF NOT EXISTS compliance_attendance TEXT;

CREATE INDEX IF NOT EXISTS idx_compliance_records_institution_id ON compliance_records(institution_id);

-- 6. CREATE OR EXTEND push_subscriptions TABLE
-- ==========================================================
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    subscription_json JSONB NOT NULL,
    is_active BOOLEAN DEFAULT true,
    last_notified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user_id ON push_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_is_active ON push_subscriptions(is_active);

CREATE TRIGGER update_push_subscriptions_updated_at
    BEFORE UPDATE ON push_subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- 7. VERIFY notifications TABLE EXISTS
-- ==========================================================
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'system' CHECK (type IN ('system', 'alert', 'success', 'info', 'warning')),
    link TEXT,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at DESC);

CREATE TRIGGER update_notifications_updated_at
    BEFORE UPDATE ON notifications
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- 8. VERIFY blockchain_records TABLE EXISTS AND ADD institution_id
-- ==========================================================
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_blockchain_records_institution_id ON blockchain_records(institution_id);

-- 9. VERIFY AND EXTEND audit_logs TABLE
-- ==========================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    details JSONB,
    affected_entity_type TEXT,
    affected_entity_id UUID,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_affected_entity ON audit_logs(affected_entity_type, affected_entity_id);

-- 10. ADD academic_year TO system_settings
-- ==========================================================
INSERT INTO system_settings (key, value, description)
VALUES ('academic_year_start', '2026-06-01', 'Start date of academic year')
ON CONFLICT (key) DO NOTHING;

INSERT INTO system_settings (key, value, description)
VALUES ('academic_year_end', '2027-05-31', 'End date of academic year')
ON CONFLICT (key) DO NOTHING;

INSERT INTO system_settings (key, value, description)
VALUES ('compliance_participation_threshold', '40', 'Minimum participation percentage required')
ON CONFLICT (key) DO NOTHING;

-- 11. ENABLE ROW LEVEL SECURITY FOR SENSITIVE TABLES (if not already enabled)
-- ==========================================================
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE push_subscriptions ENABLE ROW LEVEL SECURITY;

-- 12. CREATE RLS POLICIES IF NOT EXIST
-- ==========================================================
DO $$
BEGIN
    -- Notifications: Users can only see their own notifications
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies WHERE tablename = 'notifications' AND policyname = 'user_notifications_select'
    ) THEN
        CREATE POLICY user_notifications_select ON notifications
            FOR SELECT USING (user_id = auth.uid());
    END IF;

    -- Push Subscriptions: Users can only see their own subscriptions
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies WHERE tablename = 'push_subscriptions' AND policyname = 'user_subscriptions_select'
    ) THEN
        CREATE POLICY user_subscriptions_select ON push_subscriptions
            FOR SELECT USING (user_id = auth.uid());
    END IF;

    -- Audit Logs: Only accessible to admin and auditors
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies WHERE tablename = 'audit_logs' AND policyname = 'audit_logs_select'
    ) THEN
        CREATE POLICY audit_logs_select ON audit_logs
            FOR SELECT USING (auth.jwt() ->> 'role' IN ('admin', 'auditor', 'eb_auditor'));
    END IF;
END $$;
