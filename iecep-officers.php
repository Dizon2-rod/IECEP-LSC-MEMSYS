<?php
require_once __DIR__ . '/bootstrap.php';

// -------------------------------------------------------------------
// ALL OFFICERS DATA (Executive Board + Committee Members)
// -------------------------------------------------------------------
$officers = [
    // Adviser
    ['name' => 'Engr. Pauline Aquino', 'position' => 'Chapter Adviser', 'committee' => 'Executive Board', 'role_badge' => 'Adviser', 'icon' => 'fas fa-chalkboard-user'],
    // Executive Board
    ['name' => 'Rhyan Castillo', 'position' => 'President', 'committee' => 'Executive Board', 'role_badge' => 'Chief Executive', 'icon' => 'fas fa-user-tie'],
    ['name' => 'Janica Asajar', 'position' => 'Vice President Internal', 'committee' => 'Executive Board', 'role_badge' => 'Internal Affairs', 'icon' => 'fas fa-users-cog'],
    ['name' => 'Victor Nosis', 'position' => 'Vice President for Academies', 'committee' => 'Executive Board', 'role_badge' => 'Academics', 'icon' => 'fas fa-graduation-cap'],
    ['name' => 'Ma. Cassandra Oreste', 'position' => 'Vice President External', 'committee' => 'Executive Board', 'role_badge' => 'External Relations', 'icon' => 'fas fa-globe'],
    ['name' => 'Keith Hanna Colot', 'position' => 'Secretary General', 'committee' => 'Executive Board', 'role_badge' => 'Documentation', 'icon' => 'fas fa-pen-nib'],
    ['name' => 'Muyhyidden Barraquias', 'position' => 'Assistant Secretary', 'committee' => 'Executive Board', 'role_badge' => 'Support', 'icon' => 'fas fa-file-alt'],
    ['name' => 'James Kelvin Doloeras', 'position' => 'Treasurer', 'committee' => 'Executive Board', 'role_badge' => 'Finance', 'icon' => 'fas fa-wallet'],
    ['name' => 'Marjorie Mendoza', 'position' => 'Auditor', 'committee' => 'Executive Board', 'role_badge' => 'Compliance', 'icon' => 'fas fa-search'],
    ['name' => 'Maillah Ameril', 'position' => 'Public Relations Officer 1', 'committee' => 'Executive Board', 'role_badge' => 'Public Relations', 'icon' => 'fas fa-bullhorn'],
    ['name' => 'Paul John Reyes', 'position' => 'Public Relations Officer 2', 'committee' => 'Executive Board', 'role_badge' => 'Media', 'icon' => 'fas fa-newspaper'],
    
    // Technical Committee
    ['name' => 'Aljohn Matthew Dizon', 'position' => 'Technical Committee Head', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Lead', 'icon' => 'fas fa-microchip'],
    ['name' => 'Kyn Harper Zuniga', 'position' => 'Technical Committee Member', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Support', 'icon' => 'fas fa-microchip'],
    ['name' => 'Angelica Uri', 'position' => 'Technical Committee Member', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Support', 'icon' => 'fas fa-microchip'],
    ['name' => 'Sam Daniel Turla', 'position' => 'Technical Committee Member', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Support', 'icon' => 'fas fa-microchip'],
    ['name' => 'Albert Pedong', 'position' => 'Technical Committee Member', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Support', 'icon' => 'fas fa-microchip'],
    ['name' => 'Syra Caringal', 'position' => 'Technical Committee Member', 'committee' => 'Technical Committee', 'role_badge' => 'Tech Support', 'icon' => 'fas fa-microchip'],
    
    // Registration Committee
    ['name' => 'Kyn Harper Zuniga', 'position' => 'Registration Committee Head', 'committee' => 'Registration Committee', 'role_badge' => 'Registration', 'icon' => 'fas fa-user-check'],
    
    // Marketing Committee
    ['name' => 'Angelica Uri', 'position' => 'Marketing Committee Head', 'committee' => 'Marketing Committee', 'role_badge' => 'Marketing', 'icon' => 'fas fa-chart-line'],
    
    // Documentation Committee
    ['name' => 'Fernand Reyes', 'position' => 'Documentation Committee Head', 'committee' => 'Documentation Committee', 'role_badge' => 'Documentation', 'icon' => 'fas fa-camera'],
    
    // Creatives & Publication
    ['name' => 'Junea Ros Rivera', 'position' => 'Creatives & Publication Head', 'committee' => 'Creatives & Publication', 'role_badge' => 'Creatives', 'icon' => 'fas fa-palette'],
    ['name' => 'Princess Klyde Denise Ballesteros', 'position' => 'Creatives Member', 'committee' => 'Creatives & Publication', 'role_badge' => 'Designer', 'icon' => 'fas fa-paintbrush'],
    
    // Logistics Committee
    ['name' => 'Geralyn Sapdin', 'position' => 'Logistics Committee Head', 'committee' => 'Logistics Committee', 'role_badge' => 'Logistics', 'icon' => 'fas fa-truck'],
    ['name' => 'Kiandra Karingal', 'position' => 'Logistics Committee Member', 'committee' => 'Logistics Committee', 'role_badge' => 'Logistics', 'icon' => 'fas fa-boxes'],
];

// Helper: get photo URL (place images in /assets/officers/firstname-lastname.jpg)
function getOfficerPhoto($name) {
    $slug = strtolower(str_replace([' ', '.'], '-', $name));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $photoPath = ASSETS_URL . "/officers/{$slug}.jpg";
    // You can also check if file exists via server-side, but for simplicity we return the path.
    // The browser will show a fallback if image missing.
    return $photoPath;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Executive Leadership - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0B1D4A;
            --primary-main: #1A3A8A;
            --accent-gold: #C5A059;
            --accent-soft: #F9F6EE;
            --text-main: #1F2937;
            --text-muted: #6B7280;
            --white: #FFFFFF;
            --shadow-md: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FDFDFD;
            color: var(--text-main);
        }
        .page-hero {
            position: relative;
            background: var(--primary-dark);
            color: var(--white);
            padding: 140px 0 80px 0;
            text-align: center;
            overflow: hidden;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat;
            opacity: 0.15;
            mix-blend-mode: overlay;
        }
        .page-hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .page-hero h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .page-hero p {
            font-size: 1.1rem;
            font-weight: 300;
            opacity: 0.9;
        }
        .officers-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 60px 1.5rem;
        }
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .section-badge {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--primary-dark);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8rem;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-dark);
        }
        /* Compact Grid */
        .officers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        /* Smaller Card */
        .officer-card {
            background: var(--white);
            padding: 1.2rem 1rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid #E5E7EB;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .officer-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-main);
        }
        /* Photo / Avatar */
        .officer-photo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0.8rem;
            border: 3px solid var(--white);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            background: #f0f2f5;
        }
        .avatar-fallback {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 0.8rem;
        }
        .role-badge {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
            display: block;
        }
        .officer-name {
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0.2rem;
            line-height: 1.3;
        }
        .officer-position {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary-main);
            margin-bottom: 0.5rem;
        }
        .committee-tag {
            font-size: 0.7rem;
            color: var(--text-muted);
            background: var(--accent-soft);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.3rem;
        }
        @media (max-width: 640px) {
            .officers-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .officer-photo, .avatar-fallback { width: 70px; height: 70px; font-size: 1.5rem; }
            .officer-name { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <section class="page-hero">
        <div class="hero-overlay"></div>
        <div class="page-hero-content">
            <h1>Executive Leadership</h1>
            <p>Meet the dedicated officers and committee members steering IECEP-LSC A.Y. 2026-2027</p>
        </div>
    </section>

    <main class="officers-container">
        <!-- Group by committee for better organization -->
        <?php
        $grouped = [];
        foreach ($officers as $officer) {
            $grouped[$officer['committee']][] = $officer;
        }
        // Define order of committees
        $committeeOrder = ['Executive Board', 'Technical Committee', 'Registration Committee', 'Marketing Committee', 'Documentation Committee', 'Creatives & Publication', 'Logistics Committee'];
        foreach ($committeeOrder as $comm) {
            if (empty($grouped[$comm])) continue;
        ?>
            <div class="section-header">
                <span class="section-badge"><?php echo $comm; ?></span>
                <h2 class="section-title"><?php echo $comm; ?></h2>
            </div>
            <div class="officers-grid">
                <?php foreach ($grouped[$comm] as $off): ?>
                    <?php 
                        $photoUrl = getOfficerPhoto($off['name']);
                        // Simple check to see if image exists (optional, you can rely on CSS fallback)
                        // We'll use an <img> with onerror to fallback to avatar
                    ?>
                    <div class="officer-card">
                        <img src="<?php echo $photoUrl; ?>" 
                             alt="<?php echo htmlspecialchars($off['name']); ?>"
                             class="officer-photo"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="avatar-fallback" style="display: none;">
                            <i class="<?php echo $off['icon']; ?>"></i>
                        </div>
                        <span class="role-badge"><?php echo htmlspecialchars($off['role_badge']); ?></span>
                        <h3 class="officer-name"><?php echo htmlspecialchars($off['name']); ?></h3>
                        <div class="officer-position"><?php echo htmlspecialchars($off['position']); ?></div>
                        <span class="committee-tag"><?php echo htmlspecialchars($off['committee']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php } ?>
    </main>

    <?php include __DIR__ . '/includes/footer-new.php'; ?>
</body>
</html>