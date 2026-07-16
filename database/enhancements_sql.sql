-- SQL Changes for Deliverable 1 Enhancements
-- This file is idempotent - can be run multiple times safely

-- 1. Create survey_responses table for Post-Event Feedback Module
CREATE TABLE IF NOT EXISTS survey_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    survey_id UUID NOT NULL,
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    answers JSONB NOT NULL DEFAULT '{}',
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_survey_responses_survey ON survey_responses(survey_id);
CREATE INDEX IF NOT EXISTS idx_survey_responses_member ON survey_responses(member_id);
CREATE INDEX IF NOT EXISTS idx_survey_responses_event ON survey_responses(event_id);

-- 2. Create surveys table
CREATE TABLE IF NOT EXISTS surveys (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    questions JSONB NOT NULL DEFAULT '[]',
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    target_roles TEXT[] DEFAULT ARRAY['member'],
    is_active BOOLEAN DEFAULT true,
    created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_surveys_event ON surveys(event_id);
CREATE INDEX IF NOT EXISTS idx_surveys_active ON surveys(is_active);
CREATE INDEX IF NOT EXISTS idx_surveys_created_by ON surveys(created_by);

-- 3. Create email_tracking table for Newsletter tracking
CREATE TABLE IF NOT EXISTS email_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_blast_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    opened_at TIMESTAMPTZ,
    clicked_at TIMESTAMPTZ,
    bounce_status TEXT,
    tracking_code TEXT UNIQUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_tracking_blast ON email_tracking(email_blast_id);
CREATE INDEX IF NOT EXISTS idx_email_tracking_member ON email_tracking(member_id);
CREATE INDEX IF NOT EXISTS idx_email_tracking_code ON email_tracking(tracking_code);

-- 4. Create email_blasts table
CREATE TABLE IF NOT EXISTS email_blasts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID,
    subject TEXT NOT NULL,
    html_content TEXT NOT NULL,
    recipient_count INTEGER DEFAULT 0,
    sent_at TIMESTAMPTZ,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'failed', 'scheduled')),
    scheduled_for TIMESTAMPTZ,
    created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_blasts_status ON email_blasts(status);
CREATE INDEX IF NOT EXISTS idx_email_blasts_created_by ON email_blasts(created_by);

-- 5. Add system_settings for Facebook page URL and email contacts
INSERT INTO system_settings (key, value, description)
VALUES 
    ('facebook_page_url', 'https://www.facebook.com/IECEPLSC', 'Facebook Page URL for timeline embed'),
    ('treasurer_email', 'treasurer@iecep-lsc.org', 'Email address for receiving monthly financial reports'),
    ('president_email', 'president@iecep-lsc.org', 'Email address for receiving monthly financial reports'),
    ('cron_secret', '', 'Secret key for protecting cron job endpoints')
ON CONFLICT (key) DO NOTHING;

-- 6. Add mfa_enabled column to user_profiles for Two-Factor Authentication
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'user_profiles' AND column_name = 'mfa_enabled'
    ) THEN
        ALTER TABLE user_profiles ADD COLUMN mfa_enabled BOOLEAN DEFAULT false;
    END IF;
    
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'user_profiles' AND column_name = 'mfa_secret'
    ) THEN
        ALTER TABLE user_profiles ADD COLUMN mfa_secret TEXT;
    END IF;
END $$;

-- 7. Ensure member-photos storage bucket exists (manual setup in Supabase)
-- Note: This must be created manually in Supabase Storage UI or via API
-- Bucket name: member-photos
-- Public access: true for reading, authenticated for writing
