-- IECEP-LSC Creatives Committee Database Schema
-- MySQL/MariaDB Database

-- 1. Announcements Table
CREATE TABLE IF NOT EXISTS creatives_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    author VARCHAR(255) DEFAULT 'Creatives Committee',
    status VARCHAR(50) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Graphics Table
CREATE TABLE IF NOT EXISTS creatives_graphics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    image VARCHAR(500) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Publications Table
CREATE TABLE IF NOT EXISTS creatives_publications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    file VARCHAR(500) NOT NULL,
    size VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Team Members Table
CREATE TABLE IF NOT EXISTS creatives_team (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. Homepage Features Table
CREATE TABLE IF NOT EXISTS creatives_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(500) NOT NULL,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial data for announcements
INSERT INTO creatives_announcements (title, content, date, author, status) VALUES
('Upcoming Electronics Workshop', 'Join us for an exciting workshop on modern electronics design and innovation. Open to all IECEP-LSC members.', '2025-04-18', 'Creatives Committee', 'published'),
('National Electronics Conference Registration', 'Registration is now open for the Annual National Electronics Conference. Early bird discounts available until next month.', '2025-04-16', 'Creatives Committee', 'published'),
('Chapter Meeting Schedule', 'Monthly chapter meeting will be held on the 25th. All committee members are required to attend.', '2025-04-11', 'Creatives Committee', 'published')
ON DUPLICATE KEY UPDATE title=title;

-- Insert initial data for graphics
INSERT INTO creatives_graphics (name, image, date) VALUES
('Event Poster - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/images/nec-poster.jpg', '2025-04-18'),
('Workshop Banner', '/IECEP-LSC-MEMSYS/public/assets/images/workshop-banner.jpg', '2025-04-16'),
('Social Media Template', '/IECEP-LSC-MEMSYS/public/assets/images/social-template.jpg', '2025-04-11'),
('Chapter Logo Variant', '/IECEP-LSC-MEMSYS/public/assets/images/logo-variant.jpg', '2025-04-04')
ON DUPLICATE KEY UPDATE name=name;

-- Insert initial data for publications
INSERT INTO creatives_publications (title, file, size, date) VALUES
('Monthly Newsletter - March 2025', '/IECEP-LSC-MEMSYS/public/assets/newsletters/march-2025.pdf', '2.4 MB', '2025-04-15'),
('Chapter Annual Report 2024', '/IECEP-LSC-MEMSYS/public/assets/reports/annual-report-2024.pdf', '5.1 MB', '2025-04-11'),
('Event Program - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/programs/nec-2025.pdf', '1.8 MB', '2025-04-04')
ON DUPLICATE KEY UPDATE title=title;

-- Insert initial data for team members
INSERT INTO creatives_team (name, role, email) VALUES
('John Doe', 'PRO 1 - Committee Head', 'john.doe@iecep-lsc.org'),
('Jane Smith', 'Committee Member', 'jane.smith@iecep-lsc.org'),
('Mike Johnson', 'Committee Member', 'mike.johnson@iecep-lsc.org')
ON DUPLICATE KEY UPDATE name=name;

-- Insert initial data for homepage features
INSERT INTO creatives_features (title, description, image, link) VALUES
('IECEP News', 'Stay updated with the latest news and announcements from IECEP-LSC', '/IECEP-LSC-MEMSYS/public/assets/images/iecep-news.jpg', '#'),
('Recent Activities', 'View recent activities and events from our chapter', '/IECEP-LSC-MEMSYS/public/assets/images/activities.jpg', '#'),
('Featured Content', 'Explore featured content and highlights from our community', '/IECEP-LSC-MEMSYS/public/assets/images/featured.jpg', '#')
ON DUPLICATE KEY UPDATE title=title;
