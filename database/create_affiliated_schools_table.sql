-- Create affiliated_schools table
CREATE TABLE IF NOT EXISTS affiliated_schools (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    facebook_url VARCHAR(500),
    member_count INTEGER DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial data
INSERT INTO affiliated_schools (name, facebook_url, member_count, created_at) VALUES
('Colegio de San Juan de Letrán', 'https://www.facebook.com/LetranCalamba', 150, '2020-01-15'),
('Laguna State Polytechnic University - Santa Cruz Campus', 'https://www.facebook.com/LSPUSantaCruz', 200, '2019-06-20'),
('Laguna State Polytechnic University - San Pablo City Campus', 'https://www.facebook.com/LSPUSanPablo', 180, '2019-08-10'),
('Mapua Malayan Colleges Laguna', 'https://www.facebook.com/MMCLaguna', 120, '2020-03-25'),
('Polytechnic University of the Philippines - Santa Rosa Campus', 'https://www.facebook.com/PUPSantaRosa', 160, '2019-11-15'),
('Pamantasan ng Cabuyao', 'https://www.facebook.com/PamantasanNgCabuyao', 140, '2020-05-30'),
('University of Perpetual Help System Dalta - Calamba Campus', 'https://www.facebook.com/UPHSDCalamba', 190, '2019-07-12'),
('University of Perpetual Help System Jonelta - Biñán Campus', 'https://www.facebook.com/UPHSLBinan', 170, '2019-09-05')
ON CONFLICT (name) DO NOTHING;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_affiliated_schools_status ON affiliated_schools(status);
CREATE INDEX IF NOT EXISTS idx_affiliated_schools_name ON affiliated_schools(name);
