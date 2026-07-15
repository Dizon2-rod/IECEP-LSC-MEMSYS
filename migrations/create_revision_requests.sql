-- Affiliation Revision Requests Table
CREATE TABLE IF NOT EXISTS revision_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    affiliation_id UUID NOT NULL REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    token TEXT UNIQUE NOT NULL,
    explanation TEXT,
    requested_by UUID REFERENCES user_profiles(id),
    deadline TIMESTAMPTZ NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending','submitted','expired')),
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX idx_revision_requests_token ON revision_requests(token);
CREATE INDEX idx_revision_requests_affiliation ON revision_requests(affiliation_id);
CREATE INDEX idx_revision_requests_status ON revision_requests(status);
