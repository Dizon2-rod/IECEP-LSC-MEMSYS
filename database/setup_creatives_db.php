<?php
// Setup script for Creatives Committee Database
// Run this file once to create the database and tables

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS iecep_lsc_memsys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully.<br>";
    
    // Select the database
    $pdo->exec("USE iecep_lsc_memsys");
    
    // Create Announcements Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS creatives_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        date DATE NOT NULL,
        author VARCHAR(255) DEFAULT 'Creatives Committee',
        status VARCHAR(50) DEFAULT 'published',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Announcements table created successfully.<br>";
    
    // Create Graphics Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS creatives_graphics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        image VARCHAR(500) NOT NULL,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Graphics table created successfully.<br>";
    
    // Create Publications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS creatives_publications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file VARCHAR(500) NOT NULL,
        size VARCHAR(50) NOT NULL,
        date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Publications table created successfully.<br>";
    
    // Create Team Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS creatives_team (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Team members table created successfully.<br>";
    
    // Create Homepage Features Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS creatives_features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image VARCHAR(500) NOT NULL,
        link VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Homepage features table created successfully.<br>";
    
    // Insert initial data for announcements
    $stmt = $pdo->prepare("INSERT IGNORE INTO creatives_announcements (title, content, date, author, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Upcoming Electronics Workshop', 'Join us for an exciting workshop on modern electronics design and innovation. Open to all IECEP-LSC members.', '2025-04-18', 'Creatives Committee', 'published']);
    $stmt->execute(['National Electronics Conference Registration', 'Registration is now open for the Annual National Electronics Conference. Early bird discounts available until next month.', '2025-04-16', 'Creatives Committee', 'published']);
    $stmt->execute(['Chapter Meeting Schedule', 'Monthly chapter meeting will be held on the 25th. All committee members are required to attend.', '2025-04-11', 'Creatives Committee', 'published']);
    echo "Initial announcements data inserted.<br>";
    
    // Insert initial data for graphics
    $stmt = $pdo->prepare("INSERT IGNORE INTO creatives_graphics (name, image, date) VALUES (?, ?, ?)");
    $stmt->execute(['Event Poster - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/images/nec-poster.jpg', '2025-04-18']);
    $stmt->execute(['Workshop Banner', '/IECEP-LSC-MEMSYS/public/assets/images/workshop-banner.jpg', '2025-04-16']);
    $stmt->execute(['Social Media Template', '/IECEP-LSC-MEMSYS/public/assets/images/social-template.jpg', '2025-04-11']);
    $stmt->execute(['Chapter Logo Variant', '/IECEP-LSC-MEMSYS/public/assets/images/logo-variant.jpg', '2025-04-04']);
    echo "Initial graphics data inserted.<br>";
    
    // Insert initial data for publications
    $stmt = $pdo->prepare("INSERT IGNORE INTO creatives_publications (title, file, size, date) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Monthly Newsletter - March 2025', '/IECEP-LSC-MEMSYS/public/assets/newsletters/march-2025.pdf', '2.4 MB', '2025-04-15']);
    $stmt->execute(['Chapter Annual Report 2024', '/IECEP-LSC-MEMSYS/public/assets/reports/annual-report-2024.pdf', '5.1 MB', '2025-04-11']);
    $stmt->execute(['Event Program - NEC 2025', '/IECEP-LSC-MEMSYS/public/assets/programs/nec-2025.pdf', '1.8 MB', '2025-04-04']);
    echo "Initial publications data inserted.<br>";
    
    // Insert initial data for team members
    $stmt = $pdo->prepare("INSERT IGNORE INTO creatives_team (name, role, email) VALUES (?, ?, ?)");
    $stmt->execute(['John Doe', 'PRO 1 - Committee Head', 'john.doe@iecep-lsc.org']);
    $stmt->execute(['Jane Smith', 'Committee Member', 'jane.smith@iecep-lsc.org']);
    $stmt->execute(['Mike Johnson', 'Committee Member', 'mike.johnson@iecep-lsc.org']);
    echo "Initial team members data inserted.<br>";
    
    // Insert initial data for homepage features
    $stmt = $pdo->prepare("INSERT IGNORE INTO creatives_features (title, description, image, link) VALUES (?, ?, ?, ?)");
    $stmt->execute(['IECEP News', 'Stay updated with the latest news and announcements from IECEP-LSC', '/IECEP-LSC-MEMSYS/public/assets/images/iecep-news.jpg', '#']);
    $stmt->execute(['Recent Activities', 'View recent activities and events from our chapter', '/IECEP-LSC-MEMSYS/public/assets/images/activities.jpg', '#']);
    $stmt->execute(['Featured Content', 'Explore featured content and highlights from our community', '/IECEP-LSC-MEMSYS/public/assets/images/featured.jpg', '#']);
    echo "Initial homepage features data inserted.<br>";
    
    echo "<br><strong>Database setup completed successfully!</strong>";
    echo "<br>You can now delete this file.";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
